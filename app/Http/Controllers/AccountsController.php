<?php

namespace App\Http\Controllers;

use App\Models\Bandwidthbill;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\Customer;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AccountsController extends Controller
{
    public function accountsData()
    {
        // Get the current date and the start of the current month
        $now = Carbon::now();
        $fromDate = $now->startOfMonth()->format('Y-m-d');  // Start of the current month
        $toDate = $now->format('Y-m-d');  // Current date

        // Sum of received payments from the start of the month to the current
        // date — approved payments only (pending / rejected are not collection).
        $collected_bill = Payment::whereHas('customer')
            ->where('approval_status', 'approved')
            ->whereBetween('recieved_date', [$fromDate, $toDate])
            ->sum('received_amount');

        // Sum of installation fees from the start of the month to the current date
        $installationFee = Customer::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('con_charge');

        // Sum of sales products from the start of the month to the current date
        $salesProduct = Sale::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');

        // Sum of purchase costs from the start of the month to the current date
        $purchaseCost = Purchase::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');

        // Sum of salaries paid from the start of the month to the current date
        $salaryPaid = Payslip::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('payment_amount');

        // Sum of bandwidth bills from the start of the month to the current date
        $Bandwidthbill = Bandwidthbill::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('amount');

        // Calculate total debit (collected_bill + installationFee + salesProduct)
        $debitTotal = $collected_bill + $installationFee + $salesProduct;

        // Calculate total credit (purchaseCost + salaryPaid + Bandwidthbill)
        $creditTotal = $purchaseCost + $salaryPaid + $Bandwidthbill;

        // Calculate cash on hand (debitTotal - creditTotal)
        $cashOnHand = $debitTotal - $creditTotal;

        // Return the result as JSON
        return response()->json([
            'collected_bill' => $collected_bill,
            'installationFee' => $installationFee,
            'salesProduct' => $salesProduct,
            'purchaseCost' => $purchaseCost,
            'salaryPaid' => $salaryPaid,
            'Bandwidthbill' => $Bandwidthbill,
            'cashOnHand' => $cashOnHand,
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
        ], 200);
    }
}
