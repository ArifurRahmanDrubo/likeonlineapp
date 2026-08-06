<?php

namespace App\Services;

use Exception;
use PEAR2\Net\RouterOS\Client as RouterOSClient;
use PEAR2\Net\RouterOS\Request;
use App\Models\MikrotikServer;

class MikroTikService
{
    protected $client;
    protected $host;
    protected $username;
    protected $password;
    protected $port;

    public function __construct($host, $username, $password, $port = 8728)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;

        try {
            $this->client = new RouterOSClient($host, $username, $password, $port);
        } catch (\Exception $e) {
            throw new \Exception('Failed to connect to MikroTik: ' . $e->getMessage());
        }
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
