<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\GeneratedBill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'generate:monthly-invoices';
    protected $description = 'Generate monthly invoices for all customers';

    public function handle()
    {
        try {

            // Never generate invoices for Left clients. NULL-safe: customers
            // that predate the status column (status IS NULL) must still be
            // billed, only status = 'left' is excluded.
            $customers = Customer::where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'left');
            })->get();
            $currentMonth = Carbon::now()->format('Y-m'); // Current month in 'YYYY-MM' format

            foreach ($customers as $customer) {
                if ($customer->billingstatus !== 'Active') {
                    Log::info("Skipping inactive customer ID {$customer->id}");
                    continue;
                }

                $dateString = $customer->billingmonth;
                $convertedDateString = preg_replace('/ \([^)]*\)$/', '', $dateString);
                $billingMonth = Carbon::parse($convertedDateString)->format('Y-m');

                // Only customers whose billing month has started are billed
                if ($billingMonth > $currentMonth) {
                    Log::info("Skipping customer ID {$customer->id} as billing month {$billingMonth} is not less than or equal to current month {$currentMonth}");
                    continue;
                }

                $existingInvoice = Invoice::where('customer_id', $customer->id)->first();

                if ($existingInvoice) {
                    // Guard against double-billing when the command runs twice
                    // in the same month.
                    $existingInvoiceForMonth = Invoice::where('customer_id', $customer->id)
                        ->where('billing_month', $currentMonth)
                        ->first();
                    if ($existingInvoiceForMonth) {
                        Log::info("Invoice already exists for customer ID {$customer->id} for month {$currentMonth}");
                        continue;
                    }

                    // Add this month's bill to the outstanding balance.
                    // previous_due is intentionally NOT added here — it is a
                    // permanent reference on the customer record only.
                    $existingInvoice->amount += $customer->monthlybill;

                    // Apply advance balance. A negative amount already reflects
                    // the customer's credit, so it carries forward as advance;
                    // otherwise no advance remains after the new bill.
                    if ($existingInvoice->amount < 0) {
                        $existingInvoice->advance = abs($existingInvoice->amount);
                    } else {
                        $existingInvoice->advance = 0;
                    }

                    $existingInvoice->status = $existingInvoice->amount <= 0 ? 'paid' : 'unpaid';
                    $existingInvoice->billing_month = $currentMonth;
                    $existingInvoice->save();
                    Log::info("Invoice updated successfully for customer ID {$customer->id} for month {$currentMonth}");
                } else {
                    // First invoice for this customer — the full monthly bill.
                    Log::info("Creating new invoice for customer ID {$customer->id} for month {$currentMonth}");

                    $invoice = Invoice::create([
                        'customer_id' => $customer->id,
                        'amount' => $customer->monthlybill,
                        'status' => 'unpaid',
                        'billing_month' => $currentMonth,
                        'advance' => 0,
                    ]);
                    if ($invoice) {
                        Log::info("Invoice created successfully for customer ID {$customer->id} for month {$currentMonth}");
                    } else {
                        Log::warning("Failed to create invoice for customer ID {$customer->id} for month {$currentMonth}");
                    }
                }

                // Historical snapshot of the bill for reporting
                GeneratedBill::create([
                    'customer_id' => $customer->id,
                    'billing_month' => $currentMonth,
                    'amount' => $customer->monthlybill,
                    'package' => $customer->package,
                    'speed' => $customer->profile,
                    'status' => 'unpaid',
                    'generated_at' => Carbon::now()->format('d F Y'),
                ]);
            }

            $this->info('Monthly invoices generated successfully.');
            Log::info('Monthly invoices generated successfully for ' . $currentMonth);
        } catch (\Exception $e) {
            $this->error('Error generating monthly invoices: ' . $e->getMessage());
            Log::error('Error generating monthly invoices: ' . $e->getMessage());
        }
    }
}
