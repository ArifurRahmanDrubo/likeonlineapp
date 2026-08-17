<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PaymentSettlementService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Karim007\LaravelNagad\Facade\NagadPayment;

/**
 * Nagad payment gateway for the customer portal.
 *
 * The customer's PPPoE username is embedded in Nagad's `orderId` as
 * NGD-{username}-{timestamp}, so the unauthenticated browser callback can
 * recover the paying customer from the verified order payload.
 */
class NagadPaymentController extends Controller
{
    /**
     * POST /api/portal/payments/nagad/create
     *
     * Initiates a Nagad checkout and returns the hosted payment URL for the
     * SPA to redirect the customer to.
     */
    public function createPayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
            ]);

            $customer = $request->user()->customer;
            if (!$customer) {
                abort(403, 'No ISP subscription is linked to this account.');
            }

            // Embed the username inside the order_id so the callback can
            // resolve the customer from the verified payment payload.
            $orderId = 'NGD-' . $customer->username . '-' . time();

            // Point the gateway at the settlement callback (routes/web.php).
            config(['nagad.callback_url' => $this->absoluteUrl(config('nagad.callback_url'))]);

            $payment = NagadPayment::create((float) $request->input('amount'), $orderId);

            if ($payment && isset($payment->status) && $payment->status === 'Success' && !empty($payment->callBackUrl)) {
                return response()->json([
                    'status' => 'success',
                    'redirect_url' => $payment->callBackUrl,
                ], 200);
            }

            Log::warning('Nagad checkout initiation did not return a redirect URL.', ['response' => $payment]);
            return response()->json(['message' => 'Nagad payment initiation failed.'], 400);
        } catch (Exception $e) {
            Log::error("Nagad create payment failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to initiate Nagad payment.'], 500);
        }
    }

    /**
     * Convert a possibly-relative callback URL into an absolute one using the
     * current request scheme/host. Absolute values pass through untouched.
     */
    protected function absoluteUrl(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : url($path);
    }

    /**
     * Send the customer's browser back to the SPA billing page.
     *
     * Query parameters are assembled with http_build_query and the redirect
     * uses ->away(), which skips the URL generator entirely — so the query
     * string is never re-encoded on its way into the Location header.
     */
    protected function billingRedirect(string $status, ?string $trxID = null)
    {
        $query = http_build_query(array_filter([
            'payment_status' => $status,
            'trxID' => $trxID,
        ]));

        return redirect()->away(config('portal.frontend_url') . '/portal/billing?' . $query);
    }

    /**
     * Successful payment → dedicated SPA success page.
     *
     * Same no-re-encoding treatment as billingRedirect, but lands on
     * /portal/payment-success?type=online&trxID=...&amount=... so the SPA can
     * show the success icon, transaction ID and amount.
     */
    protected function successRedirect(string $trxID, float $amount)
    {
        $query = http_build_query([
            'type' => 'online',
            'trxID' => $trxID,
            'amount' => number_format($amount, 2, '.', ''),
        ]);

        return redirect()->away(config('portal.frontend_url') . '/portal/payment-success?' . $query);
    }

    /**
     * GET /portal/payments/nagad/callback  (browser redirect from Nagad)
     *
     * Verifies the payment with Nagad, extracts the customer from the
     * NGD-{username}-{timestamp} order id and auto-settles the bill.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->get('payment_ref_id');

        if (!$paymentId) {
            return $this->billingRedirect('failed');
        }

        try {
            $verify = NagadPayment::verify($paymentId);
        } catch (Exception $e) {
            Log::error("Nagad verify failed for payment_ref_id {$paymentId}: {$e->getMessage()}");
            return $this->billingRedirect('failed');
        }

        if (!isset($verify->status) || $verify->status !== 'Success') {
            Log::warning('Nagad callback: payment not successful.', ['payment_ref_id' => $paymentId]);
            return $this->billingRedirect('failed');
        }

        // Recover the username from NGD-{username}-{timestamp}. The username
        // itself may contain dashes, so strip the prefix and trailing timestamp
        // instead of naively splitting on '-'.
        $username = $this->usernameFromOrderId($verify->orderId ?? '');
        $customer = $username ? Customer::where('username', $username)->first() : null;

        if (!$customer) {
            $orderId = $verify->orderId ?? '';
            Log::error("Nagad callback: no customer found for order id '{$orderId}'.");
            return $this->billingRedirect('failed');
        }

        try {
            $trxID = $verify->issuerPaymentRefNo ?? null;

            app(PaymentSettlementService::class)->settleAutomaticPayment(
                $customer,
                (float) ($verify->amount ?? 0),
                $trxID ?: $paymentId,
                'Nagad'
            );

            return $this->successRedirect($trxID ?: $paymentId, (float) ($verify->amount ?? 0));
        } catch (Exception $e) {
            Log::error("Nagad callback settlement failed for {$username}: {$e->getMessage()}");
            return $this->billingRedirect('failed');
        }
    }

    /**
     * Parse the customer username out of a Nagad order id of the form
     * NGD-{username}-{timestamp}.
     */
    protected function usernameFromOrderId(string $orderId): ?string
    {
        if (!str_starts_with($orderId, 'NGD-')) {
            return null;
        }

        $rest = substr($orderId, 4); // strip the NGD- prefix
        $lastDash = strrpos($rest, '-');

        // No trailing timestamp → the whole remainder is the username.
        return $lastDash === false ? $rest : substr($rest, 0, $lastDash);
    }
}
