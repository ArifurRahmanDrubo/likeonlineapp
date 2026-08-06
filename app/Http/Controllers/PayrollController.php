<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\Generatedsallary;

class PayrollController extends Controller
{
    public function store(Request $request)
    {
        try {
            $payableamount = $request->input('payableamount');
            $employee_code = $request->input('employee_code');
            $basic_salary = $request->input('basic_salary');
            $transactionno = $request->input('transactionno');
            $remainingdue = $request->input('remainingdue');
            $paid_amount = $request->input('paid_amount');
            $notes = $request->input('notes');
            $payment_by = $request->input('payment_by');
            $payment_info = $request->input('paymentmethod');
            $payment_date = $request->input('payment_date');
            $employee_id = $request->input('employee_id');

            if ($remainingdue < 0) {
                $status = 'paid';
            } elseif ($remainingdue == 0) {
                $status = 'paid';
            } elseif ($remainingdue > 0) {
                $status = 'unpaid';
            }

            $date = Carbon::parse($payment_date);
            $formattedDate = $date->format('d F Y');

            $payroll = Payroll::where('employee_id', $employee_id)->first();
            $payroll->update([
                'total_salary' => $payableamount,
                'status' => $status,
                'notes' => $notes,
            ]);
            Payslip::create([
                'employee_id' => $employee_id,
                'payment_amount' => $paid_amount,
                'employee_code' => $employee_code,
                'payment_date' => $formattedDate,
                'payment_by' => $payment_by,
                'transaction_no' => $transactionno,
                'notes' => $notes,
                'payment_info' => $payment_info,


            ]);
            return response()->json([
                'message' => 'Payment Successful'
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
            $employee_id = $request->input('id');

            $employee = Payslip::where('employee_id', $employee_id)->latest()->first();

            if (!$employee) {
                return response()->json([
                    'error' => 'Employee not found.'
                ], 404);
            }
            return response()->json([
                'employee' => $employee
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while fetching the data.'
            ], 500);
        }
    }

    public function detailspayslip(Request $request)
    {
        try {
            $employee_id = $request->input('id');
            $companyProfile = CompanyProfile::all();
            $details = Employee::where('id', $employee_id)->with('payroll')->first();

            if (!$details) {
                return response()->json([
                    'error' => 'Employee not found.'
                ], 404);
            }
            return response()->json([
                'Details' => $details,
                'companyProfile' => $companyProfile,
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while fetching the data.'
            ], 500);
        }
    }
}
