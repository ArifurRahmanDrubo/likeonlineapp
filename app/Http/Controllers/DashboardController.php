<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\Customer;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Models\Bandwidthbill;
use App\Models\GeneratedBill;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getActiveClientsByYear($year)
    {
        // Get all customers created within the year
        $customers = Customer::select('created_at', 'billingstatus', 'id')
            ->whereYear('created_at', '<=', $year)
            ->get();

        // Initialize an array to track the count of active clients for each month
        $monthlyClients = array_fill(1, 12, 0);

        // Initialize an array to track active clients IDs
        $activeClients = [];

        // Iterate through each month
        for ($month = 1; $month <= 12; $month++) {
            // Filter clients that are active as of the end of the current month
            foreach ($customers as $customer) {
                $createdAt = new \DateTime($customer->created_at);
                $createdMonth = (int)$createdAt->format('m');
                $createdYear = (int)$createdAt->format('Y');

                if ($createdYear < $year || ($createdYear == $year && $createdMonth <= $month)) {
                    if ($customer->billingstatus == 'Active') {
                        $activeClients[$customer->id] = true;
                    } else {
                        unset($activeClients[$customer->id]);
                    }
                }
            }

            // Count active clients for the current month
            $monthlyClients[$month] = count($activeClients);
        }

        return response()->json($monthlyClients);
    }
    public function getMonthlyNewClients()
    {
        $currentYear = Carbon::now()->year;

        // Initialize an array with 12 months set to 0
        $monthlyClients = array_fill(1, 12, 0);

        // Retrieve clients created in the current year, grouped by month
        $clients = Customer::whereYear('created_at', $currentYear)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->get();

        // Populate the array with the actual data
        foreach ($clients as $client) {
            $monthlyClients[$client->month] = $client->count;
        }

        return response()->json($monthlyClients,);
    }
    public function dashboardData()
    {
        $TotalClient = Customer::count();
        $InactiveClient = Customer::where('mikrotikStatus', false)->count();
        $OnlineClient = Customer::where('mikrotikStatus', true)->count();

        $RunningClient = Customer::where('billingstatus', '!=', 'Left')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'left');
            })
            ->count();
        $LeftClient = Customer::where(function ($q) {
            $q->where('status', 'left')->orWhere('billingstatus', 'Left');
        })->count();
        $FreeClient = Customer::where('billingstatus', '=', 'Free')->count();
        // Get the first and current date of the current month
        $startOfMonth = Carbon::now()->startOfMonth();
        $currentDate = Carbon::now();
        $currentMonth = Carbon::now()->format('Y-m');

        // Count paid clients (customers with 'paid' invoices for the current month)
        $paidClient = Customer::whereHas('invoice', function ($query) use ($startOfMonth, $currentDate) {
            $query->where('status', '=', 'paid')
                ->whereBetween('created_at', [$startOfMonth, $currentDate]); // Filter by current month
        })->count();

        // Count unpaid clients (customers with 'unpaid' or 'partial' invoices for the current month)
        $unpaidClient = Customer::whereHas('invoice', function ($query) use ($startOfMonth, $currentDate) {
            $query->whereIn('status', ['unpaid', 'partial'])
                ->whereBetween('created_at', [$startOfMonth, $currentDate]); // Filter by current month
        })->count();

        // Total received amount for invoices created in the current month
        $received_amount = Invoice::whereHas('customer')
            ->whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('received_amount');

        // Total outstanding due — all unpaid/partial invoices, no date filters
        $due_amount = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->sum('amount');

        // Total advance payments for invoices created in the current month
        $advance_amount = Invoice::whereHas('customer')
            ->whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('advance');

        // Total discount applied to invoices in the current month
        $discount_amount = Invoice::whereHas('customer')
            ->whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('discount');

        // Total monthly generated bill — snapshot rows for the current billing month
        $generated_bill = GeneratedBill::where('billing_month', $currentMonth)
            ->sum('amount');

        // Total salary paid in the current month
        $paid_salary = Payslip::whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('payment_amount');

        // Total bandwidth bills in the current month
        $Bandwidthbill = Bandwidthbill::whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('amount');

        // Total purchase cost for the current month
        $purchaseCost = Purchase::whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('total');

        // Total sales product amount in the current month
        $salesProduct = Sale::whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('total');

        // Total collected bill amount in the current month
        $collected_bill = Payment::whereBetween('recieved_date', [$startOfMonth, $currentDate])
            ->sum('received_amount');

        // Total installation fee amount in the current month
        $installationFee = Customer::whereBetween('created_at', [$startOfMonth, $currentDate])
            ->sum('con_charge');

        // Calculate total debit (collected_bill + installationFee + salesProduct)
        $debitTotal = $collected_bill + $installationFee + $salesProduct;

        // Calculate total credit (purchaseCost + salaryPaid + Bandwidthbill)
        $creditTotal = $purchaseCost + $paid_salary + $Bandwidthbill;

        // Calculate cash on hand (debitTotal - creditTotal)
        $cashOnHand = $debitTotal - $creditTotal;

        // Count new clients registered in the current month
        $newClientsCount = Customer::whereBetween('created_at', [$startOfMonth, $currentDate])
            ->count();


        // Return all data as JSON
        return response()->json([
            'RunningClient' => $RunningClient,
            'newClientsCount' => $newClientsCount,
            'FreeClient' => $FreeClient,
            'TotalClient' => $TotalClient,
            'InactiveClient' => $InactiveClient,
            'OnlineClient' => $OnlineClient,
            'LeftClient' => $LeftClient,
            'paidClient' => $paidClient,
            'unpaidClient' => $unpaidClient,
            'received_amount' => $received_amount,
            'due_amount' => $due_amount,
            'advance_amount' => $advance_amount,
            'discount_amount' => $discount_amount,
            'generated_bill' => $generated_bill,
            'paid_salary' => $paid_salary,
            'Bandwidthbill' => $Bandwidthbill,
            'collected_bill' => $collected_bill,
            'installationFee' => $installationFee,
            'salesProduct' => $salesProduct,
            'purchaseCost' => $purchaseCost,
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            'cashOnHand' => $cashOnHand, // Cash on hand (debitTotal - creditTotal)
        ], 200);
    }
}
