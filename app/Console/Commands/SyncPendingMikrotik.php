<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ScheduledChangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPendingMikrotik extends Command
{
    protected $signature = 'mikrotik:sync-pending';

    protected $description = 'Sync pending MikroTik re-enables for paid customers';

    /**
     * Retry MikroTik re-enables for invoices that were flagged
     * pending_mikrotik_sync = true during payment approval but could not be
     * synced at that moment (e.g. the router was unreachable).
     *
     * Only fully-paid invoices (amount <= 0) belonging to active customers are
     * considered. Each invoice is handled in its own try/catch so a single
     * unreachable router never blocks the remaining customers.
     */
    public function handle()
    {
        $invoices = Invoice::where('pending_mikrotik_sync', true)
            ->where('amount', '<=', 0)
            ->whereHas('customer', function ($query) {
                $query->where('status', 'active');
            })
            ->with('customer')
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            try {
                app(ScheduledChangeService::class)->reEnableMikrotik($invoice->customer);

                $invoice->pending_mikrotik_sync = false;
                $invoice->save();
                $synced++;

                Log::info("MikroTik re-enable synced for invoice {$invoice->id} (customer {$invoice->customer_id}).");
            } catch (\Exception $e) {
                $failed++;
                Log::error("MikroTik re-enable failed for invoice {$invoice->id} (customer {$invoice->customer_id}): {$e->getMessage()}");
            }
        }

        $this->info("MikroTik sync completed: {$synced} synced, {$failed} failed.");
    }
}
