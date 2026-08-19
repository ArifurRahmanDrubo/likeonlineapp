<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\PackageChanged;
use App\Models\StatusChanged;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;

/**
 * Shared MikroTik change-application logic.
 *
 * Every flow that executes a package / status change (the immediate controller
 * path, the artisan cron command, the change-request approval, and the
 * scheduler force-run/retry) calls these methods, so the RouterOS API
 * interactions + local customer updates live in exactly ONE place.
 */
class ScheduledChangeService
{
    /**
     * Build a MikroTik client for a customer's assigned server.
     * Returns null when there is no server/radius id so callers can skip.
     */
    protected function clientFor(Customer $customer): ?Client
    {
        if (!$customer->server_id || !$customer->radius_id) {
            return null;
        }

        $server = MikrotikServer::find($customer->server_id);
        if (!$server) {
            return null;
        }

        return new Client([
            'host' => $server->serverip,
            'user' => $server->Username,
            'pass' => $server->password,
            'port' => $server->port,
        ]);
    }

    /**
     * Fully execute a scheduled package-change request: apply the new profile
     * on the router, update the customer record and mark the request completed.
     *
     * @throws \Exception when the customer is missing or the router rejects the
     *                   operation (the caller decides how to mark the request)
     */
    public function applyPackageRequest(PackageChanged $request): void
    {
        $customer = Customer::with('server')->find($request->customer_id);
        if (!$customer) {
            throw new \Exception("Customer record not found (ID {$request->customer_id}).");
        }

        // MikroTik: set secret profile + kick active session
        $this->applyPackageChange($customer, $request->profile);

        // Update the local customer record
        $customer->update([
            'profile'     => $request->profile,
            'monthlybill' => $request->monthlybill,
            'package'     => $request->package ?: $customer->package,
        ]);

        $request->update(['status' => 'completed', 'error_log' => null]);
    }

    /**
     * Fully execute a scheduled status-change request: enable/disable the
     * secret + kick the session on the router, update the customer record and
     * mark the request completed.
     *
     * @throws \Exception when the customer is missing or the router rejects the
     *                   operation
     */
    public function applyStatusRequest(StatusChanged $request): void
    {
        $customer = Customer::with('server')->find($request->customer_id);
        if (!$customer) {
            throw new \Exception("Customer record not found (ID {$request->customer_id}).");
        }

        $status = $request->billingstatus;

        // MikroTik: enable/disable secret + kick session based on status
        $this->applyStatusChange($customer, $status);

        // Update the local customer record (mikrotikStatus keeps the dashboard
        // Online/Inactive counts in sync with the router)
        $customer->update([
            'status'         => $status,
            'billingstatus'  => ucfirst($status),
            'mikrotikStatus' => $status === 'active',
            'caller_id'      => $status === 'left' ? null : $customer->caller_id,
            'left_date'      => $status === 'left' ? now() : $customer->left_date,
            'left_reason'    => $status === 'left' ? ($request->notes ?: $customer->left_reason) : $customer->left_reason,
        ]);

        $request->update(['status' => 'completed', 'error_log' => null]);
    }

    /**
     * Update a PPPoE secret's profile and kick any active session so the new
     * profile takes effect immediately.
     *
     * @throws \Exception when the router rejects the operation
     */
    public function applyPackageChange(Customer $customer, string $profile): void
    {
        $client = $this->clientFor($customer);
        if (!$client) {
            Log::warning("No MikroTik server/radius for customer {$customer->id} — package change applied to DB only.");
            return;
        }

        // Update secret profile
        $setProfile = new Query('/ppp/secret/set');
        $setProfile->equal('.id', $customer->radius_id);
        $setProfile->equal('profile', $profile);
        $client->query($setProfile)->read();

        // Disconnect active session so the new profile is picked up
        $this->kickActiveSession($client, $customer->username);
    }

    /**
     * Enable/disable the PPPoE secret and kick active sessions based on the
     * target status.
     *
     * - 'active'      -> secret enabled
     * - 'suspended'   -> secret disabled + session kicked
     * - 'expired'     -> secret disabled + session kicked
     * - 'left'        -> secret disabled + session kicked + caller-id cleared
     *
     * @throws \Exception when the router rejects the operation
     */
    public function applyStatusChange(Customer $customer, string $status): void
    {
        $client = $this->clientFor($customer);
        if (!$client) {
            Log::warning("No MikroTik server/radius for customer {$customer->id} — status change applied to DB only.");
            return;
        }

        $disabled = in_array($status, ['suspended', 'expired', 'left'], true);

        // Enable/disable the secret
        $setDisabled = new Query('/ppp/secret/set');
        $setDisabled->equal('.id', $customer->radius_id);
        $setDisabled->equal('disabled', $disabled ? 'true' : 'false');
        $client->query($setDisabled)->read();

        // Kick the live session for non-active statuses
        if ($disabled) {
            $this->kickActiveSession($client, $customer->username);
        }

        // Left clients should also have their MAC binding cleared
        if ($status === 'left' && $customer->caller_id) {
            $unbind = new Query('/ppp/secret/set');
            $unbind->equal('.id', $customer->radius_id);
            $unbind->equal('caller-id', '');
            $client->query($unbind)->read();
        }
    }

    /**
     * Re-enable a paid-up customer's PPPoE secret and kick any active session
     * so the re-enabled state takes effect immediately.
     *
     * Used by payment approval to restore service once the invoice is fully
     * paid (a) enable the secret, b) kick the live session).
     *
     * @throws \Exception when the router rejects the operation — the caller
     *                   decides whether to flag the invoice as pending sync
     */
    public function reEnableMikrotik(Customer $customer): void
    {
        $client = $this->clientFor($customer);
        if (!$client) {
            Log::warning("No MikroTik server/radius for customer {$customer->id} — re-enable skipped.");
            return;
        }

        // a) Enable the secret
        $setEnabled = new Query('/ppp/secret/set');
        $setEnabled->equal('.id', $customer->radius_id);
        $setEnabled->equal('disabled', 'false');
        $client->query($setEnabled)->read();

        // b) Kick any active session so the re-enabled state applies immediately
        $this->kickActiveSession($client, $customer->username);
    }

    /**
     * Remove any live /ppp/active session matching the customer's username.
     */
    protected function kickActiveSession(Client $client, string $username): void
    {
        $activeQuery = new Query('/ppp/active/print');
        $activeQuery->where('name', $username);
        $sessions = $client->query($activeQuery)->read();

        foreach ($sessions as $session) {
            if (($session['name'] ?? null) === $username && !empty($session['.id'])) {
                $remove = new Query('/ppp/active/remove');
                $remove->equal('.id', $session['.id']);
                $client->query($remove)->read();
                break;
            }
        }
    }
}
