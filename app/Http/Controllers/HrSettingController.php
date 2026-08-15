<?php

namespace App\Http\Controllers;

use App\Models\HrSetting;
use Illuminate\Http\Request;

class HrSettingController extends Controller
{
    /**
     * Return the overtime / late-fee rule settings with defaults.
     */
    public function index()
    {
        try {
            return response()->json([
                'settings' => [
                    'overtime_mode' => HrSetting::getValue('overtime_mode', 'salary_based'),
                    'overtime_fixed_rate' => HrSetting::getValue('overtime_fixed_rate', '100'),
                    'overtime_multiplier' => HrSetting::getValue('overtime_multiplier', '1.5'),
                    'late_fee_mode' => HrSetting::getValue('late_fee_mode', 'salary_based'),
                    'late_fee_fixed_amount' => HrSetting::getValue('late_fee_fixed_amount', '50'),
                    'late_grace_days' => HrSetting::getValue('late_grace_days', '3'),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Persist the overtime / late-fee rule settings (key-value store).
     */
    public function save(Request $request)
    {
        try {
            $validated = $request->validate([
                'overtime_mode' => 'required|in:salary_based,fixed_rate',
                'overtime_fixed_rate' => 'nullable|numeric|min:0',
                'overtime_multiplier' => 'nullable|numeric|min:0',
                'late_fee_mode' => 'required|in:salary_based,fixed_per_late,fixed_per_minute',
                'late_fee_fixed_amount' => 'nullable|numeric|min:0',
                'late_grace_days' => 'nullable|integer|min:0',
            ]);

            foreach ($validated as $key => $value) {
                HrSetting::setValue($key, $value);
            }

            return response()->json([
                'message' => 'HR settings saved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
