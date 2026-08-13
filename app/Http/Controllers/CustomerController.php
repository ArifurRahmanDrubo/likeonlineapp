<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\ClientType;
use App\Models\CompanyProfile;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerBillingStatus;
use App\Models\District;
use App\Models\Employee;
use App\Models\GeneratedBill;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PackageChanged;
use App\Models\ProtocolType;
use App\Models\StatusChanged;
use App\Models\SystemPermission;
use App\Models\Upazila;
use App\Models\Upzila;
use App\Models\Zone;
use App\Services\ScheduledChangeService;
use Carbon\Carbon;
use Dotenv\Exception\ValidationException;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PEAR2\Net\RouterOS;
use RouterOS\Client;
use RouterOS\Exception as RouterOSException;
use RouterOS\Query;

class CustomerController extends Controller
{

    public function index()
    {
        try {
            $customers = Customer::with('invoice')->get();
            return response()->json([
                'customers' => $customers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching customers.'
            ], 500);
        }
    }
    /**
     * Master init endpoint: fetches all dropdown/lookup data needed by the
     * Add/Edit Client form in a single request (replaces 11 separate API calls
     * which used to hit the 429 rate limit).
     */
    public function getClientFormInitData()
    {
        try {
            return response()->json([
                'status' => true,
                'clientTypes' => ClientType::select('id', 'client_type')->orderBy('client_type')->get(),
                'districts' => District::select('id', 'districtname')->orderBy('districtname')->get(),
                'upzilas' => Upzila::select('id', 'upzilaname')->orderBy('upzilaname')->get(),
                'zones' => Zone::select('id', 'zone_name')->orderBy('zone_name')->get(),
                'servers' => MikrotikServer::select('id', 'serverName')->orderBy('serverName')->get(),
                'connectionTypes' => ConnectionType::select('id', 'connection_type')->orderBy('connection_type')->get(),
                'packages' => Package::select('id', 'packagename')->orderBy('packagename')->get(),
                'boxes' => Box::select('id', 'box_name')->orderBy('box_name')->get(),
                'protocolTypes' => ProtocolType::select('id', 'protocol_type')->orderBy('protocol_type')->get(),
                'billingStatuses' => CustomerBillingStatus::select('id', 'billingstatus')->orderBy('billingstatus')->get(),
                'employees' => Employee::select('id', 'name')->orderBy('name')->get(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load client form data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClient($id)
    {
        try {
            // Single eager-loaded query: avoids the N+1 lookups the profile
            // page previously triggered for invoice + server data.
            $customer = Customer::with(['invoice', 'server'])->find($id);

            if (!$customer) {
                return response()->json([
                    'message' => 'Client not found'
                ], 404);
            }

            return response()->json([
                'customer' => $customer
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function clientData()
    {
        try {
            $user = Auth::user();
            $customers = Customer::where('user_id', $user->id)->with('invoice')->first();
            return response()->json([
                'customers' => $customers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching customers.'
            ], 500);
        }
    }

    /**
     * Build a MikroTik API client from a server record.
     * (Used by the left-client flow; the rest of the controller still
     * constructs clients inline for now.)
     */
    protected function mikrotikClient(MikrotikServer $server): Client
    {
        return new Client([
            'host' => $server->serverip,
            'user' => $server->Username,
            'pass' => $server->password,
            'port' => $server->port,
        ]);
    }

    /**
     * Convert "Delete Customer" into "Make Left Client".
     *
     * 1. Disables the PPPoE secret on the assigned MikroTik server.
     * 2. Terminates any live /ppp/active session for the username.
     * 3. Clears the caller-id (MAC) binding on the secret.
     * 4. Updates the local record: status='left', left_date=now(),
     *    left_reason, caller_id=null, and frees onu_mac when the
     *    ONU/router was returned.
     *
     * The local update always runs — a router being unreachable logs a
     * warning instead of blocking the DB change.
     */
    public function makeClientLeft(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'left_reason' => 'required|string|max:255',
                'onu_returned' => 'nullable|boolean',
            ]);

            $id = $validated['id'];
            $leftReason = $validated['left_reason'];
            $onuReturned = $request->boolean('onu_returned');

            $customer = Customer::with('server')->find($id);
            if (!$customer) {
                return response()->json(['message' => 'Client not found.'], 404);
            }
            if ($customer->status === 'left') {
                return response()->json(['message' => 'Client is already marked as left.'], 422);
            }

            $warnings = [];

            // 1-3) Router operations (only when a server + radius id exist)
            if ($customer->server_id && $customer->radius_id) {
                try {
                    $server = $customer->server ?? MikrotikServer::find($customer->server_id);
                    if ($server) {
                        // $client = $this->mikrotikClient($server);

                        $client = new Client([
                            'host' => $server->serverip,
                            'user' => $server->Username,
                            'pass' => $server->password,
                            'port' => $server->port,
                        ]);
                        $client->connect();

                        // a) Disable the user in /ppp/secret
                        $disable = new Query('/ppp/secret/set');
                        $disable->equal('.id', $customer->radius_id);
                        $disable->equal('disabled', 'true');
                        $client->query($disable)->read();

                        // b) Terminate any active session immediately
                        $activeQuery = new Query('/ppp/active/print');
                        $activeQuery->where('name', $customer->username);
                        $sessions = $client->query($activeQuery)->read();
                        foreach ($sessions as $session) {
                            if (($session['name'] ?? null) === $customer->username && !empty($session['.id'])) {
                                $remove = new Query('/ppp/active/remove');
                                $remove->equal('.id', $session['.id']);
                                $client->query($remove)->read();
                                break;
                            }
                        }

                        // c) Clear/unbind caller-id on the secret
                        $unbind = new Query('/ppp/secret/set');
                        $unbind->equal('.id', $customer->radius_id);
                        $unbind->equal('caller-id', '');
                        $client->query($unbind)->read();
                    }
                } catch (\Exception $e) {
                    Log::warning("MikroTik left-client steps failed for customer {$id}: {$e->getMessage()}");
                    $warnings[] = 'MikroTik update skipped: ' . $e->getMessage();
                }
            }

            // 4) Local record — always updated.
            // billingstatus is kept in sync ('Left') so legacy dashboards that
            // still read the old field count these clients correctly.
            $update = [
                'status' => 'left',
                'billingstatus' => 'Left',
                'left_date' => now(),
                'left_reason' => $leftReason ?: null,
                'caller_id' => null,
                'mikrotikStatus' => false,
            ];
            if ($onuReturned) {
                // f) ONU/router returned — free the hardware field
                $update['onu_mac'] = null;
            }
            $customer->update($update);

            return response()->json([
                'status' => 'success',
                'message' => 'Client marked as left successfully.',
                'warnings' => $warnings,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to mark client left: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while marking the client as left.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a left client back to 'active' / 'expired' without creating a
     * duplicate record. Re-enables the MikroTik secret when possible and
     * clears the left-date/reason fields.
     */
    public function restoreLeftClient(Request $request)
    {
        try {
            $id = $request->input('id');
            $status = $request->input('status', 'active');
            if (!in_array($status, ['active', 'expired'])) {
                $status = 'active';
            }

            $customer = Customer::with('server')->find($id);
            if (!$customer) {
                return response()->json(['message' => 'Client not found.'], 404);
            }
            if ($customer->status !== 'left') {
                return response()->json(['message' => 'Client is not marked as left.'], 422);
            }

            $warnings = [];
            if ($customer->server_id && $customer->radius_id) {
                try {
                    $server = $customer->server ?? MikrotikServer::find($customer->server_id);
                    if ($server) {
                        // $client = $this->mikrotikClient($server);
                        $client = new Client([
                            'host' => $server->serverip,
                            'user' => $server->Username,
                            'pass' => $server->password,
                            'port' => $server->port,
                        ]);
                        $client->connect();
                        $enable = new Query('/ppp/secret/set');
                        $enable->equal('.id', $customer->radius_id);
                        $enable->equal('disabled', 'false');
                        $client->query($enable)->read();
                    }
                } catch (\Exception $e) {
                    Log::warning("MikroTik restore failed for customer {$id}: {$e->getMessage()}");
                    $warnings[] = 'MikroTik re-enable skipped: ' . $e->getMessage();
                }
            }

            $customer->update([
                'status' => $status,
                'billingstatus' => $status === 'active' ? 'Active' : ucfirst($status),
                'left_date' => null,
                'left_reason' => null,
                'mikrotikStatus' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Client restored successfully.',
                'warnings' => $warnings,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to restore left client: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while restoring the client.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Single endpoint for the Left Client page.
     *
     * GET /api/customers/left-clients?page=&per_page=&left_reason=&date_from=&date_to=
     *
     * - Queries ONLY customers where status = 'left'.
     * - Accepts pagination (page, per_page) plus the structured reason/date
     *   filters. The free-text SEARCH is deliberately NOT processed here — it
     *   is handled 100% reactively on the frontend across all columns.
     * - Returns a predictable payload: { status, data, current_page,
     *   last_page, total, per_page, meta }.
     *
     * NOTE: eager-loading uses with('server') only. 'package' is a COLUMN on
     * customers, not a relation — with('package') throws RelationNotFoundException.
     */
    public function leftClients(Request $request)
    {
        try {


            $perPage = max(1, $request->integer('per_page', 25));

            $query = Customer::with('server')->where('status', 'left');

            // Exact reason filter (dropdown)
            if ($reason = $request->input('left_reason')) {
                $query->where('left_reason', $reason);
            }

            // Date range filter on left_date (calendar pickers)
            if ($from = $request->input('date_from')) {
                $query->whereDate('left_date', '>=', Carbon::parse($from));
            }
            if ($to = $request->input('date_to')) {
                $query->whereDate('left_date', '<=', Carbon::parse($to));
            }

            $leftClients = $query->orderByDesc('left_date')->paginate($perPage);

            // Aggregated metadata (counts respect the date filters too)
            $statsQuery = Customer::where('status', 'left');
            if ($from = $request->input('date_from')) {
                $statsQuery->whereDate('left_date', '>=', Carbon::parse($from));
            }
            if ($to = $request->input('date_to')) {
                $statsQuery->whereDate('left_date', '<=', Carbon::parse($to));
            }

            $totalLeft = $statsQuery->count();
            $leftThisMonth = Customer::where('status', 'left')
                ->whereBetween('left_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();

            // Reasons breakdown mirrors the same date range as the table so the
            // dropdown options always match the visible rows.
            $reasonsQuery = Customer::where('status', 'left')
                ->whereNotNull('left_reason')
                ->where('left_reason', '!=', '');
            if ($from = $request->input('date_from')) {
                $reasonsQuery->whereDate('left_date', '>=', Carbon::parse($from));
            }
            if ($to = $request->input('date_to')) {
                $reasonsQuery->whereDate('left_date', '<=', Carbon::parse($to));
            }
            $reasons = $reasonsQuery
                ->selectRaw('left_reason, COUNT(*) as total')
                ->groupBy('left_reason')
                ->orderByDesc('total')
                ->get();

            return response()->json([
                'status'       => 'success',
                'data'         => $leftClients->items(),
                'current_page' => $leftClients->currentPage(),
                'last_page'    => $leftClients->lastPage(),
                'total'        => $leftClients->total(),
                'per_page'     => $leftClients->perPage(),
                'meta'         => [
                    'total_left'      => $totalLeft,
                    'left_this_month' => $leftThisMonth,
                    'reasons'         => $reasons,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the REAL exception so missing columns / bad relations are visible
            Log::error("Failed to fetch left clients: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch left clients.',
                'error'   => $e->getMessage(), // surfaced for easier debugging
            ], 500);
        }
    }


    public function clientlistdashboard()
    {
        // Count left clients via the new status column OR the legacy billingstatus.
        $numberofRunningClient = Customer::where('billingstatus', '!=', 'Left')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'left');
            })
            ->count();
        $numberofLeftClient = Customer::where(function ($q) {
            $q->where('status', 'left')->orWhere('billingstatus', 'Left');
        })->count();
        $numberofFreeClient = Customer::where('billingstatus', '=', 'Free')->count();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Count the number of new clients created in the current month
        $newClientsCount = Customer::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();
        return response()->json([
            'RunningClient' => $numberofRunningClient,
            'LeftClient' => $numberofLeftClient,
            'newClientsCount' => $newClientsCount,
            'numberofFreeClient' => $numberofFreeClient
        ], 200);
    }
    public function billinglistdashboard()
    {
        $paidClient = Customer::whereHas('invoice', function ($query) {
            $query->where('status', '=', 'paid'); // Adjust this field based on your actual invoice status column
        })->count();
        $unpaidClient = Customer::whereHas('invoice', function ($query) {
            $query->where('status', '=', 'unpaid'); // Adjust this field based on your actual invoice status column
        })->count();
        $received_amount = Invoice::whereHas('customer')
            ->sum('received_amount');
        $due_amount = Invoice::whereHas('customer')
            ->sum('amount');
        $advance_amount = Invoice::whereHas('customer')
            ->sum('advance');
        $generated_bill = GeneratedBill::whereHas('customer')
            ->sum('amount');
        return response()->json([
            'paidClient' => $paidClient,
            'unpaidClient' => $unpaidClient,
            'received_amount' => $received_amount,
            'due_amount' => $due_amount,
            'generated_bill' => $generated_bill,
            'advance_amount ' => $advance_amount

        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'occupation' => 'nullable|string|max:255',
                'remarks' => 'nullable|string',
                'nid' => 'nullable|string|max:255',
                'gender' => 'nullable',
                'dateofbirth' => 'nullable',
                'registrationno' => 'nullable|string|max:255',
                'fathername' => 'nullable|string|max:255',
                'mothername' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'facebook' => 'nullable|string|max:255',
                'linkedin' => 'nullable|string|max:255',
                'twitter' => 'nullable|string|max:255',
                'mobile' => 'required|string',
                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255',
                'district' => 'nullable|string|max:255',
                'upzila' => 'nullable|string|max:255',
                'roadnumber' => 'nullable|string|max:255',
                'housenumber' => 'nullable|string|max:255',
                'praddress' => 'nullable|string',
                'paraddress' => 'nullable|string',
                'server' => 'required|string|max:255',
                'subzone' => 'nullable|string|max:255',
                'zone' => 'required|string|max:255',
                'protocoltype' => 'required|string|max:255',
                'box' => 'nullable|string|max:255',
                'connectiontype' => 'required|string|max:255',
                'cable' => 'nullable|string|max:255',
                'fiber' => 'nullable|string|max:255',
                'coreno' => 'nullable|string|max:255',
                'corecolor' => 'nullable|string|max:255',
                'con_charge' => 'nullable|string|max:255',
                'package' => 'required|string|max:255',
                'profile' => 'required|string|max:255',
                'username' => 'required|string|max:255',
                'password' => 'required|string|max:255',
                'clienttype' => 'required|string|max:255',
                'expireddate' => 'required',
                'server_id' => 'required|integer|max:255',
                'radius_id' => 'nullable',

                'referenceby' => 'nullable|string|max:255',
                'connectedby' => 'nullable|string|max:255',
                'joiningdate' => 'required',
                'billingmonth' => 'required|string|max:255',
                'billingstatus' => 'required|string|max:255',
                'monthlybill' => 'required|numeric',
            ]);
            // Upload images to Cloudinary (update replaces the old image, create uploads new)
            $existingCustomer = $request->input('id') ? Customer::find($request->input('id')) : null;
            foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                if ($request->hasFile($field)) {
                    $imageData = $existingCustomer
                        ? cloudinary_update($request->file($field), $existingCustomer->{$field . '_public_id'}, 'customers')
                        : cloudinary_upload($request->file($field), 'customers');
                    $validated[$field] = $imageData['url'];
                    $validated[$field . '_public_id'] = $imageData['public_id'];
                }
            }



            $mobile = $request->input('mobile');
            $address = $request->input('address');
            $username = $request->input('username');
            $password = $request->input('password');
            $profile = $request->input('profile');
            $protocoltype = $request->input('protocoltype');
            $serverId = $request->input('server_id');
            $radius_id = $request->input('radius_id');
            $connectedby = $request->input('connectedby');
            $subzone = $request->input('subzone');
            $housenumber = $request->input('housenumber');
            $name = $request->input('name');
            $comment = $request->input('remarks');
            $joiningDate = $request->input('joiningdate');

            $dateString = $joiningDate;
            $simplifiedDateString = preg_replace('/ \([^\)]+\)$/', '', $dateString);
            $date = Carbon::parse($simplifiedDateString);
            $formattedDate = $date->format('d F Y');
            $validated['joiningdate'] = $formattedDate;

            $fullComment = $comment;
            if ($name) {
                $fullComment .= " Name: " . $name;
            }
            if ($mobile) {
                $fullComment .= " Mobile: " . $mobile;
            }
            if ($address) {
                $fullComment .= " Address: " . $address;
            }
            if ($housenumber) {
                $fullComment .= " House No: " . $housenumber;
            }
            if ($subzone) {
                $fullComment .= "Location: " . $subzone;
            }
            if ($connectedby) {
                $fullComment .= " Connectedby: " . $subzone;
            }
            if ($request->input('id')) {
                $customer = $existingCustomer;

                $settings = SystemPermission::first();

                if ($settings && $settings->save_comment_in_mikrotik === 'enable') {
                    $comment = $fullComment;
                } else {
                    $comment = '';
                }
                $mikrotikServer = MikrotikServer::find($request->input('server_id'));
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // Connect to the router
                $client->connect();
                $query = new Query('/ppp/secret/set');
                $query->equal('.id', $radius_id);
                $query->equal('name', $username);
                $query->equal('password', $password);
                $query->equal('profile', $profile);
                $query->equal('service', $protocoltype);
                $query->equal('comment', $comment);
                $response = $client->query($query)->read();



                $customer->update($validated);
                return response()->json(['message' => 'Client updated successfully']);
            } else {
                // Customer::create($validated);
                if ($radius_id) {
                    Customer::create($validated);
                } else {
                    $mikrotikServer = MikrotikServer::findOrFail($serverId);
                    $mikrotikClienCreate = $this->addMikrotikUser($mikrotikServer, $username, $password, $profile, $fullComment);
                    $validated['radius_id'] = $mikrotikClienCreate;
                    Customer::create($validated);
                }

                return response()->json(['message' => 'Client created successfully']);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updateclientbillingstatus(Request $request)
    {
        try {
            $customer_id = $request->input('id');
            $billingstatus = $request->input('billingstatus');
            $notes = $request->input('notes');
            $executiondate = $request->input('executiondate');
            // Only stamp a date when one is provided (Carbon::parse(null) would silently record "now")
            $formattedDate = $executiondate ? Carbon::parse($executiondate)->format('d F Y') : null;

            $customer = Customer::where('id', $customer_id)->first();
            StatusChanged::create([
                'customer_id' => $customer_id,
                'billingstatus' => $billingstatus,
                'notes' => $notes,
                'executiondate' => $formattedDate,
                'status' => 'completed', // applied immediately, never enters the pending queue
            ]);
            $customer->update([
                'billingstatus' => $billingstatus,
            ]);
            return response()->json([
                'message' => ' Updated Status Successful.'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while updated the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updatebulkStatus(Request $request)
    {
        try {
            $billingStatus = $request->input('billingstatus');
            Customer::whereIn('id', $request->ids)->update([
                'billingstatus' => $billingStatus
            ]);
            return response()->json([
                'message' => ' Updated Status Successful.'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while updated the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updatepPackageStatus(Request $request)
    {
        try {
            $customer_id = $request->input('customer_id');
            $server = $request->input('server');
            $notes = $request->input('notes');
            $protocoltype = $request->input('protocoltype');
            $profile = $request->input('profile');
            $package = $request->input('package');
            $monthlybill = $request->input('monthlybill');
            $executiondate = $request->input('executiondate');
            // Only stamp a date when one is provided (Carbon::parse(null) would silently record "now")
            $formattedDate = $executiondate ? Carbon::parse($executiondate)->format('d F Y') : null;

            // Fetch the customer
            $customer = Customer::findOrFail($customer_id);

            // Create a record in PackageChanged if any data is provided
            PackageChanged::create([
                'customer_id' => $customer_id,
                'server' => $server,
                'protocoltype' => $protocoltype,
                'profile' => $profile,
                'package' => $package,
                'monthlybill' => $monthlybill,
                'notes' => $notes,
                'executiondate' => $formattedDate,
                'status' => 'completed', // applied immediately, never enters the pending queue
            ]);

            // Prepare update data based on provided fields
            $updateData = [];
            if ($profile) {
                $updateData['profile'] = $profile;
                // Update MikroTik server if profile is provided.
                // Fall back to the customer's stored server when the request
                // omits server_id (the frontend never sent it, which used to
                // make every package change throw a ModelNotFoundException).
                $mikrotikServer = MikrotikServer::findOrFail($request->input('server_id') ?: $customer->server_id);
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
                $radius_id = $customer->radius_id;
                // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                $updateRequest = new Query('/ppp/secret/set');
                // $updateRequest->setArgument('.id', $radius_id);
                $updateRequest->equal('.id', $radius_id);
                $updateRequest->equal('profile', $profile);
                $client->query($updateRequest)->read();
            }
            if ($server) {
                $updateData['server'] = $server;
            }
            if ($protocoltype) {
                $updateData['protocoltype'] = $protocoltype;
            }
            if ($package) {
                $updateData['package'] = $package;
            }
            if ($monthlybill) {
                $updateData['monthlybill'] = $monthlybill;
            }

            // Update customer details if there's any update data
            if (!empty($updateData)) {
                $customer->update($updateData);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Updated package status successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the data.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Change a customer's package — immediately (executiondate <= today) or
     * scheduled (executiondate > today, processed by the cron command).
     *
     * POST /api/customers/{id}/change-package
     * Accepts: package_id (optional), package (name), profile, monthlybill,
     *          executiondate (YYYY-MM-DD), notes.
     */
    public function changePackage(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'package_id'    => 'nullable|integer',
                'package'       => 'nullable|string|max:255',
                'profile'       => 'required|string|max:255',
                'monthlybill'   => 'required|numeric',
                'executiondate' => 'required|date',
                'notes'         => 'nullable|string|max:1000',
            ]);

            $customer = Customer::with('server')->findOrFail($id);

            // Resolve the package name: prefer the explicit name, else the id.
            $packageName = $validated['package'] ?? null;
            if (!$packageName && !empty($validated['package_id'])) {
                $packageName = Package::find($validated['package_id'])?->packagename;
            }
            $packageName = $packageName ?: $customer->package;

            $executionDate = Carbon::parse($validated['executiondate'])->format('Y-m-d');
            $today = now()->format('Y-m-d');
            $isImmediate = $executionDate <= $today;

            $record = [
                'customer_id'     => $customer->id,
                'old_profile'     => $customer->profile,
                'old_monthlybill' => $customer->monthlybill,
                'server'          => $customer->server,
                'protocoltype'    => $customer->protocoltype,
                'profile'         => $validated['profile'],
                'package'         => $packageName,
                'monthlybill'     => $validated['monthlybill'],
                'notes'           => $validated['notes'],
                'requested_by'    => Auth::user()?->name,
                'executiondate'   => $executionDate,
            ];

            if ($isImmediate) {
                // Apply now: MikroTik profile + kick session, then local update.
                app(ScheduledChangeService::class)->applyPackageChange($customer, $validated['profile']);

                $customer->update([
                    'package'     => $packageName,
                    'monthlybill' => $validated['monthlybill'],
                    'profile'     => $validated['profile'],
                ]);

                $record['status'] = 'completed';
                $message = 'Package changed successfully.';
            } else {
                $record['status'] = 'pending';
                $message = 'Package change scheduled for ' . $executionDate . '.';
            }

            $packageChanged = PackageChanged::create($record);

            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'data'    => $packageChanged,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to change package for customer {$id}: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to change the package.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change a customer's billing status — immediately or scheduled.
     *
     * POST /api/customers/{id}/change-status
     * Accepts: billingstatus (active|suspended|expired|left), executiondate,
     *          notes.
     */
    public function changeStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'billingstatus' => 'required|in:active,suspended,expired,left',
                'executiondate' => 'required|date',
                'notes'         => 'nullable|string|max:1000',
            ]);

            $customer = Customer::with('server')->findOrFail($id);
            $status = $validated['billingstatus'];

            $executionDate = Carbon::parse($validated['executiondate'])->format('Y-m-d');
            $today = now()->format('Y-m-d');
            $isImmediate = $executionDate <= $today;

            $record = [
                'customer_id'      => $customer->id,
                'old_billingstatus' => $customer->status ?: $customer->billingstatus,
                'billingstatus'    => $status,
                'notes'            => $validated['notes'],
                'requested_by'     => Auth::user()?->name,
                'executiondate'    => $executionDate,
            ];

            if ($isImmediate) {
                // Apply now: enable/disable secret + kick session, then update local.
                app(ScheduledChangeService::class)->applyStatusChange($customer, $status);

                $customer->update([
                    'status'         => $status,
                    'billingstatus'  => ucfirst($status),
                    'mikrotikStatus' => $status === 'active', // keeps dashboard Online/Inactive counts in sync
                    'caller_id'      => $status === 'left' ? null : $customer->caller_id,
                    'left_date'      => $status === 'left' ? now() : $customer->left_date,
                    'left_reason'    => $status === 'left' ? ($validated['notes'] ?: $customer->left_reason) : $customer->left_reason,
                ]);

                $record['status'] = 'completed';
                $message = 'Status changed successfully.';
            } else {
                $record['status'] = 'pending';
                $message = 'Status change scheduled for ' . $executionDate . '.';
            }

            $statusChanged = StatusChanged::create($record);

            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'data'    => $statusChanged,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to change status for customer {$id}: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to change the status.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * History of package + status change requests for one customer.
     *
     * GET /api/customers/{id}/changes
     */
    public function getCustomerChanges($id)
    {
        try {
            $packageChanges = PackageChanged::where('customer_id', $id)
                ->orderByDesc('created_at')
                ->get();
            $statusChanges = StatusChanged::where('customer_id', $id)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'status'         => 'success',
                'package_changes' => $packageChanges,
                'status_changes'  => $statusChanges,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch changes for customer {$id}: {$e->getMessage()}");
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch change history.',
            ], 500);
        }
    }

    /**
     * Relations that must be eager-loaded for each optional PDF section.
     * Only the relations required by the user-selected sections are loaded,
     * so generating a "Profile only" PDF never queries payments/bills/etc.
     */
    protected const PDF_SECTION_RELATIONS = [
        'Profile' => [],
        'Service' => [],
        'Personal' => [],
        'Network' => ['server'],
        'ReceivedBill' => ['payment'],
        'ProductSell' => [],
        'GenerateBill' => ['generatedBill'],
        'Message' => [],
        'CustomerStatus' => ['statusChanged'],
    ];

    /**
     * Generates a PDF of the client profile (backend, mPDF) containing only
     * the sections requested via ?sections[]=Profile&sections[]=Service...
     *
     * Replaces the old frontend html2canvas+jsPDF pipeline (slow, layout
     * breaking, and silently dropped the Message/ProductSell/CustomerStatus
     * selections) with a single server-side request.
     */
    public function generateClientProfilePdf(Request $request)
    {
        try {
            $id = (int) $request->query('id');
            $sections = $request->query('sections', []);

            // Normalize sections (single value or array) and drop unknown keys
            $sections = is_array($sections) ? $sections : [$sections];
            $sections = array_values(array_intersect(array_keys(self::PDF_SECTION_RELATIONS), $sections));
            if (empty($sections)) {
                $sections = ['Profile'];
            }

            // Dynamically eager-load ONLY the relations used by selected sections
            $relations = collect($sections)
                ->flatMap(fn($key) => self::PDF_SECTION_RELATIONS[$key] ?? [])
                ->unique()
                ->values()
                ->all();

            $customer = Customer::with($relations)->findOrFail($id);
            $company = CompanyProfile::first();

            $html = view('pdf.client-profile', [
                'customer' => $customer,
                'company' => $company,
                'sections' => $sections,
                'billingStartMonth' => $this->formatMonthYear($customer->billingmonth),
                // Direct image URLs (not base64) — mPDF fetches them at render time
                'images' => [
                    'profile' => $this->resolveImageUrl($customer->profileimage),
                    'nid' => $this->resolveImageUrl($customer->nidimage),
                    'registration' => $this->resolveImageUrl($customer->registrationimage),
                    'company' => $this->resolveImageUrl($company?->image),
                ],
            ])->render();

            $tempDir = storage_path('app/pdf-tmp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 14,
                'margin_bottom' => 14,
                'tempDir' => $tempDir,
            ]);
            $mpdf->SetTitle('Client Profile - ' . $customer->name);
            $mpdf->SetAuthor($company->title ?? config('app.name'));
            $mpdf->WriteHTML($html);

            return response(
                $mpdf->Output('ClientProfile.pdf', \Mpdf\Output\Destination::STRING_RETURN),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="ClientProfile.pdf"',
                ]
            );
        } catch (\Exception $e) {
            Log::error("Failed to generate client profile PDF for customer {$request->query('id')}: {$e->getMessage()}");
            return response()->json([
                'message' => 'Failed to generate the PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format a date-ish string as "Month Year" (e.g. "March 2024") so the PDF
     * never exposes a full stored timestamp. Falls back to the raw value when
     * it cannot be parsed.
     */
    protected function formatMonthYear($value): string
    {
        if (empty($value)) {
            return 'N/A';
        }
        try {
            return Carbon::parse($value)->format('F Y');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Return a directly-embeddable image URL for the PDF.
     * Full URLs (e.g. Cloudinary) are passed through untouched; legacy
     * relative paths are turned into an absolute URL built from the app URL
     * (asset() may return a root-relative path when there is no incoming
     * request). Note: mPDF requires allow_url_fopen or the curl extension
     * to fetch remote images.
     */
    protected function resolveImageUrl(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            $base = url('/');
        }

        return $base . (str_starts_with($image, '/') ? '' : '/') . $image;
    }

    public function addMikrotikUser($mikrotikServer, $username, $password, $profile, $fullComment, $service = 'pppoe')
    {
        try {
            $settings = SystemPermission::first();

            if ($settings && $settings->save_comment_in_mikrotik === 'enable') {
                $comment = $fullComment;
            } else {
                $comment = '';
            }
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            // Connect to the router
            $client->connect();
            // Log::info("Successfully connected to MikroTik router.");
            // Create a query to add a PPPoE user
            $query = new Query('/ppp/secret/add');
            $query->equal('name', $username);
            $query->equal('password', $password);
            $query->equal('profile', $profile);
            $query->equal('service', $service);
            $query->equal('comment', $comment);

            $response = $client->query($query)->read();
            // Log::info("Raw Response from MikroTik: " . print_r($response, true));

            // var_dump($response);
            $result = $response['after'];
            $mikrotikUserId = $result['ret'];
            // Log::info("Successfully received MikroTik user ID: " . $mikrotikUserId);
            return $mikrotikUserId;
        } catch (Exception $e) {
            Log::error("Failed to create Mikrotik users: {$e->getMessage()}");
            return response()->json(['status' => 'error', 'message' => 'Failed to add user'], 500);
        }
    }



    public function removeMikrotikUser(Request $request)
    {
        try {
            $serverId = $request->input('server_id');
            $clientId = $request->input('id');
            $radius_id = $request->input('radius_id');
            // $username = $request->input('username');

            $mikrotikServer = MikrotikServer::findOrFail($serverId);
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
            // if (!$radius_id) {
            //     $userid = $this->findUserByName($username, $serverId);
            // } else {
            //     $userid = $radius_id;
            // }

            $request = new Query('/ppp/secret/remove');
            $request->equal('.id', $radius_id);
            $responses = $client->query($request)->read();

            $client = Customer::find($clientId);
            if ($client) {
                // Delete associated images from Cloudinary before deleting the record
                foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                    if ($client->{$field . '_public_id'}) {
                        cloudinary_delete($client->{$field . '_public_id'});
                    }
                }
                $client->delete();
                return response()->json([
                    'message' => 'Client Delete Successfull.'
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function disabledSelectedClient(Request $request)
    {
        try {
            $disabled = $request->input('disabled');
            $customers = Customer::whereIn('id', $request->ids)->get();
            foreach ($customers as $customer) {
                $server = MikrotikServer::find($customer->server_id);

                if ($server) {
                    // Connect to the MikroTik server
                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);
                    // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);

                    // Prepare MikroTik API request to update 'disabled' status
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $customer->radius_id);
                    $updateRequest->equal('disabled', $disabled);

                    // Send the request
                    $client->query($updateRequest)->read();
                }

                // Update 'disabled' status in the database
                $customer->update([
                    'mikrotikStatus' => false,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Disabled status updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }
    public function enabledSelectedClient(Request $request)
    {
        try {
            $disabled = $request->input('disabled');
            $customers = Customer::whereIn('id', $request->ids)->get();
            foreach ($customers as $customer) {
                $server = MikrotikServer::find($customer->server_id);

                if ($server) {
                    // Connect to the MikroTik server
                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);
                    // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);

                    // Prepare MikroTik API request to update 'disabled' status
                    // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $customer->radius_id);
                    $updateRequest->equal('disabled', $disabled);

                    // Send the request
                    $client->query($updateRequest)->read();
                }

                // Update 'disabled' status in the database
                $customer->update([
                    'mikrotikStatus' => true,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Enabled status updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }
    protected function findUserByName($username, $serverId)
    {
        try {
            $mikrotikServer = MikrotikServer::findOrFail($serverId);
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);

            // $request = new RouterOS\Request('/ppp/secret/print');
            $request = new Query('/ppp/secret/print');
            $responses = $client->query($request)->read();
            foreach ($responses as $response) {
                foreach ($response as $key => $value) {
                    if ($key === 'name' && $value === $username) {
                        return $response['.id'];
                    }
                }
            }
            return null; // User not found
        } catch (Exception $e) {
            Log::error("Failed to fetch user by name: {$e->getMessage()}");
            throw new \Exception("Failed to fetch user by name");
        }
    }
    public function toggleClient(Request $request)
    {
        try {
            $disabled = $request->input('disabled');
            $server_id = $request->input('server_id');
            $radius_id = $request->input('radius_id');
            $id = $request->input('id');
            $mikrotikStatus = $request->input('mikrotikStatus');
            $server = MikrotikServer::find($server_id);
            if ($server) {
                // Connect to the MikroTik server

                $client = new Client([
                    'host' => $server->serverip,
                    'user' => $server->Username,
                    'pass' => $server->password,
                    'port' => $server->port,
                ]);
                // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);

                // Prepare MikroTik API request to update 'disabled' status
                $updateRequest = new Query('/ppp/secret/set');
                $updateRequest->equal('.id', $radius_id);
                $updateRequest->equal('disabled', $disabled);

                // Send the request
                $client->query($updateRequest)->read();
                $customer = Customer::find($id);
                // Update 'disabled' status in the database
                $customer->update([
                    'mikrotikStatus' => $mikrotikStatus,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => ' status updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }


    /**
     * On-demand live session check (GET /api/customers/{id}/live-mac).
     *
     * Queries ONLY the customer's assigned MikroTik server for a single
     * username, so a page load never touches the routers. Connection/query
     * failures are handled gracefully and reported as "offline" instead of
     * throwing a 500.
     */
    public function getCustomerLiveMac($id)
    {
        try {
            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            if (empty($customer->server_id)) {
                return response()->json([
                    'is_online' => false,
                    'live_mac' => null,
                    'ip_address' => null,
                ], 200);
            }

            $server = MikrotikServer::find($customer->server_id);
            if (!$server) {
                return response()->json([
                    'is_online' => false,
                    'live_mac' => null,
                    'ip_address' => null,
                ], 200);
            }

            $client = new Client([
                'host' => $server->serverip,
                'user' => $server->Username,
                'pass' => $server->password,
                'port' => $server->port,
            ]);

            $query = new Query('/ppp/active/print');
            $query->where('name', $customer->username);
            $responses = $client->query($query)->read();

            foreach ($responses as $item) {
                if (($item['name'] ?? null) === $customer->username) {
                    return response()->json([
                        'is_online' => true,
                        'live_mac' => $item['caller-id'] ?? null,
                        'ip_address' => $item['address'] ?? null,
                    ], 200);
                }
            }

            // Username not present in active sessions
            return response()->json([
                'is_online' => false,
                'live_mac' => null,
                'ip_address' => null,
            ], 200);
        } catch (\Exception $e) {
            // Router unreachable/query failed — never break the UI, report offline
            Log::error("Failed to check live MAC for customer {$id}: {$e->getMessage()}");
            return response()->json([
                'is_online' => false,
                'live_mac' => null,
                'ip_address' => null,
            ], 200);
        }
    }

    public function bindMac(Request $request)
    {
        try {
            $mac_address = trim((string) $request->input('mac_address'));
            $id = $request->input('customer_id') ?: $request->input('id');
            $server_id = $request->input('server_id');
            $radius_id = $request->input('radius_id');

            if ($mac_address === '') {
                return response()->json(['message' => 'MAC address is required to bind.'], 422);
            }

            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            $server = MikrotikServer::find($server_id);
            if (!$server) {
                return response()->json(['message' => 'MikroTik server not found.'], 404);
            }

            // Fall back to the stored radius_id if the request omits it
            $radius_id = $radius_id ?: $customer->radius_id;
            if (empty($radius_id)) {
                return response()->json(['message' => 'Customer has no MikroTik user id to bind.'], 422);
            }

            // 1) Update MikroTik: /ppp/secret/set caller-id = {mac_address}
            $client = new Client([
                'host' => $server->serverip,
                'user' => $server->Username,
                'pass' => $server->password,
                'port' => $server->port,
            ]);
            $updateRequest = new Query('/ppp/secret/set');
            $updateRequest->equal('.id', $radius_id);
            $updateRequest->equal('caller-id', $mac_address);
            $client->query($updateRequest)->read();

            // 2) Persist the bound MAC in the local database
            $customer->update([
                'caller_id' => $mac_address
            ]);

            return response()->json([
                'message' => 'MAC address bound successfully.',
                'caller_id' => $mac_address
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to bind MAC address: {$e->getMessage()}");
            return response()->json([
                'message' => 'Failed to bind MAC address.',
            ], 500);
        }
    }
    public function unbindMac(Request $request)
    {
        try {
            $id = $request->input('customer_id') ?: $request->input('id');
            $server_id = $request->input('server_id');
            $radius_id = $request->input('radius_id');

            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            $server = MikrotikServer::find($server_id);
            if (!$server) {
                return response()->json(['message' => 'MikroTik server not found.'], 404);
            }

            // Fall back to the stored radius_id if the request omits it
            $radius_id = $radius_id ?: $customer->radius_id;
            if (empty($radius_id)) {
                return response()->json(['message' => 'Customer has no MikroTik user id to unbind.'], 422);
            }

            // 1) Clear caller-id on MikroTik: /ppp/secret/set caller-id = ""
            $client = new Client([
                'host' => $server->serverip,
                'user' => $server->Username,
                'pass' => $server->password,
                'port' => $server->port,
            ]);
            $updateRequest = new Query('/ppp/secret/set');
            $updateRequest->equal('.id', $radius_id);
            $updateRequest->equal('caller-id', '');
            $client->query($updateRequest)->read();

            // 2) Clear the MAC field in the local database
            $customer->update([
                'caller_id' => null
            ]);

            return response()->json([
                'message' => 'MAC address unbound successfully.',
                'caller_id' => null
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to unbind MAC address: {$e->getMessage()}");
            return response()->json([
                'message' => 'Failed to unbind MAC address.',
            ], 500);
        }
    }

    public function bindSelectedMacAddresses(Request $request)
    {
        try {
            $users = $request->input('users', []);
            if (empty($users)) {
                return response()->json(['message' => 'No users selected.'], 422);
            }

            $bound = 0;
            $failed = 0;
            foreach ($users as $user) {
                // Per-user try/catch so one bad router/user never aborts the whole batch
                try {
                    $customer = Customer::find($user['id'] ?? null);
                    if (!$customer) {
                        $failed++;
                        continue;
                    }

                    $server = MikrotikServer::find($user['server_id'] ?? null);
                    if (!$server) {
                        $failed++;
                        continue;
                    }

                    $mac_address = trim((string) ($user['mac_address'] ?? ''));
                    $radius_id = $user['radius_id'] ?? $customer->radius_id;
                    if ($mac_address === '' || empty($radius_id)) {
                        $failed++;
                        continue;
                    }

                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $radius_id);
                    $updateRequest->equal('caller-id', $mac_address);
                    $client->query($updateRequest)->read();

                    $customer->update([
                        'caller_id' => $mac_address
                    ]);
                    $bound++;
                } catch (\Exception $e) {
                    $userId = $user['id'] ?? 'unknown';
                    Log::error("Failed to bind MAC for user {$userId}: {$e->getMessage()}");
                    $failed++;
                }
            }

            $message = "{$bound} MAC address(es) bound successfully";
            if ($failed > 0) {
                $message .= ", {$failed} failed.";
            } else {
                $message .= '.';
            }

            return response()->json([
                'message' => $message,
                'bound' => $bound,
                'failed' => $failed
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to bind selected MAC addresses: {$e->getMessage()}");
            return response()->json([
                'message' => 'Failed to bind selected MAC addresses.',
            ], 500);
        }
    }
    public function unbindSelectedMacAddresses(Request $request)
    {
        try {
            $users = $request->input('users', []);
            if (empty($users)) {
                return response()->json(['message' => 'No users selected.'], 422);
            }

            $unbound = 0;
            $failed = 0;
            foreach ($users as $user) {
                // Per-user try/catch so one bad router/user never aborts the whole batch
                try {
                    $customer = Customer::find($user['id'] ?? null);
                    if (!$customer) {
                        $failed++;
                        continue;
                    }

                    $server = MikrotikServer::find($user['server_id'] ?? null);
                    if (!$server) {
                        $failed++;
                        continue;
                    }

                    $radius_id = $user['radius_id'] ?? $customer->radius_id;
                    if (empty($radius_id)) {
                        $failed++;
                        continue;
                    }

                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $radius_id);
                    $updateRequest->equal('caller-id', '');
                    $client->query($updateRequest)->read();

                    $customer->update([
                        'caller_id' => null
                    ]);
                    $unbound++;
                } catch (\Exception $e) {
                    $userId = $user['id'] ?? 'unknown';
                    Log::error("Failed to unbind MAC for user {$userId}: {$e->getMessage()}");
                    $failed++;
                }
            }

            $message = "{$unbound} MAC address(es) unbound successfully";
            if ($failed > 0) {
                $message .= ", {$failed} failed.";
            } else {
                $message .= '.';
            }

            return response()->json([
                'message' => $message,
                'unbound' => $unbound,
                'failed' => $failed
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to unbind selected MAC addresses: {$e->getMessage()}");
            return response()->json([
                'message' => 'Failed to unbind selected MAC addresses.',
            ], 500);
        }
    }





    protected function clientList(Request $request)
    {
        try {
            $customers = Customer::with('invoice')->get();
            $customersWithMacs = $customers->map(function ($customer) {
                $mikrotikServer = MikrotikServer::findOrFail($customer->server_id);
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
                // $request = new RouterOS\Request('/ppp/active/print');
                $request = new Query('/ppp/active/print');
                // $responses = $client->sendSync($request);
                $responses = $client->query($request)->read();
                $macAddress = 'N/A';

                foreach ($responses as $response) {

                    if ($response['name'] === $customer->username) {
                        $macAddress = $response['caller-id'];
                        $address = $response['address'];
                        break;
                    }
                }
                return $customer->toArray() + ['mac_address' => $macAddress, 'address' => $address];
            });
            return response()->json([
                'customers' => $customersWithMacs
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
            throw new \Exception("Failed to fetch users from Mikrotik Server");
        }
    }

    protected function BTRClientList(Request $request)
    {
        try {
            $customers = Customer::with('invoice')->get();
            $customersWithMacs = $customers->map(function ($customer) {
                $mikrotikServer = MikrotikServer::findOrFail($customer->server_id);
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
                $request = new Query('/ppp/active/print');
                // $responses = $client->sendSync($request);
                $responses = $client->query($request)->read();
                $macAddress = 'N/A';
                $address = '';
                foreach ($responses as $response) {
                    if ($response['name'] === $customer->username) {
                        $macAddress = $response['caller-id'];
                        $address = $response['address'];
                        break;
                    }
                }
                return $customer->toArray() + ['mac_address' => $macAddress];
            });
            return response()->json([
                'customers' => $customersWithMacs
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
            throw new \Exception("Failed to fetch users from Mikrotik Server");
        }
    }
}
