<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;

class PositionController extends Controller
{
    public function index()
    {
        $Positions = Position::all();
        return response()->json($Positions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $Position = Position::create($request->all());
        return response()->json($Position, 201);
    }

    public function show(Position $Position)
    {
        return response()->json($Position);
    }

    public function update(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');

        $status = $request->input('status'); // Assuming status is also updated

        try {
            // Find the Position by ID
            $Position = Position::findOrFail($id);

            // Update the Position instance
            $Position->update([
                'name' => $name,
                'status' => $status,
            ]);

            // Return updated Position as JSON response
            return response()->json($Position);
        } catch (\Exception $e) {
            // Handle any exceptions (e.g., model not found)
            return response()->json(['error' => 'Failed to update Position.'], 500);
        }
    }

    public function destroy(Request $request)
    {
        $Position = Position::findOrFail($request->input('id'));
        $Position->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        Position::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Position  deleted successfully']);
    }
}
