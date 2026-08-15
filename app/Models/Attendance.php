<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $table = 'attendance';
    protected $guarded = [];

    // Define a relationship to the Employee model
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // The shift this attendance record was punched against
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Derive late minutes and overtime hours from punch times against a shift.
     *
     * - late_minutes    : minutes between the actual in-time and (start + grace).
     * - overtime_hours  : hours worked beyond the shift end-time.
     *
     * @return array{late_minutes:int|null, overtime_hours:float|null}
     */
    public static function calculatePunch(?Shift $shift, $inTime, $outTime): array
    {
        $lateMinutes = null;
        $overtimeHours = null;

        if ($shift && $inTime) {
            $start = Carbon::parse($shift->start_time);
            $graceEnd = $start->copy()->addMinutes((int) $shift->grace_minutes);
            $in = Carbon::parse($inTime);
            // Only punches AFTER the grace-end count as late. diffInMinutes is
            // absolute here, and since $in > $graceEnd it is the positive
            // number of minutes past the grace window.
            if ($in->gt($graceEnd)) {
                $lateMinutes = (int) $in->diffInMinutes($graceEnd);
            }
        }

        if ($shift && $outTime) {
            $end = Carbon::parse($shift->end_time);
            $out = Carbon::parse($outTime);
            // Only time beyond the shift end counts as overtime.
            if ($out->gt($end)) {
                $overtimeHours = round($out->diffInMinutes($end) / 60, 2);
            }
        }

        return [
            'late_minutes' => $lateMinutes,
            'overtime_hours' => $overtimeHours,
        ];
    }
}
