<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageChanged;
use App\Services\ScheduledChangeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    /**
     * The authenticated portal user's Customer record.
     *
     * Strict IDOR protection: the customer is ALWAYS derived from the session
     * user (Auth::user()->customer) — never from request input.
     */
    protected function customer(Request $request): Customer
    {
        $customer = $request->user()->customer;
        if (!$customer) {
            abort(403, 'No ISP subscription is linked to this account.');
        }
        return $customer;
    }

    /**
     * GET /api/portal/packages
     *
     * All available packages + the customer's current plan + request history.
     */
    public function index(Request $request)
    {
        try {
            $customer = $this->customer($request);

            $requests = PackageChanged::where('customer_id', $customer->id)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'packages' => Package::orderBy('price')->get(),
                'current' => [
                    'package' => $customer->package,
                    'profile' => $customer->profile,
                    'monthlybill' => (float) ($customer->monthlybill ?? 0),
                ],
                'requests' => $requests,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Portal packages failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to load packages.'], 500);
        }
    }

    /**
     * POST /api/portal/package-change
     *
     * Requests an upgrade/downgrade. `apply_mode` controls whether the change
     * is applied immediately (executiondate <= today) or queued for the next
     * billing cycle. Reuses ScheduledChangeService so the RouterOS + local
     * update live in the same place as the admin flows.
     */
    public function requestChange(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'nullable|integer',
                'profile' => 'required|string|max:255',
                'monthlybill' => 'required|numeric',
                'apply_mode' => 'required|in:immediate,next_cycle',
                'executiondate' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
            ]);

            $customer = Customer::with('server')->find($this->customer($request)->id);

            if (!$customer) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            // Resolve the package name (prefer the id, fall back to the name).
            $packageName = $customer->package;
            if (!empty($request->input('package_id'))) {
                $packageName = Package::find((int) $request->input('package_id'))?->packagename ?: $packageName;
            }

            $today = now()->format('Y-m-d');
            $executionDate = $request->filled('executiondate')
                ? Carbon::parse($request->input('executiondate'))->format('Y-m-d')
                : $today;

            // "Next cycle" always defers to the start of the following month.
            if ($request->input('apply_mode') === 'next_cycle') {
                $executionDate = now()->addMonthNoOverflow()->startOfMonth()->format('Y-m-d');
            }

            $isImmediate = $executionDate <= $today;

            $record = [
                'customer_id' => $customer->id,
                'old_profile' => $customer->profile,
                'old_monthlybill' => $customer->monthlybill,
                'server' => $customer->server,
                'protocoltype' => $customer->protocoltype,
                'profile' => $request->input('profile'),
                'package' => $packageName,
                'monthlybill' => $request->input('monthlybill'),
                'notes' => $request->input('notes'),
                'requested_by' => Auth::user()?->name,
                'executiondate' => $executionDate,
            ];

            if ($isImmediate) {
                // Apply now through the shared service: MikroTik profile + kick.
                app(ScheduledChangeService::class)->applyPackageChange($customer, $request->input('profile'));

                $customer->update([
                    'package' => $packageName,
                    'monthlybill' => $request->input('monthlybill'),
                    'profile' => $request->input('profile'),
                ]);

                $record['status'] = 'completed';
                $message = 'Package changed successfully.';
            } else {
                $record['status'] = 'pending';
                $message = 'Package change scheduled for ' . $executionDate . '.';
            }

            $change = PackageChanged::create($record);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $change,
            ], 200);
        } catch (Exception $e) {
            Log::error("Portal package change failed: {$e->getMessage()}");
            return response()->json(['status' => 'error', 'message' => 'Failed to request package change.'], 500);
        }
    }
}
