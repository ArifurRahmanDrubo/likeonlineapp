<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use Illuminate\Http\Request;
use App\Models\ResellerPosition;
use Illuminate\Support\Facades\Auth;

class ResellerPositionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $Positions = ResellerPosition::where('mac_reseller_id', $reseller->id)->get();
        return response()->json($Positions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $Position = ResellerPosition::create([
            'name' => $request->input('name'),
            'status' => $request->input('status'),
            'mac_reseller_id' => $reseller->id,
        ]);
        return response()->json($Position, 201);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $name = $request->input('name');

        $status = $request->input('status'); // Assuming status is also updated

        try {
            // Find the Position by ID
            $Position = ResellerPosition::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();

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
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $Position = ResellerPosition::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $Position->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerPosition::where('mac_reseller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Position  deleted successfully']);
    }
}
