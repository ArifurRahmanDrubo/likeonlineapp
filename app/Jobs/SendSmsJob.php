<?php

namespace App\Jobs;

use App\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $phoneNumber;
    private $message;
    private $type;

    public function __construct(string $phoneNumber, string $message, string $type)
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->type = $type;
    }

    public function handle(SmsService $smsService)
    {
        $smsService->sendSms($this->phoneNumber, $this->message, $this->type);
    }
}
