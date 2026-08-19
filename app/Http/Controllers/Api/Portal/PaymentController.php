<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentGateway;
use App\Services\PaymentGatewayService;
use App\Services\PaymentSettlementService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Karim007\LaravelBkashTokenize\Facade\BkashPaymentTokenize;
use Karim007\LaravelNagad\Facade\NagadPayment;
use Karim007\SslcommerzLaravel\Facade\SSLCommerzPayment;
use Karim007\SslcommerzLaravel\SslCommerz\SslCommerzNotification;

/**
 * Unified dynamic payment gateway controller for the customer portal.
 *
 * bKash, Nagad and SSLCommerz are all configured from the `payment_gateways`
 * table (admin Payment Gateway page) and dispatched through one endpoint. The
 * customer's PPPoE username is embedded in every transaction id as
 * TRX-{username}-{timestamp} (and echoed via SSLCommerz's value_a custom
 * field), so the unauthenticated browser callbacks can resolve the paying
 * customer from the verified gateway payload.
 */
class PaymentController extends Controller
{
    public function __construct(protected PaymentGatewayService $gatewayService)
    {
    }

    /**
     * GET /api/portal/payments/gateways
     *
     * Public-safe gateway metadata for the checkout UI (no credentials).
     * The SPA renders disabled "Unavailable" cards when is_active is false.
     */
    public function gateways(Request $request)
    {
        $gateways = PaymentGateway::orderBy('id')
            ->get(['name', 'title', 'is_active', 'mode'])
            ->map(function ($gateway) {
                return [
                    'name' => $gateway->name,
                    'title' => $gateway->title,
                    'is_active' => (bool) $gateway->is_active,
                    'mode' => $gateway->mode,
                ];
            });

        return response()->json([
            'status' => 'success',
            'gateways' => $gateways,
        ]);
    }

    /**
     * POST /api/portal/payments/create
     *
     * Initiates the checkout session for the selected gateway using its DB
     * credentials and returns the hosted payment URL for the SPA to redirect
     * the customer to. Body: { gateway: 'bkash'|'nagad'|'sslcommerz', amount }.
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'gateway' => 'required|in:bkash,nagad,sslcommerz',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $customer = $request->user()->customer;
            if (!$customer) {
                abort(403, 'No ISP subscription is linked to this account.');
            }

            $gateway = PaymentGateway::where('name', $request->input('gateway'))->first();
            if (!$gateway || !$gateway->is_active) {
                return response()->json([
                    'message' => 'This payment method is currently unavailable. Please choose another.',
                ], 400);
            }

            // Load this gateway's DB credentials into the legacy config keys.
            $this->gatewayService->applyConfig($gateway);

            $amount = (float) $request->input('amount');

            return match ($gateway->name) {
                'bkash' => $this->createBkashPayment($customer, $amount),
                'nagad' => $this->createNagadPayment($customer, $amount),
                'sslcommerz' => $this->createSslcommerzPayment($customer, $amount),
            };
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Preserve abort(403/404) responses (e.g. no linked subscription).
            throw $e;
        } catch (Exception $e) {
            Log::error("Payment create failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to initiate payment.'], 500);
        }
    }

    /**
     * bKash tokenized checkout — mirrors the dedicated BkashPaymentController
     * flow, but with credentials applied from the payment_gateways table.
     */
    protected function createBkashPayment(Customer $customer, float $amount)
    {
        $requestdata = [
            'intent' => 'sale',
            'mode' => '0011', // 0011 = tokenized checkout
            'payerReference' => (string) $customer->username, // unique PPPoE username
            'currency' => 'BDT',
            'amount' => number_format($amount, 2, '.', ''),
            'merchantInvoiceNumber' => $this->gatewayService->generateTransactionId($customer->username),
            'callbackURL' => $this->absoluteUrl(config('bkash.callbackURL')),
        ];

        $response = BkashPaymentTokenize::cPayment(json_encode($requestdata));

        if (isset($response['bkashURL'])) {
            // Warm the cache-backed token so the callback can reuse it.
            $sessionToken = session()->get('bkash_token');
            if ($sessionToken) {
                \Illuminate\Support\Facades\Cache::put('bkash_id_token', $sessionToken, now()->addMinutes(50));
            }

            return response()->json([
                'status' => 'success',
                'redirect_url' => $response['bkashURL'],
                'payment_id' => $response['paymentID'] ?? null,
            ], 200);
        }

        Log::warning('bKash checkout initiation did not return a URL.', ['response' => $response]);
        return response()->json([
            'message' => $response['statusMessage'] ?? 'bKash payment initiation failed.',
        ], 400);
    }

    /**
     * Nagad checkout — mirrors the dedicated NagadPaymentController flow with
     * DB credentials applied. The username rides inside NGD-{username}-{ts}.
     */
    protected function createNagadPayment(Customer $customer, float $amount)
    {
        $orderId = 'NGD-' . $customer->username . '-' . time();

        config(['nagad.callback_url' => $this->absoluteUrl(config('nagad.callback_url'))]);

        $payment = NagadPayment::create($amount, $orderId);

        if ($payment && isset($payment->status) && $payment->status === 'Success' && !empty($payment->callBackUrl)) {
            return response()->json([
                'status' => 'success',
                'redirect_url' => $payment->callBackUrl,
            ], 200);
        }

        Log::warning('Nagad checkout initiation did not return a redirect URL.', ['response' => $payment]);
        return response()->json(['message' => 'Nagad payment initiation failed.'], 400);
    }

    /**
     * SSLCommerz hosted checkout.
     *
     * The username travels in TWO places: tran_id (TRX-{username}-{timestamp},
     * truncated to SSLCommerz's 30-char limit) and the value_a custom field
     * (raw username, echoed back verbatim on the callback).
     */
    protected function createSslcommerzPayment(Customer $customer, float $amount)
    {
        $username = (string) $customer->username;

        // tran_id is capped at 30 chars by SSLCommerz.
        $tranId = $this->gatewayService->generateTransactionId($username);
        if (strlen($tranId) > 30) {
            $tranId = 'TRX-' . substr($this->safeUsername($username), 0, 12) . '-' . time();
        }

        $postData = [
            'total_amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $tranId, // must be unique
            'product_category' => 'Internet Bill',
            // Custom payload — SSLCommerz echoes these back on the callback.
            'value_a' => $username,
        ];

        $customerInfo = [
            'name' => $customer->name ?: $username,
            'email' => $customer->email ?: 'customer@example.com',
            'address_1' => $customer->district ?: 'Dhaka',
            'address_2' => $customer->upzila ?: '',
            'city' => $customer->upzila ?: 'Dhaka',
            'state' => '',
            'postcode' => '',
            'country' => 'Bangladesh',
            'phone' => $customer->mobile ?: '01XXXXXXXXX',
            'fax' => '',
        ];

        // The facade resolves a fresh instance per call, so build the
        // notification directly to keep setCustomerInfo + makePayment chained
        // on the SAME object (the package stores customer info on $this->data).
        $sslc = new SslCommerzNotification();
        $sslc->setCustomerInfo($customerInfo);

        $response = $sslc->makePayment($postData, 'checkout', 'json');
        $decoded = is_string($response) ? json_decode($response, true) : $response;

        if (is_array($decoded) && strtolower($decoded['status'] ?? '') === 'success' && !empty($decoded['data'])) {
            return response()->json([
                'status' => 'success',
                'redirect_url' => $decoded['data'], // GatewayPageURL
            ], 200);
        }

        Log::warning('SSLCommerz checkout initiation did not return a URL.', ['response' => $decoded]);
        return response()->json([
            'message' => $decoded['message'] ?? 'SSLCommerz payment initiation failed.',
        ], 400);
    }

    /**
     * POST /portal/payments/sslcommerz/success  (browser redirect from gateway)
     *
     * Verifies the transaction with SSLCommerz, recovers the customer from
     * value_a (or the TRX-{username}-{timestamp} tran_id) and auto-settles.
     */
    public function sslcommerzSuccess(Request $request)
    {
        return $this->handleSslcommerzCallback($request, 'success');
    }

    /**
     * POST /portal/payments/sslcommerz/fail
     */
    public function sslcommerzFail(Request $request)
    {
        Log::warning('SSLCommerz payment failed.', $request->except(['verify_sign']));
        return $this->billingRedirect('failed');
    }

    /**
     * POST /portal/payments/sslcommerz/cancel
     */
    public function sslcommerzCancel(Request $request)
    {
        return $this->billingRedirect('cancelled');
    }

    /**
     * POST /portal/payments/sslcommerz/ipn  (server-to-server notification)
     */
    public function sslcommerzIpn(Request $request)
    {
        try {
            $this->gatewayService->applyConfig(PaymentGateway::where('name', 'sslcommerz')->firstOrNew());
            $validated = SSLCommerzPayment::orderValidate(
                $request->all(),
                $request->input('tran_id'),
                (float) $request->input('amount'),
                $request->input('currency', 'BDT')
            );
            return response()->json(['status' => $validated ? 'VALID' : 'INVALID']);
        } catch (Exception $e) {
            Log::error("SSLCommerz IPN failed: {$e->getMessage()}");
            return response()->json(['status' => 'FAILED'], 500);
        }
    }

    /**
     * Shared SSLCommerz success-verification + settlement pipeline.
     */
    protected function handleSslcommerzCallback(Request $request, string $type)
    {
        $tranId = $request->input('tran_id');
        $amount = (float) $request->input('amount');

        if (!$tranId) {
            return $this->billingRedirect('failed');
        }

        try {
            // Re-apply DB credentials — the callback is a fresh request where
            // config has been reset to the .env fallbacks.
            $gateway = PaymentGateway::where('name', 'sslcommerz')->first();
            if ($gateway) {
                $this->gatewayService->applyConfig($gateway);
            }

            $validated = SSLCommerzPayment::orderValidate(
                $request->all(),
                $tranId,
                $amount,
                $request->input('currency', 'BDT')
            );

            if (!$validated) {
                Log::warning('SSLCommerz callback: order validation failed.', ['tran_id' => $tranId]);
                return $this->billingRedirect('failed');
            }

            // Recover the username: value_a is echoed verbatim by SSLCommerz;
            // fall back to parsing the TRX-{username}-{timestamp} tran_id.
            $username =  $this->gatewayService->extractUsername($tranId);
            $customer = $username ? Customer::where('username', $username)->first() : null;

            if (!$customer) {
                Log::error("SSLCommerz callback: no customer found for '{$username}'.");
                return $this->billingRedirect('failed');
            }

            $trxID = $request->input('bank_tran_id') ?: $tranId;

            app(PaymentSettlementService::class)->settleAutomaticPayment(
                $customer,
                $amount,
                $trxID,
                'SSLCommerz'
            );

            return $this->successRedirect($trxID, $amount);
        } catch (Exception $e) {
            Log::error("SSLCommerz callback settlement failed: {$e->getMessage()}");
            return $this->billingRedirect('failed');
        }
    }

    /**
     * Sanitize a username to the chars gateways accept (alphanumeric, dash,
     * underscore).
     */
    protected function safeUsername(string $username): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '-', $username) ?? $username;
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
}
