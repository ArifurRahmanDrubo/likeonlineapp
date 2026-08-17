<?php

namespace App\Services;

use App\Models\PaymentGateway;

/**
 * Dynamic payment-gateway configuration and transaction-id handling.
 *
 * Every gateway credential now lives in the `payment_gateways` table; this
 * service pushes those DB values into the legacy config keys each package
 * reads (bkash.* / nagad.* / sslcommerz.*) right before an API call, so the
 * admin Payment Gateway page is the single source of truth with .env as a
 * fallback.
 *
 * Transaction IDs embed the customer's PPPoE username as
 * TRX-{username}-{timestamp} so the unauthenticated browser callback can
 * recover the paying customer from the verified gateway payload. SSLCommerz
 * additionally echoes the raw username back via the value_a custom field.
 */
class PaymentGatewayService
{
    /**
     * Overlay a gateway's DB credentials onto the legacy config keys the
     * karim007 packages read. Unset/empty values keep the .env fallback.
     */
    public function applyConfig(PaymentGateway $gateway): void
    {
        $credentials = $gateway->credentials ?? [];

        switch ($gateway->name) {
            case 'bkash':
                config([
                    'bkash.sandbox' => $gateway->mode === 'sandbox',
                    'bkash.bkash_app_key' => $credentials['app_key'] ?? config('bkash.bkash_app_key'),
                    'bkash.bkash_app_secret' => $credentials['app_secret'] ?? config('bkash.bkash_app_secret'),
                    'bkash.bkash_username' => $credentials['username'] ?? config('bkash.bkash_username'),
                    'bkash.bkash_password' => $credentials['password'] ?? config('bkash.bkash_password'),
                ]);
                break;

            case 'nagad':
                config([
                    'nagad.sandbox' => $gateway->mode === 'sandbox',
                    'nagad.merchant_id' => $credentials['merchant_id'] ?? config('nagad.merchant_id'),
                    'nagad.public_key' => $credentials['public_key'] ?? config('nagad.public_key'),
                    'nagad.private_key' => $credentials['private_key'] ?? config('nagad.private_key'),
                ]);
                break;

            case 'sslcommerz':
                config([
                    'sslcommerz.sandbox' => $gateway->mode === 'sandbox',
                    'sslcommerz.store_id' => $credentials['store_id'] ?? config('sslcommerz.store_id'),
                    'sslcommerz.store_password' => $credentials['store_password'] ?? config('sslcommerz.store_password'),
                ]);
                break;
        }
    }

    /**
     * Build a gateway-safe transaction id carrying the customer username:
     * TRX-{username}-{timestamp}. The username is sanitized to the chars the
     * gateways accept (alphanumeric, dash, underscore) so the id stays valid
     * for bKash merchant invoices, Nagad order ids and SSLCommerz tran_ids.
     */
    public function generateTransactionId(string $username): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '-', $username);

        return 'TRX-' . $safe . '-' . time();
    }

    /**
     * Recover the username from a TRX-{username}-{timestamp} id. The username
     * itself may contain dashes, so strip the prefix and the trailing
     * timestamp instead of naively splitting on '-'.
     */
    public function extractUsername(?string $transactionId): ?string
    {
        if (!$transactionId || !str_starts_with($transactionId, 'TRX-')) {
            return null;
        }

        $rest = substr($transactionId, 4);
        $lastDash = strrpos($rest, '-');

        if ($lastDash === false) {
            return $rest ?: null;
        }

        $username = substr($rest, 0, $lastDash);

        return $username ?: null;
    }
}
