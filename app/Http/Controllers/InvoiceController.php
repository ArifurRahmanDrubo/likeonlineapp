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
        $pendingPayments = Payment::with('customer')
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

            $result = DB::transaction(function () use ($request, $id, $user) {
                // Lock the payment row so the same payment cannot be approved twice
                $payment = Payment::where('id', $id)
                    ->where('approval_status', 'pending')
                    ->lockForUpdate()
                    ->firstOrFail();
                $customer_id = $payment->customer_id;

                // Lock ALL open invoices for this customer so concurrent
                // approvals never read stale balances (FIFO settlement).
                $invoices = Invoice::where('customer_id', $customer_id)
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($invoices->isEmpty() && !Invoice::where('customer_id', $customer_id)->exists()) {
                    return response()->json(['error' => 'Invoice not found for this customer.'], 404);
                }

                // Total payment being settled: received amount + discount granted.
                // (invoices.amount IS the running due balance in this app — it
                // plays the role of due_amount, so no separate column is needed.)
                $paymentAmount = (float) $payment->received_amount + (float) $payment->discount;

                // FIFO: apply the payment to the oldest open invoices first.
                $lastInvoice = null;
                foreach ($invoices as $invoice) {
                    $due = (float) $invoice->amount;
                    if ($paymentAmount >= $due) {
                        // Payment covers this invoice in full.
                        $paymentAmount -= $due;
                        $invoice->amount = 0;
                        $invoice->status = 'paid';
                    } else {
                        // Partial payment — deduct what remains and mark Partial.
                        $invoice->amount = $due - $paymentAmount;
                        $invoice->status = 'partial';
                        $paymentAmount = 0;
                    }
                    $lastInvoice = $invoice;
                    $invoice->save();

                    if ($paymentAmount <= 0) {
                        break;
                    }
                }

                // Overpayment: leftover beyond all open invoices becomes advance
                // on the most recent invoice (a negative amount reflects credit).
                if ($paymentAmount > 0) {
                    $advanceInvoice = $lastInvoice
                        ?? Invoice::where('customer_id', $customer_id)->latest('id')->first();
                    if ($advanceInvoice) {
                        $advanceInvoice->amount = (float) $advanceInvoice->amount - $paymentAmount;
                        $advanceInvoice->advance = abs($advanceInvoice->amount);
                        $advanceInvoice->status = 'paid';
                        $advanceInvoice->save();
                        $lastInvoice = $advanceInvoice;
                    }
                }

                // Received total + transaction reference live on the last
                // invoice touched (keeps the Received column meaningful).
                if ($lastInvoice) {
                    $lastInvoice->received_amount = (float) $lastInvoice->received_amount
                        + (float) $payment->received_amount;
                    $lastInvoice->transaction_no = $payment->transaction_no;
                    $lastInvoice->notes = $payment->notes;
                    $lastInvoice->save();
                }

                // Remaining due after allocation drives the payment status.
                $remainingDue = Invoice::where('customer_id', $customer_id)
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->sum('amount');

                // ২. পেমেন্ট স্ট্যাটাস Approved করা
                $payment->update([
                    'payment_status' => $remainingDue <= 0 ? 'paid' : 'partial',
                    'approval_status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                // ৩. মেইল পাঠানো (অ্যাপ্রুভ হওয়ার পর)
                $customer = Customer::find($customer_id);
                if ($customer && $customer->email) {
                    Mail::to($customer->email)
                        ->send(new PaymentSuccessMail($payment->total_amount, $payment->transaction_no, $customer->name));
                }

                // Customer due sync: previous_due mirrors the remaining unpaid
                // invoice totals after settlement.
                if ($customer) {
                    $customer->previous_due = $remainingDue;
                    $customer->save();
                }

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

                return response()->json([
                    'message' => 'Payment approved successfully and confirmation email sent!'
                ], 200);
            });

            return $result;

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while approving payment.',
                'message' => $e->getMessage()
            ], 500);
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
                'payment_status' => 'failed',
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

            // পেমেন্টটি যদি অলরেডি Approved হয়ে থাকে, তবেই ইনভয়েস ব্যালেন্স রোলব্যাক করা হবে
            if ($payment->approval_status === 'approved') {
                $invoice = Invoice::where('customer_id', $customer_id)->lockForUpdate()->first();
                if ($invoice) {
                    // 1. Add back the payment to the outstanding balance
                    $invoice->amount += ($payment->received_amount + $payment->discount);

                    // 2. Decrement the received total (never below zero)
                    $invoice->received_amount = max(0, $invoice->received_amount - $payment->received_amount);

                    // 3. Status follows the remaining balance
                    $invoice->status = ($invoice->amount <= 0) ? 'paid' : 'unpaid';

                    // 4. Advance must mirror a negative amount (credit)
                    $invoice->advance = ($invoice->amount < 0) ? abs($invoice->amount) : 0;

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
            $paymentData = Payment::where('customer_id', $customer_id)->get();

            if (!$paymentData) {
                return response()->json(['error' => 'Bill Is Not Generated Yet.'], 404);
            }
            return response()->json($paymentData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}