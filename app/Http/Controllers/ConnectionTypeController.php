<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConnectionType;

class ConnectionTypeController extends Controller
{
    public function index()
    {
        $connectiontypes = ConnectionType::all();
        return response()->json($connectiontypes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'connection_type' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);

        $connectiontype = ConnectionType::create($request->all());
        return response()->json($connectiontype, 201);
    }



    public function update(Request $request)
    {
        $id = $request->input('id');
        $connection_type = $request->input('connection_type');
        $details = $request->input('details');
        $status = $request->input('status'); // Assuming status is also updated

        try {
            // Find the ConnectionType by ID
            $connectionType = ConnectionType::findOrFail($id);

            // Update the ConnectionType instance
            $connectionType->update([
                'connection_type' => $connection_type,
                'details' => $details,
                'status' => $status,
            ]);

            // Return updated ConnectionType as JSON response
            return response()->json($connectionType);
        } catch (\Exception $e) {
            // Handle any exceptions (e.g., model not found)
            return response()->json(['error' => 'Failed to update ConnectionType.'], 500);
        }
    }

    public function destroy(Request $request)
    {
        $connectiontype = ConnectionType::findOrFail($request->input('id'));
        $connectiontype->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        ConnectionType::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Connectiontype  deleted successfully']);
    }
}
