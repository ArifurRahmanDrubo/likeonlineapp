<?php
namespace App\Http\Controllers;
// require 'vendor/autoload.php';
use App\Models\Customer;
use App\Models\IPPool;
use App\Models\MikrotikServer;
use App\Models\MProfile;
use App\Models\MUser;
use App\Services\MikroTikService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Exception as RouterOSException;
use RouterOS\Client;
use RouterOS\Query;
// use RouterOS\Client;
// use RouterOS\Exceptions\Exception;
class MikroTikController extends Controller
{
    protected $encryptionKey;
    public function __construct()
    {
        $this->encryptionKey = Config::get('app.encryption_key');
    }
    public function index()
    {
        $servers = MikrotikServer::all();
        foreach ($servers as $server) {
            try {
                // Initialize the MikroTik client
                $client = new Client([
                    'host' => $server->serverip,
                    'user' => $server->Username,
                    'pass' => $server->password,
                    'port' => $server->port,
                ]);
                // Attempt to connect to the MikroTik server
                if ($client->connect()) {
                    // If connected successfully, update the status to active
                    $server->status = 'active';
                } else {
                    // If the connection fails, update the status to inactive
                    $server->status = 'inactive';
                }
                // Save the updated server status
                $server->save();
            } catch (\Exception $e) {
                // If any exception occurs (e.g., connection failure), set the status to inactive
                $server->status = 'inactive';
                $server->save();
            }
        }
        // Return the updated list of servers with their statuses
        return response()->json($servers);
    }
    public function store(Request $request)
    {
        try {
            $request->validate([
                'serverName' => 'required|string|max:255',
                'serverip' => 'required|ip',
                'Username' => 'required|string|max:255',
                'password' => 'required|string|max:255',
                'port' => 'required|integer',
                // 'version' => 'required|string|max:255',
                // 'timeout' => 'required|integer',
                // 'status' => 'required|string',
            ]);
            $mikrotikServer = MikrotikServer::create($request->all());
            $status = $request->input('status');
            // Connect or disconnect based on status
            $this->toggleMikrotikConnection($mikrotikServer, $status);
            return response()->json(['message' => 'MikrotikServer created successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }
    public function show(MikrotikServer $mikrotikServer)
    {
        return response()->json($mikrotikServer);
    }

    public function update(Request $request)
    {
        try {
            $request->validate([
                'serverName' => 'string|max:255',
                'serverip' => 'ip',
                'Username' => 'string|max:255',
                'password' => 'string|max:255',
                'port' => 'integer',
            ]);

            $id = $request->input('id');

            $mikrotikServer = MikrotikServer::find($id);

            if (!$mikrotikServer) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Mikrotik server not found.'
                ], 404);
            }

            $mikrotikServer->serverName = $request->input('serverName');
            $mikrotikServer->serverip = $request->input('serverip');
            $mikrotikServer->Username = $request->input('Username');
            $mikrotikServer->password = $request->input('password');
            $mikrotikServer->port = $request->input('port');
            $mikrotikServer->version = $request->input('version');
            $mikrotikServer->timeout = $request->input('timeout');
            $mikrotikServer->status = $request->input('status');

            $mikrotikServer->save();

            return response()->json([
                'status' => 'success',
                'message' => 'MikrotikServer updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $mikrotikServer = MikrotikServer::findOrFail($id);
        $mikrotikServer->delete();
        // Disconnect Mikrotik server upon deletion
        $this->disconnectMikrotikServer($mikrotikServer);
        return response()->json(['message' => 'MikrotikServer deleted successfully']);
    }
    protected function toggleMikrotikConnection($mikrotikServer, $status)
    {
        if ($status === 'active') {
            $this->connectMikrotikServer($mikrotikServer);
        } else {
            $this->disconnectMikrotikServer($mikrotikServer);
        }
    }
    protected function disconnectMikrotikServer($mikrotikServer)
    {
        unset($client);
    }

    
    public function getMUser(Request $request, $id)
{
    try {
        $user = MUser::find($id);
        return response()->json([
            'status' => true,
            'users'  => $user
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'error'   => 'Failed to fetch users.',
            'message' => $e->getMessage()
        ], 500);
    }
}
public function getUsers(Request $request)
{
    try {
        $perPage  = $request->input('per_page', 20);
        $page     = $request->input('page', 1); // 🌟 Current Page ট্র্যাকিং
        $serverId = $request->input('server_id');
        $profile  = $request->input('profile');
        $userType = $request->input('usertype');

        // ১. কাস্টমার না থাকা ইউজারদের কোয়েরি
        $query = MUser::doesntHave('customer');

        // ২. Server-id ফিল্টার
        if (!empty($serverId)) {
            $query->where('server_id', $serverId);
        }

        // ৩. Profile ফিল্টার
        if (!empty($profile)) {
            $query->where('profile', $profile);
        }

        // ৪. User Type ফিল্টার
        if (!empty($userType)) {
            $query->where('user_status', $userType);
        }

        // Server-side text search across all displayable columns
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('profile', 'like', "%{$search}%")
                  ->orWhere('caller_id', 'like', "%{$search}%")
                  ->orWhere('server_name', 'like', "%{$search}%")
                  ->orWhere('user_status', 'like', "%{$search}%");
            });
        }

        // Fetch ONLY the columns rendered by the Vue Import From Mikrotik page:
        // Name, Password, Service, Profile, Caller Id, Server Name, LogOut Time,
        // User Status, the status InputSwitch and the action payload fields.
        $unimportedUsers = $query->orderBy('id', 'desc')->paginate(
            $perPage,
            ['id', 'name', 'password', 'service', 'profile', 'caller_id', 'server_name', 'last_logged_out', 'user_status', 'disabled', 'mikrotik_id', 'server_id'],
            'page',
            $page
        );

        return response()->json([
            'status' => true,
            'users'  => $unimportedUsers
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'error'   => 'Failed to fetch users.',
            'message' => $e->getMessage()
        ], 500);
    }
}
    public function getPppoeProfiles(Request $request)
    {
        try {
       
            $mikrotikServer = MikrotikServer::find($request->input('id'));
            $profiles = MProfile::where('server_id', $mikrotikServer->id)->get();
        
            return response()->json($profiles);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        }
    }
    protected function getUsersFromMikrotikServer($mikrotikServer)
    {
        try {
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            $client->connect(); // Attempt to connect
            // Log::info("Successfully connected to MikroTik router.");
            $query = new Query('/ppp/secret/print');
            $responses = $client->query($query)->read();
            $users = [];
            foreach ($responses as $response) {
                // Extract all properties of the response
                $userProperties = [];
                foreach ($response as $key => $value) {
                    if ($key === 'disabled') {
                        $userProperties['mikrotikStatus'] = ($value === 'false');
                    }
                    $userProperties[$key] = $value;
                }
                // Add additional server information
                $userProperties['serverName'] = $mikrotikServer->serverName;
                $userProperties['serverId'] = $mikrotikServer->id;
                $userProperties['userStatus'] = "Unique"; // Set a default user status
                // Append the user properties to the users array
                $users[] = $userProperties;
            }
            // Log::info("Received users from MikroTik server: ", $users);
            // Check if users were received
            if (empty($users)) {
                Log::warning("No user data received from MikroTik server.");
            }
            // Return the users data as JSON
            return response()->json(['data' => $users]);
        } catch (\Throwable $e) { // Using \Throwable to catch any error
            // Handle connection error and provide detailed message
            echo "Failed to connect to the MikroTik router.\n";
            echo "Reason: " . $e->getMessage() . "\n";
        }
    }

    public function syncServerData($serverId)
    {
        $server = MikrotikServer::find($serverId);

        if (!$server) {
            return response()->json([
                'status' => false,
                'message' => 'MikroTik server not found!'
            ], 404);
        }

        try {
            $client = new Client([
                'host' => $server->serverip,
                'user' => $server->Username,
                'pass' => $server->password,
                'port' => (int) $server->port,
            ]);
            $userQuery = new Query('/ppp/secret/print');
            $users = $client->query($userQuery)->read();

            foreach ($users as $item) {
                if (empty($item['name']))
                    continue;

                $lastLoggedOut = null;

                if (!empty($item['last-logged-out'])) {
                    try {
                        $parsedDate = Carbon::parse($item['last-logged-out']);

                        // সাল ১৯৭০-এর চেয়ে বড় হলেই কেবল ভ্যালিড তারিখ হিসেবে গ্রহণ করবে, নয়তো NULL থাকবে
                        if ($parsedDate->year > 1970) {
                            $lastLoggedOut = $parsedDate;
                        }
                    } catch (\Exception $e) {
                        $lastLoggedOut = null;
                    }
                }

                MUser::updateOrCreate(
                    ['name' => $item['name']],
                    [
                        'server_id' => $server->id,
                        'server_name' => $server->serverName,
                        'mikrotik_id' => $item['.id'] ?? null,
                        'password' => $item['password'] ?? null,
                        'service' => $item['service'] ?? null,
                        'profile' => $item['profile'] ?? null,
                        'disabled' => isset($item['disabled']) && $item['disabled'] === 'true',
                        'caller_id' => $item['caller-id'] ?? null,
                        'last_caller_id' => $item['last-caller-id'] ?? null,
                        'last_disconnect_reason' => $item['last-disconnect-reason'] ?? null,
                        'last_logged_out' => $lastLoggedOut,
                        'limit_bytes_in' => (int) ($item['limit-bytes-in'] ?? 0),
                        'limit_bytes_out' => (int) ($item['limit-bytes-out'] ?? 0),
                        'ipv6_routes' => $item['ipv6-routes'] ?? null,
                        'routes' => $item['routes'] ?? null,
                        'comment' => $item['comment'] ?? null,
                        'user_status' => 'Unique',
                    ]
                );
            }

                $profileQuery = new Query('/ppp/profile/print');
                $profiles = $client->query($profileQuery)->read();

                foreach ($profiles as $item) {
                    if (empty($item['name']))
                        continue;

                    MProfile::updateOrCreate(
                        [
                            'server_id' => $server->id,
                            'name' => $item['name'],
                        ],
                        [
                            'mikrotik_id' => $item['.id'] ?? null,
                            'address_list' => $item['address-list'] ?? null,
                            'bridge_learning' => $item['bridge-learning'] ?? null,
                            'change_tcp_mss' => $item['change-tcp-mss'] ?? null,
                            'default' => isset($item['default']) && $item['default'] === 'true',
                            'dns_server' => $item['dns-server'] ?? null,
                             'local_address' => $item['local-address'] ?? null,
                            'remote_address' => $item['remote-address'] ?? null,
                            'on_down' => $item['on-down'] ?? null,
                            'on_up' => $item['on-up'] ?? null,
                            'only_one' => $item['only-one'] ?? null,
                            'use_compression' => $item['use-compression'] ?? null,
                            'use_encryption' => $item['use-encryption'] ?? null,
                            'use_ipv6' => $item['use-ipv6'] ?? null,
                            'use_mpls' => $item['use-mpls'] ?? null,
                            'use_upnp' => $item['use-upnp'] ?? null,
                        ]
                    );
                }

            $ipPoolQuery = new Query('/ip/pool/print');
            $ippools = $client->query($ipPoolQuery)->read();

            foreach ($ippools as $item) {
                if (empty($item['name']))
                    continue;

                IPPool::updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'name' => $item['name'],
                    ],
                    [
                        'mikrotik_id' => $item['.id'] ?? null,
                        'ranges' => $item['ranges'] ?? null,
                    ]
                );

            }


            return response()->json([
                'status' => true,
                'message' => 'Server users synced successfully!'
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Manual Sync Failed [Server ID {$server->id}]: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Live Traffic Monitoring trigger.
     *
     * Called by the LiveMonitoring.vue page every ~1 second. It connects to the
     * MikroTik router, snapshots the traffic of the dynamic PPPoE interface named
     * after the client's username (/interface monitor-traffic ... once), normalizes
     * the values and broadcasts them over Pusher on channel traffic.{mikrotik_id}
     * so the open browser tab(s) update in real time.
     *
     * @param  \Illuminate\Http\Request $request  Expects mikrotik_id, username, server_id
     * @return \Illuminate\Http\JsonResponse
     */
    // public function triggerLiveTraffic(Request $request)
    // {
    //     $startedAt = microtime(true);
    //     $mikrotikId = $request->input('mikrotik_id');
    //     $stateKey = 'live_traffic_' . ($mikrotikId ?: '0') . '_' . ($mikrotikId ?: $mikrotikId ?: 'anon');
    //     $wasOnline = (bool) Cache::get($stateKey, false);

    //     try {
    //         // 1) Resolve username + server from the DB where possible.
    //         //    mikrotik_id == RouterOS secret .id (customers.radius_id) and
    //         //    MUser.name == the PPPoE username (customers.username).
    //         $mUser = null;
    //         if ($mikrotikId) {
    //             $mUser = Customer::where('mikrotik_id', $mikrotikId)->first();
    //         }

    //         $server = MikrotikServer::find($mUser->server_id);
    //         if (!$server) {
    //             return response()->json(['status' => 'error', 'message' => 'MikroTik server not found'], 404);
    //         }

    //         // 2) Connect to the router (same pattern as the rest of this controller)
    //         $client = new Client([
    //             'host' => $server->serverip,
    //             'user' => $server->Username,
    //             'pass' => $server->password,
    //             'port' => $server->port,
    //         ]);
    //         $client->connect();

    //         // 3) One-shot traffic snapshot for the interface named after the PPPoE user.
    //         //    'once' makes RouterOS return a single sample instead of streaming forever.
    //         $query = (new Query('/interface/monitor-traffic'))
    //             ->equal('interface', $mUser->username)
    //             ->equal('once', '');
    //         $responses = $client->query($query)->read();

    //         // 4) Normalize into a compact, frontend-friendly payload.
    //         //    RouterOS reports bits/s → convert to Mbps.
    //         $sample = $responses[0] ?? [];
    //         $rxBits = (float) ($sample['rx-bits-per-second'] ?? 0);
    //         $txBits = (float) ($sample['tx-bits-per-second'] ?? 0);
    //         $online = count($responses) > 0;

    //         $payload = [
    //             'mikrotik_id'   => $mikrotikId,
    //             'username'      => $$mUser->username,
    //             'download_mbps' => round($rxBits / 1_000_000, 3),
    //             'upload_mbps'   => round($txBits / 1_000_000, 3),
    //             'rx_pps'        => (int) ($sample['rx-packets-per-second'] ?? 0),
    //             'tx_pps'        => (int) ($sample['tx-packets-per-second'] ?? 0),
    //             'online'        => $online,
    //             'latency_ms'    => round((microtime(true) - $startedAt) * 1000, 1),
    //             'timestamp'     => now()->toIso8601String(),
    //         ];

    //         // 5) Push to every open monitoring tab for this client (sync broadcast).
    //         broadcast(new UserTrafficUpdated($payload['mikrotik_id'], $payload));
    //         Cache::put($stateKey, true, now()->addMinutes(10));

    //         return response()->json(['status' => 'success', 'data' => $payload], 200);
    //     } catch (\Throwable $e) {
    //         // User offline (no interface) or router unreachable → flip the UI to Offline
    //         // instead of freezing it. Log it and keep the poll loop alive.
    //         Log::error("triggerLiveTraffic failed [user:]: {$e->getMessage()}");

    //         $offline = [
    //             'mikrotik_id'   => $mikrotikId ?: ($username ?? ''),
    //             'username'      => $mUser->username,
    //             'download_mbps' => 0,
    //             'upload_mbps'   => 0,
    //             'rx_pps'        => 0,
    //             'tx_pps'        => 0,
    //             'online'        => false,
    //             'latency_ms'    => round((microtime(true) - $startedAt) * 1000, 1),
    //             'timestamp'     => now()->toIso8601String(),
    //         ];

    //         // Only broadcast the offline flip once per transition — the HTTP
    //         // response below still delivers the offline payload on every poll.
    //         if ($wasOnline && $offline['mikrotik_id']) {
    //             broadcast(new UserTrafficUpdated($offline['mikrotik_id'], $offline));
    //         }
    //         Cache::put($stateKey, false, now()->addMinutes(10));

    //         return response()->json(['status' => 'offline', 'data' => $offline], 200);
    //     }
    // }

    /**
     * Start real-time traffic monitoring via the Node.js monitoring-service.
     *
     * Called by LiveMonitoring.vue on page load. Resolves the customer's RouterOS
     * server from the DB, then proxies to POST {MONITORING_SERVICE_URL}/monitor/start
     * with the shared X-Internal-Secret header. The microservice keeps a 2-second
     * polling loop alive and streams snapshots to public Pusher channel traffic.{key}.
     */
public function startMonitoring(Request $request)
{
    $request->validate([
        'mikrotik_id' => ['required'],
    ]);

    $mikrotikId = trim((string) $request->input('mikrotik_id'));

    /*
    |--------------------------------------------------------------------------
    | 1. Actual customer খুঁজবে
    |--------------------------------------------------------------------------
    */
    $customer = Customer::where('radius_id', $mikrotikId)->first();

    if (! $customer) {
        return response()->json([
            'status' => 'error',
            'message' => 'Customer not found',
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Customer-এর MikroTik server
    |--------------------------------------------------------------------------
    */
    if (! $customer->server_id) {
        return response()->json([
            'status' => 'error',
            'message' => 'Customer has no MikroTik server assigned',
        ], 404);
    }

    $server = MikrotikServer::find($customer->server_id);

    if (! $server) {
        return response()->json([
            'status' => 'error',
            'message' => 'MikroTik server not found',
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Customer-এর actual RouterOS username
    |--------------------------------------------------------------------------
    */
    if (! $customer->username) {
        return response()->json([
            'status' => 'error',
            'message' => 'Customer username is missing',
        ], 404);
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | 4. Laravel -> Node Monitoring Service
        |--------------------------------------------------------------------------
        */
        $response = Http::timeout(10)
            ->withHeaders([
                'X-Internal-Secret' => config('services.monitoring.secret'),
            ])
            ->post(
                rtrim(
                    (string) config('services.monitoring.base_url'),
                    '/'
                ) . '/monitor/start',
                [
                    // Customer identification
                    'mikrotik_id' => (string) $customer->radius_id,
                    'username'    => (string) $customer->username,
                    'service'     => $customer->protocoltype ?? 'ppp',
                    'ip'          => $customer->ip ?? '',

                    // MikroTik connection
                    'host'        => $server->serverip,
                    'port'        => (int) $server->port,
                    'server_user' => $server->Username,
                    'password'    => $server->password,
                ]
            );

        return response()->json(
            $response->json(),
            $response->status()
        );

    } catch (\Throwable $e) {

        Log::error(
            'Failed to reach monitoring-service',
            [
                'mikrotik_id' => $mikrotikId,
                'customer_id' => $customer->id,
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]
        );

        return response()->json([
            'status' => 'error',
            'message' => 'Monitoring service unreachable',
        ], 502);
    }
}

    /**
     * Explicitly stop a running monitor in the Node.js monitoring-service.
     * (The automatic stop path is the Pusher channel_vacated webhook.)
     */
    public function stopMonitoring(Request $request)
    {
        $mikrotikId = $request->input('mikrotik_id');

        if (! $mikrotikId) {
            return response()->json(['status' => 'error', 'message' => 'mikrotik_id is required'], 400);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Internal-Secret' => config('services.monitoring.secret')])
                ->post(rtrim((string) config('services.monitoring.base_url'), '/').'/monitor/stop', [
                    'mikrotik_id' => $mikrotikId,
                ]);

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::error("Failed to reach monitoring-service: {$e->getMessage()}");

            return response()->json(['status' => 'error', 'message' => 'Monitoring service unreachable'], 502);
        }
    }

    // public function index()
// {
//     try {
//         $client = new Client([
//             'host' => '103.76.195.50',
//             'user' => 'Billingsoftware',
//             'pass' => 'riazsoft7429',
//             'port' => 4455,
//         ]);

    //         $client->connect();

    //         $query = new Query('/ppp/secret/print');

    //         $responses = $client->query($query)->read();

    //         $users = [];

    //         foreach ($responses as $response) {

    //             $userProperties = [];

    //             foreach ($response as $key => $value) {

    //                 if ($key === 'disabled') {
//                     $userProperties['mikrotikStatus'] = ($value === 'false');
//                 }

    //                 $userProperties[$key] = $value;
//             }

    //             $userProperties['userStatus'] = 'Unique';

    //             $users[] = $userProperties;
//         }

    //         if (empty($users)) {
//             Log::warning('No user data received from MikroTik server.');
//         }

    //         return response()->json([
//             'status' => 'success',
//             'data' => $users
//         ]);

    //     } catch (\Throwable $e) {

    //         Log::error('MikroTik connection failed', [
//             'host' => '103.76.195.50',
//             'port' => 4455,
//             'error' => $e->getMessage(),
//         ]);

    //         return response()->json([
//             'status' => 'fail',
//             'message' => 'Failed to connect to MikroTik router.',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// }

    public function deleteMultiple(Request $request)
    {
        MikrotikServer::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'MikrotikServer deleted successfully']);
    }
    protected function getActiveUsersFromMikrotikServer($mikrotikServer)
    {
        try {
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            $client->connect();
            $request = new Query('/ppp/active/print');
            $responses = $client->query($request)->read();
            $users = [];
            foreach ($responses as $response) {
                // Extract all properties of the response
                $userProperties = [];
                foreach ($response as $key => $value) {
                    if ($key === 'disabled' && $value === 'false') {
                        $userProperties['mikrotikStatus'] = true;
                    } elseif ($key === 'disabled' && $value === 'true') {
                        $userProperties['mikrotikStatus'] = false;
                    }
                    $userProperties[$key] = $value;
                }
                $userProperties['serverName'] = $mikrotikServer->serverName;
                $userProperties['serverId'] = $mikrotikServer->id;
                // $userProperties['mikrotikStatus'] =  true;
                $userProperties['userStatus'] = "Unique";
                $users[] = $userProperties; // Store all properties of each user
            }
            // Log::info("Received users from MikroTik server: ", $users);
            if (empty($users)) {
                Log::warning("No user data received from MikroTik server.");
            }
            $data = $users;
            return response()->json(['data' => $data]);
        } catch (Exception $e) {
            Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
            throw new \Exception("Failed to fetch users from Mikrotik Server");
        }
    }
    public function toggleUserStatus(Request $request)
    {
        try {
            $serverid = $request->input('serverId');
            // $userid = $request->input('userid');
            $username = $request->input('username');
            $mikrotikServer = MikrotikServer::findOrFail($serverid);
            $mikrotikStatus = $request->input('mikrotikStatus');
            $disabled = $mikrotikStatus ? 'false' : 'true';
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            $userid = $this->findUserByName($username, $serverid);
            // Update the user's disabled status
            $updateRequest = new Query('/ppp/secret/set');
            $updateRequest->equal('.id', $userid);
            $updateRequest->equal('disabled', $disabled);
            $client->query($updateRequest)->read();
            return response()->json(['status' => 'success', 'message' => 'User status updated successfully']);
        } catch (Exception $e) {
            Log::error("Failed to update PPPoE user status: {$e->getMessage()}");
            return response()->json(['status' => 'error', 'message' => 'Failed to update user status'], 500);
        }
    }
    // protected function findUserByName($username, $serverId)
    // {
    //     try {
    //         $mikrotikServer = MikrotikServer::findOrFail($serverId);
    //         $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
    //         $request = new RouterOS\Request('/ppp/secret/print');
    //         $responses = $client->sendSync($request);
    //         foreach ($responses as $response) {
    //             foreach ($response as $key => $value) {
    //                 if ($key === 'name' && $value === $username) {
    //                     return $response->getProperty('.id');
    //                 }
    //             }
    //         }
    //         return null; // User not found
    //     } catch (Exception $e) {
    //         Log::error("Failed to fetch user by name: {$e->getMessage()}");
    //         throw new \Exception("Failed to fetch user by name");
    //     }
    // }
    // public function addClinetUser($serverId, $comment, $username, $password, $profile, $service)
    // {
    //     try {
    //         // Create a new client connection
    //         $mikrotikServer = MikrotikServer::findOrFail($serverId);
    //         $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
    //         Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);
    //         // Create a new request to add a user
    //         $request = new RouterOS\Request('/ppp/secret/add');
    //         $request->setArgument('name', $username);
    //         $request->setArgument('password', $password);
    //         $request->setArgument('profile', $profile);
    //         $request->setArgument('service', $service);
    //         $request->setArgument('comment', $comment);
    //         // Send the request
    //         $response = $client->sendSync($request);
    //         Log::info("User added successfully: " . $username);
    //         return response()->json(['status' => 'success', 'message' => 'User added successfully']);
    //     } catch (Exception $e) {
    //         Log::error("Failed to add user: {$e->getMessage()}");
    //         return response()->json(['status' => 'error', 'message' => 'Failed to add user'], 500);
    //     }
    // }
}
// <?php
// namespace App\Http\Controllers;
// use Exception;
// use Illuminate\Http\Request;
// use App\Models\MikrotikServer;
// use App\Services\MikroTikService;
// class MikroTikController extends Controller
// {
//     protected $mikroTikService;
//     public function __construct(MikroTikService $mikroTikService)
//     {
//         $this->mikroTikService = $mikroTikService;
//     }
//     public function getUsers()
//     {
//         $users = $this->mikroTikService->getUsers();
//         return response()->json($users);
//     }
//     public function addUser(Request $request)
//     {
//         $username = $request->input('username');
//         $password = $request->input('password');
//         $response = $this->mikroTikService->addUser($username, $password);
//         return response()->json($response);
//     }
//     public function index()
//     {
//         $servers = MikrotikServer::all();
//         return response()->json($servers);
//     }
//     public function store(Request $request)
//     {
//         try {
//             $request->validate([
//                 'serverName' => 'required|string|max:255',
//                 'serverip' => 'required|ip',
//                 'Username' => 'required|string|max:255',
//                 'password' => 'required|string|max:255',
//                 'port' => 'required|integer',
//                 'version' => 'required|string|max:255',
//                 'timeout' => 'required|integer',
//                 'status' => 'required|string',
//             ]);
//             $mikrotikServer = MikrotikServer::create($request->all());
//             $status = $request->input('status');
//             $response = $this->mikroTikService->toggleServerStatus($mikrotikServer, $status);
//             return response()->json(['message' => 'MikrotikServer created successfully']);
//         } catch (Exception $e) {
//             return response()->json(['status' => 'fail', 'message' => $e->getMessage(),]);
//         }
//     }
//     public function show(MikrotikServer $mikrotikServer)
//     {
//         return response()->json($mikrotikServer);
//     }
//     public function update(Request $request)
//     {
//         try {
//             $request->validate([
//                 'serverName' => 'string|max:255',
//                 'serverip' => 'ip',
//                 'Username' => 'string|max:255',
//                 'password' => 'string|max:255',
//                 'port' => 'integer',
//                 'version' => 'string|max:255',
//                 'timeout' => 'integer',
//                 'status' => 'string',
//             ]);
//             $id = $request->input('id');
//             $serverName = $request->input('serverName');
//             $serverip = $request->input('serverip');
//             $Username = $request->input('Username');
//             $password = $request->input('password');
//             $port = $request->input('port');
//             $version = $request->input('version');
//             $timeout = $request->input('timeout');
//             $status = $request->input('status');
//             $mikrotikServer = MikrotikServer::findOrFail($id);
//             $mikrotikServer->update([
//                 'serverName' => $serverName,
//                 'serverip' => $serverip,
//                 'Username' => $Username,
//                 'password' => $password,
//                 'port' =>  $port,
//                 'version' => $version,
//                 'timeout' => $timeout,
//                 'status' => $status,
//             ]);
//             return response()->json(['message' => 'MikrotikServer updated successfully']);
//         } catch (Exception $e) {
//             return response()->json(['status' => 'fail', 'message' => $e->getMessage(),]);
//         }
//     }
//     public function deleteMultiple(Request $request)
//     {
//         MikrotikServer::whereIn('id', $request->ids)->delete();
//         return response()->json(['message' => 'MikrotikServer deleted successfully']);
//     }
//     public function destroy(Request $request)
//     {
//         $id = $request->input('id');
//         $mikrotikServer = MikrotikServer::findOrFail($id);
//         $mikrotikServer->delete();
//         return response()->json(['message' => 'MikrotikServer deleted successfully']);
//     }
// }