<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\MikrotikServer;
use Illuminate\Console\Command;
use App\Models\SystemPermission;
// use PEAR2\Net\RouterOS\Client;
// use PEAR2\Net\RouterOS\Request;
use Illuminate\Support\Facades\Log;

use RouterOS\Client;
use RouterOS\Query;

class DisableExpiredCustomers extends Command
{
    protected $signature = 'customers:disable-expired';
    protected $description = 'Disable customers with unpaid invoices after expiration date';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $now = Carbon::now();
        $todayDay = (int) $now->day;

        // Fetch system permission settings
        $settings = SystemPermission::first();

        // Payment-status-wise disabling only runs when the flag is enabled
        // (default: OFF when no settings row exists).
        if (!$settings || !$settings->isEnabled('payment_status_wise_client_disabled')) {
            $this->info('The permission to disable clients based on payment status is not enabled.');
            return; // Exit if the permission is disabled
        }

        // customers.expireddate is a FIXED day-of-month expiry (e.g. the 5th of
        // every month). It is never incremented/extended on payment — the day
        // value simply decides when each month's payment-status check runs.
        //
        // Disable candidates: non-left customers with unpaid/partial invoices
        // whose monthly expiry day has already been reached this month.
        // (Day comparison happens in PHP so the query stays database-agnostic.)
        $customers = Customer::whereNotNull('expireddate')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'left');
            })
            ->whereHas('invoice', function ($query) {
                $query->whereIn('status', ['unpaid', 'partial']); // filter unpaid/partial invoices
            })
            ->get()
            ->filter(function (Customer $customer) use ($todayDay) {
                $expiryDay = $this->expiryDayOfMonth($customer->expireddate);
                if ($expiryDay === null) {
                    return false; // unknown expiry day — never auto-disable
                }

                // Reached the customer's expiry day in the current month?
                return $todayDay >= $expiryDay;
            });

        // Optional block profile: when block_mikrotik_profile holds a real
        // MikroTik profile name it is applied to the secret while disabling.
        $blockProfile = $this->blockProfileName($settings);

        // Check if there are customers to disable
        if ($customers->isEmpty()) {
            $this->info('No customers to disable.');
            return; // Exit if there are no customers to disable
        }

        // Disable the customers
        foreach ($customers as $customer) {
            $server = MikrotikServer::find($customer->server_id);

            if ($server && $customer->radius_id) {
                try {
                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);

                    // Prepare MikroTik API request to update 'disabled' status
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $customer->radius_id);
                    $updateRequest->equal('disabled', 'true');
                    // Apply the configured block profile (if any) so the
                    // customer is throttled to the blocking plan.
                    if ($blockProfile) {
                        $updateRequest->equal('profile', $blockProfile);
                    }

                    // Send the request
                    $client->query($updateRequest)->read();

                    // Log successful disabling
                    // Log::info("Disabled MikroTik user: {$customer->radius_id}");
                } catch (\Exception $e) {
                    // Log the error if API request fails
                    Log::error("Failed to disable MikroTik user {$customer->radius_id}: " . $e->getMessage());
                }
            }

            // Update 'disabled' status in the database
            $customer->update([
                'mikrotikStatus' => false,
            ]);
        }

        $this->info('Customers disabled successfully.');
    }

    /**
     * Extract the fixed day-of-month from customers.expireddate.
     *
     * The column is a string and the client form stores a plain day value
     * (e.g. "05" for the 5th of every month). Legacy records may hold a full
     * date — in that case the day is read from the parsed date.
     *
     * @return int|null day of month (1-31), or null when unknown.
     */
    private function expiryDayOfMonth($expireddate): ?int
    {
        $value = trim((string) $expireddate);
        if ($value === '') {
            return null;
        }

        // Plain day-of-month string ("05") → use it directly. Pure-digit
        // values outside 1-31 are invalid and must not fall through to the
        // date parser ("0" would otherwise parse as today).
        if (ctype_digit($value)) {
            $day = (int) $value;
            return ($day >= 1 && $day <= 31) ? $day : null;
        }

        // Full/partial date → take the day component.
        try {
            return (int) Carbon::parse($value)->day;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the MikroTik blocking profile from system_permissions.
     *
     * The legacy settings UI stored 'enable'/'disable' in this column, so
     * boolean-like keywords are ignored and null is returned — only a real
     * profile name is treated as a configured block profile.
     */
    private function blockProfileName(?SystemPermission $settings): ?string
    {
        $value = trim((string) ($settings?->block_mikrotik_profile ?? ''));
        if ($value === '') {
            return null;
        }

        if (in_array(strtolower($value), ['enable', 'disable', 'true', 'false', 'yes', 'no', 'on', 'off', '1', '0'], true)) {
            return null;
        }

        return $value;
    }
}
