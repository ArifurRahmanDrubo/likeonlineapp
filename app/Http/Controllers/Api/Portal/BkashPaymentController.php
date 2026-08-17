<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\BkashService;
use App\Services\PaymentSettlementService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Karim007\LaravelBkashTokenize\Facade\BkashPaymentTokenize;

/**
 * bKash (tokenized checkout) payment gateway for the customer portal.
 *
 * The customer's PPPoE username travels inside `payerReference`, so the
 * unauthenticated browser callback can resolve the paying customer without
 * trusting any client-supplied identifier.
 */
class BkashPaymentController extends Controller
{
    /**
     * POST /api/portal/payments/bkash/create
     *
     * Creates a bKash tokenized checkout session and returns the hosted
     * checkout URL for the SPA to redirect the customer to.
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

            Log::info('bKash payment customer', [
                'customer' => $customer?->toArray(),
            ]);

            $requestdata = [
                'intent' => 'sale',
                'mode' => '0011', // 0011 = tokenized checkout
                'payerReference' => (string) $customer->username, // unique PPPoE username
                'currency' => 'BDT',
                'amount' => number_format((float) $request->input('amount'), 2, '.', ''),
                'merchantInvoiceNumber' => 'INV-BKASH-' . time(),
                // The gateway needs an absolute URL; resolve relative config
                // values against the current request host.
                'callbackURL' => $this->absoluteUrl(config('bkash.callbackURL')),
            ];

            $response = BkashPaymentTokenize::cPayment(json_encode($requestdata));

            if (isset($response['bkashURL'])) {
                // Warm the cache-backed token (50-min TTL) so the callback can
                // reuse it without a fresh grant — still safely under bKash's
                // 60-minute token lifetime.
                $sessionToken = session()->get('bkash_token');
                if ($sessionToken) {
                    Cache::put('bkash_id_token', $sessionToken, now()->addMinutes(50));
                }

                return response()->json([
                    'status' => 'success',
                    'bkash_url' => $response['bkashURL'],
                    'payment_id' => $response['paymentID'] ?? null,
                ], 200);
            }

            Log::warning('bKash checkout initiation did not return a URL.', ['response' => $response]);
            return response()->json([
                'message' => $response['statusMessage'] ?? 'bKash payment initiation failed.',
            ], 400);
        } catch (Exception $e) {
            Log::error("bKash create payment failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to initiate bKash payment.'], 500);
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
     * GET /portal/payments/bkash/callback  (browser redirect from bKash)
     *
     * Executes/queries the payment, resolves the customer from payerReference
     * and auto-settles the bill before sending the customer back to the portal.
     */
    public function callback(Request $request)
    {
        if ($request->status === 'cancel') {
            return $this->billingRedirect('cancelled');
        }

        if ($request->status !== 'success' || !$request->paymentID) {
            return $this->billingRedirect('failed');
        }

        try {
            $bkash = app(BkashService::class);

            // Execute the payment; fall back to a status query when the
            // payment is not immediately executable. Both calls go through
            // withTokenRefresh(), which swaps in a fresh id_token and retries
            // once whenever bKash reports "The incoming token has expired"
            // (statusCode 2001 / 2002).
            $response = $bkash->withTokenRefresh(
                fn () => BkashPaymentTokenize::executePayment($request->paymentID)
            );

            if (!$response) {
                $response = $bkash->withTokenRefresh(
                    fn () => BkashPaymentTokenize::queryPayment($request->paymentID)
                );
            }

            // Still failing with an expired-token error after the refresh?
            // The payment session is gone — send the customer back gracefully.
            if ($bkash->isTokenExpired($response)) {
                Log::error('bKash callback: token expired and refresh retry still failed.', [
                    'paymentID' => $request->paymentID,
                    'response' => $response,
                ]);
                return $this->paymentSessionExpiredRedirect();
            }

            $completed = isset($response['statusCode'])
                && $response['statusCode'] === '0000'
                && ($response['transactionStatus'] ?? null) === 'Completed';

            if (!$completed) {
                Log::warning('bKash callback: payment not completed.', ['paymentID' => $request->paymentID, 'response' => $response]);
                return $this->billingRedirect('failed');
            }

            $username = $response['payerReference'] ?? null;
            $customer = $username ? Customer::where('username', $username)->first() : null;

            if (!$customer) {
                Log::error("bKash callback: no customer found for payerReference '{$username}'.");
                return $this->billingRedirect('failed');
            }

            $trxID = $response['trxID'] ?? null;

            app(PaymentSettlementService::class)->settleAutomaticPayment(
                $customer,
                (float) ($response['amount'] ?? 0),
                $trxID,
                'bKash'
            );

            return $this->successRedirect($trxID, (float) ($response['amount'] ?? 0));
        } catch (Exception $e) {
            Log::error("bKash callback settlement failed: {$e->getMessage()}");
            return $this->billingRedirect('failed');
        }
    }

    /**
     * The bKash token expired and the automatic refresh retry still failed —
     * send the customer back to the SPA with a friendly message instead of a
     * raw gateway error page.
     */
    protected function paymentSessionExpiredRedirect()
    {
        $query = http_build_query([
            'type' => 'failed',
            'message' => 'Payment session expired. Please try again.',
        ]);

        return redirect()->away(config('portal.frontend_url') . '/portal/payment-success?' . $query);
    }
}
