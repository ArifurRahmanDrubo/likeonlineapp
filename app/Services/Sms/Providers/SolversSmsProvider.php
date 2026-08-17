<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolversSmsProvider implements SmsProviderInterface
{
    private $authToken = 'ZWFzdGVybml0fG9jdEA3NjU=';
    private $sendFrom = 'MM Academy';

    public function sendSms(string $to, string $message): bool
    {
        try {
            $url = "http://116.212.108.50/BulkSMSAPI/BulkSMSExtAPI.php";
            $to = trim($to);

            // Manually replace '+' with '%2B'
            $message = str_replace("+", "%2B", $message);

            $queryParams = [
                'SendFrom' => urlencode($this->sendFrom),
                'SendTo' => $to,
                'InMSgID' => uniqid(), // Generate a unique ID for the message
                'AuthToken' => $this->authToken,
                'Msg' => urlencode($message),
            ];

            $response = Http::get($url, $queryParams);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("ProviderTwo SMS Failed: {$e->getMessage()}");
            return false;
        }
    }
}
