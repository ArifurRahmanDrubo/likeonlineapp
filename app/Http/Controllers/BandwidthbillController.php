<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bandwidthbill;

class BandwidthbillController extends Controller
{
    public function index()
    {
        $Bandwidthbills = Bandwidthbill::all();
        return response()->json($Bandwidthbills);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        Bandwidthbill::create($request->all());

        return response()->json([
            'message' => 'Bandwidthbill Create Succesfull'
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */

    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $equipment = Bandwidthbill::findOrFail($id);
        if ($equipment) {
            $equipment->delete();
            return response()->json([
                'message' => 'Bandwidthbill Delete Succesfull'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Something Wrong'
            ], 200);
        }
    }
    public function deleteMultiple(Request $request)
    {
        Bandwidthbill::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Bandwidthbill deleted successfully']);
    }
}
