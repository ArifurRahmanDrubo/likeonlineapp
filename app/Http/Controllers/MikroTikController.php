<?php

namespace App\Http\Controllers;

// require 'vendor/autoload.php';
use Exception;
// use PEAR2\Net\RouterOS;
use Illuminate\Http\Request;
use App\Models\MikrotikServer;
// use PEAR2\Net\RouterOS\Client;
use App\Services\MikroTikService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Encryption\DecryptException;
use PEAR2\Net\RouterOS\Exception as RouterOSException;

// use PEAR2\Net\RouterOS\Util;
// use PEAR2\Net\RouterOS\Response;

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
                'version' => 'required|string|max:255',
                'timeout' => 'required|integer',
                'status' => 'required|string',
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
                'version' => 'string|max:255',
                'timeout' => 'integer',
                'status' => 'string',
            ]);
            $id = $request->input('id');
            $serverip = $request->input('serverip');
            $serverName = $request->input('serverName');
            $Username = $request->input('Username');
            $password = $request->input('password');
            $port = $request->input('port');
            $version = $request->input('version');
            $timeout = $request->input('timeout');
            $status = $request->input('status');

            $mikrotikServer = MikrotikServer::findOrFail($id);
            $mikrotikServer->update([
                'serverName' => $serverName,
                'serverip' => $serverip,
                'Username' => $Username,
                'password' => $password,
                'port' =>  $port,
                'version' => $version,
                'timeout' => $timeout,
                'status' => $status
            ]);


            // Connect or disconnect based on status
            $this->toggleMikrotikConnection($mikrotikServer, $status);

            return response()->json(['message' => 'MikrotikServer updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
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

    // protected function connectMikrotikServer($mikrotikServer)
    // {
    //     try {
    //         $client = new Query($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);

    //         // Example: Perform some action on the Mikrotik server upon connection
    //         // This could involve sending commands or queries to the RouterOS device

    //         // For example, fetching system identity:
    //         $response = $client->sendSync(new RouterOS\Request('/system identity print'));
    //         $identity = $response->getAllOfType(RouterOS\Response::TYPE_DATA)[0]->getProperty('name');

    //         // Handle response as needed
    //         // Example: Log the identity received
    //         Log::info("Connected to Mikrotik Server: {$identity}");
    //     } catch (\Exception $e) {
    //         Log::error("Failed to connect to Mikrotik Server: {$e->getMessage()}");
    //         throw new \Exception("Failed to connect to Mikrotik Server");
    //     }
    // }

    protected function disconnectMikrotikServer($mikrotikServer)
    {
        unset($client);
    }
    public function getUsers(Request $request)
    {
        try {
            $id = $request->input('id');
            // Log::info('Received id: ' . $id);
            $mikrotikServer = MikrotikServer::findOrFail($id);

            $users = $this->getUsersFromMikrotikServer($mikrotikServer);

            return response()->json(['users' => $users]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch usersssss.', 'message' => $e->getMessage()], 500);
        }
    }
    public function getPppoeProfiles(Request $request)
    {
        try {
            $id = $request->input('id');
            Log::info("Received id: {$id}");

            $mikrotikServer = MikrotikServer::findOrFail($id);

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
    // protected function getUsersFromMikrotikServer($mikrotikServer)
    // {
    //     try {
    //         $routerIp = '103.76.195.50';
    //         $username = 'soft';
    //         $password = '1234';

    //         $port = 4455;
    //         $userName = '*3D1';
    //         $userPassword = 'test9';
    //         // Create a client instance
    //         $client = new Client([
    //             'host' => $routerIp,
    //             'user' => $username,
    //             'pass' => $password,
    //             'port' => $port,
    //         ]);

    //         // Connect to the router
    //         $client->connect();

    //         // Create a query to add a PPPoE user
    //         $query = new Query('/ppp/secret/remove');
    //         $query->equal('.id', $userName);
    //         // Ensure this is set to the appropriate service type

    //         // Send the query
    //         $response = $client->query($query)->read();

    //         // Check if response contains errors
    //         if (isset($response['!trap'])) {
    //             throw new Exception("Error from MikroTik: " . $response['!trap']);
    //         }

    //         echo "User {$userName} remove  successfully.";
    //     } catch (Exception $e) {
    //         echo "Error: {$e->getMessage()}";
    //     }
    // }
    // protected function getUsersFromMikrotikServer($mikrotikServer)
    // {
    //     try {
    //         // $client = new Client('103.76.195.50', 'user1', '', 4455);
    //         $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);

    //         $request = new RouterOS\Request('/ppp/secret/print');
    //         $responses = $client->sendSync($request);

    //         $users = [];

    //         foreach ($responses as $response) {
    //             // Extract all properties of the response
    //             $userProperties = [];
    //             foreach ($response as $key => $value) {
    //                 if ($key === 'disabled' && $value === 'false') {
    //                     $userProperties['mikrotikStatus'] = true;
    //                 } elseif ($key === 'disabled' && $value === 'true') {
    //                     $userProperties['mikrotikStatus'] = false;
    //                 }
    //                 $userProperties[$key] = $value;
    //             }


    //             $userProperties['serverName'] = $mikrotikServer->serverName;
    //             $userProperties['serverId'] = $mikrotikServer->id;

    //             // $userProperties['mikrotikStatus'] =  true;
    //             $userProperties['userStatus'] = "Unique";


    //             $users[] = $userProperties; // Store all properties of each user
    //         }


    //         Log::info("Received users from MikroTik server: ", $users);

    //         if (empty($users)) {
    //             Log::warning("No user data received from MikroTik server.");
    //         }
    //         $data = $users;

    //         return response()->json(['data' => $data]);
    //     } catch (Exception $e) {
    //         Log::error("Failed to fetch users from Mikrotik Servers: {$e->getMessage()}");
    //         throw new \Exception("Failed to fetch users from Mikrotik Servers: {$e->getMessage()}");
    //     }
    // }
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
    // public function encryptData(Request $request)
    // {
    //     $data = $request->input('id');
    //     try {
    //         // $dataString = (string) $data;
    //         $encryptedData = openssl_encrypt(json_encode($data), 'AES-128-ECB', $this->encryptionKey);
    //         return response()->json(['data' => $encryptedData]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Invalid data'], 400);
    //     }
    // }
    // public function decryptData(Request $request)
    // {
    //     $encryptedData = $request->input('data');
    //     try {
    //         $decryptedData = json_decode(openssl_decrypt($encryptedData, 'AES-128-ECB', $this->encryptionKey), true);
    //         return response()->json(['data' => $decryptedData]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Invalid data'], 400);
    //     }
    // }






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
