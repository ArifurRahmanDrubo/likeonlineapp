<?php

namespace App\Services;

use App\Models\SmsRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    


    protected $apiUrl;
    protected $token;
    protected $senderId;

    public function __construct()
    {
        $this->apiUrl = env('SMS_API_URL', 'https://sysadmin.muthobarta.com/api/v1/send-sms-get');
        $this->token = env('SMS_API_TOKEN', '35dba06b85cc032b6499755b250775bf6e36e5ee');
        $this->senderId = env('SMS_SENDER_ID', '8809617613336');
    }

    public function sendSms($phone, $message, $type)
    {
        // Remove whitespace from phone
        $phone = trim($phone);

        // Manually replace '+' with '%2B'
        $message = str_replace("+", "%2B", $message);

        // URL encode the entire message
        $message = urlencode($message);

        $smsLength = strlen($message);
        $smsCount = (int)ceil($smsLength / 160);
        $smsCost = $smsCount * 0.38;

        // Log the SMS record with status 'pending'
        try {
            $smsRecord = SmsRecord::create([
                'send_from' => $this->senderId,
                'send_to' => $phone,
                'branch_id' => auth()->user()->branch_id,
                'send_by' => auth()->user()->id,
                'sms_text' => $message,
                'number_of_sms' => $smsCount,
                'sms_cost' => $smsCost,
                // 'status' => Status::PENDING,
                'status' => 'pending',
                'send_time' => now(),
                'type' => $type
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create SMS record: '.$e->getMessage());
            return false;
        }

        $response = Http::get($this->apiUrl, [
            'token' => $this->token,
            'sender_id' => $this->senderId,
            'receiver' => $phone,
            'message' => $message,
            'remove_duplicate' => 'true',
        ]);

        // Decode the response
        $responseBody = $response->json();

        if ($response->successful() && $responseBody['code'] == 200) {
            // Update the SMS record status and cost
            $smsRecord->update([
                // 'status' => Status::DELIVERED,
                'status' => 'delivered',
            ]);

            return true;
        } else {
            // Update the SMS record status to 'failed'
            $smsRecord->update(['status' => 'failed']);

            // Log error or handle the failure accordingly
            Log::error('SMS sending failed: '.$response->body());
            return false;
        }
    }


    public function storeSmsResult($phone, $message, $type)
    {
        // Remove whitespace from phone
        $phone = trim($phone);

        // Manually replace '+' with '%2B'
        $message = str_replace("+", "%2B", $message);

        // URL encode the entire message
        $message = urlencode($message);

        $smsLength = strlen($message);
        $smsCount = (int)ceil($smsLength / 160);
        $smsCost = $smsCount * 0.38;

        // Log the SMS record with status 'pending'
        try {
            $smsRecord = SmsRecord::create([
                'send_from' => $this->senderId,
                'send_to' => $phone,
                'branch_id' => auth()->user()->branch_id,
                'send_by' => auth()->user()->id,
                'sms_text' => $message,
                'number_of_sms' => $smsCount,
                'sms_cost' => $smsCost,
                'status' => 'delivered',
                'send_time' => now(),
                'type' => $type
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create SMS record: '.$e->getMessage());
            return false;
        }

    }
}
