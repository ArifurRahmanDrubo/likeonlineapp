<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use App\Models\ResellerBox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResellerBoxController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $boxes = ResellerBox::where('mac_reseller_id', $reseller->id)->with('subzone', 'zone')->get();
        return response()->json($boxes);
    }
    public function store(Request $request)
    {
        $request->validate([
            'box_name' => 'required|string',
            'details' => 'nullable|string',
            'reseller_subzone_id' => 'nullable|exists:reseller_subzones,id',
            'reseller_zone_id' => 'nullable|exists:reseller_zones,id'
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $box = ResellerBox::create([
            'box_name' => $request->input('box_name'),
            'details' => $request->input('details'),
            'reseller_subzone_id' => $request->input('reseller_subzone_id'),
            'reseller_zone_id' => $request->input('reseller_zone_id'),
            'mac_reseller_id' => $reseller->id,
        ]);
        return response()->json($box, 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            'box_name' => 'required|string|max:255',
            'details' => 'nullable|string',

        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $box = ResellerBox::where('mac_reseller_id', $reseller->id)->where('id', $request->input('id'))->first();
        $box->update([
            'box_name' => $request->input('box_name'),
            'details' => $request->input('details'),
            'reseller_subzone_id' => $request->input('reseller_subzone_id'),
            'reseller_zone_id' => $request->input('reseller_zone_id'),

        ]);
        return response()->json($box);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $box = ResellerBox::where('mac_reseller_id', $reseller->id)->where('id', $request->input('id'))->first();
        $box->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerBox::where('mac_resller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'boxs deleted successfully']);
    }
}
