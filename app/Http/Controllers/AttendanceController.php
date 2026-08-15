<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Shift;
use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class AttendanceController extends Controller
{
    /**
     * Canonical attendance statuses. Everything is stored lowercase so the
     * payroll engine can aggregate paid/unpaid leave, absence, lateness and
     * overtime directly from the attendance log.
     */
    private const STATUSES = 'present,absent,late,paid_leave,unpaid_leave,holiday,off_day';

    /**
     * Record attendance for an employee on a given day.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'date' => 'required',
                'status' => 'required|in:' . self::STATUSES,
                'in_time' => 'nullable',
                'out_time' => 'nullable',
                'shift_id' => 'nullable|exists:shifts,id',
                'late_minutes' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
            ]);
            $validated['date'] = Carbon::parse($validated['date'])->format('Y-m-d');
            $validated['status'] = strtolower($validated['status']);
            $validated['in_time'] = $validated['in_time'] ?: null;
            $validated['out_time'] = $validated['out_time'] ?: null;
            $validated['shift_id'] = $validated['shift_id'] ?: null;

            // Fall back to the employee's assigned shift when no explicit
            // shift_id is sent in the payload.
            if (!$validated['shift_id']) {
                $employee = Employee::find($validated['employee_id']);
                $validated['shift_id'] = $employee?->shift_id ?: null;
            }

            // Auto-calculate late minutes / overtime hours from the punch times
            // against the assigned shift. The status is derived from the punch
            // rules: past the grace window => Late, on time => Present. A stale
            // or manually-set Late on an on-time punch is corrected to Present.
            $shift = $validated['shift_id'] ? Shift::find($validated['shift_id']) : null;
            $punch = Attendance::calculatePunch($shift, $validated['in_time'], $validated['out_time']);
            if ($validated['in_time'] && $shift) {
                if ($punch['late_minutes'] !== null) {
                    if ($validated['status'] === 'present') {
                        $validated['status'] = 'late';
                    }
                } elseif ($validated['status'] === 'late') {
                    $validated['status'] = 'present';
                }
            }
            $validated['late_minutes'] = $validated['status'] === 'late' ? $punch['late_minutes'] : null;
            $validated['overtime_hours'] = $punch['overtime_hours'];

            Attendance::create($validated);

            return response()->json(['message' => 'Employee attendance created successfully'], 201);
        } catch (QueryException $e) {
            // The (employee_id, date) unique constraint rejects duplicate entries
            if ($e->errorInfo[1] === 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['message' => 'Attendance already recorded for this employee on this day.'], 422);
            }
            return response()->json(['message' => 'Failed to create attendance.'], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * List attendance records for an employee.
     */
    public function index(Request $request)
    {
        try {
            $attendance = Attendance::with('shift')->where('employee_id', $request->input('id'))
                ->orderByDesc('date')
                ->get();

            return response()->json([
                'attendance' => $attendance,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing attendance record.
     */
    public function update(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:attendance,id',
                'date' => 'required',
                'status' => 'required|in:' . self::STATUSES,
                'in_time' => 'nullable',
                'out_time' => 'nullable',
                'shift_id' => 'nullable|exists:shifts,id',
                'late_minutes' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            $attendance = Attendance::with('employee')->findOrFail($request->input('id'));
            $updates = [
                'date' => Carbon::parse($request->input('date'))->format('Y-m-d'),
                'status' => strtolower($request->input('status')),
                'notes' => $request->input('notes'),
            ];
            if ($request->has('in_time')) {
                $updates['in_time'] = $request->input('in_time') ?: null;
            }
            if ($request->has('out_time')) {
                $updates['out_time'] = $request->input('out_time') ?: null;
            }
            if ($request->has('shift_id')) {
                $updates['shift_id'] = $request->input('shift_id') ?: null;
            }

            // Recompute late minutes / overtime hours from the (possibly new)
            // punch times against the assigned shift. Falls back to the
            // employee's assigned shift when neither the payload nor the record
            // specifies one.
            $shiftId = $updates['shift_id'] ?? $attendance->shift_id;
            if (!$shiftId) {
                $shiftId = $attendance->employee?->shift_id ?: null;
            }
            $shift = $shiftId ? Shift::find($shiftId) : null;
            $inTime = $updates['in_time'] ?? $attendance->in_time;
            $outTime = $updates['out_time'] ?? $attendance->out_time;
            $punch = Attendance::calculatePunch($shift, $inTime, $outTime);
            if ($inTime && $shift) {
                if ($punch['late_minutes'] !== null) {
                    if ($updates['status'] === 'present') {
                        $updates['status'] = 'late';
                    }
                } elseif ($updates['status'] === 'late') {
                    $updates['status'] = 'present';
                }
            }
            $updates['late_minutes'] = $updates['status'] === 'late' ? $punch['late_minutes'] : null;
            $updates['overtime_hours'] = $punch['overtime_hours'];

            $attendance->update($updates);

            return response()->json(['message' => 'Employee attendance updated successfully']);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['message' => 'Attendance already recorded for this employee on this day.'], 422);
            }
            return response()->json(['message' => 'Failed to update attendance.'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk-record attendance for many employees on the same day.
     * Rows that already exist for that day are skipped.
     */
    public function bulkStore(Request $request)
    {
        try {
            $request->validate([
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:employees,id',
                'date' => 'required',
                'status' => 'required|in:' . self::STATUSES,
                'late_minutes' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            $date = Carbon::parse($request->input('date'))->format('Y-m-d');
            $status = strtolower($request->input('status'));
            $lateMinutes = $status === 'late' ? $request->input('late_minutes') : null;
            $notes = $request->input('notes');

            $created = 0;
            $skipped = 0;
            foreach ($request->input('employee_ids') as $employeeId) {
                // Default the shift to the employee's assigned shift.
                $employee = Employee::find($employeeId);
                try {
                    Attendance::create([
                        'employee_id' => $employeeId,
                        'date' => $date,
                        'status' => $status,
                        'shift_id' => $employee?->shift_id ?: null,
                        'late_minutes' => $lateMinutes,
                        'notes' => $notes,
                    ]);
                    $created++;
                } catch (QueryException $e) {
                    if ($e->errorInfo[1] === 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                        $skipped++;
                        continue;
                    }
                    throw $e;
                }
            }

            return response()->json([
                'message' => "Attendance saved: {$created} recorded, {$skipped} already recorded.",
                'created' => $created,
                'skipped' => $skipped,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load the daily punch sheet: one row per employee for the selected date.
     * Existing attendance rows are merged in; new rows default to the shift
     * selected in the sheet filter.
     */
    public function dailySheet(Request $request)
    {
        try {
            $date = Carbon::parse($request->input('date', now()->toDateString()))->format('Y-m-d');
            $shiftId = $request->input('shift_id');

            $employees = Employee::with(['position', 'department', 'shift'])->orderBy('name')->get();

            // When a specific shift is selected, only show employees that are
            // actually assigned to it.
            if ($shiftId) {
                $employees = $employees->filter(function ($employee) use ($shiftId) {
                    return (int) $employee->shift_id === (int) $shiftId;
                })->values();
            }

            $existing = Attendance::where('date', $date)->get()->keyBy('employee_id');

            $rows = $employees->map(function ($employee) use ($date, $existing, $shiftId) {
                $record = $existing->get($employee->id);

                return [
                    'attendance_id' => $record?->id ?? null,
                    'employee_id' => $employee->id,
                    'formatted_id' => $employee->formatted_id,
                    'name' => $employee->name,
                    'designation' => $employee->position_name,
                    'department' => $employee->department_name,
                    'profileimage' => $employee->profileimage,
                    // Default the sheet row to the employee's assigned shift,
                    // falling back to the sheet-level filter when unassigned.
                    'shift_id' => $record?->shift_id ?? $employee->shift_id ?? $shiftId,
                    'in_time' => $record?->in_time,
                    'out_time' => $record?->out_time,
                    'status' => $record?->status ?? 'present',
                    'late_minutes' => $record?->late_minutes,
                    'overtime_hours' => $record?->overtime_hours,
                    'notes' => $record?->notes,
                ];
            })->values();

            return response()->json([
                'date' => $date,
                'rows' => $rows,
                'shifts' => Shift::orderBy('start_time')->get(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk upsert the daily punch sheet for one date.
     * Late minutes and overtime hours are always recomputed server-side from
     * the assigned shift — the client values are never trusted.
     */
    public function saveDailySheet(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required',
                'rows' => 'required|array',
                'rows.*.employee_id' => 'required|exists:employees,id',
                'rows.*.in_time' => 'nullable',
                'rows.*.out_time' => 'nullable',
                'rows.*.status' => 'required|in:' . self::STATUSES,
                'rows.*.shift_id' => 'nullable|exists:shifts,id',
                'rows.*.notes' => 'nullable|string',
            ]);

            $date = Carbon::parse($request->input('date'))->format('Y-m-d');
            $saved = 0;

            foreach ($request->input('rows') as $row) {
                // Fall back to the employee's assigned shift when the row does
                // not specify one explicitly.
                $shiftId = $row['shift_id'] ?? null;
                if (!$shiftId) {
                    $employee = Employee::find($row['employee_id']);
                    $shiftId = $employee?->shift_id ?: null;
                }
                $shift = $shiftId ? Shift::find($shiftId) : null;
                $punch = Attendance::calculatePunch($shift, $row['in_time'] ?? null, $row['out_time'] ?? null);

                $status = strtolower($row['status']);
                // Derive the status from the punch rules in both directions:
                // past the grace window => Late, on time => Present (fixes any
                // stale Late the client sent for an on-time punch).
                if (!empty($row['in_time']) && $shift) {
                    if ($punch['late_minutes'] !== null) {
                        if ($status === 'present') {
                            $status = 'late';
                        }
                    } elseif ($status === 'late') {
                        $status = 'present';
                    }
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $row['employee_id'], 'date' => $date],
                    [
                        'shift_id' => $shiftId,
                        'in_time' => $row['in_time'] ?: null,
                        'out_time' => $row['out_time'] ?: null,
                        'status' => $status,
                        'late_minutes' => $status === 'late' ? $punch['late_minutes'] : null,
                        'overtime_hours' => $punch['overtime_hours'],
                        'notes' => $row['notes'] ?? null,
                    ]
                );
                $saved++;
            }

            return response()->json([
                'message' => "Daily attendance saved for {$saved} employee(s)",
                'saved' => $saved,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all attendance for a given month (YYYY-MM), including employee names.
     */
    public function monthly(Request $request)
    {
        try {
            $request->validate([
                'month' => 'required|date_format:Y-m',
                'employee_id' => 'nullable|exists:employees,id',
            ]);

            $month = Carbon::parse($request->input('month'));
            $query = Attendance::with(['shift', 'employee:id,name'])
                ->whereBetween('date', [$month->copy()->startOfMonth()->format('Y-m-d'), $month->copy()->endOfMonth()->format('Y-m-d')]);

            // Optionally narrow the roster to a single employee.
            if ($request->input('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
            $records = $query->orderBy('date')->get();

            return response()->json([
                'attendance' => $records,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
