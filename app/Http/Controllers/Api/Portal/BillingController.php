<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\GeneratedBill;
use App\Models\Invoice;
use App\Models\InvoiceSetup;
use App\Models\Payment;
use App\Models\SystemPermission;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    /**
     * The authenticated portal user's Customer record.
     *
     * Strict IDOR protection: the customer is ALWAYS derived from the session
     * user (Auth::user()->customer) — never from request input — so a client
     * can only ever see or pay their own bills.
     */
    protected function customer(Request $request): Customer
    {
        $customer = $request->user()->customer;
        if (!$customer) {
            abort(403, 'No ISP subscription is linked to this account.');
        }
        return $customer;
    }

    /**
     * Whether the company name/contact block should appear on generated
     * invoice PDFs, driven by system_permissions.company_name_invoice.
     *
     * Defaults to ENABLED when no settings row exists (matches the admin UI
     * note: "If you don't set Invoice Setting then Company Name will be enable
     * by default in Invoice").
     */
    protected function companyNameEnabled(): bool
    {
        $settings = SystemPermission::first();
        return $settings ? $settings->isEnabled('company_name_invoice', true) : true;
    }

    /**
     * GET /api/portal/invoices
     *
     * Bill list for the authenticated customer. The overall due comes from
     * the single Customer Account Ledger row in `invoices` (due_amount); the
     * per-month detail comes from `generated_bills`. Any ledger balance not
     * covered by the itemized monthly bills — an opening / previous due — is
     * surfaced as a virtual "PREV-DUE" line at the top of the list.
     */
    public function invoices(Request $request)
    {
        try {
            $customer = $this->customer($request);

            // 1. Fetch Single Ledger Invoice — the running overall due.
            $ledger = Invoice::where('customer_id', $customer->id)->first();
            $totalLedgerDue = $ledger ? (float) $ledger->due_amount : 0.00;

            // 2. Fetch Itemized Generated Bills.
            $bills = GeneratedBill::where('customer_id', $customer->id)
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($bill) {
                    return [
                        'id' => $bill->id,
                        'bill_no' => 'BILL-' . $bill->id,
                        'billing_month' => $bill->billing_month,
                        'amount' => (float) $bill->amount,
                        'paid_amount' => (float) $bill->paid_amount,
                        'due_amount' => max(0, (float) $bill->amount - (float) $bill->paid_amount),
                        'status' => $bill->status,
                        'created_at' => $bill->created_at ? $bill->created_at->format('Y-m-d') : null,
                        'type' => 'monthly_bill',
                    ];
                })
                ->toArray();

            // 3. Calculate sum of unpaid generated bills.
            $generatedBillsDueSum = array_reduce($bills, function ($carry, $item) {
                return $carry + $item['due_amount'];
            }, 0);

            // 4. Opening Balance / Previous Due in the ledger not covered by
            //    the itemized generated bills.
            $previousDue = max(0, $totalLedgerDue - $generatedBillsDueSum);

            if ($previousDue > 0) {
                // Prepend Previous Due as a virtual item in the bill list.
                array_unshift($bills, [
                    'id' => 'prev-due',
                    'bill_no' => 'PREV-DUE',
                    'billing_month' => 'Previous Balance',
                    'amount' => $previousDue,
                    'paid_amount' => 0.00,
                    'due_amount' => $previousDue,
                    'status' => 'unpaid',
                    'created_at' => $customer->created_at ? $customer->created_at->format('Y-m-d') : null,
                    'type' => 'previous_due',
                ]);
            }

            $payments = Payment::where('customer_id', $customer->id)
                ->with(['creator:id,name', 'approver:id,name'])
                ->orderByDesc('recieved_date')
                ->get();

            return response()->json([
                'status' => 'success',
                'due_total' => round($totalLedgerDue, 2), // Overall due from Ledger
                'invoices' => $bills,                     // Monthly bills + Previous Due
                'payments' => $payments,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Portal invoices failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to load invoices.'], 500);
        }
    }

    /**
     * GET /api/portal/invoices/{id}/pdf
     *
     * Dynamically streams a branded PDF invoice (mPDF) for one of the
     * customer's invoices — scoped by the authenticated customer, so a
     * customer can never fetch another subscriber's invoice.
     */
    public function downloadPdf(Request $request, $id)
    {
        try {
            $customer = $this->customer($request);

            // The portal bill list now comes from generated_bills, so resolve
            // the record there first; fall back to the legacy invoices table.
            $bill = GeneratedBill::where('customer_id', $customer->id)->find((int) $id);

            if ($bill) {
                // Shape the bill into the fields the PDF view reads.
                $invoice = (object) [
                    'id' => $bill->id,
                    'billing_month' => $bill->billing_month,
                    'amount' => $bill->amount,
                    'discount' => 0,
                    'received_amount' => $bill->paid_amount,
                    'advance' => 0,
                    'status' => $bill->status,
                ];
            } else {
                // Legacy invoice ID fallback — shape the ledger row into the
                // exact fields the PDF view reads, using existing columns only.
                $legacy = Invoice::with(['payments' => function ($q) {
                    $q->orderByDesc('recieved_date');
                }])->where('customer_id', $customer->id)
                    ->findOrFail((int) $id);

                $latestBill = GeneratedBill::where('customer_id', $customer->id)->orderByDesc('id')->first();
                $invoice = (object) [
                    'id' => $legacy->id,
                    'billing_month' => $latestBill?->billing_month ?? '—',
                    'amount' => $legacy->amount,
                    'discount' => $legacy->discount,
                    'received_amount' => (float) $legacy->payments->sum('received_amount'),
                    'advance' => $legacy->advance,
                    'status' => $legacy->status,
                ];
            }

            $company = CompanyProfile::first();
            $setup = InvoiceSetup::first();

            $html = view('pdf.invoice', [
                'invoice' => $invoice,
                'customer' => $customer,
                'company' => $company,
                'setup' => $setup,
                'show_company' => $this->companyNameEnabled(),
            ])->render();

            $filename = 'Invoice-' . str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT) . '.pdf';

            return $this->streamPdf($html, $filename);
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to generate invoice PDF. Details logged.'], 500);
        }
    }

    /**
     * POST /api/portal/payments/submit
     *
     * Submits a payment for the customer's bill. It lands in the existing
     * pending-approval queue (payments.approval_status = 'pending') and is
     * settled by the super admin via the existing /payment/approve flow.
     */
    public function submitPayment(Request $request)
    {
        try {
            $request->validate([
                'recieveamount' => 'required|numeric|min:0.01',
                'discount' => 'nullable|numeric|min:0',
                'transactionno' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
                'paymentmethod' => 'nullable|string|max:255',
                'recievedate' => 'nullable|date',
            ]);

            $customer = $this->customer($request);
            $recieveamount = (float) $request->input('recieveamount');
            $discount = (float) $request->input('discount', 0);

            // Reject duplicate TrxID submissions — the same transaction must
            // never be queued twice (which would double-settle on approval).
            if ($request->filled('transactionno')
                && Payment::where('transaction_no', $request->input('transactionno'))->exists()) {
                return response()->json([
                    'message' => 'This transaction ID (TrxID) has already been submitted. Please check and try again.',
                ], 422);
            }

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'received_amount' => $recieveamount,
                'client_code' => $customer->username,
                'recieved_date' => $request->filled('recievedate')
                    ? Carbon::parse($request->input('recievedate'))->format('Y-m-d')
                    : now()->format('Y-m-d'),
                'recieved_by' => $customer->name,
                'discount' => $discount,
                'transaction_no' => $request->input('transactionno'),
                'created_by' => Auth::id(),
                'notes' => $request->input('notes'),
                'payment_info' => $request->input('paymentmethod'),
                'payment_method' => $request->input('paymentmethod'),
                'total_amount' => $recieveamount + $discount,
                'payment_status' => 'pending',
                'approval_status' => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment submitted successfully. Awaiting Super Admin approval.',
                'payment' => $payment,
            ], 201);
        } catch (Exception $e) {
            Log::error("Portal payment submission failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to submit payment.'], 500);
        }
    }

    /**
     * Shared mPDF streaming helper for every portal PDF endpoint.
     */
    protected function streamPdf(string $html, string $filename)
    {
        $tempDir = storage_path('app/pdf-tmp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetTitle(pathinfo($filename, PATHINFO_FILENAME));
        $mpdf->WriteHTML($html);

        return response(
            $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * GET /api/portal/payments/{id}/pdf
     *
     * Streams a payment receipt PDF for one transaction row in `payments`,
     * scoped to the authenticated customer (IDOR-safe).
     */
    public function paymentPdf(Request $request, $id)
    {
        try {
            $customer = $this->customer($request);

            $payment = Payment::with(['creator:id,name', 'approver:id,name'])
                ->where('customer_id', $customer->id)
                ->findOrFail((int) $id);

            $company = CompanyProfile::first();
            $setup = InvoiceSetup::first();

            $html = view('pdf.payment-receipt', [
                'payment' => $payment,
                'customer' => $customer,
                'company' => $company,
                'setup' => $setup,
                'show_company' => $this->companyNameEnabled(),
            ])->render();

            $filename = 'Payment-Receipt-' . ($payment->payment_id ?? 'PAY-' . $payment->id) . '.pdf';

            return $this->streamPdf($html, $filename);
        } catch (\Exception $e) {
            Log::error("Payment receipt PDF failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to generate payment receipt PDF.'], 500);
        }
    }

    /**
     * GET /api/portal/reports/all-bills-pdf
     *
     * Consolidated PDF statement of ALL generated_bills for the customer. When
     * the ledger has a Previous Due not covered by the itemized bills, it is
     * included as the first itemized row.
     */
    public function allBillsPdf(Request $request)
    {
        try {
            $customer = $this->customer($request);

            $ledger = Invoice::where('customer_id', $customer->id)->first();
            $dueTotal = $ledger ? (float) $ledger->due_amount : 0.00;

            $bills = GeneratedBill::where('customer_id', $customer->id)
                ->orderBy('id', 'asc')
                ->get();

            $openBillsDue = $bills
                ->filter(fn ($bill) => in_array($bill->status, ['unpaid', 'partially_paid']))
                ->sum(fn ($bill) => (float) $bill->amount - (float) $bill->paid_amount);

            $previousDue = max(0, $dueTotal - $openBillsDue);

            $rows = [];
            if ($previousDue > 0) {
                $rows[] = [
                    'type' => 'previous_due',
                    'billing_month' => 'Previous Balance',
                    'amount' => $previousDue,
                    'paid_amount' => 0.00,
                    'due_amount' => $previousDue,
                    'status' => 'unpaid',
                ];
            }

            foreach ($bills as $bill) {
                $rows[] = [
                    'type' => 'monthly_bill',
                    'billing_month' => $bill->billing_month,
                    'amount' => (float) $bill->amount,
                    'paid_amount' => (float) $bill->paid_amount,
                    'due_amount' => max(0, (float) $bill->amount - (float) $bill->paid_amount),
                    'status' => $bill->status,
                ];
            }

            $company = CompanyProfile::first();
            $setup = InvoiceSetup::first();

            $html = view('pdf.bill-statement', [
                'customer' => $customer,
                'company' => $company,
                'setup' => $setup,
                'rows' => $rows,
                'due_total' => $dueTotal,
                'show_company' => $this->companyNameEnabled(),
            ])->render();

            $filename = 'Bill-Statement-' . $customer->username . '.pdf';

            return $this->streamPdf($html, $filename);
        } catch (\Exception $e) {
            Log::error("All-bills PDF failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to generate the bill statement PDF.'], 500);
        }
    }

    /**
     * GET /api/portal/reports/all-payments-pdf
     *
     * Complete PDF ledger of ALL approved transactions from `payments`.
     */
    public function allPaymentsPdf(Request $request)
    {
        try {
            $customer = $this->customer($request);

            $payments = Payment::with(['creator:id,name', 'approver:id,name'])
                ->where('customer_id', $customer->id)
                ->where('approval_status', 'approved')
                ->orderByDesc('recieved_date')
                ->get();

            $company = CompanyProfile::first();
            $setup = InvoiceSetup::first();

            $html = view('pdf.payment-ledger', [
                'customer' => $customer,
                'company' => $company,
                'setup' => $setup,
                'payments' => $payments,
                'show_company' => $this->companyNameEnabled(),
            ])->render();

            $filename = 'Payment-Ledger-' . $customer->username . '.pdf';

            return $this->streamPdf($html, $filename);
        } catch (\Exception $e) {
            Log::error("All-payments PDF failed: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to generate the payment ledger PDF.'], 500);
        }
    }
}
