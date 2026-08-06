<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use Illuminate\Http\Request;
use App\Models\ResellerDistrict;
use Illuminate\Support\Facades\Auth;

class ResellerDistrictController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $districts = ResellerDistrict::where('mac_reseller_id', $reseller->id)->all();
        return response()->json($districts);
    }


    public function store(Request $request)
    {
        $request->validate([
            'districtname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);


        $district = ResellerDistrict::create([
            'districtname' => $request->input('districtname'),
            'details' => $request->input('details'),
            'mac_reseller_id' => $reseller->id,
        ]);

        return response()->json($district, 201);
    }


    public function update(Request $request)
    {
        $request->validate([
            'districtname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $districtname = $request->input('districtname');
        $details = $request->input('details');
        $district = ResellerDistrict::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $district->update([
            'districtname' => $districtname,
            'details' => $details,

        ]);

        return response()->json($district);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $district = ResellerDistrict::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $district->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerDistrict::where('mac_reseller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Districts deleted successfully']);
    }
}
