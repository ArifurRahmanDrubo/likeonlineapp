<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * bKash (tokenized checkout) token management.
 *
 * The karim007/laravel-bkash-tokenize package stores the id_token in the
 * session and never refreshes a stale token inside executePayment() /
 * queryPayment() — so a customer who takes longer than bKash's ~60-minute
 * token lifetime between checkout creation and the gateway callback hits
 * "The incoming token has expired" (statusCode 2001 / 2002).
 *
 * This service adds a Laravel-cache-backed token with a 50-minute TTL (a
 * safety margin under bKash's 60-minute expiry) plus an automatic
 * refresh-and-retry wrapper for the callback API calls.
 */
class BkashService
{
    /**
     * @var string bKash tokenized checkout base URL (sandbox / live).
     */
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('bkash.sandbox')
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized';
    }

    /**
     * Grant a brand-new id_token from bKash and cache it for 50 minutes.
     *
     * bKash tokens live for 60 minutes; the shorter TTL guarantees the token
     * is always refreshed while still valid, eliminating the edge case where
     * it expires between our cache read and the API call.
     */
    public function grantToken(): ?string
    {
        $postToken = json_encode([
            'app_key' => config('bkash.bkash_app_key'),
            'app_secret' => config('bkash.bkash_app_secret'),
        ]);

        $ch = curl_init($this->baseUrl . '/checkout/token/grant');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json',
                'password:' . config('bkash.bkash_password'),
                'username:' . config('bkash.bkash_username'),
            ],
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postToken,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT => 30,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true);

        if (!is_array($response) || empty($response['id_token'])) {
            Log::error('bKash token grant failed.', ['response' => $response]);
            return null;
        }

        Cache::put('bkash_id_token', $response['id_token'], now()->addMinutes(50));

        return $response['id_token'];
    }

    /**
     * The cached id_token while it is still fresh, otherwise a newly granted
     * one. Never returns an expired token.
     */
    public function token(): ?string
    {
        return Cache::get('bkash_id_token') ?: $this->grantToken();
    }

    /**
     * Detect bKash's "incoming token has expired" responses: status codes
     * 2001 / 2002 or the matching status message.
     */
    public function isTokenExpired($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        $status = (string) ($response['statusCode'] ?? '');
        $message = strtolower((string) ($response['statusMessage'] ?? $response['message'] ?? ''));

        return in_array($status, ['2001', '2002'], true)
            || str_contains($message, 'token has expired')
            || str_contains($message, 'invalid token');
    }

    /**
     * Run a bKash API call with automatic token recovery:
     *
     *   1. ensure a fresh cached token is in the session store (the package
     *      reads its Authorization header from session()->get('bkash_token')),
     *   2. call the API,
     *   3. if bKash reports the token as expired: forget the cache, grant a
     *      fresh token, put it back into the session and retry ONCE.
     *
     * The closure receives the token (the package reads it from the session,
     * so most callers can ignore the argument).
     */
    public function withTokenRefresh(callable $call)
    {
        $token = $this->token();
        if ($token) {
            session()->put('bkash_token', $token);
        }

        $response = $call($token);

        if ($this->isTokenExpired($response)) {
            Log::warning('bKash reported an expired token — refreshing and retrying the API call.');

            Cache::forget('bkash_id_token');
            $freshToken = $this->grantToken();
            if ($freshToken) {
                session()->put('bkash_token', $freshToken);
            }

            $response = $call($freshToken);
        }

        return $response;
    }
}
