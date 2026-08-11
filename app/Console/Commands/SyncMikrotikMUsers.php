<?php

namespace App\Console\Commands;

use App\Models\IPPool;
use App\Models\MikrotikServer;
use App\Models\MProfile;
use App\Models\MUser; // নিশ্চিত করুন আপনার MUser Model তৈরি আছে
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;

class SyncMikrotikMUsers extends Command
{
    protected $signature = 'mikrotik:sync-m-users';
    protected $description = 'Sync MikroTik PPPoE secrets directly into m_users table';

    public function handle()
    {
        $servers = MikrotikServer::all();

        foreach ($servers as $server) {
            try {
                $client = new Client([
                    'host' => $server->serverip,
                    'user' => $server->Username,
                    'pass' => $server->password,
                    'port' => (int) $server->port,
                    'timeout' => 60,
                ]);

                $query = new Query('/ppp/secret/print');
                $responses = $client->query($query)->read();

                foreach ($responses as $item) {
                    if (empty($item['name'])) {
                        continue;
                    }
                    $lastLoggedOut = null;
                    if (!empty($item['last-logged-out'])) {
                        try {
                            $parsedDate = Carbon::parse($item['last-logged-out']);
                            if ($parsedDate->year > 1970) {
                                $lastLoggedOut = $parsedDate;
                            }
                        } catch (\Exception $e) {
                            $lastLoggedOut = null;
                        }
                    }
                    MUser::updateOrCreate(
                        [
                            'name' => $item['name'],
                        ],
                        [
                            'mikrotik_id' => $item['.id'],
                            'server_id' => $server->id,
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

                $this->info("Successfully synced  Server: {$server->serverName} ({$server->serverip})");

            } catch (\Throwable $e) {
                Log::error("MikroTik Sync Failed [Server ID {$server->id}]: " . $e->getMessage());
                $this->error("Failed to sync Server {$server->serverip}: " . $e->getMessage());
            }
        }
    }
}