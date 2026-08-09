<?php
namespace App\Http\Controllers;
// require 'vendor/autoload.php';
use App\Models\MikrotikServer;
use App\Models\MProfile;
use App\Models\MUser;
use App\Services\MikroTikService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
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
        $unimportedUsers = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

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
            // $id = $request->input('id');
            // Log::info("Received id: {$id}");
            $mikrotikServer = MikrotikServer::find();
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);
            $request = new Query('/ppp/profile/print');
            $responses = $client->query($request)->read();
            $profiles = [];
            foreach ($responses as $response) {
                // Extract all properties of the response
                $profileProperties = [];
                foreach ($response as $key => $value) {
                    $profileProperties[$key] = $value;
                }
                $profiles[] = $profileProperties; // Store all properties of each user
            }
            Log::info("Received PPPoE profiles from MikroTik server: ", $profiles);
            if (empty($profiles)) {
                Log::warning("No PPPoE profiles received from MikroTik server.");
            }
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