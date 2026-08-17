<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

/**
 * Seeds the three supported payment gateways (bKash, Nagad, SSLCommerz).
 *
 * Credentials fall back to the legacy .env-driven config (BKASH_*, NAGAD_*,
 * SSLCOMMERZ_*) so the gateways work out of the box; the admin Payment
 * Gateway page can then override them per gateway in the database.
 */
class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gateways = [
            [
                'name'        => 'bkash',
                'title'       => 'bKash',
                'logo'        => 'https://upload.wikimedia.org/wikipedia/commons/6/67/BKash_logo.svg',
                'is_active'   => (bool) env('BKASH_SANDBOX', true) || env('BKASH_APP_KEY', '') !== '',
                'mode'        => env('BKASH_SANDBOX', true) ? 'sandbox' : 'live',
                'credentials' => [
                    'app_key'    => env('BKASH_APP_KEY', ''),
                    'app_secret' => env('BKASH_APP_SECRET', ''),
                    'username'   => env('BKASH_USERNAME', ''),
                    'password'   => env('BKASH_PASSWORD', ''),
                ],
            ],
            [
                'name'        => 'nagad',
                'title'       => 'Nagad',
                'logo'        => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/Nagad_logo.svg/320px-Nagad_logo.svg.png',
                'is_active'   => (bool) env('NAGAD_SANDBOX', true) || env('NAGAD_MERCHANT_ID', '') !== '',
                'mode'        => env('NAGAD_SANDBOX', true) ? 'sandbox' : 'live',
                'credentials' => [
                    'merchant_id' => env('NAGAD_MERCHANT_ID', ''),
                    'public_key'  => env('NAGAD_PUBLIC_KEY', ''),
                    'private_key' => env('NAGAD_PRIVATE_KEY', ''),
                ],
            ],
            [
                'name'        => 'sslcommerz',
                'title'       => 'SSLCommerz',
                'logo'        => 'https://www.sslcommerz.com/wp-content/uploads/2020/03/sslcommerz.png',
                'is_active'   => (bool) env('SSLCOMMERZ_SANDBOX', false) || env('SSLCOMMERZ_STORE_ID', '') !== '',
                'mode'        => env('SSLCOMMERZ_SANDBOX', false) ? 'sandbox' : 'live',
                'credentials' => [
                    'store_id'      => env('SSLCOMMERZ_STORE_ID', ''),
                    'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', env('SSLCOMMERZ__STORE_PASSWORD', '')),
                ],
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['name' => $gateway['name']],
                $gateway
            );
        }
    }
}
