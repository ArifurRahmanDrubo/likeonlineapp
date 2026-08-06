<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    // public function __construct()
    // {
    //     $this->client = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    // }

    public function sendSms($message)
    {


        $response = $this->client->messages->create(
            "+88001955937326",
            [
                'from' => env('TWILIO_PHONE_NUMBER'),
                'body' => $message,
            ]
        );

        return $response;
    }
}
