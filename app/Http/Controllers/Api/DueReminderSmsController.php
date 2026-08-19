<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Sms\SmsAutomation;
use Illuminate\Http\Request;

class DueReminderSmsController extends Controller
{
    /**
     * POST /api/send-due-reminder
     *
     * Send a payment-reminder SMS to a single customer.
     * Middleware: auth:sanctum + permission:send_sms_reminder,read
     */
    public function sendReminder(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer',
        ]);

        $customer = Customer::whereHas('invoice', function ($q) {
            $q->whereIn('status', ['unpaid', 'partial']);
        })->find($request->input('customer_id'));

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found or has no unpaid invoices.',
            ], 404);
        }

        if (empty($customer->mobile)) {
            return response()->json([
                'message' => 'No mobile number on file for this customer.',
            ], 422);
        }

        $dueAmount = (float) Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum('due_amount');

        SmsAutomation::queueDueReminder(
            $customer,
            $dueAmount,
            now()->format('d M Y')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Reminder SMS queued.',
        ]);
    }

    /**
     * POST /api/send-bulk-due-reminders
     *
     * Send payment-reminder SMS to multiple customers at once.
     * Middleware: auth:sanctum + permission:send_sms_reminder,write
     */
    public function sendBulkReminders(Request $request)
    {
        $request->validate([
            'customer_ids'     => 'required|array|min:1',
            'customer_ids.*'   => 'integer',
        ]);

        $customerIds = $request->input('customer_ids');

        // Only grab customers who actually have unpaid/partial invoices.
        $customers = Customer::whereHas('invoice', function ($q) {
            $q->whereIn('status', ['unpaid', 'partial']);
        })->whereIn('id', $customerIds)->get();

        $queued = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            if (empty($customer->mobile)) {
                $skipped++;
                continue;
            }

            $dueAmount = (float) Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->sum('due_amount');

            SmsAutomation::queueDueReminder(
                $customer,
                $dueAmount,
                now()->format('d M Y')
            );
            $queued++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Reminder SMS queued for {$queued} customer(s). Skipped: {$skipped}.",
        ]);
    }
}
