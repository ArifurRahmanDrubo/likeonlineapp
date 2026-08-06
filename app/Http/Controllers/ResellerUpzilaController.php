<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use Illuminate\Http\Request;
use App\Models\ResellerUpzila;
use Illuminate\Support\Facades\Auth;

class ResellerUpzilaController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $upzilas = ResellerUpzila::where('mac_reseller_id', $reseller->id)->get();
        return response()->json($upzilas);
    }


    public function store(Request $request)
    {
        $request->validate([
            'upzilaname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);

        $upzila = ResellerUpzila::create([
            'upzilaname' => $request->input('upzilaname'),
            'details' => $request->input('details'),
            'mac_reseller_id' => $reseller->id,
        ]);

        return response()->json($upzila, 201);
    }



    public function update(Request $request)
    {
        $request->validate([
            'upzilaname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $upzilaname = $request->input('upzilaname');
        $details = $request->input('details');
        $upzila = ResellerUpzila::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $upzila->update([
            'upzilaname' => $upzilaname,
            'details' => $details
        ]);

        return response()->json($upzila);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $upzila = ResellerUpzila::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $upzila->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerUpzila::where('mac_reseller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Upzilas deleted successfully']);
    }
}
