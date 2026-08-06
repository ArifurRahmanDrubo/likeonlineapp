<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use Illuminate\Http\Request;
use App\Models\ResellerSubzone;
use Illuminate\Support\Facades\Auth;

class ResellerSubzoneController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $subzones = ResellerSubzone::where('mac_reseller_id', $reseller->id)->with('resellerzone')->get(); // Eager load the related zone
        return response()->json($subzones);
    }
    public function Subzonebyzone(Request $request)
    {
        $id = $request->input('id');
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $subzones = ResellerSubzone::where('mac_reseller_id', $reseller->id)->where('zone_id', $id)->get(); // Eager load the related zone
        return response()->json($subzones);
    }
    public function store(Request $request)
    {
        $request->validate([
            'subzone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'reseller_zone_id' => 'required|exists:zones,id' // Validate zone_id
        ]);

        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $subzone = ResellerSubzone::create([
            'subzone_name' => $request->input('subzone_name'),
            'details' => $request->input('details'),
            'reseller_zone_id' => $request->input('reseller_zone_id'),
            'mac_reseller_id' => $reseller->id,
        ]);
        return response()->json($subzone, 201);
    }

    public function show(ResellerSubzone $resellersubzone)
    {
        return response()->json($resellersubzone->load('resellerzone')); // Load the related zone
    }

    public function update(Request $request)
    {
        $request->validate([
            'subzone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'reseller_zone_id' => 'required|exists:zones,id' // Validate zone_id
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $subzone = ResellerSubzone::where('mac_reseller_id', $reseller->id)->where('id', $request->input('id'))->first();
        $subzone->update([
            'subzone_name' => $request->input('subzone_name'),
            'details' => $request->input('details'),
            'reseller_zone_id' => $request->input('reseller_zone_id'),
        ]);
        return response()->json($subzone);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $subzone = ResellerSubzone::where('mac_reseller_id', $reseller->id)->where('id', $request->input('id'))->first();
        $subzone->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerSubzone::where('mac_reseller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Subzones deleted successfully']);
    }
}
