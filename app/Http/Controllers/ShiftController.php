<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * List all shifts ordered by start time.
     */
    public function index()
    {
        try {
            return response()->json([
                'shifts' => Shift::orderBy('start_time')->get(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create / update shifts sent from the HR Settings page.
     */
    public function save(Request $request)
    {
        try {
            $request->validate([
                'shifts' => 'required|array',
                'shifts.*.id' => 'nullable|exists:shifts,id',
                'shifts.*.name' => 'required|string|max:255',
                'shifts.*.start_time' => 'required',
                'shifts.*.end_time' => 'required',
                'shifts.*.grace_minutes' => 'nullable|integer|min:0',
            ]);

            $saved = 0;
            foreach ($request->input('shifts') as $shiftData) {
                $payload = [
                    'name' => $shiftData['name'],
                    'start_time' => $shiftData['start_time'],
                    'end_time' => $shiftData['end_time'],
                    'grace_minutes' => $shiftData['grace_minutes'] ?? 0,
                ];

                if (!empty($shiftData['id'])) {
                    $shift = Shift::findOrFail($shiftData['id']);
                    $shift->update($payload);
                } else {
                    Shift::create($payload);
                }
                $saved++;
            }

            return response()->json([
                'message' => "{$saved} shift(s) saved successfully",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
