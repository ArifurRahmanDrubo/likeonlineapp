<?php

namespace App\Services;

use Exception;
use PEAR2\Net\RouterOS\Client as RouterOSClient;
use PEAR2\Net\RouterOS\Request;
use App\Models\MikrotikServer;
use RouterOS\Client;
use RouterOS\Query;

class MikroTikService
{
    protected $client;
    protected $host;
    protected $username;
    protected $password;
    protected $port;

    public function __construct($host = null, $username = null, $password = null, $port = 8728)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;

        // Credentials are optional so the service can be resolved from the
        // container (app(MikroTikService::class)) and connect lazily per-call;
        // eager connection is only attempted when a host is supplied.
        if (!$host) {
            return;
        }

        try {
            $this->client = new RouterOSClient($host, $username, $password, $port);
        } catch (\Exception $e) {
            throw new \Exception('Failed to connect to MikroTik: ' . $e->getMessage());
        }
    }

    /**
     * Look up a customer's live PPPoE session on the assigned MikroTik server
     * and return the active caller-id (MAC address).
     *
     * Returns null when the server is missing, the user is offline, or the
     * session carries no caller-id — callers should fall back gracefully.
     *
     * @param int|string|null $serverId customers.server_id
     * @param string          $username customers.username
     * @return array{name: string|null, caller-id: string|null, address: string|null, uptime: string|null}|null
     */
    public function getActivePppoeSession($serverId, $username)
    {
        $server = MikrotikServer::find($serverId);
        if (!$server) {
            return null;
        }

        $client = new Client([
            'host' => $server->serverip,
            'user' => $server->Username,
            'pass' => $server->password,
            'port' => (int) ($server->port ?? 8728),
        ]);

        $query = new Query('/ppp/active/print');
        $query->where('name', $username);

        foreach ($client->query($query)->read() as $session) {
            if (($session['name'] ?? null) === $username) {
                return [
                    'name' => $session['name'] ?? null,
                    'caller-id' => $session['caller-id'] ?? null,
                    'address' => $session['address'] ?? null,
                    'uptime' => $session['uptime'] ?? null,
                ];
            }
        }

        return null;
    }

    public function connect($host, $username, $password)
    {
        try {
            $this->client = new RouterOSClient($host, $username, $password);
            return 'MikroTik server connected.';
        } catch (\Exception $e) {
            return 'Failed to connect to MikroTik: ' . $e->getMessage();
        }
    }

    public function disconnect()
    {
        $this->client = null;
        return 'MikroTik server disconnected.';
    }

    public function toggleServerStatus(MikrotikServer $mikrotikServer, $status)
    {
        try {
            if ($status === 'active') {
                return $this->connect($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password);
            } elseif ($status === 'inactive') {
                return $this->disconnect();
            } else {
                throw new \Exception('Invalid status provided.');
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getUsers()
    {
        try {
            $query = new Request('/ip/hotspot/user/print');
            $response = $this->client->sendSync($query);
            return $response;
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function addUser($username, $password)
    {
        try {
            $addRequest = new Request('/ip/hotspot/user/add');
            $addRequest->setArgument('name', $username);
            $addRequest->setArgument('password', $password);
            $this->client->sendSync($addRequest);
            return ['success' => true, 'message' => 'User added successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeUser($username)
    {
        try {
            $removeRequest = new Request('/ip/hotspot/user/remove');
            $removeRequest->setArgument('numbers', $username);
            $this->client->sendSync($removeRequest);
            return ['success' => true, 'message' => 'User removed successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
