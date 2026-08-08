<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;

use App\Models\Advance;

use App\Models\LateFee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\Allowance;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Models\Generatedsallary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Dotenv\Exception\ValidationException;


class EmployeeController extends Controller
{
    public function index()
    {
        try {
            $Employees = Employee::with('payroll')->get();
            return response()->json([
                'Employees' => $Employees
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getEmployee($id)
    {
        try {
            $Employee = Employee::with('payroll')->find($id);

            if (!$Employee) {
                return response()->json([
                    'message' => 'Employee not found'
                ], 404);
            }

            return response()->json([
                'Employee' => $Employee
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function EmployeeData(Request $request)
    {
        try {
            $id = $request->input('id');
            $Employees = Employee::where('id', $id)->with('invoice')->first();
            return response()->json([
                'Employees' => $Employees
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching Employees.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'experience' => 'nullable|string',
                'nid' => 'nullable|string|max:255',
                'gender' => 'nullable',
                'dateofbirth' => 'nullable',
                'basic_salary' => 'nullable',
                'registrationno' => 'nullable|string|max:255',
                'fathername' => 'nullable|string|max:255',
                'mothername' => 'nullable|string|max:255',
                'maritalstatus' => 'nullable',
                'officecContact' => 'nullable|numeric',
                'facebook' => 'nullable|string|max:255',
                'guardianContact' => 'nullable',
                'twitter' => 'nullable|string|max:255',
                'mobile' => 'required|string',
                'degeree' => 'nullable|string|max:255',
                'institute' => 'nullable|string|max:255',
                'passingYear' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255',
                'district' => 'nullable|string|max:255',
                'upzila' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'position' => 'nullable|string|max:255',
                'praddress' => 'nullable|string',
                'paraddress' => 'nullable|string',
                'referenceby' => 'nullable|string|max:255',

            ]);
            // Upload images to Cloudinary (update replaces the old image, create uploads new)
            $existingEmployee = $request->input('id') ? Employee::find($request->input('id')) : null;
            foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                if ($request->hasFile($field)) {
                    $imageData = $existingEmployee
                        ? cloudinary_update($request->file($field), $existingEmployee->{$field . '_public_id'}, 'employees')
                        : cloudinary_upload($request->file($field), 'employees');
                    $validated[$field] = $imageData['url'];
                    $validated[$field . '_public_id'] = $imageData['public_id'];
                }
            }
            $joiningDate = $request->input('joiningdate');
            $dateOfBirth = $request->input('dateofbirth');
            $dateString = $joiningDate;
            $simplifiedDateString = preg_replace('/ \([^\)]+\)$/', '', $dateString);
            $date = Carbon::parse($simplifiedDateString);
            $formattedDate = $date->format('d F Y');
            $validated['joiningdate'] = $formattedDate;
            $dateString1 = $dateOfBirth;
            $simplifiedDateString1 = preg_replace('/ \([^\)]+\)$/', '', $dateString1);
            $date1 = Carbon::parse($simplifiedDateString1);
            $formattedDate1 = $date1->format('d F Y');
            $validated['dateofbirth'] = $formattedDate1;
            if ($request->input('id')) {
                $Employee = Employee::findOrFail($request->input('id'));
                $Employee->update($validated);
                return response()->json(['message' => 'Employee updated successfully']);
            } else {
                Employee::create($validated);
            }

            return response()->json(['message' => 'Employee created successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee = Employee::find($id);
            if ($employee) {
                // Delete associated images from Cloudinary before deleting the record
                foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                    if ($employee->{$field . '_public_id'}) {
                        cloudinary_delete($employee->{$field . '_public_id'});
                    }
                }
                $employee->delete();
                return response()->json(['message' => 'Employee deleted successfully']);
            }
            return response()->json(['message' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function attendence(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|max:255',
                'notes' => 'nullable|string',
                'employee_id' => 'nullable',
            ]);
            $employee_id = $request->input('employee_id');
            $date = $request->input('date');
            $formatteddate = Carbon::parse($date)->format('Y-m-d');
            $validated['date'] = $formatteddate;

            Attendance::create($validated);
            return response()->json(['message' => 'Employee attendence Create successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Employee have attendence for this Day '
            ], 500);
        }
    }

    public function updateAttendence(Request $request)
    {
        try {
            $id = $request->input('id');
            $date = $request->input('date');
            $status = $request->input('status');
            $notes = $request->input('notes');
            $formatteddate = Carbon::parse($date)->format('Y-m-d');


            $attendance = Attendance::where('id', $id)->first();
            if ($attendance) {
                $attendance->update([
                    'date' => $formatteddate,
                    'status' => $status,
                    'notes' => $notes,
                ]);
                return response()->json(['message' => 'Employee attendence Update successfully']);
            }
            return response()->json(['message' => 'Attendance Not found']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Employee have attendence for this Day '
            ], 500);
        }
    }
    public function getAttendance(Request $request)
    {
        try {
            $employee_id = $request->input('id');
            $attendance = Attendance::where('employee_id', $employee_id)->get();
            return response()->json([
                'attendance' => $attendance
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function allowance(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required',
                'description' => 'nullable',
                'employee_id' => 'nullable',
            ]);
            $employee_id = $request->input('employee_id');
            $date = $request->input('date');
            $formatteddate = Carbon::parse($date)->format('Y-m-d');
            $validated['date'] = $formatteddate;

            Allowance::create($validated);
            return response()->json(['message' => 'Employee allowance Create successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getAllowance(Request $request)
    {

        try {
            $employee_id = $request->input('employee_id');
            $allowance = Allowance::where('employee_id', $employee_id)->get();
            return response()->json([
                'allowance' => $allowance
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteAllowance(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');
            $allowance = Allowance::where('employee_id', $employee_id)->where('id', $id)->first();
            if ($allowance) {
                $allowance->delete();
                return response()->json(['message' => 'Employee allowance Delete successfully']);
            } else {
                return response()->json(['message' => 'Employee allowance note found']);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function LateFees(Request $request)
    {
        try {
            $validated = $request->validate([
                'rate_per_hour' => 'required',
                'hours_late' => 'nullable',
                'employee_id' => 'nullable',
                'notes' => 'nullable',

            ]);
            $employee_id = $request->input('employee_id');
            $date = $request->input('date');
            $formatteddate = Carbon::parse($date)->format('Y-m-d');
            $validated['date'] = $formatteddate;

            LateFee::create($validated);
            return response()->json(['message' => 'Employee LateFees Assign successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getLateFees(Request $request)
    {

        try {
            $employee_id = $request->input('id');
            $LateFee = LateFee::where('employee_id', $employee_id)->get();
            return response()->json([
                'LateFee' => $LateFee,
                'message' => 'heelo'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteLateFee(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');
            $LateFee = LateFee::where('employee_id', $employee_id)->where('id', $id)->first();
            if ($LateFee) {
                $LateFee->delete();
                return response()->json(['message' => 'Employee LateFee Delete successfully']);
            }

            return response()->json(['message' => 'Employee LateFee note found']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function overtime(Request $request)
    {
        try {
            $validated = $request->validate([
                'rate_per_hour' => 'required',
                'hours_overtime' => 'nullable',
                'employee_id' => 'nullable',
                'notes' => 'nullable',

            ]);
            $employee_id = $request->input('employee_id');
            $date = $request->input('date');
            $formatteddate = Carbon::parse($date)->format('Y-m-d');
            $validated['date'] = $formatteddate;

            Overtime::create($validated);
            return response()->json(['message' => 'Employee Overtime Assign successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getOverTime(Request $request)
    {

        try {
            $employee_id = $request->input('id');
            $Overtime = Overtime::where('employee_id', $employee_id)->get();
            return response()->json([
                'Overtime' => $Overtime
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteOvertime(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');
            $Overtime = Overtime::where('employee_id', $employee_id)->where('id', $id)->first();
            if ($Overtime) {
                $Overtime->delete();
                return response()->json(['message' => 'Employee Overtim Delete successfully']);
            }

            return response()->json(['message' => 'Employee Overtim note found']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function advance(Request $request)
    {
        try {
            $validated = $request->validate([

                'amount' => 'nullable',
                'employee_id' => 'nullable',
                'notes' => 'nullable',

            ]);
            $employee_id = $request->input('employee_id');
            $date = $request->input('date');
            $formatteddate = Carbon::parse($date)->format('Y-m-d');
            $validated['date'] = $formatteddate;

            Advance::create($validated);
            return response()->json(['message' => 'Employee Advance Assign successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getAdvance(Request $request)
    {

        try {
            $employee_id = $request->input('id');
            $Advance = Advance::where('employee_id', $employee_id)->get();
            return response()->json([
                'Advance' => $Advance
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAdvance(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');
            $Advance = Advance::where('employee_id', $employee_id)->where('id', $id)->first();
            if ($Advance) {
                $Advance->delete();
                return response()->json(['message' => 'Employee Advance Delete successfully']);
            }

            return response()->json(['message' => 'Employee Advance note found']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getGeneratedSalary(Request $request)
    {

        try {
            $employee_id = $request->input('id');
            $GeneratedSalary = Generatedsallary::where('employee_id', $employee_id)->get();
            return response()->json([
                'GeneratedSalary' => $GeneratedSalary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteGeneratedSalary(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');
            $GeneratedSalary = Generatedsallary::where('employee_id', $employee_id)->where('id', $id)->first();
            if ($GeneratedSalary) {
                $GeneratedSalary->delete();
                return response()->json(['message' => 'Employee GeneratedSalary Delete successfully']);
            }

            return response()->json(['message' => 'Employee GeneratedSalary note found']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getPayslip(Request $request)
    {

        try {
            $employee_id = $request->input('id');
            $Payslip = Payslip::where('employee_id', $employee_id)->get();
            return response()->json([
                'Payslip' => $Payslip,
                'message' => 'heloo',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deletePayslip(Request $request)
    {





        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');
            $total_amount = $request->input('amount');
            $Payslip = Payslip::where('employee_id', $employee_id)->where('id', $id)->first();

            if ($Payslip) {
                $Payroll = Payroll::where('employee_id', $employee_id)->first();
                $amount = $Payroll->total_salary;
                $update_amount = $amount + $total_amount;
                $Payroll->update([
                    'total_salary' => $update_amount,
                    'status' => 'unpaid',

                ]);
                $Payslip->delete();
                return response()->json(['message' => 'Employee Payslip Delete successfully']);
            }

            return response()->json(['message' => 'Employee Payslip note found']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getpdfImages(Request $request)
    {
        try {
            $id = $request->input('id');
            $Employee = Employee::find($id);

            if (!$Employee) {
                return response()->json([
                    'error' => 'Employee not found'
                ], 404);
            }

            // Convert an image (Cloudinary URL or legacy local path) to a base64 data URL
            $base64Image = function ($image) {
                if (!$image) {
                    return null;
                }

                try {
                    if (filter_var($image, FILTER_VALIDATE_URL)) {
                        // Image stored as a remote (Cloudinary) URL
                        $response = Http::timeout(30)->get($image);
                        if (!$response->successful()) {
                            return null;
                        }
                        $imageData = $response->body();
                        $mimeType = strtok($response->header('Content-Type') ?: 'application/octet-stream', ';');
                    } else {
                        // Legacy image stored as a local path
                        $path = public_path($image);
                        if (!file_exists($path)) {
                            return null;
                        }
                        $imageData = file_get_contents($path);
                        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
                    }
                } catch (\Exception $e) {
                    return null;
                }

                if (empty($imageData)) {
                    return null;
                }

                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            };

            return response()->json([
                'profileImage' => $base64Image($Employee->profileimage) ?: 'Profile image not found',
                'nidImage' => $base64Image($Employee->nidimage) ?: 'NID image not found',
                'registrationImage' => $base64Image($Employee->registrationimage) ?: 'Registration image not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching Employee images.',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
