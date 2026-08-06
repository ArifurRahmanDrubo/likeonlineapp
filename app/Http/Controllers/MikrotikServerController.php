<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\MikrotikServer;
use RouterOS\Client;
use RouterOS\Query;

class MikrotikServerController extends Controller
{
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
            $server = MikrotikServer::create($request->all());
            return response()->json(['message' => 'MikrotikServer created successfully']);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(),]);
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
            $serverName = $request->input('serverName');
            $serverip = $request->input('serverip');
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
                'status' => $status,
            ]);

            return response()->json(['message' => 'MikrotikServer updated successfully']);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(),]);
        }
    }

    public function deleteMultiple(Request $request)
    {
        MikrotikServer::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'MikrotikServer deleted successfully']);
    }
    public function destroy(Request $request)
    {
        $id = $request->input('id');

        $mikrotikServer = MikrotikServer::findOrFail($id);
        $mikrotikServer->delete();
        return response()->json(['message' => 'MikrotikServer deleted successfully']);
    }
}
