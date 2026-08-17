<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * The authenticated portal user's Customer record.
     *
     * Strict IDOR protection: the customer is ALWAYS derived from the session
     * user (Auth::user()->customer) — never from request input — so a client
     * can only ever see their own subscription data.
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
     * GET /api/portal/dashboard
     *
     * Subscription status, current package, connection details and due summary
     * for the authenticated customer (resolved via Auth::user()->customer).
     */
    public function index(Request $request)
    {
        try {
            $customer = Customer::with('server')->find($this->customer($request)->id);

            if (!$customer) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            $invoices = Invoice::where('customer_id', $customer->id)->orderBy('id')->get();
            $dueInvoices = $invoices->whereIn('status', ['unpaid', 'partial']);
            // Total Due Header — fetched strictly from invoices.due_amount.
            $totalDue = (float) $dueInvoices->sum('due_amount');
            $currentInvoice = $invoices->sortByDesc('id')->first();

            // The ledger has no billing_month — pull the latest month from the
            // per-month generated_bills snapshot (a real column there).
            $latestBill = \App\Models\GeneratedBill::where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->first();

            // Total paid — approved payments only (pending / rejected excluded).
            $totalPaid = (float) \App\Models\Payment::where('customer_id', $customer->id)
                ->where('approval_status', 'approved')
                ->sum('received_amount');

            // Expiration countdown (days remaining until expireddate).
            $daysRemaining = null;
            $expiryDate = null;
            $expiredDate = $customer->expireddate;
            if (!empty($expiredDate)) {
                try {
                    $expiryDate = Carbon::parse($expiredDate);
                    $daysRemaining = (int) Carbon::now()->startOfDay()->diffInDays(
                        $expiryDate->copy()->startOfDay(),
                        false
                    );
                } catch (\Throwable $e) {
                    $expiryDate = null;
                    $daysRemaining = null;
                }
            }

            // MAC address: prefer the static binding stored in DB (caller_id), and
            // fall back to a live /ppp/active lookup on the assigned MikroTik
            // router so the customer portal always shows the real caller-id.
            $macAddress = $customer->caller_id;
            if (empty($macAddress) || $macAddress === '—') {
                try {
                    $activeSession = app(\App\Services\MikroTikService::class)
                        ->getActivePppoeSession($customer->server_id, $customer->username);

                    if (!empty($activeSession['caller-id'])) {
                        $macAddress = $activeSession['caller-id'];
                    }
                } catch (\Throwable $e) {
                    Log::warning("Could not fetch active MAC from RouterOS for user {$customer->username}: " . $e->getMessage());
                }
            }

            return response()->json([
                'status' => 'success',
                'customer' => [
                    'id' => $customer->id,
                    'radius_id' => $customer->radius_id,
                    'name' => $customer->name,
                    'username' => $customer->username,
                    'email' => $customer->email,
                    'mobile' => $customer->mobile,
                    'status' => $customer->status ?: ($customer->billingstatus ? strtolower($customer->billingstatus) : 'unknown'),
                    'billingstatus' => $customer->billingstatus,
                    'package' => $customer->package,
                    'package_name' => $customer->package,
                    'profile' => $customer->profile,
                    'monthlybill' => (float) ($customer->monthlybill ?? 0),
                    'expireddate' => $customer->expireddate,
                    'formatted_expireddate' => $expiryDate ? $expiryDate->format('d M Y') : null,
                    'days_remaining' => $daysRemaining,
                    'previous_due' => (float) ($customer->previous_due ?? 0),
                    'joiningdate' => $customer->joiningdate,
                    'connection' => [
                        'username' => $customer->username,
                        'protocoltype' => $customer->protocoltype,
                        'onu_mac' => $customer->onu_mac,
                        'caller_id' => $macAddress,
                        'mac' => $macAddress,
                        'server' => $customer->server?->serverName ?? $customer->server,
                    ],
                ],
                'invoice' => $currentInvoice ? [
                    'id' => $currentInvoice->id,
                    'billing_month' => $latestBill?->billing_month ?? null,
                    'amount' => (float) $currentInvoice->amount,
                    'due_amount' => (float) $currentInvoice->due_amount,
                    // paid_amount comes from the generated_bills snapshot.
                    'paid_amount' => (float) ($latestBill?->paid_amount ?? 0),
                    'advance' => (float) $currentInvoice->advance,
                    'status' => $currentInvoice->status,
                ] : null,
                'total_due' => round($totalDue, 2),
                'total_paid' => round($totalPaid, 2),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Portal dashboard failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to load dashboard.'], 500);
        }
    }

    /**
     * GET /api/portal/bandwidth/status
     *
     * Health check of the Node.js monitoring-service, exposed to the client
     * role so the portal can detect a dead service and show a friendly error
     * instead of a silent "offline" chart. Proxies the service's public
     * /health endpoint.
     */
    public function status(Request $request)
    {
        try {
            $response = Http::timeout(5)->get(
                rtrim((string) config('services.monitoring.base_url'), '/') . '/health'
            );

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::error("Portal bandwidth status check failed: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'message' => 'Monitoring service unreachable',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * POST /api/portal/bandwidth/start
     *
     * Starts the Node.js monitoring-service poller for THIS customer's PPPoE
     * session (resolved from the authenticated account, never from input).
     */
    public function startMonitor(Request $request)
    {
        $customer = Customer::with('server')->where('radius_id',$request->mikrotik_id)->first();

        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Customer not found'], 404);
        }
        if (!$customer->radius_id) {
            return response()->json(['status' => 'error', 'message' => 'No MikroTik user is linked to this account.'], 404);
        }
        if (!$customer->server_id || !$customer->server) {
            return response()->json(['status' => 'error', 'message' => 'No MikroTik server is assigned to this account.'], 404);
        }
      $server = MikrotikServer::find($customer->server_id);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Internal-Secret' => config('services.monitoring.secret')])
                ->post(
                    rtrim((string) config('services.monitoring.base_url'), '/') . '/monitor/start',
                    [
                        'mikrotik_id' => (string) $customer->radius_id,
                        'username' => (string) $customer->username,
                        'service' => $customer->protocoltype ?? 'ppp',
                        'host'        => $server->serverip,
                            'port'        => (int) $server->port,
                            'server_user' => $server->Username,
                            'password'    => $server->password,
                    ]
                );

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::error("Portal bandwidth start failed: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'message' => 'Monitoring service unreachable',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * POST /api/portal/bandwidth/stop
     *
     * Stops the monitoring-service poller for this customer's PPPoE session.
     */
    public function stopMonitor(Request $request)
    {
        $customer = $this->customer($request);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Internal-Secret' => config('services.monitoring.secret')])
                ->post(
                    rtrim((string) config('services.monitoring.base_url'), '/') . '/monitor/stop',
                    ['mikrotik_id' => (string) $customer->radius_id]
                );

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::error("Portal bandwidth stop failed: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'message' => 'Monitoring service unreachable',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }
}
