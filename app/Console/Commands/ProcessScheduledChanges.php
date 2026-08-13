<?php

namespace App\Console\Commands;

use App\Models\PackageChanged;
use App\Models\StatusChanged;
use App\Services\ScheduledChangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledChanges extends Command
{
    protected $signature = 'isp:process-scheduled-changes';
    protected $description = 'Apply all pending/approved package and status changes whose execution date has arrived';

    public function handle(): int
    {
        $this->processPendingPackageChanges();
        $this->processPendingStatusChanges();

        $this->info('Scheduled changes processed.');
        return self::SUCCESS;
    }

    /**
     * Apply due package changes. Both 'pending' (awaiting approval) and
     * 'approved' (waiting for the due date) requests are picked up once the
     * execution date has arrived.
     */
    protected function processPendingPackageChanges(): void
    {
        $pending = PackageChanged::whereIn('status', ['pending', 'approved'])
            ->whereDate('executiondate', '<=', now()->toDateString())
            ->get();

        foreach ($pending as $request) {
            try {
                app(ScheduledChangeService::class)->applyPackageRequest($request);
                Log::info("Package change #{$request->id} for customer {$request->customer_id} completed.");
            } catch (\Exception $e) {
                Log::error("Package change #{$request->id} failed: {$e->getMessage()}");
                $request->update([
                    'status'    => 'failed',
                    'error_log' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Apply due status changes (same pending/approved semantics as above).
     */
    protected function processPendingStatusChanges(): void
    {
        $pending = StatusChanged::whereIn('status', ['pending', 'approved'])
            ->whereDate('executiondate', '<=', now()->toDateString())
            ->get();

        foreach ($pending as $request) {
            try {
                app(ScheduledChangeService::class)->applyStatusRequest($request);
                Log::info("Status change #{$request->id} for customer {$request->customer_id} completed.");
            } catch (\Exception $e) {
                Log::error("Status change #{$request->id} failed: {$e->getMessage()}");
                $request->update([
                    'status'    => 'failed',
                    'error_log' => $e->getMessage(),
                ]);
            }
        }
    }
}
