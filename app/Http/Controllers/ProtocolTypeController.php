<?php

namespace App\Http\Controllers;

use App\Models\ProtocolType;
use Illuminate\Http\Request;

class ProtocolTypeController extends Controller
{
    public function index()
    {
        $protocoltypes = ProtocolType::all();
        return response()->json($protocoltypes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'protocol_type' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);

        $protocoltype = ProtocolType::create($request->all());
        return response()->json($protocoltype, 201);
    }

    public function show(protocoltype $protocoltype)
    {
        return response()->json($protocoltype);
    }

    public function update(Request $request)
    {
        $id = $request->input('id');
        $protocol_type = $request->input('protocol_type');
        $details = $request->input('details');
        $status = $request->input('status'); // Assuming status is also updated

        try {
            // Find the protocoltype by ID
            $protocoltype = ProtocolType::findOrFail($id);

            // Update the protocoltype instance
            $protocoltype->update([
                'protocol_type' => $protocol_type,
                'details' => $details,
                'status' => $status,
            ]);

            // Return updated protocoltype as JSON response
            return response()->json($protocoltype);
        } catch (\Exception $e) {
            // Handle any exceptions (e.g., model not found)
            return response()->json(['error' => 'Failed to update protocoltype.'], 500);
        }
    }

    public function destroy(Request $request)
    {
        $protocoltype = ProtocolType::findOrFail($request->input('id'));
        $protocoltype->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        ProtocolType::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'ProtocolType  deleted successfully']);
    }
}
