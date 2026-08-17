<?php

namespace App\Services\Sms\Contracts;

interface SmsProviderInterface
{
    public function sendSms(string $to, string $message): bool;
}
