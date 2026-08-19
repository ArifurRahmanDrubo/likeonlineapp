<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\SmsManager;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function send(Request $request, SmsManager $smsManager)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'message' => 'required|string',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $message = trim($validated['message']);

        if (blank($customer->mobile)) {
            return response()->json([
                'message' => 'The selected customer does not have a registered mobile number.'
            ], 422);
        }

        $log = $smsManager->sendRawSms($customer->mobile, $message, 'manual_sms');

        if (($log->status ?? null) !== 'success') {
            return response()->json([
                'message' => $log->response ?? 'Failed to send SMS.'
            ], 500);
        }

        return response()->json([
            'message' => 'Message sent successfully.',
            'customer_id' => $customer->id,
            'mobile' => $customer->mobile,
        ]);
    }
}
