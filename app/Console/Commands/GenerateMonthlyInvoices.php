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


            $customers = Customer::all();
            $currentMonth = Carbon::now()->format('Y-m'); // Current month in 'YYYY-MM' format

            foreach ($customers as $customer) {
                if ($customer->billingstatus === 'Active') {
                    $dateString = $customer->billingmonth;
                    $convertedDateString = preg_replace('/ \([^)]*\)$/', '', $dateString);
                    $billingMonth = Carbon::parse($convertedDateString)->format('Y-m');

                    // Check if the billing month is less than or equal to the current month
                    if ($billingMonth <= $currentMonth) {
                        // Check if an invoice for the current month already exists

                        $existingInvoice = Invoice::where('customer_id', $customer->id)->first();
                        if ($existingInvoice) {
                            $existingInvoiceForMonth = Invoice::where('customer_id', $customer->id)
                                ->where('billing_month', $currentMonth)
                                ->first();
                            if ($existingInvoiceForMonth) {
                                Log::info("Invoice already exists for customer ID {$customer->id} for month {$currentMonth}");
                            } else {
                                $unpaidAmount = Invoice::where('customer_id', $customer->id)
                                    ->where('status', 'unpaid')
                                    ->sum('amount');
                                $totalAmount = $customer->monthlybill + $unpaidAmount;
                                $generatedAt = Carbon::now()->format('d F Y');

                                $updated = Invoice::where('customer_id', $customer->id)
                                    ->update([
                                        'amount' => $totalAmount,
                                        'status' => 'unpaid',
                                        'billing_month' => $currentMonth,
                                        'advance' => 0
                                    ]);
                                if ($updated) {
                                    Log::info("Invoice updated successfully for customer ID {$customer->id} for month {$currentMonth}");
                                } else {
                                    Log::warning("Failed to update invoice for customer ID {$customer->id} for month {$currentMonth}");
                                }
                                GeneratedBill::create([
                                    'customer_id' => $customer->id,
                                    'billing_month' => $currentMonth,
                                    'amount' => $customer->monthlybill,
                                    'package' => $customer->package,
                                    'speed' => $customer->profile,
                                    'generated_at' =>  $generatedAt // Store the current timestamp
                                ]);
                            }
                            // $advance = Invoice::where('customer_id', $customer->id)
                            //     ->sum('advance');
                            // $totalAmount -= $advance;
                        } else {
                            Log::info("Creating new invoice for customer ID {$customer->id} for month {$currentMonth}");

                            $totalAmount = $customer->monthlybill;

                            $generatedAt = Carbon::now()->format('d F Y');


                            $invoice = Invoice::create([
                                'customer_id' => $customer->id,
                                'amount' => $totalAmount,
                                'status' => 'unpaid',
                                'billing_month' => $currentMonth,
                                'advance' => 0 // Initially set to 0, will be updated after payment
                            ]);
                            if ($invoice) {
                                Log::info("Invoice created successfully for customer ID {$customer->id} for month {$currentMonth}");
                            } else {
                                Log::warning("Failed to create invoice for customer ID {$customer->id} for month {$currentMonth}");
                            }

                            GeneratedBill::create([
                                'customer_id' => $customer->id,
                                'billing_month' => $currentMonth,
                                'amount' => $customer->monthlybill,
                                'package' => $customer->package,
                                'speed' => $customer->profile,
                                'generated_at' => $generatedAt  // Store the current timestamp
                            ]);
                        }
                    } else {
                        Log::info("Skipping customer ID {$customer->id} as billing month {$billingMonth} is not less than or equal to current month {$currentMonth}");
                    }
                } else {
                    Log::info("Skipping inactive customer ID {$customer->id}");
                }
            }

            $this->info('Monthly invoices generated successfully.');
            Log::info('Monthly invoices generated successfully for ' . $currentMonth);
        } catch (\Exception $e) {
            $this->error('Error generating monthly invoices: ' . $e->getMessage());
            Log::error('Error generating monthly invoices: ' . $e->getMessage());
        }
    }
}
