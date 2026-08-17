<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\SystemPermission;

class SystemSetupController extends Controller
{
    /**
     * Normalize a stored system_permissions flag into the 'enable' / 'disable'
     * strings the settings UI uses. Handles legacy boolean-ish storage
     * ('0' / '1', 0 / 1, true / false) as well as null / empty values.
     */
    private function normalizeFlag($value): string
    {
        if ($value === null || $value === '') {
            return 'disable';
        }

        if (is_bool($value)) {
            return $value ? 'enable' : 'disable';
        }

        $lower = strtolower((string) $value);

        return in_array($lower, ['1', 'enable', 'true', 'yes', 'on'], true) ? 'enable' : 'disable';
    }

    /**
     * Normalize the block_mikrotik_profile setting. The column can hold either
     * a boolean-ish enable/disable value OR a real MikroTik profile name, so
     * only boolean-ish storage is mapped to 'enable' / 'disable' — anything
     * else (e.g. an actual profile name) is preserved as-is.
     */
    private function normalizeBlockProfile($value): ?string
    {
        if ($value === null || $value === '') {
            return 'disable';
        }

        if (is_bool($value)) {
            return $value ? 'enable' : 'disable';
        }

        $lower = strtolower((string) $value);

        if (in_array($lower, ['1', 'enable', 'true', 'yes', 'on'], true)) {
            return 'enable';
        }

        if (in_array($lower, ['0', 'disable', 'false', 'no', 'off'], true)) {
            return 'disable';
        }

        // Real MikroTik profile name — keep it untouched.
        return $value;
    }

    /**
     * Fetch (or create, on first use) the single system_permissions row with
     * flag values normalized to the 'enable' / 'disable' strings the UI binds
     * to its RadioButtons.
     */
    public function getSystemSetup()
    {
        $systemSetup = SystemPermission::first();

        if (!$systemSetup) {
            $systemSetup = SystemPermission::create([
                'payroll_with_late_fees' => 'disable',
                'payroll_with_overtime' => 'disable',
                'payroll_with_absence' => 'disable',
                'payment_status_wise_client_disabled' => 'disable',
                'company_name_invoice' => 'disable',
                'block_mikrotik_profile' => 'disable',
                'save_comment_in_mikrotik' => 'disable',
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $systemSetup->id,
                'company_name_invoice' => $this->normalizeFlag($systemSetup->company_name_invoice),
                'block_mikrotik_profile' => $this->normalizeBlockProfile($systemSetup->block_mikrotik_profile),
                'save_comment_in_mikrotik' => $this->normalizeFlag($systemSetup->save_comment_in_mikrotik),
                'payment_status_wise_client_disabled' => $this->normalizeFlag($systemSetup->payment_status_wise_client_disabled),
                'payroll_with_overtime' => $this->normalizeFlag($systemSetup->payroll_with_overtime),
                'payroll_with_late_fees' => $this->normalizeFlag($systemSetup->payroll_with_late_fees),
                'payroll_with_absence' => $this->normalizeFlag($systemSetup->payroll_with_absence),
            ],
        ], 200);
    }

    public function saveCommonSetup(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => 'nullable|exists:system_permissions,id',
                'company_name_invoice' => 'nullable|string',
                'block_mikrotik_profile' => 'nullable|string',
                'save_comment_in_mikrotik' => 'nullable|string',
            ]);

            // Single canonical row — update it when it exists, otherwise create.
            $system = SystemPermission::first();
            if (!$system) {
                $system = new SystemPermission();
            }

            $system->company_name_invoice = $this->normalizeFlag($data['company_name_invoice'] ?? null);
            $system->block_mikrotik_profile = $this->normalizeBlockProfile($data['block_mikrotik_profile'] ?? null);
            $system->save_comment_in_mikrotik = $this->normalizeFlag($data['save_comment_in_mikrotik'] ?? null);
            $system->save();

            return response()->json(['message' => 'Common setup saved successfully!'], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function savePayrollSetup(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => 'nullable|exists:system_permissions,id',
                'payroll_with_late_fees' => 'nullable|string',
                'payroll_with_overtime' => 'nullable|string',
                'payroll_with_absence' => 'nullable|string',
            ]);

            // Single canonical row — update it when it exists, otherwise create.
            $system = SystemPermission::first();
            if (!$system) {
                $system = new SystemPermission();
            }

            $system->payroll_with_late_fees = $this->normalizeFlag($data['payroll_with_late_fees'] ?? null);
            $system->payroll_with_overtime = $this->normalizeFlag($data['payroll_with_overtime'] ?? null);
            $system->payroll_with_absence = $this->normalizeFlag($data['payroll_with_absence'] ?? null);
            $system->save();

            return response()->json(['message' => 'Payroll setup saved successfully!'], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveBillingSetup(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => 'nullable|exists:system_permissions,id',
                'payment_status_wise_client_disabled' => 'nullable|string',
            ]);

            // Single canonical row — update it when it exists, otherwise create.
            $system = SystemPermission::first();
            if (!$system) {
                $system = new SystemPermission();
            }

            $system->payment_status_wise_client_disabled = $this->normalizeFlag($data['payment_status_wise_client_disabled'] ?? null);
            $system->save();

            return response()->json(['message' => 'Billing setup saved successfully!'], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
