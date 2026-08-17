<?php

// namespace App\Http\Controllers;

// use App\Mail\PaymentSuccessMail;
// use App\Models\CompanyProfile;
// use App\Models\Customer;
// use App\Models\GeneratedBill;
// use App\Models\Invoice;
// use App\Models\Payment;
// use Carbon\Carbon;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Mail;
// use PhpParser\Node\Stmt\TryCatch;

// class InvoiceController extends Controller
// {


//         public function store(Request $request)
//     {
//         // Start the transaction
//         DB::beginTransaction();

//         try {
//             $user = Auth::user();
//             $payableamount = $request->input('payableamount');
//             $clientcode = $request->input('clientcode');
//             $monthlybill = $request->input('monthlybill');
//             $dueamount = $request->input('dueamount');
//             $balancedue = $request->input('balancedue');
//             $recievefrom = $request->input('recievefrom');
//             $discount = $request->input('discount');
//             $transactionno = $request->input('transactionno');
//             $recieveamount = $request->input('recieveamount');
//             $notes = $request->input('notes');
//             $recieveby = $request->input('recieveby');
//             $recievedate = $request->input('recievedate');
//             $paymentmethod = $request->input('paymentmethod');
//             $customer_id = $request->input('Cus_id');
//             $advance = 0;

//             // Determine payment status
//             if ($balancedue < 0) {
//                 $advance = -$balancedue;
//                 $status = 'paid';
//             } elseif ($balancedue == 0) {
//                 $status = 'paid';
//             } else {
//                 $status = 'unpaid';
//             }

//             // Calculate total amount
//             $total_amount = $discount + $recieveamount;
//             $date = Carbon::parse($recievedate);
//             $formattedDate = $date->format('d F Y');

//             // Update invoice
//             $invoice = Invoice::where('customer_id', $customer_id)->first();
//             $invoice->update([
//                 'amount' => $payableamount,
//                 'advance' => $advance,
//                 'status' => $status,
//                 'received_amount' => $recieveamount,
//                 'transaction_no' => $transactionno,
//                 'notes' => $notes,
//             ]);

//             // Create payment record
//             Payment::create([
//                 'customer_id' => $customer_id,
//                 'received_amount' => $recieveamount,
//                 'client_code' => $clientcode,
//                 'recieved_date' => $formattedDate,
//                 'recieved_by' => $recieveby,
//                 'discount' => $discount,
//                 'transaction_no' => $transactionno,
//                 'created_by' => $user->name,
//                 'notes' => $notes,
//                 'payment_info' => $paymentmethod,
//                 'total_amount' => $total_amount,
//             ]);

//             // Fetch customer details
//             $customer = Customer::find($customer_id);
//             if (!$customer || !$customer->email) {
//                 return response()->json([
//                     'error' => 'Customer not found or email is missing.',
//                 ], 404);
//             }

//             // Send payment success email
//             Mail::to($customer->email) // Sending to the customer's email
//                 ->send(new PaymentSuccessMail($total_amount, $transactionno, $customer->name));

//             // Commit the transaction
//             DB::commit();

//             return response()->json([
//                 'message' => 'Payment Successful, email sent!'
//             ]);
//         } catch (\Exception $e) {
//             // Rollback the transaction if an error occurs
//             DB::rollBack();

//             return response()->json([
//                 'error' => 'An error occurred while processing the data.',
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
//     public function index(Request $request)
//     {
//         try {
//             $customer_id = $request->input('id');

//             $customer = Payment::where('customer_id', $customer_id)->latest()->first();

//             if (!$customer) {
//                 return response()->json([
//                     'error' => 'Customer not found.'
//                 ], 404);
//             }
//             return response()->json([
//                 'customer' => $customer
//             ], 200);
//         } catch (\Exception $e) {
//             Log::error($e);
//             return response()->json([
//                 'error' => 'An error occurred while fetching the data.'
//             ], 500);
//         }
//     }
//     public function detailsinvoice(Request $request)
//     {
//         try {
//             $customer_id = $request->input('id');
//             $details = Customer::where('id', $customer_id)->with('invoice')->first();

//             if (!$details) {
//                 return response()->json([
//                     'error' => 'Customer not found.'
//                 ], 404);
//             }
//             return response()->json([
//                 'Details' => $details,

//             ], 200);
//         } catch (\Exception $e) {

//             return response()->json([
//                 'error' => 'An error occurred while fetching the data.'
//             ], 500);
//         }
//     }
//     public function GenerateBillData(Request $request)
//     {
//         try {
//             $customer_id = $request->input('id');
//             $generatedBillDetails = GeneratedBill::where('customer_id', $customer_id)->get();

//             if (!$generatedBillDetails) {
//                 return response()->json([
//                     'error' => 'Bill Is Not Generated Yet.'
//                 ], 404);
//             }
//             return response()->json($generatedBillDetails);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'error' => 'An error occurred while processing the data.',
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }

//     public function paymentData(Request $request)
//     {
//         try {
//             $customer_id = $request->input('id');
//             $paymentData = Payment::where('customer_id', $customer_id)->get();

//             if (!$paymentData) {
//                 return response()->json([
//                     'error' => 'Bill Is Not Generated Yet.'
//                 ], 404);
//             }
//             return response()->json($paymentData);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'error' => 'An error occurred while processing the data.',
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
//     public function deletePaymentData(Request $request)
//     {
//         try {
//             $customer_id = $request->input('customer_id');
//             $id = $request->input('id');
//             $total_amount = $request->input('total_amount');
//             $payment = Payment::where('customer_id', $customer_id)->where('id', $id)->first();

//             $invoice = Invoice::where('customer_id', $customer_id)->first();
//             $amount = $invoice->amount;
//             $updated_amount = $amount + $total_amount;
//             $invoice->update([
//                 'amount' => $updated_amount,
//                 'status' => 'unpaid',
//                 'advance' => 0,

//             ]);


//             $payment->delete();
//             return response()->json([
//                 'message' => 'Payment Delete Successfully'
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'error' => 'An error occurred while processing the data.',
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }
// }


namespace App\Http\Controllers;

use App\Mail\PaymentSuccessMail;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\GeneratedBill;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    // 1. পেমেন্ট এন্ট্রি (শুধুমাত্র পেন্ডিং হিসেবে সেভ হবে - ইনভয়েস বা মেইলে কোনো প্রভাব পড়বে না)
    public function store(Request $request)
    {
        try {
            $clientcode = $request->input('clientcode');
            $discount = $request->input('discount', 0);
            $transactionno = $request->input('transactionno');
            $recieveamount = $request->input('recieveamount');
            $notes = $request->input('notes');
            $recieveby = $request->input('recieveby');
            $recievedate = $request->input('recievedate');
            $paymentmethod = $request->input('paymentmethod');
            $customer_id = $request->input('Cus_id');

            $total_amount = $discount + $recieveamount;
            // recieved_date is a DATE column — always store standard SQL format (Y-m-d),
            // never a human-readable string like '13 August 2026'.
            $date = Carbon::parse($recievedate);
            $formattedDate = $date->format('Y-m-d');

            // Reject duplicate TrxID submissions — the same transaction must
            // never be queued twice (which would double-settle on approval).
            if ($transactionno && Payment::where('transaction_no', $transactionno)->exists()) {
                return response()->json([
                    'error' => 'This transaction ID (TrxID) has already been submitted. Please check and try again.',
                ], 422);
            }

            $payment_id = 'PAY-' . time() . rand(100, 999);

            // পেমেন্ট রেকর্ড শুধুমাত্র Pending অবস্থায় সেভ হবে
            $payment = Payment::create([
                'payment_id' => $payment_id,
                'customer_id' => $customer_id,
                'received_amount' => $recieveamount,
                'client_code' => $clientcode,
                'recieved_date' => $formattedDate,
                'recieved_by' => $recieveby,
                'discount' => $discount,
                'transaction_no' => $transactionno,
                // created_by is an unsignedBigInteger FK to users.id — store the user ID, not the name.
                'created_by' => auth()->id(),
                'notes' => $notes,
                'payment_info' => $paymentmethod,
                'payment_method' => $paymentmethod,
                'total_amount' => $total_amount,
                'payment_status' => 'pending',
                'approval_status' => 'pending', // 🛑 Pending
            ]);

            return response()->json([
                'message' => 'Payment submitted successfully. Awaiting Super Admin approval.',
                'payment' => $payment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 2. পেন্ডিং পেমেন্ট লিস্ট (Super Admin Dashboard-এ দেখানোর জন্য)
public function pendingPayments()
{
    try {
        $pendingPayments = Payment::with(['customer', 'creator:id,name', 'approver:id,name'])
            ->where('approval_status', 'pending')
            ->latest()
            ->get();

        // 🌟 মোট পেন্ডিং টাকা এবং মোট পেন্ডিং কাউন্ট হিসাব
        $totalPendingAmount = $pendingPayments->sum('received_amount');
        $totalPendingCount  = $pendingPayments->count();

        return response()->json([
            'payments'             => $pendingPayments,
            'total_pending_amount' => $totalPendingAmount,
            'total_pending_count'  => $totalPendingCount,
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    // 3. পেমেন্ট অ্যাপ্রুভ করা (এখানে ইনভয়েস আপডেট হবে এবং মেইল যাবে)
    public function approvePayment(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $result = DB::transaction(function () use ($id, $user) {
                // Lock the payment row so the same payment cannot be approved twice
                $payment = Payment::where('id', $id)
                    ->where('approval_status', 'pending')
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->applyApproval($payment, $user);

                return response()->json([
                    'message' => 'Payment approved successfully and confirmation email sent!'
                ], 200);
            });

            return $result;

        } catch (\Exception $e) {
            // Preserve the original 404 behaviour for customers without invoices.
            if ($e->getMessage() === 'Invoice not found for this customer.') {
                return response()->json(['error' => 'Invoice not found for this customer.'], 404);
            }
            return response()->json([
                'error' => 'An error occurred while approving payment.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 3b. বাল্ক পেমেন্ট অ্যাপ্রুভ করা — সবগুলো পেমেন্ট একটি ট্রানজেকশনে FIFO সেটেল হয়
    public function bulkApprove(Request $request)
    {
        try {
            $request->validate([
                'payment_ids' => 'required|array',
                'payment_ids.*' => 'integer',
            ]);

            $user = Auth::user();
            $ids = array_values(array_unique(array_filter((array) $request->input('payment_ids'))));

            if (empty($ids)) {
                return response()->json(['error' => 'No payment IDs provided.'], 422);
            }

            $result = DB::transaction(function () use ($ids, $user) {
                $approved = 0;
                $skipped = 0;

                foreach ($ids as $id) {
                    // Lock each payment row — rows already approved (or that no
                    // longer exist) are skipped without failing the whole batch.
                    $payment = Payment::where('id', $id)
                        ->where('approval_status', 'pending')
                        ->lockForUpdate()
                        ->first();

                    if (!$payment) {
                        $skipped++;
                        continue;
                    }

                    $this->applyApproval($payment, $user);
                    $approved++;
                }

                return ['approved' => $approved, 'skipped' => $skipped];
            });

            $message = $result['approved'] > 0
                ? "{$result['approved']} payment(s) approved successfully."
                : 'No pending payments were approved.';
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} payment(s) were skipped (already approved or not found).";
            }

            return response()->json([
                'message' => $message,
                'approved' => $result['approved'],
                'skipped' => $result['skipped'],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while approving payments.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Shared approval logic: settles a payment against the customer's open
     * invoices (FIFO) inside the caller's DB transaction, marks the payment
     * approved, syncs the customer due, sends the confirmation email and
     * re-enables MikroTik when the account becomes fully paid.
     */
    private function applyApproval(Payment $payment, $user)
    {
        $customer_id = $payment->customer_id;

        // Lock ALL open generated_bills for this customer so concurrent
        // approvals never read stale balances (FIFO settlement). This mirrors
        // the online payment engine (PaymentSettlementService) so manual and
        // gateway payments settle the exact same way.
        $unpaidBills = GeneratedBill::where('customer_id', $customer_id)
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        if ($unpaidBills->isEmpty() && !Invoice::where('customer_id', $customer_id)->exists()) {
            throw new \Exception('Invoice not found for this customer.');
        }

        // Total payment being settled: received amount + discount granted.
        $paymentAmount = (float) $payment->received_amount + (float) $payment->discount;
        $remaining = $paymentAmount;
        $advanceCredit = 0.0;
        // Human-readable settlement log — stored in payments.payment_info.
        $settledLog = [];
        // billing_month of the first bill actually settled / partially settled
        // — stored in payments.billing_month (NULL when only the Previous Due
        // was cleared).
        $settledBillMonth = null;

        $ledger = Invoice::where('customer_id', $customer_id)->lockForUpdate()->first();

        // ---- Step 1: settle the Previous Due first ---------------------------
        // previous_due = ledger due not covered by the itemized open bills.
        $openBillsDue = $unpaidBills->sum(fn ($bill) => (float) $bill->amount - (float) $bill->paid_amount);
        $previousDue = $ledger ? max(0, (float) $ledger->due_amount - $openBillsDue) : 0.0;

        if ($previousDue > 0 && $remaining > 0) {
            $settled = min($previousDue, $remaining);
            $remaining -= $settled;
            $settledLog[] = sprintf('Paid Previous Due: %.2f BDT', $settled);
        }

        // ---- Step 2: FIFO on generated_bills (oldest open bill first) -------
        foreach ($unpaidBills as $bill) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) $bill->amount - (float) $bill->paid_amount;
            $settled = min($due, $remaining);
            $remaining -= $settled;

            if ($settled >= $due) {
                // Payment covers this bill in full.
                $bill->update([
                    'paid_amount' => $bill->amount,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                $settledLog[] = sprintf('Settled Bill %s: %.2f BDT', $bill->billing_month, $settled);
            } else {
                // Partial payment — deduct what remains and keep the bill open.
                $bill->update([
                    'paid_amount' => (float) $bill->paid_amount + $settled,
                    'status' => 'partially_paid',
                ]);
                $settledLog[] = sprintf('Partial Payment for %s: %.2f BDT', $bill->billing_month, $settled);
            }                // payments.billing_month is a nullable TIMESTAMP column, so the
                // bill's "2026-08" label is stored as its first-of-month date.
                if ($settledBillMonth === null && !empty($bill->billing_month)) {
                    try {
                        $settledBillMonth = Carbon::parse($bill->billing_month);
                    } catch (\Throwable $e) {
                        $settledBillMonth = null;
                    }
                }
            }

            // ---- Step 3: leftover payment → advance credit -----------------------
        if ($remaining > 0) {
            $advanceCredit = $remaining;
            $settledLog[] = sprintf('Advance Balance: %.2f BDT', $remaining);
            $remaining = 0;
        }

        // ---- Single ledger update (invoices: due_amount/advance/status) ------
        // `amount` mirrors the running due so every existing reader stays
        // consistent.
        if ($ledger) {
            $settledAgainstDues = $paymentAmount - $advanceCredit;
            $newDue = max(0, (float) $ledger->due_amount - $settledAgainstDues);
            $ledger->update([
                'amount' => $newDue,
                'due_amount' => $newDue,
                'advance' => (float) $ledger->advance + $advanceCredit,
                'status' => $newDue <= 0 ? 'paid' : 'partial',
            ]);
            $remainingDue = $newDue;
        } else {
            // No ledger row yet — remaining due is the open bill total.
            $remainingDue = (float) $unpaidBills->sum(fn ($bill) => (float) $bill->amount - (float) $bill->paid_amount);
        }

        // ২. পেমেন্ট স্ট্যাটাস Approved করা + settlement metadata
        $payment->update([
            'payment_status' => $remainingDue <= 0 ? 'paid' : 'partial',
            'approval_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'payment_info' => implode(' | ', $settledLog) ?: $payment->payment_info,
            'billing_month' => $settledBillMonth,
        ]);

        // ৩. মেইল পাঠানো (অ্যাপ্রুভ হওয়ার পর)
        $customer = Customer::find($customer_id);
        if ($customer && $customer->email) {
            Mail::to($customer->email)
                ->send(new PaymentSuccessMail($payment->total_amount, $payment->transaction_no, $customer->name));
        }

        // The payment workflow touches ONLY the three billing tables
        // (generated_bills / invoices / payments) — the customers record,
        // including expireddate, is intentionally never altered here.

        // ৪. বিল সম্পূর্ণ পরিশোধ হলে Active কাস্টমারের MikroTik সার্ভিস আবার enable
        // (status enum মান ছোট হাতের 'active'; billingstatus 'Active' রাখা হয়)
        if ($customer && $remainingDue <= 0 && $customer->status === 'active') {
            try {
                app(\App\Services\ScheduledChangeService::class)->reEnableMikrotik($customer);
                Invoice::where('customer_id', $customer_id)->update(['pending_mikrotik_sync' => false]);
            } catch (\Exception $e) {
                // MikroTik ব্যর্থ হলেও পেমেন্ট অ্যাপ্রুভাল ট্রানজেকশন ব্যর্থ হবে না —
                // ইনভয়েসটি পেন্ডিং সিঙ্ক হিসাবে চিহ্নিত হয়।
                Log::error("MikroTik re-enable failed for customer {$customer_id} after payment approval: {$e->getMessage()}");
                Invoice::where('customer_id', $customer_id)->update(['pending_mikrotik_sync' => true]);
            }
        }
    }

    // 4. পেমেন্ট রিজেক্ট করা
    public function rejectPayment(Request $request, $id)
    {
        try {
            $request->validate([
                'rejection_reason' => 'required|string'
            ]);

            $payment = Payment::where('id', $id)->where('approval_status', 'pending')->firstOrFail();

            $payment->update([
                'approval_status' => 'rejected',
                'payment_status' => 'unpaid', // 'failed' is not a valid payment_status enum value
                'approved_by' => Auth::id(),
                'rejection_reason' => $request->input('rejection_reason'),
            ]);

            return response()->json([
                'message' => 'Payment rejected successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 5. পেমেন্ট ডিলিট করা
    public function deletePaymentData(Request $request)
    {
        try {
            $customer_id = $request->input('customer_id');
            $id = $request->input('id');

            $result = DB::transaction(function () use ($customer_id, $id) {
                $payment = Payment::where('customer_id', $customer_id)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$payment) {
                    return response()->json(['error' => 'Payment not found.'], 404);
                }

            // পেমেন্টটি যদি অলরেডি Approved হয়ে থাকে, তবেই ইনভয়েস ব্যালেন্স রোলব্যাক করা হবে
            if ($payment->approval_status === 'approved') {
                $invoice = Invoice::where('customer_id', $customer_id)->lockForUpdate()->first();
                if ($invoice) {
                    // 1. Add the payment back to the outstanding balance, consuming
                    //    any advance credit first (existing columns only).
                    $rollback = (float) $payment->received_amount + (float) $payment->discount;
                    $advance = (float) $invoice->advance;
                    $fromAdvance = min($advance, $rollback);

                    $invoice->amount = max(0, (float) $invoice->amount + ($rollback - $fromAdvance));
                    $invoice->due_amount = $invoice->amount;
                    $invoice->advance = $advance - $fromAdvance;

                    // 2. Status follows the remaining balance
                    $invoice->status = ($invoice->due_amount <= 0) ? 'paid' : 'unpaid';

                    $invoice->save();
                }
            }

                $payment->delete();

                return response()->json([
                    'message' => 'Payment Deleted Successfully'
                ]);
            });

            return $result;

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $customer_id = $request->input('id');
            $customer = Payment::where('customer_id', $customer_id)->latest()->first();

            if (!$customer) {
                return response()->json(['error' => 'Customer not found.'], 404);
            }
            return response()->json(['customer' => $customer], 200);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json(['error' => 'An error occurred while fetching the data.'], 500);
        }
    }

    public function detailsinvoice(Request $request)
    {
        try {
            $customer_id = $request->input('id');
            $details = Customer::where('id', $customer_id)->with('invoice')->first();

            if (!$details) {
                return response()->json(['error' => 'Customer not found.'], 404);
            }
            return response()->json(['Details' => $details], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching the data.'], 500);
        }
    }

    public function GenerateBillData(Request $request)
    {
        try {
            $customer_id = $request->input('id');
            $generatedBillDetails = GeneratedBill::where('customer_id', $customer_id)->get();

            if (!$generatedBillDetails) {
                return response()->json(['error' => 'Bill Is Not Generated Yet.'], 404);
            }
            return response()->json($generatedBillDetails);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function paymentData(Request $request)
    {
        try {
            $customer_id = $request->input('id');
            $paymentData = Payment::with(['creator:id,name', 'approver:id,name'])
                ->where('customer_id', $customer_id)
                ->get();

            if (!$paymentData) {
                return response()->json(['error' => 'Bill Is Not Generated Yet.'], 404);
            }
            return response()->json($paymentData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}