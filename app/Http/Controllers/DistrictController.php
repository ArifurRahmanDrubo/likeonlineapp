<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function index()
    {
        $districts = District::all();
        return response()->json($districts);
    }


    public function store(Request $request)
    {
        $request->validate([
            'districtname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);

        $district = District::create($request->all());

        return response()->json($district, 201);
    }

    public function show(District $district)
    {
        return response()->json($district);
    }

    public function update(Request $request)
    {
        $request->validate([
            'districtname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $id = $request->input('id');
        $districtname = $request->input('districtname');
        $details = $request->input('details');
        $district = District::findOrFail($id);
        $district->update([
            'districtname' => $districtname,
            'details' => $details
        ]);

        return response()->json($district);
    }

    public function destroy(Request $request)
    {
        $district = District::findOrFail($request->input('id'));
        $district->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        District::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Districts deleted successfully']);
    }
}
