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
        // Get the current date
        $currentDate = Carbon::now();

        // Fetch system permission settings
        $settings = SystemPermission::first();

        $applyDisabled = $settings ? $settings->payment_status_wise_client_disabled : 'disable'; // Make sure this matches your DB structure

        // Check if the permission is enabled
        if ($applyDisabled !== 'enable') {
            $this->info('The permission to disable clients based on payment status is not enabled.');
            return; // Exit if the permission is disabled
        }

        // Fetch customers with unpaid invoices whose expiration date is past.
        // Left clients are skipped — they are already disconnected and must
        // not be re-disabled or re-processed. NULL-safe: legacy customers
        // without a status still qualify.
        $customers = Customer::where('expireddate', '<', $currentDate)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'left');
            })
            ->whereHas('invoice', function ($query) {
                $query->whereIn('status', ['unpaid', 'partial']); // filter unpaid/partial invoices
            })
            ->get();

        // Check if there are customers to disable
        if ($customers->isEmpty()) {
            $this->info('No customers to disable.');
            return; // Exit if there are no customers to disable
        }

        // Disable the customers
        foreach ($customers as $customer) {
            $server = MikrotikServer::find($customer->server_id);

            if ($server) {
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
}
