<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\GeneratedBill;

class DailyCollectionController extends Controller
{

    public function getDailyBillCollection()
    {
        // Get the current date
        $currentDate = Carbon::now()->toDateString();

        // Fetch daily payments with customer details
        $dailyPayments = Payment::with('customer.invoice')
            ->whereDate('created_at', $currentDate)
            ->get();

        // Return data as JSON response
        return response()->json($dailyPayments);
    }
    public function getDailyBillCollectionQuery(Request $request)
    {
        // Validate query parameters
        try {

            $validated = $request->validate([
                'userId' => 'nullable|string',

                'recieved_by' => 'nullable|string',
                'created_by' => 'nullable|string',
                'payment_method' => 'nullable|string',
            ]);
            $todate = $request->input('toDate');

            $toDate = Carbon::parse($todate)->format('d F Y');
            $fromdate = $request->input('fromDate');
            $fromDate = Carbon::parse($fromdate)->format('d F Y');

            // Build query
            $query = Payment::with('customer.invoice');

            if ($request->filled('userId')) {
                $query->where('customer_id', $request->input('userId'));
            }
            if ($request->filled('toDate')) {
                $query->whereDate('recieved_date', '<=', $toDate);
            }
            if ($request->filled('fromDate')) {
                $query->whereDate('recieved_date', '>=', $fromDate);
            }
            if ($request->filled('recieved_by')) {
                $query->where('recieved_by', $request->input('recieved_by'));
            }
            if ($request->filled('created_by')) {
                $query->where('created_by', $request->input('created_by'));
            }
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->input('payment_method'));
            }


            // Execute query and get results
            $paymentDetails = $query->get();

            // Return results as JSON
            return response()->json($paymentDetails);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function dailycollectiondashboard()
    {

        $now = Carbon::now();
        $toDate = $now->format('d F Y');
        $received_amount = Payment::whereHas('customer')->where('recieved_date', '<=', $toDate)
            ->sum('received_amount');
        $due_amount = Invoice::whereHas('customer')
            ->sum('amount');
        $discount = Payment::whereHas('customer')->where('recieved_date', '<=', $toDate)
            ->sum('discount');
        return response()->json([
            'received_amount' => $received_amount,
            'due_amount' => $due_amount,
            'discount ' => $discount

        ], 200);
    }
}
