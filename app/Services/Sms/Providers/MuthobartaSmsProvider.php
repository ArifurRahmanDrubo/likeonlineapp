<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MuthobartaSmsProvider implements SmsProviderInterface
{
    private $token = '35dba06b85cc032b6499755b250775bf6e36e5ee';
    private $senderId = '8809617613336';

    public function sendSms(string $to, string $message): bool
    {
        try {
            $url = "https://sysadmin.muthobarta.com/api/v1/send-sms-get";

            $to = trim($to);

            // Manually replace '+' with '%2B'
            $message = str_replace("+", "%2B", $message);

            // URL encode the entire message
            $message = urlencode($message);
            $queryParams = [
                'token' => $this->token,
                'sender_id' => $this->senderId,
                'receiver' => $to,
                'message' => $message,
                'remove_duplicate' => true,
            ];

            $response = Http::get($url, $queryParams);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("ProviderOne SMS Failed: {$e->getMessage()}");
            return false;
        }
    }
}
