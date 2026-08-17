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

        // Fetch today's APPROVED payments only (pending / rejected money must
        // not inflate collection figures). recieved_date is the payment date,
        // kept consistent with the stat cards and filtered query below.
        $dailyPayments = Payment::with(['customer.invoice', 'creator:id,name', 'approver:id,name'])
            ->where('approval_status', 'approved')
            ->whereDate('recieved_date', $currentDate)
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
            // recieved_date is stored as Y-m-d — always compare against Y-m-d strings.
            $toDate = $request->filled('toDate') ? Carbon::parse($request->input('toDate'))->format('Y-m-d') : null;
            $fromDate = $request->filled('fromDate') ? Carbon::parse($request->input('fromDate'))->format('Y-m-d') : null;

            // Build query
            $query = Payment::with(['customer.invoice', 'creator:id,name', 'approver:id,name']);

            // Approved payments only — pending / rejected money is not collection.
            $query->where('approval_status', 'approved');

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
                // Match both new rows (integer user ID) and legacy rows (user name string).
                $createdBy = $this->createdByFilter($request->input('created_by'));
                $query->where(function ($q) use ($createdBy) {
                    $q->whereIn('created_by', $createdBy['userIds']);
                    if ($createdBy['rawInput'] !== null && $createdBy['rawInput'] !== '') {
                        $q->orWhere('created_by', $createdBy['rawInput']);
                    }
                });
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
        $toDate = $now->toDateString(); // Y-m-d, matching the recieved_date column format
        $received_amount = Payment::whereHas('customer')
            ->where('approval_status', 'approved')
            ->where('recieved_date', '<=', $toDate)
            ->sum('received_amount');
        // Total outstanding due — all unpaid/partial invoices, no date filters
        $due_amount = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->sum('due_amount');
        $discount = Payment::whereHas('customer')
            ->where('approval_status', 'approved')
            ->where('recieved_date', '<=', $toDate)
            ->sum('discount');
        return response()->json([
            'received_amount' => $received_amount,
            'due_amount' => $due_amount,
            'discount' => $discount

        ], 200);
    }
}
