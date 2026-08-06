<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\SystemPermission;

class SystemSetupController extends Controller
{
    public function getSystemSetup()
    {
        $systemSetup = SystemPermission::first();
        return response()->json(['data' => $systemSetup], 200);
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
            $id = $request->input('id');
            if ($id) {
                $system = SystemPermission::find($id);
                $system->update([
                    'company_name_invoice' => $data['company_name_invoice'],
                    'block_mikrotik_profile' => $data['block_mikrotik_profile'],
                    'save_comment_in_mikrotik' => $data['save_comment_in_mikrotik'],
                ]);
                return response()->json(['message' => 'Common setup updated successfully!'], 200);
            } else {
                SystemPermission::create([
                    'company_name_invoice' => $data['company_name_invoice'],
                    'block_mikrotik_profile' => $data['block_mikrotik_profile'],
                    'save_comment_in_mikrotik' => $data['save_comment_in_mikrotik'],
                ]);
                return response()->json(['message' => 'Common setup saved successfully!'], 200);
            }
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
            $id = $request->input('id');
            if ($id) {
                $system = SystemPermission::find($id);
                $system->update([
                    'payroll_with_late_fees' => $data['payroll_with_late_fees'],
                    'payroll_with_overtime' => $data['payroll_with_overtime'],
                    'payroll_with_absence' => $data['payroll_with_absence'],
                ]);
                return response()->json(['message' => 'Payroll setup updated successfully!',], 200);
            } else {
                SystemPermission::create([
                    'payroll_with_late_fees' => $data['payroll_with_late_fees'],
                    'payroll_with_overtime' => $data['payroll_with_overtime'],
                    'payroll_with_absence' => $data['payroll_with_absence'],
                ]);
                return response()->json(['message' => 'Payroll setup saved successfully!',], 200);
            }
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
            $id = $request->input('id');
            if ($id) {
                $system = SystemPermission::find($id);
                $system->update([
                    'payment_status_wise_client_disabled' => $data['payment_status_wise_client_disabled'],
                ]);
                return response()->json(['message' => 'Billing setup updated successfully!',], 200);
            } else {
                SystemPermission::create(
                    [
                        'payment_status_wise_client_disabled' => $data['payment_status_wise_client_disabled'],
                    ]
                );
                return response()->json(['message' => 'Billing setup saved successfully!'], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
