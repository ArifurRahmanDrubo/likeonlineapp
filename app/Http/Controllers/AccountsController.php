<?php

namespace App\Http\Controllers;

use App\Models\Bandwidthbill;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AccountsController extends Controller
{
    /**
     * GET /api/getAccountsData
     *
     * Complete accounting overview sourced directly from the database:
     *
     * - Current month summary (debit / credit / cash on hand)
     * - All-time totals (collected, due, advance, pending)
     * - Customer health counts
     * - Recent approved transactions for the activity feed
     */
    public function accountsData()
    {
        // Current month range
        $now = Carbon::now();
        $fromDate = $now->copy()->startOfMonth()->format('Y-m-d');
        $toDate = $now->copy()->format('Y-m-d');
        $monthLabel = $now->format('F Y');

        // -------------------------------------------------------------------
        // 1. Current month summary
        // -------------------------------------------------------------------

        // Approved bill collections from the start of the month to today
        $collected_bill = (float) Payment::whereHas('customer')
            ->where('approval_status', 'approved')
            ->whereBetween('recieved_date', [$fromDate, $toDate])
            ->sum('received_amount');

        // Installation / connection charges from customers created this month
        $installationFee = (float) Customer::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('con_charge');

        // Product sales this month
        $salesProduct = (float) Sale::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');

        // Equipment / stock purchases this month
        $purchaseCost = (float) Purchase::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');

        // Salaries paid this month
        $salaryPaid = (float) Payslip::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('payment_amount');

        // Bandwidth bills this month
        $Bandwidthbill = (float) Bandwidthbill::whereBetween('created_at', [$fromDate, $toDate])
            ->sum('amount');

        $debitTotal = $collected_bill + $installationFee + $salesProduct;
        $creditTotal = $purchaseCost + $salaryPaid + $Bandwidthbill;
        $cashOnHand = $debitTotal - $creditTotal;

        // -------------------------------------------------------------------
        // 2. All-time totals
        // -------------------------------------------------------------------
        $totalCollected = (float) Payment::where('approval_status', 'approved')
            ->sum('received_amount');

        $totalDue = (float) Invoice::whereIn('status', ['unpaid', 'partial'])
            ->sum('due_amount');

        $totalAdvance = (float) Invoice::sum('advance');

        $pendingPayments = (float) Payment::where('approval_status', 'pending')
            ->sum('received_amount');

        // -------------------------------------------------------------------
        // 3. Customer health counts
        // -------------------------------------------------------------------
        $totalCustomers = Customer::count();

        $activeCustomers = Customer::where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'left');
        })->where('billingstatus', '!=', 'Left')->count();

        $paidCustomers = Customer::whereHas('invoice', function ($q) {
            $q->where('status', 'paid');
        })->count();

        $unpaidCustomers = Customer::whereHas('invoice', function ($q) {
            $q->whereIn('status', ['unpaid', 'partial']);
        })->count();

        // -------------------------------------------------------------------
        // 4. Recent approved transactions (activity feed)
        // -------------------------------------------------------------------
        $recentPayments = Payment::with('customer:id,name,username,monthlybill')
            ->where('approval_status', 'approved')
            ->orderByDesc('recieved_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'customer' => $p->customer?->name ?? 'N/A',
                    'username' => $p->customer?->username ?? '',
                    'recieved_date' => $p->recieved_date,
                    'payment_method' => $p->payment_method ?? 'N/A',
                    'billing_month' => $p->billing_month ?? null,
                    'transaction_no' => $p->transaction_no ?? null,
                    'received_amount' => (float) $p->received_amount,
                    'discount' => (float) ($p->discount ?? 0),
                ];
            });

        return response()->json([
            'month_label' => $monthLabel,
            // monthly summary
            'collected_bill' => $collected_bill,
            'installationFee' => $installationFee,
            'salesProduct' => $salesProduct,
            'purchaseCost' => $purchaseCost,
            'salaryPaid' => $salaryPaid,
            'Bandwidthbill' => $Bandwidthbill,
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            'cashOnHand' => $cashOnHand,
            // all-time totals
            'totalCollected' => $totalCollected,
            'totalDue' => $totalDue,
            'totalAdvance' => $totalAdvance,
            'pendingPayments' => $pendingPayments,
            // customer health
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'paidCustomers' => $paidCustomers,
            'unpaidCustomers' => $unpaidCustomers,
            // activity feed
            'recentPayments' => $recentPayments,
        ], 200);
    }
}
