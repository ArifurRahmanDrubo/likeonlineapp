<?php


namespace App\Http\Controllers;

use App\Models\IPPool;
use App\Models\MikrotikServer;
use App\Models\MProfile;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;

class PppoeProfile extends Controller
{
    public function getpppoeProfile()
    {

        $data = MProfile::with('server:id,serverName')->get();

        return response()->json(['success' => true, 'data' => $data]);
        // try {
        //     $mikrotikServer = MikrotikServer::find(2);

        //     $client = new Client([
        //         'host' => $mikrotikServer->serverip,
        //         'user' => $mikrotikServer->Username,
        //         'pass' => $mikrotikServer->password,
        //         'port' => $mikrotikServer->port,
        //     ]);

        //     // Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);

        //     $request = new Query('/ppp/profile/print');
        //     $responses = $client->query($request)->read();


        //     $query = new Query('/ip/pool/print');
        //     $response = $client->query($query)->read();
        //     // Log::info("Received PPPoE profiles from MikroTik server: ", $profiles);

        //     if (empty($profiles)) {
        //         Log::warning("No PPPoE profiles received from MikroTik server.");
        //     }

        //     return response()->json($responses);

        //         $data = MProfile::with('server:id,serverName')->get();

        //    return response()->json(['success' => true, 'response' => $data]);

        // } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        //     return response()->json(['error' => 'MikrotikServer not found.'], 404);
        // } catch (Exception $e) {
        //     // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
        //     return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        // }
    }
    public function getpppoeIPpool()
    {
        $response = IPPool::with('server:id,serverName')
            ->get(['id', 'server_id', 'name', 'ranges', 'mikrotik_id']);
        return response()->json($response);
    }

    // public function createpppoeProfile(Request $request)
    // {
    //     try {
    //         $id = $request->input('server_id');
    //         $name = $request->input('name');
    //         $localAddress = $request->input('local_address');
    //         $remoteAddress = $request->input('remote_address');
    //         $maxUpload = $request->input('max_upload');
    //         $maxDownload = $request->input('max_download');
    //         $dnsServer = $request->input('dns_server');
    //         $mikrotikServer = MikrotikServer::find($id);
    //         // $onUpScript = "/queue simple add name=$name target=$remoteAddress max-limit={$maxUpload}k/{$maxDownload}k";
    //         // $onDownScript = "/queue simple remove [find name=$name]";
    //         $client = new Client([
    //             'host' => $mikrotikServer->serverip,
    //             'user' => $mikrotikServer->Username,
    //             'pass' => $mikrotikServer->password,
    //             'port' => $mikrotikServer->port,
    //         ]);

    //         // Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);

    //         $query = new Query('/ppp/profile/add');
    //         $query->equal('name', $name);
    //         $query->equal('local-address', $localAddress);
    //         $query->equal('remote-address', $remoteAddress);
    //         $query->equal('dns-server', $dnsServer);
    //         $query->equal('only-one', "yes");
    //         $responses = $client->query($query)->read();

    //         Log::info('PPP Profile response', [
    //             'response' => $responses,
    //         ]);
    //         $queueQuery = new Query('/queue/simple/add');
    //         $queueQuery->equal('name', $name);
    //         $queueQuery->equal('target', $remoteAddress);
    //         $queueQuery->equal('max-limit', "{$maxUpload}M/{$maxDownload}M");

    //         $queueResponse = $client->query($queueQuery)->read();
    //         Log::info('Queue response', [
    //             'response' => $queueResponse,
    //         ]);
    //         MProfile::updateOrCreate(
    //             [
    //                 'server_id' => $mikrotikServer->id,
    //                 'name' => $name,
    //             ],
    //             [
    //                 'mikrotik_id' => $responses[0]['.id'] ?? null,
    //                 'local_address' => $localAddress,
    //                 'remote_address' => $remoteAddress,
    //                 'dns_server' => $dnsServer,
    //                 'only_one' => 'yes',

    //                 // MikroTik profile response থেকে থাকলে
    //                 'address_list' => $responses[0]['address-list'] ?? null,
    //                 'bridge_learning' => $responses[0]['bridge-learning'] ?? null,
    //                 'change_tcp_mss' => $responses[0]['change-tcp-mss'] ?? null,
    //                 'default' => isset($responses[0]['default'])
    //                     ? $responses[0]['default'] === 'true'
    //                     : false,
    //                 'on_down' => $responses[0]['on-down'] ?? null,
    //                 'on_up' => $responses[0]['on-up'] ?? null,
    //                 'use_compression' => $responses[0]['use-compression'] ?? null,
    //                 'use_encryption' => $responses[0]['use-encryption'] ?? null,
    //                 'use_ipv6' => $responses[0]['use-ipv6'] ?? null,
    //                 'use_mpls' => $responses[0]['use-mpls'] ?? null,
    //                 'use_upnp' => $responses[0]['use-upnp'] ?? null,
    //             ]
    //         );
    //         $data = MProfile::with('server:id,serverName')->get();

    //         return response()->json(['success' => true, 'response' => $data]);
    //     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    //         return response()->json(['error' => 'MikrotikServer not found.'], 404);
    //     } catch (Exception $e) {
    //         // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
    //         return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function createpppoeProfile(Request $request)
{
    try {
        $serverId = $request->input('server_id');
        $name = $request->input('name');
        $localAddress = $request->input('local_address');
        $remoteAddress = $request->input('remote_address');
        $maxUpload = $request->input('max_upload');
        $maxDownload = $request->input('max_download');
        $dnsServer = $request->input('dns_server');

        $mikrotikServer = MikrotikServer::findOrFail($serverId);

        $client = new Client([
            'host' => $mikrotikServer->serverip,
            'user' => $mikrotikServer->Username,
            'pass' => $mikrotikServer->password,
            'port' => $mikrotikServer->port,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create PPPoE Profile
        |--------------------------------------------------------------------------
        */

        $query = new Query('/ppp/profile/add');

        $query->equal('name', $name);
        $query->equal('local-address', $localAddress);
        $query->equal('remote-address', $remoteAddress);
        $query->equal('dns-server', $dnsServer);
        $query->equal('only-one', 'yes');

        // Upload/Download speed
        $query->equal(
            'rate-limit',
            "{$maxUpload}M/{$maxDownload}M"
        );

        $responses = $client->query($query)->read();
        /*
        |--------------------------------------------------------------------------
        | Get created profile ID
        |--------------------------------------------------------------------------
        */

        $mikrotikId = $responses[0]['.id'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Save Local Database
        |--------------------------------------------------------------------------
        */

        MProfile::updateOrCreate(
            [
                'server_id' => $mikrotikServer->id,
                'name' => $name,
            ],
            [
                'mikrotik_id' => $mikrotikId,

                'local_address' => $localAddress,
                'remote_address' => $remoteAddress,
                'dns_server' => $dnsServer,
                'only_one' => 'yes',

                'address_list' =>
                    $responses[0]['address-list'] ?? null,

                'bridge_learning' =>
                    $responses[0]['bridge-learning'] ?? null,

                'change_tcp_mss' =>
                    $responses[0]['change-tcp-mss'] ?? null,

                'default' =>
                    isset($responses[0]['default'])
                        ? $responses[0]['default'] === 'true'
                        : false,

                'on_down' =>
                    $responses[0]['on-down'] ?? null,

                'on_up' =>
                    $responses[0]['on-up'] ?? null,

                'use_compression' =>
                    $responses[0]['use-compression'] ?? null,

                'use_encryption' =>
                    $responses[0]['use-encryption'] ?? null,

                'use_ipv6' =>
                    $responses[0]['use-ipv6'] ?? null,

                'use_mpls' =>
                    $responses[0]['use-mpls'] ?? null,

                'use_upnp' =>
                    $responses[0]['use-upnp'] ?? null,
            ]
        );
        $data = MProfile::with('server:id,serverName')->get();

        return response()->json([
            'success' => true,
            'message' => 'PPPoE profile created successfully.',
            'response' => $data,
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

        return response()->json([
            'success' => false,
            'error' => 'MikrotikServer not found.'
        ], 404);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'error' => 'Failed to create PPPoE profile.',
            'message' => $e->getMessage()
        ], 500);
    }
}
public function editpppoeProfile(Request $request)
{
    try {
        $profileId = $request->input('id');

        $name = $request->input('name');
        $localAddress = $request->input('local_address');
        $remoteAddress = $request->input('remote_address');
        $maxUpload = $request->input('max_upload');
        $maxDownload = $request->input('max_download');
        $dnsServer = $request->input('dns_server');

        /*
        |--------------------------------------------------------------------------
        | Local Profile
        |--------------------------------------------------------------------------
        */

        $profile = MProfile::findOrFail($profileId);

        /*
        |--------------------------------------------------------------------------
        | MikroTik Server
        |--------------------------------------------------------------------------
        */

        $mikrotikServer = MikrotikServer::findOrFail($profile->server_id);

        $client = new Client([
            'host' => $mikrotikServer->serverip,
            'user' => $mikrotikServer->Username,
            'pass' => $mikrotikServer->password,
            'port' => $mikrotikServer->port,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update PPPoE Profile
        |--------------------------------------------------------------------------
        */

        $query = new Query('/ppp/profile/set');

        $query->equal('numbers', $profile->mikrotik_id);
        $query->equal('name', $name);
        $query->equal('local-address', $localAddress);
        $query->equal('remote-address', $remoteAddress);
        $query->equal('dns-server', $dnsServer);
        $query->equal('only-one', 'yes');

        // Bandwidth
        $query->equal(
            'rate-limit',
            "{$maxUpload}M/{$maxDownload}M"
        );

        $response = $client->query($query)->read();

        Log::info('PPP Profile updated', [
            'profile_id' => $profile->mikrotik_id,
            'response' => $response,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Local MProfile
        |--------------------------------------------------------------------------
        */

        $profile->name = $name;
        $profile->local_address = $localAddress;
        $profile->remote_address = $remoteAddress;
        $profile->max_upload = $maxUpload;
        $profile->max_download = $maxDownload;
        $profile->dns_server = $dnsServer;
        $profile->only_one = 'yes';

        // MikroTik response থেকে available fields
        $profile->address_list =
            $response[0]['address-list'] ?? $profile->address_list;

        $profile->bridge_learning =
            $response[0]['bridge-learning'] ?? $profile->bridge_learning;

        $profile->change_tcp_mss =
            $response[0]['change-tcp-mss'] ?? $profile->change_tcp_mss;

        $profile->default =
            isset($response[0]['default'])
                ? $response[0]['default'] === 'true'
                : $profile->default;

        $profile->on_down =
            $response[0]['on-down'] ?? $profile->on_down;

        $profile->on_up =
            $response[0]['on-up'] ?? $profile->on_up;

        $profile->use_compression =
            $response[0]['use-compression'] ?? $profile->use_compression;

        $profile->use_encryption =
            $response[0]['use-encryption'] ?? $profile->use_encryption;

        $profile->use_ipv6 =
            $response[0]['use-ipv6'] ?? $profile->use_ipv6;

        $profile->use_mpls =
            $response[0]['use-mpls'] ?? $profile->use_mpls;

        $profile->use_upnp =
            $response[0]['use-upnp'] ?? $profile->use_upnp;

        $profile->save();

        /*
        |--------------------------------------------------------------------------
        | Return Updated Profiles
        |--------------------------------------------------------------------------
        */

        $data = MProfile::with('server:id,serverName')->get();

        return response()->json([
            'success' => true,
            'message' => 'PPPoE profile updated successfully.',
            'response' => $data,
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

        return response()->json([
            'success' => false,
            'error' => 'PPPoE profile or MikroTik server not found.'
        ], 404);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'error' => 'Failed to update PPPoE profile.',
            'message' => $e->getMessage(),
        ], 500);
    }
}
public function deletepppoeProfile(Request $request)
{
    try {
        $id = $request->input('id');

        // Local profile
        $profile = MProfile::findOrFail($id);

        // MikroTik server
        $mikrotikServer = MikrotikServer::findOrFail($profile->server_id);

        $client = new Client([
            'host' => $mikrotikServer->serverip,
            'user' => $mikrotikServer->Username,
            'pass' => $mikrotikServer->password,
            'port' => $mikrotikServer->port,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Remove PPP Profile from MikroTik
        |--------------------------------------------------------------------------
        */

        $query = new Query('/ppp/profile/remove');

        $query->equal('numbers', $profile->mikrotik_id);

        $response = $client->query($query)->read();

        /*
        |--------------------------------------------------------------------------
        | Delete Local Profile
        |--------------------------------------------------------------------------
        */

        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'PPPoE profile deleted successfully.',
            'response' => $response,
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

        return response()->json([
            'success' => false,
            'error' => 'Profile or MikroTik Server not found.',
        ], 404);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'error' => 'Failed to delete PPPoE profile.',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    public function createpppoeIppool(Request $request)
    {
        try {
            $id = $request->input('server_id');
            $name = $request->input('name');
            $range = $request->input('range');
            $mikrotikServer = MikrotikServer::find($id);
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);

            // Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);

            $query = new Query('/ip/pool/add');
            $query->equal('name', $name);
            $query->equal('ranges', $range);

            // Execute the command on MikroTik
            $response = $client->query($query)->read();

            IPPool::updateOrCreate(
                [
                    'server_id' => $mikrotikServer->id,
                    'name' => $name,
                ],
                [
                    'ranges' => $range,
                    'mikrotik_id' => $response[0]['.id'] ?? null,
                ]
            );

            $data = IPPool::with('server:id,serverName')
                ->get(['id', 'server_id', 'name', 'ranges', 'mikrotik_id']);

            return response()->json(['success' => true, 'response' => $data]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        }
    }

    public function editPppoeIppool(Request $request)
    {
        try {
            $id = $request->input('id');
            $name = $request->input('name');
            $range = $request->input('range');

            $ipPool = IPPool::find($id);

            $mikrotikServer = MikrotikServer::find($ipPool->server_id);

            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);

            // Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);

            $query = new Query('/ip/pool/set');
            $query->equal('numbers', $ipPool->mikrotik_id);
            $query->equal('name', $name);
            $query->equal('ranges', $range);

            // Execute the command on MikroTik
            $response = $client->query($query)->read();

            $ipPool->update([
                'name' => $name,
                'ranges' => $range,
            ]);

            $data = IPPool::with('server:id,serverName')
                ->get(['id', 'server_id', 'name', 'ranges', 'mikrotik_id']);

            return response()->json(['success' => true, 'response' => $data]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        }
    }

    public function deletePppoeIppool(Request $request)
    {
        try {
            $id = $request->input('id');

            $ipPool = IPPool::findOrFail($id);

            $mikrotikServer = MikrotikServer::findOrFail($ipPool->server_id);

            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);

            // MikroTik থেকে pool remove
            $query = new Query('/ip/pool/remove');

            $query->equal('numbers', $ipPool->mikrotik_id);

            $response = $client->query($query)->read();

            // Local database থেকেও remove
            $ipPool->delete();

            return response()->json([
                'success' => true,
                'message' => 'IP Pool deleted successfully.',
                'response' => $response,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'error' => 'IP Pool or MikroTik Server not found.'
            ], 404);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete IP Pool.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
