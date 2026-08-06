<?php


namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use RouterOS\Query;
use RouterOS\Client;
use Illuminate\Http\Request;
use App\Models\MikrotikServer;
use Illuminate\Support\Facades\Log;

class PppoeProfile extends Controller
{
    public function getpppoeProfile()
    {
        try {
            $mikrotikServer = MikrotikServer::first();

            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);

            // Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);

            $request = new Query('/ppp/profile/print');
            $responses = $client->query($request)->read();


            $query = new Query('/ip/pool/print');
            $response = $client->query($query)->read();
            // Log::info("Received PPPoE profiles from MikroTik server: ", $profiles);

            if (empty($profiles)) {
                Log::warning("No PPPoE profiles received from MikroTik server.");
            }

            return response()->json($responses);
            return response()->json(['profiles' => $responses, 'ip_pools' => $response]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        }
    }
    public function getpppoeIPpool()
    {
        try {
            $mikrotikServer = MikrotikServer::first();

            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);

            $query = new Query('/ip/pool/print');
            $response = $client->query($query)->read();
            // Log::info("Received PPPoE profiles from MikroTik server: ", $profiles);

            if (empty($response)) {
                Log::warning("No PPPoE IPPOOL received from MikroTik server.");
            }

            return response()->json($response);
            // return response()->json(['profiles' => $responses, 'ip_pools' => $response]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        }
    }

    public function createpppoeProfile(Request $request)
    {
        try {
            $id = $request->input('server_id');
            $name = $request->input('name');
            $localAddress = $request->input('local_address');
            $remoteAddress = $request->input('remote_address');
            $maxUpload = $request->input('max_upload');
            $maxDownload = $request->input('max_download');
            $dnsServer = $request->input('dns_server');
            $mikrotikServer = MikrotikServer::find($id);
            // $onUpScript = "/queue simple add name=$name target=$remoteAddress max-limit={$maxUpload}k/{$maxDownload}k";
            // $onDownScript = "/queue simple remove [find name=$name]";
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);

            // Log::info("Connected to MikroTik server: " . $mikrotikServer->serverip);

            $query = new Query('/ppp/profile/add');
            $query->equal('name', $name);
            $query->equal('local-address', $localAddress);
            $query->equal('remote-address', $remoteAddress);
            $query->equal('dns-server', $dnsServer);
            $query->equal('only-one', "yes");
            $responses = $client->query($query)->read();

            $queueQuery = new Query('/queue/simple/add');
            $queueQuery->equal('name', $name);
            $queueQuery->equal('target', $remoteAddress);
            $queueQuery->equal('max-limit', "{$maxUpload}M/{$maxDownload}M");

            // Execute the queue command
            $queueResponse = $client->query($queueQuery)->read();
            return response()->json(['response ' => $responses]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
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

            return response()->json(['success' => true, 'response' => $response]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'MikrotikServer not found.'], 404);
        } catch (Exception $e) {
            // Log::error("Failed to fetch PPPoE profiles from Mikrotik Server: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch PPPoE profiles from Mikrotik Server', 'message' => $e->getMessage()], 500);
        }
    }
}
