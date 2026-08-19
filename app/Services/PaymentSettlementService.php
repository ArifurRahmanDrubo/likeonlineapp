<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GeneratedBill;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Sms\SmsAutomation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-settlement engine for online (bKash / Nagad) payments.
 *
 * Mirrors the admin approval settlement (see InvoiceController::applyApproval)
 * but is fully automatic: it is triggered from the payment gateway callbacks,
 * records an already-approved Payment, settles open generated_bills FIFO,
 * syncs the single invoices ledger and re-enables the MikroTik service when
 * the account becomes fully paid. Subscription expiry is never touched.
 */
class PaymentSettlementService
{
    /**
     * Settle an online payment with Previous Due priority:
     *   1. Previous Due (ledger balance not covered by open generated_bills),
     *   2. open generated_bills FIFO (oldest id first),
     *   3. leftover → invoices.advance.
     * and update the single Customer Account Ledger row in `invoices`.
     *
     * `generated_bills` is the bill tracking table (per-month unpaid /
     * partially_paid / paid); `invoices` is treated strictly as the customer's
     * account ledger (amount / due_amount / advance / status). The payment's
     * `payment_info` / `billing_month` columns are populated with a
     * human-readable settlement log and the settled bill's month.
     *
     * Runs inside a single DB transaction so a partial failure can never leave
     * the bills, ledger, payments and customer records out of sync.
     *
     * @param Customer    $customer      The paying customer.
     * @param float       $amount        Received gateway amount.
     * @param string|null $transactionNo Gateway transaction reference (TrxID).
     * @param string      $method        Payment method label ('bKash' | 'Nagad').
     * @return array{payment: Payment, remaining_due: float}
     *
     * @throws Exception rethrown — callers decide how to surface the failure.
     */
    public function settleAutomaticPayment(Customer $customer, float $amount, ?string $transactionNo, string $method): array
    {
        $result = DB::transaction(function () use ($customer, $amount, $transactionNo, $method) {
            $customerId = $customer->id;
            $paidAmount = (float) $amount;
            $remaining = $paidAmount;

            // Track overpayment beyond all dues → customer wallet credit.
            $advanceCredit = 0.0;
            // Human-readable settlement log — stored in payments.payment_info.
            $settledLog = [];
            // billing_month of the first bill actually settled / partially
            // settled — stored in payments.billing_month (NULL when only the
            // Previous Due was cleared).
            $settledBillMonth = null;

            // Lock the open bills AND the single ledger row so concurrent
            // callbacks never settle the same bill twice with stale balances.
            $unpaidBills = GeneratedBill::where('customer_id', $customerId)
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $ledger = Invoice::where('customer_id', $customerId)->lockForUpdate()->first();

            // ---- Step 1: settle the Previous Due first -----------------------
            // previous_due = ledger due not covered by the itemized open bills.
            $openBillsDue = $unpaidBills->sum(fn ($bill) => (float) $bill->amount - (float) $bill->paid_amount);
            $previousDue = $ledger ? max(0, (float) $ledger->due_amount - $openBillsDue) : 0.0;

            if ($previousDue > 0 && $remaining > 0) {
                $settled = min($previousDue, $remaining);
                $remaining -= $settled;
                $settledLog[] = sprintf('Paid Previous Due: %.2f BDT', $settled);
            }

            // ---- Step 2: FIFO on generated_bills (oldest open bill first) ---
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
                }

                // payments.billing_month is a nullable TIMESTAMP column, so the
                // bill's "2026-08" label is stored as its first-of-month date.
                $settledBillMonth ??= $this->parseBillingMonth($bill->billing_month);
            }

            // ---- Step 3: leftover payment → advance credit -------------------
            if ($remaining > 0) {
                $advanceCredit = $remaining;
                $settledLog[] = sprintf('Advance Balance: %.2f BDT', $remaining);
                $remaining = 0;
            }

            // ---- Single ledger update (invoices: due_amount/advance/status) --
            // `amount` mirrors the running due so every existing reader of the
            // ledger stays consistent.
            if ($ledger) {
                $settledAgainstDues = $paidAmount - $advanceCredit;
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
                $remainingDue = (float) GeneratedBill::where('customer_id', $customerId)
                    ->whereIn('status', ['unpaid', 'partially_paid'])
                    ->get()
                    ->sum(fn ($bill) => (float) $bill->amount - (float) $bill->paid_amount);
            }

            // Record the payment as already approved (auto-settlement).
            $payment = Payment::create([
                'customer_id' => $customerId,
                'received_amount' => $amount,
                'client_code' => $customer->username,
                'recieved_date' => now()->format('Y-m-d'),
                'recieved_by' => $customer->name,
                'discount' => 0,
                'transaction_no' => $transactionNo,
                'created_by' => $customer->user_id,
                'notes' => "Auto-settled via {$method} online payment.",
                'payment_info' => implode(' | ', $settledLog) ?: $method,
                'payment_method' => $method,
                'total_amount' => $amount,
                'billing_month' => $settledBillMonth,
                'payment_status' => $remainingDue <= 0 ? 'paid' : 'partial',
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);

            // The payment workflow touches ONLY the three billing tables
            // (generated_bills / invoices / payments) — the customers record,
            // including expireddate, is intentionally never altered here.

            // Fully paid & active account → re-enable the MikroTik connection.
            if ($remainingDue <= 0 && $customer->status === 'active') {
                try {
                    app(ScheduledChangeService::class)->reEnableMikrotik($customer);
                    Invoice::where('customer_id', $customerId)->update(['pending_mikrotik_sync' => false]);
                } catch (Exception $e) {
                    // MikroTik failure must not roll back the settlement — the
                    // invoice is flagged as pending sync for a later retry.
                    Log::error("MikroTik re-enable failed for customer {$customerId} after auto-settlement: {$e->getMessage()}");
                    Invoice::where('customer_id', $customerId)->update(['pending_mikrotik_sync' => true]);
                }
            }

            return [
                'payment' => $payment,
                'remaining_due' => $remainingDue,
            ];
        });

        // Payment-confirmation SMS — dispatched AFTER the transaction commits
        // and only when the "Send SMS on Payment" permission is enabled.
        // Fully non-blocking: queueing failures are caught inside
        // SmsAutomation, so an SMS timeout can never fail the settlement or
        // the gateway response the customer receives.
        if (SmsAutomation::permissionEnabled('send_sms_on_payment')) {
            SmsAutomation::queuePaymentConfirmation($customer, $amount, $transactionNo);
        }

        return $result;
    }

    /**
     * Convert a generated_bills.billing_month string ("2026-08") into a Carbon
     * date. payments.billing_month is a nullable TIMESTAMP column, so the
     * human-readable month label must be parsed before it is persisted.
     * Returns null for empty / unparseable legacy labels.
     */
    private function parseBillingMonth(?string $month): ?Carbon
    {
        if (empty($month)) {
            return null;
        }

        try {
            return Carbon::parse($month);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
