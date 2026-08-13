<?php

namespace App\Http\Controllers;

use App\Models\PackageChanged;
use App\Models\StatusChanged;
use App\Services\ScheduledChangeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Change Request & Scheduler management.
 *
 * Both tables (package_changed + status_changed) are treated as a single
 * "change request" stream with a `type` discriminator, so the Change Request
 * page (approve / reject / edit date) and the Scheduler page (queue, force
 * run, retry, error logs) operate on the exact same records the artisan
 * command `isp:process-scheduled-changes` executes.
 *
 * Actual execution (MikroTik + local customer update + marking completed)
 * always goes through ScheduledChangeService so there is ONE implementation.
 */
class ChangeRequestController extends Controller
{
    /**
     * GET /api/change-requests?type=&status=&requested_by=&from_date=&to_date=&page=&per_page=
     *
     * Merged, paginated list of package + status change requests with summary
     * counts for the page's stat cards.
     *
     * @param string $type        package | status (optional)
     * @param string $status      pending | approved | rejected (optional)
     * @param string $requested_by admin/user who created the request (optional)
     * @param string $from_date   Y-m-d lower bound on created_at / executiondate (optional)
     * @param string $to_date     Y-m-d upper bound on created_at / executiondate (optional)
     */
    public function index(Request $request)
    {
        try {
            $type = $request->input('type'); // package | status
            $status = $request->input('status');

            $rows = $this->mergedRequests([
                'type'         => $type,
                'status'       => $status,
                'requested_by' => $request->input('requested_by'),
                'from_date'    => $request->input('from_date'),
                'to_date'      => $request->input('to_date'),
            ])->sortByDesc('created_at')->values();

            $payload = $this->paginate($rows, $request);

            // Summary cards (respect the type filter, never the status filter —
            // the cards ARE the status dimension)
            $payload['meta'] = [
                'pending_total'       => $this->countAcross(PackageChanged::query()->where('status', 'pending'), StatusChanged::query()->where('status', 'pending'), $type),
                'approved_today'      => $this->countAcross(PackageChanged::query()->where('status', 'approved')->whereDate('updated_at', now()->toDateString()), StatusChanged::query()->where('status', 'approved')->whereDate('updated_at', now()->toDateString()), $type),
                'rejected_today'      => $this->countAcross(PackageChanged::query()->where('status', 'rejected')->whereDate('updated_at', now()->toDateString()), StatusChanged::query()->where('status', 'rejected')->whereDate('updated_at', now()->toDateString()), $type),
                'requested_by_options' => $this->requestedByOptions($type),
            ];

            return response()->json($payload, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch change requests: {$e->getMessage()}");
            Log::error($e->getTraceAsString());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch change requests.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/change-requests/{id}/approve
     * Body: { type: package|status, executiondate?: YYYY-MM-DD }
     *
     * - An optional executiondate lets the admin EDIT the date before approving.
     * - If the (possibly edited) date is today or earlier the change is executed
     *   immediately (completed / failed + error_log).
     * - If the date is in the future the request moves to the scheduler queue
     *   (status = approved) and the cron command executes it on the due date.
     */
    public function approve(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type'          => 'required|in:package,status',
                'executiondate' => 'nullable|date',
            ]);

            $record = $this->resolveRecord($id, $validated['type']);
            if (!$record) {
                return response()->json(['status' => 'error', 'message' => 'Change request not found.'], 404);
            }
            if ($record->status !== 'pending') {
                return response()->json(['status' => 'error', 'message' => 'Only pending requests can be approved.'], 422);
            }

            if (!empty($validated['executiondate'])) {
                $record->update(['executiondate' => Carbon::parse($validated['executiondate'])->format('Y-m-d')]);
            }

            $executionDate = Carbon::parse($record->executiondate)->format('Y-m-d');
            $isImmediate = $executionDate <= now()->format('Y-m-d');

            if ($isImmediate) {
                try {
                    $this->executeRecord($record, $validated['type']);
                    $message = 'Request approved and executed successfully.';
                } catch (\Exception $e) {
                    Log::error("Change request #{$id} approved but failed: {$e->getMessage()}");
                    return response()->json([
                        'status'   => 'error',
                        'message'  => 'Request was approved but execution failed: ' . $e->getMessage(),
                        'error'    => $e->getMessage(),
                        'executed' => false,
                    ], 200);
                }
            } else {
                $record->update(['status' => 'approved']);
                $message = 'Request approved — scheduled for ' . $executionDate . '.';
            }

            return response()->json([
                'status'   => 'success',
                'message'  => $message,
                'executed' => $isImmediate,
                'data'     => $this->normalizeRecord($record, $validated['type']),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to approve change request #{$id}: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to approve the request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/change-requests/{id}/reject
     * Body: { type: package|status, rejection_reason: string }
     */
    public function reject(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type'             => 'required|in:package,status',
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $record = $this->resolveRecord($id, $validated['type']);
            if (!$record) {
                return response()->json(['status' => 'error', 'message' => 'Change request not found.'], 404);
            }
            if ($record->status !== 'pending') {
                return response()->json(['status' => 'error', 'message' => 'Only pending requests can be rejected.'], 422);
            }

            $record->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Request rejected successfully.',
                'data'    => $this->normalizeRecord($record, $validated['type']),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to reject change request #{$id}: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reject the request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/change-requests/{id}/update-date
     * Body: { type: package|status, executiondate: YYYY-MM-DD }
     *
     * Reschedule the effective date of a pending or already-scheduled
     * (approved/queued) change request.
     */
    public function updateDate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type'          => 'required|in:package,status',
                'executiondate' => 'required|date',
            ]);

            $record = $this->resolveRecord($id, $validated['type']);
            if (!$record) {
                return response()->json(['status' => 'error', 'message' => 'Change request not found.'], 404);
            }
            if (!in_array($record->status, ['pending', 'approved'], true)) {
                return response()->json(['status' => 'error', 'message' => 'Only pending or scheduled requests can be rescheduled.'], 422);
            }

            $record->update(['executiondate' => Carbon::parse($validated['executiondate'])->format('Y-m-d')]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Execution date updated successfully.',
                'data'    => $this->normalizeRecord($record, $validated['type']),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update change request date #{$id}: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update the execution date.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/scheduler?tab=queue|logs&type=&requested_by=&from_date=&to_date=&page=&per_page=
     *
     * - tab=queue (default): upcoming tasks — status pending/approved with an
     *   execution date of today or later, soonest first.
     * - tab=logs: execution history — completed & failed, most recent first.
     *
     * @param string $requested_by admin/user who created the request (optional)
     * @param string $from_date    Y-m-d lower bound on created_at / executiondate (optional)
     * @param string $to_date      Y-m-d upper bound on created_at / executiondate (optional)
     *
     * meta: { scheduled_today, completed_total, failed_total, requested_by_options }
     */
    public function schedulerIndex(Request $request)
    {
        try {
            $type = $request->input('type');
            $tab = $request->input('tab', 'queue');

            $rows = $this->mergedRequests([
                'type'         => $type,
                'requested_by' => $request->input('requested_by'),
                'from_date'    => $request->input('from_date'),
                'to_date'      => $request->input('to_date'),
            ]);

            if ($tab === 'logs') {
                $rows = $rows->filter(fn ($r) => in_array($r['status'], ['completed', 'failed'], true))
                    ->sortByDesc('updated_at');
            } else {
                $rows = $rows->filter(function ($r) {
                    if (!in_array($r['status'], ['pending', 'approved'], true)) {
                        return false;
                    }
                    // Legacy rows (e.g. from updateclientbillingstatus) may have
                    // a NULL executiondate — exclude them instead of letting
                    // Carbon::parse(null) treat them as "due now".
                    if (empty($r['executiondate'])) {
                        return false;
                    }
                    $date = Carbon::parse($r['executiondate']);
                    return $date->isToday() || $date->isFuture();
                })->sortBy('executiondate');
            }

            $rows = $rows->values();
            $payload = $this->paginate($rows, $request);
            $payload['tab'] = $tab;

            // Summary cards (respect the type filter)
            $today = now()->toDateString();
            $payload['meta'] = [
                'scheduled_today'     => $this->countAcross(PackageChanged::query()->whereIn('status', ['pending', 'approved'])->whereDate('executiondate', $today), StatusChanged::query()->whereIn('status', ['pending', 'approved'])->whereDate('executiondate', $today), $type),
                'completed_total'     => $this->countAcross(PackageChanged::query()->where('status', 'completed'), StatusChanged::query()->where('status', 'completed'), $type),
                'failed_total'        => $this->countAcross(PackageChanged::query()->where('status', 'failed'), StatusChanged::query()->where('status', 'failed'), $type),
                'requested_by_options' => $this->requestedByOptions($type),
            ];

            return response()->json($payload, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch scheduler records: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch scheduler records.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/scheduler/{id}/force-run
     * Body: { type: package|status }
     *
     * Execute a queued (pending/approved) task immediately, bypassing the
     * midnight cron.
     */
    public function forceRun(Request $request, $id)
    {
        return $this->runTask($request, $id, ['pending', 'approved'], 'forced to run');
    }

    /**
     * POST /api/scheduler/{id}/retry
     * Body: { type: package|status }
     *
     * Re-attempt a failed task after the underlying issue was fixed.
     */
    public function retry(Request $request, $id)
    {
        return $this->runTask($request, $id, ['failed'], 'retried');
    }

    /**
     * Shared execution for force-run / retry.
     */
    protected function runTask(Request $request, $id, array $allowedStatuses, string $actionLabel)
    {
        try {
            $validated = $request->validate(['type' => 'required|in:package,status']);

            $record = $this->resolveRecord($id, $validated['type']);
            if (!$record) {
                return response()->json(['status' => 'error', 'message' => 'Scheduler record not found.'], 404);
            }
            if (!in_array($record->status, $allowedStatuses, true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Only " . implode('/', $allowedStatuses) . " tasks can be {$actionLabel}.",
                ], 422);
            }

            try {
                $this->executeRecord($record, $validated['type']);
                $message = 'Task ' . $actionLabel . ' and executed successfully.';
            } catch (\Exception $e) {
                Log::error("Scheduler task #{$id} {$actionLabel} but failed: {$e->getMessage()}");
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Task execution failed: ' . $e->getMessage(),
                    'error'   => $e->getMessage(),
                ], 200);
            }

            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'data'    => $this->normalizeRecord($record, $validated['type']),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Scheduler task #{$id} action failed: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to ' . $actionLabel . ' the task.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Merge package + status change requests into one normalized collection.
     *
     * Supported filters: type, status, requested_by, from_date, to_date.
     * The date range matches rows whose created_at OR executiondate falls
     * between from_date and to_date (inclusive).
     */
    protected function mergedRequests(array $filters = []): Collection
    {
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $requestedBy = $filters['requested_by'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $merged = collect();

        if ($type === null || $type === 'package') {
            $query = PackageChanged::with('customer')->orderByDesc('created_at');
            $this->applyCommonFilters($query, $status, $requestedBy, $fromDate, $toDate);
            $query->get()->each(function ($row) use (&$merged) {
                $merged->push($this->normalizePackage($row));
            });
        }

        if ($type === null || $type === 'status') {
            $query = StatusChanged::with('customer')->orderByDesc('created_at');
            $this->applyCommonFilters($query, $status, $requestedBy, $fromDate, $toDate);
            $query->get()->each(function ($row) use (&$merged) {
                $merged->push($this->normalizeStatus($row));
            });
        }

        return $merged;
    }

    /**
     * Apply the shared status / requested_by / date-range filters to an
     * Eloquent query builder for either change table.
     */
    protected function applyCommonFilters($query, ?string $status, ?string $requestedBy, ?string $fromDate, ?string $toDate): void
    {
        if ($status) {
            $query->where('status', $status);
        }

        if ($requestedBy) {
            $query->where('requested_by', $requestedBy);
        }

        if ($fromDate || $toDate) {
            $query->where(function ($q) use ($fromDate, $toDate) {
                $q->where(function ($created) use ($fromDate, $toDate) {
                    $this->applyDateRange($created, 'created_at', $fromDate, $toDate);
                })->orWhere(function ($executed) use ($fromDate, $toDate) {
                    $this->applyDateRange($executed, 'executiondate', $fromDate, $toDate);
                });
            });
        }
    }

    /**
     * Constrain a query builder column with an inclusive date range.
     */
    protected function applyDateRange($query, string $column, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate) {
            $query->whereDate($column, '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate($column, '<=', $toDate);
        }
    }

    /**
     * Unique list of admins/users who created requests, used to populate the
     * "Created By" filter dropdown. Respects the optional type filter.
     */
    protected function requestedByOptions(?string $type): array
    {
        $options = collect();

        if ($type !== 'status') {
            $options = $options->merge(
                PackageChanged::query()->whereNotNull('requested_by')->where('requested_by', '!=', '')->distinct()->pluck('requested_by')
            );
        }
        if ($type !== 'package') {
            $options = $options->merge(
                StatusChanged::query()->whereNotNull('requested_by')->where('requested_by', '!=', '')->distinct()->pluck('requested_by')
            );
        }

        return $options->filter()->unique()->sort()->values()->all();
    }

    /**
     * Normalize a package_changed row into the shared request shape.
     */
    protected function normalizePackage(PackageChanged $p): array
    {
        $customer = $p->customer;

        $current = trim(
            ($p->old_profile ? 'Profile: ' . $p->old_profile : '')
            . ($p->old_monthlybill !== null ? ' · Bill: ' . $p->old_monthlybill : '')
        );

        $requested = trim(
            'Profile: ' . $p->profile
            . ($p->package ? ' · Package: ' . $p->package : '')
            . ($p->monthlybill !== null ? ' · Bill: ' . $p->monthlybill : '')
        );

        return [
            'id'               => $p->id,
            'type'             => 'package',
            'status'           => $p->status,
            'customer_id'      => $p->customer_id,
            'customer_name'    => $customer?->name,
            'username'         => $customer?->username,
            'mobile'           => $customer?->mobile,
            'current_value'    => $current ?: '—',
            'requested_value'  => $requested ?: '—',
            'executiondate'    => $p->executiondate,
            'requested_by'     => $p->requested_by,
            'notes'            => $p->notes,
            'rejection_reason' => $p->rejection_reason,
            'error_log'        => $p->error_log,
            'created_at'       => optional($p->created_at)->toDateTimeString(),
            'updated_at'       => optional($p->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * Normalize a status_changed row into the shared request shape.
     */
    protected function normalizeStatus(StatusChanged $s): array
    {
        $customer = $s->customer;

        return [
            'id'               => $s->id,
            'type'             => 'status',
            'status'           => $s->status,
            'customer_id'      => $s->customer_id,
            'customer_name'    => $customer?->name,
            'username'         => $customer?->username,
            'mobile'           => $customer?->mobile,
            'current_value'    => $s->old_billingstatus ?: '—',
            'requested_value'  => $s->billingstatus,
            'executiondate'    => $s->executiondate,
            'requested_by'     => $s->requested_by,
            'notes'            => $s->notes,
            'rejection_reason' => $s->rejection_reason,
            'error_log'        => $s->error_log,
            'created_at'       => optional($s->created_at)->toDateTimeString(),
            'updated_at'       => optional($s->updated_at)->toDateTimeString(),
        ];
    }

    protected function normalizeRecord($record, string $type): array
    {
        return $type === 'package' ? $this->normalizePackage($record) : $this->normalizeStatus($record);
    }

    /**
     * Find a change request in the correct table by id + type.
     */
    protected function resolveRecord($id, string $type)
    {
        return $type === 'package'
            ? PackageChanged::with('customer')->find($id)
            : StatusChanged::with('customer')->find($id);
    }

    /**
     * Execute a change request via the shared service. Marks the record
     * completed on success; throws on failure (caller marks failed).
     *
     * @throws \Exception
     */
    protected function executeRecord($record, string $type): void
    {
        $service = app(ScheduledChangeService::class);
        if ($type === 'package') {
            $service->applyPackageRequest($record);
        } else {
            $service->applyStatusRequest($record);
        }
    }

    /**
     * PHP-side pagination for the merged (already sorted) collection.
     */
    protected function paginate(Collection $rows, Request $request): array
    {
        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $page = max((int) $request->input('page', 1), 1);
        $total = $rows->count();

        return [
            'status'       => 'success',
            'data'         => $rows->forPage($page, $perPage)->values()->all(),
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'per_page'     => $perPage,
        ];
    }

    /**
     * Sum a count across both change tables, honouring the optional type filter.
     * Pass the already-filtered package and status query builders.
     */
    protected function countAcross($packageQuery, $statusQuery, ?string $type): int
    {
        $total = 0;
        if ($type !== 'status') {
            $total += $packageQuery->count();
        }
        if ($type !== 'package') {
            $total += $statusQuery->count();
        }
        return $total;
    }
}
