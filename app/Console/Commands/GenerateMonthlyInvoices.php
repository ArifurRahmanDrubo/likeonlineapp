<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\GeneratedBill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'generate:monthly-invoices';
    protected $description = 'Generate monthly invoices with advance balance adjustment';

    public function handle()
    {
        $currentMonth = Carbon::now()->format('Y-m');
        Log::info("Starting monthly invoice generation for: {$currentMonth}");

        // Process in chunks to prevent server memory crashes
        Customer::where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'left');
        })->chunk(100, function ($customers) use ($currentMonth) {

            foreach ($customers as $customer) {
                try {
                    // 1. Skip inactive customers
                    if ($customer->billingstatus !== 'Active') {
                        continue;
                    }

                    // 2. Safe Date Parsing Check for Billing Start Month
                    if (!empty($customer->billingmonth)) {
                        $convertedDateString = preg_replace('/ \([^)]*\)$/', '', $customer->billingmonth);
                        try {
                            $billingMonth = Carbon::parse($convertedDateString)->format('Y-m');
                            if ($billingMonth > $currentMonth) {
                                continue; // Future billing start month, skip
                            }
                        } catch (\Exception $e) {
                            Log::warning("Invalid billingmonth format for Customer ID {$customer->id}: {$customer->billingmonth}");
                            continue;
                        }
                    }

                    // 3. Double-billing Guard
                    $billedThisMonth = GeneratedBill::where('customer_id', $customer->id)
                        ->where('billing_month', $currentMonth)
                        ->exists();

                    if ($billedThisMonth) {
                        continue;
                    }

                    // 4. Safe Database Operation with Transaction
                    DB::transaction(function () use ($customer, $currentMonth) {
                        // Whole-number bill — round legacy decimal values too.
                        $monthlyBill = round((float) $customer->monthlybill);
                        $existingInvoice = Invoice::where('customer_id', $customer->id)->first();

                        if ($existingInvoice) {
                            $currentAdvance = (float) $existingInvoice->advance;
                            $currentDue = (float) $existingInvoice->due_amount;

                            // Total ledger amount updated
                            $existingInvoice->amount += $monthlyBill;

                            if ($currentAdvance > 0) {
                                if ($currentAdvance >= $monthlyBill) {
                                    // Advance covers the entire monthly bill
                                    $existingInvoice->advance = $currentAdvance - $monthlyBill;
                                    // Due amount remains unchanged (bill is paid from advance)
                                } else {
                                    // Advance partially covers the monthly bill
                                    $remainingBill = $monthlyBill - $currentAdvance;
                                    $existingInvoice->due_amount = $currentDue + $remainingBill;
                                    $existingInvoice->advance = 0; // Advance fully used up
                                }
                            } else {
                                // No advance available, add full bill to due
                                $existingInvoice->due_amount = $currentDue + $monthlyBill;
                            }

                            // Determine invoice payment status
                            $existingInvoice->status = $existingInvoice->due_amount <= 0 ? 'paid' : 'unpaid';
                            $existingInvoice->save();

                        } else {
                            // First invoice for this customer
                            $dueAmount = $monthlyBill;
                            $status = 'unpaid';

                            $invoice = Invoice::create([
                                'customer_id' => $customer->id,
                                'amount'      => $monthlyBill,
                                'due_amount'  => $dueAmount,
                                'status'      => $status,
                                'advance'     => 0,
                            ]);
                        }

                        // Historical snapshot of the bill for monthly ledger
                        GeneratedBill::create([
                            'customer_id'   => $customer->id,
                            'billing_month' => $currentMonth,
                            'amount'        => $monthlyBill,
                            'package'       => $customer->package,
                            'speed'         => $customer->profile,
                            'status'        => ($existingInvoice && $existingInvoice->advance >= $monthlyBill) ? 'paid' : 'unpaid',
                            'generated_at'  => Carbon::now()->toDateString(),
                        ]);
                    });

                    Log::info("Successfully generated invoice and adjusted advance for Customer ID {$customer->id}");

                } catch (\Exception $e) {
                    Log::error("Failed to generate invoice for Customer ID {$customer->id}: " . $e->getMessage());
                }
            }
        });

        $this->info("Monthly invoice generation and advance adjustment completed.");
    }
}