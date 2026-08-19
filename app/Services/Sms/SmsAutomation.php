<?php

namespace App\Services\Sms;

use App\Jobs\SendAutomatedSmsJob;
use App\Models\CustomPermission;
use App\Models\CustomRole;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Permission-gated, non-blocking SMS automation.
 *
 * The two automation permissions ("send_sms_on_payment" and
 * "send_sms_reminder") are regular permissions stored in the `permissions`
 * table and granted to roles via the Permission Setup UI. Automated jobs have
 * no authenticated user, so "enabled" means: a super-admin user exists (super
 * admin always implies every permission) OR the permission is assigned to at
 * least one role.
 *
 * All dispatch helpers swallow their own errors — an SMS failure can never
 * block, interrupt or fail the payment / cron flow that called it.
 */
class SmsAutomation
{
    /**
     * Whether an automation permission is currently enabled.
     */
    public static function permissionEnabled(string $permission): bool
    {
        // Super admin has every permission by definition.
        if (CustomRole::where('name', 'super admin')->whereHas('users')->exists()) {
            return true;
        }

        return CustomPermission::where('name', $permission)->whereHas('roles')->exists();
    }

    /**
     * Queue a payment-confirmation SMS. Never throws.
     */
    public static function queuePaymentConfirmation(Customer $customer, float $amount, ?string $transactionNo): void
    {
        try {
            SendAutomatedSmsJob::dispatch(
                (string) $customer->mobile,
                'payment_confirmation',
                [
                    'customer_name' => $customer->name ?: $customer->username,
                    'amount' => number_format($amount, 2),
                    'invoice_no' => $transactionNo ?: (string) $customer->id,
                    'company_name' => config('app.name'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to queue payment-confirmation SMS for customer {$customer->id}: {$e->getMessage()}");
        }
    }

    /**
     * Queue a due-reminder SMS (used by the daily cron). Never throws.
     */
    public static function queueDueReminder(Customer $customer, float $dueAmount, ?string $dueDate): void
    {
        try {
            SendAutomatedSmsJob::dispatch(
                (string) $customer->mobile,
                'bill_reminder',
                [
                    'customer_name' => $customer->name ?: $customer->username,
                    'amount' => number_format($dueAmount, 2),
                    'package' => $customer->package ?: '',
                    'due_date' => $dueDate ?: now()->format('d M Y'),
                    'company_name' => config('app.name'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to queue due-reminder SMS for customer {$customer->id}: {$e->getMessage()}");
        }
    }
}
