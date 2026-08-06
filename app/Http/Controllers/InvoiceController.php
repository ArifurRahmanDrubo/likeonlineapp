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
            $user = Auth::user();
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
            $date = Carbon::parse($recievedate);
            $formattedDate = $date->format('d F Y');

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
                'created_by' => $user->name,
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
        DB::beginTransaction();

        try {
            $user = Auth::user();

            $payment = Payment::where('id', $id)->where('approval_status', 'pending')->firstOrFail();
            $customer_id = $payment->customer_id;

            $invoice = Invoice::where('customer_id', $customer_id)->first();
            if (!$invoice) {
                return response()->json(['error' => 'Invoice not found for this customer.'], 404);
            }

            // ডিউ ও অ্যাডভান্স হিসাব
            $payableamount = $invoice->amount;
            $recieveamount = $payment->received_amount;
            $balancedue = $payableamount - ($recieveamount + $payment->discount);

            $advance = 0;
            if ($balancedue < 0) {
                $advance = -$balancedue;
                $status = 'paid';
            } elseif ($balancedue == 0) {
                $status = 'paid';
            } else {
                $status = 'unpaid';
            }

            // ১. ইনভয়েস আপডেট
            $invoice->update([
                'advance' => $advance,
                'status' => $status,
                'received_amount' => $invoice->received_amount + $recieveamount,
                'transaction_no' => $payment->transaction_no,
                'notes' => $payment->notes,
            ]);

            // ২. পেমেন্ট স্ট্যাটাস Approved করা
            $payment->update([
                'payment_status' => ($status === 'paid') ? 'paid' : 'partial',
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // ৩. মেইল পাঠানো (অ্যাপ্রুভ হওয়ার পর)
            $customer = Customer::find($customer_id);
            if ($customer && $customer->email) {
                Mail::to($customer->email)
                    ->send(new PaymentSuccessMail($payment->total_amount, $payment->transaction_no, $customer->name));
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment approved successfully and confirmation email sent!'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
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

            $payment = Payment::where('customer_id', $customer_id)->where('id', $id)->first();

            if (!$payment) {
                return response()->json(['error' => 'Payment not found.'], 404);
            }

            // পেমেন্টটি যদি অলরেডি Approved হয়ে থাকে, তবেই ইনভয়েস ব্যালেন্স রোলব্যাক করা হবে
            if ($payment->approval_status === 'approved') {
                $invoice = Invoice::where('customer_id', $customer_id)->first();
                if ($invoice) {
                    $invoice->update([
                        'amount' => $invoice->amount + $payment->total_amount,
                        'status' => 'unpaid',
                        'advance' => 0,
                    ]);
                }
            }

            $payment->delete();

            return response()->json([
                'message' => 'Payment Deleted Successfully'
            ]);
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