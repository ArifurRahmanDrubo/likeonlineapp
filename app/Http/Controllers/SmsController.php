<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TwilioService;

class SmsController extends Controller
{
    protected $twilio;

    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }

    public function send(Request $request)
    {
        $request->validate([

            'message' => 'required|string',
        ]);


        $message = $request->message;


        $response = $this->twilio->sendSms($message);

        return response()->json(['status' => 'Message sent']);
    }
}
