<?php

namespace App\Services\Sms;

use App\Constants\Status;
use App\Models\SmsRecord;
use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private $primaryProvider;

    public function __construct(SmsProviderInterface $primaryProvider)
    {
        $this->primaryProvider = $primaryProvider;
    }

    public function sendSms(string $to, string $message, string $type)
    {
        // Try the primary provider
        if ($this->primaryProvider->sendSms($to, $message)) {

            $this->storeSmsRecord([
                'status' => Status::DELIVERED,
                'provider' => 'Muthobarta',
                'to' => $to,
                'message' => $message,
                'type' => $type,
            ]);

            return true;
        }

       

        // If both fail
        $this->storeSmsRecord([
            'status' => Status::FAILED,
            'provider' => null,
            'to' => $to,
            'message' => $message,
            'type' => $type,
        ]);

        return false;
    }


    public function storeSmsRecord($data)
    {
        // Remove whitespace from phone
        $phone = trim($data['to']);

        // Manually replace '+' with '%2B'
        $message = $data['message'];

        // URL encode the entire message
        $message = urlencode($message);

        $smsLength = strlen($message);
        $smsCount = (int)ceil($smsLength / 160);
        $smsCost = $smsCount * 0.38;


        // Log the SMS record with status 'pending'
        try {
            $smsData = [
                'send_from' => $data['provider'] ?? null,
                'send_to' => $phone,
                'sms_text' => $message,
                'sms_cost' => $smsCost,
                'number_of_sms' => $smsCount,
                'type' =>  $data['type'] ?? null,
                'status' => $data['status'] ?? null,
                'send_time' => now(),
            ];

            SmsRecord::create($smsData);

        } catch (\Exception $e) {
            Log::error('Failed to create SMS record: ' . $e->getMessage());
            return false;
        }
    }
}
