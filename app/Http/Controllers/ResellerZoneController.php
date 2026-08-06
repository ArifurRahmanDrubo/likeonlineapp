<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use App\Models\ResellerZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResellerZoneController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $zones = ResellerZone::where('mac_reseller_id', $reseller->id)->get();
        return response()->json($zones);
    }

    public function store(Request $request)
    {
        $request->validate([
            'zone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);

        $zone = ResellerZone::create([
            'zone_name' => $request->input('zone_name'),
            'details' => $request->input('details'),
            'mac_reseller_id' => $reseller->id,
        ]);
        return response()->json($zone, 201);
    }


    public function update(Request $request,)
    {
        $request->validate([
            'zone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $zone = ResellerZone::where('mac_reseller_id', $reseller->id);
        $zone->update([
            'zone_name' => $request->input('zone_name'),
            'details' => $request->input('details'),
            'mac_reseller_id' => $reseller->id,
        ]);
        return response()->json($zone);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $zone = ResellerZone::where('mac_reseller_id', $reseller->id);
        $zone->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerZone::where('mac_reseller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Zones deleted successfully']);
    }
}
