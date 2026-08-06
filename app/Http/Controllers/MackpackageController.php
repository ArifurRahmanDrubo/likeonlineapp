<?php

namespace App\Http\Controllers;

use App\Models\Mackpackage;
use Illuminate\Http\Request;

class MackpackageController extends Controller
{
    public function index()
    {
        $mackpackages = Mackpackage::all();
        return response()->json($mackpackages);
    }


    public function store(Request $request)
    {
        $request->validate([
            'packagename' => 'required|max:255',
            'Bandwith_Allowcation_MB' => 'required|integer|max:255',
            'details' => 'nullable|string',
        ]);

        $Mackpackage = Mackpackage::create($request->all());

        return response()->json($Mackpackage, 201);
    }

    public function show(Mackpackage $Mackpackage)
    {
        return response()->json($Mackpackage);
    }

    public function update(Request $request)
    {
        $request->validate([
            'packagename' => 'required|max:255',
            'Bandwith_Allowcation_MB' => 'required|integer|max:255',
            'details' => 'nullable|string'

        ]);
        $id = $request->input('id');
        $packagename = $request->input('packagename');
        $Bandwith_Allowcation_MB = $request->input('Bandwith_Allowcation_MB');
        $details = $request->input('details');
        $Mackpackage = Mackpackage::findOrFail($id);
        $Mackpackage->update([
            'packagename' => $packagename,
            'Bandwith_Allowcation_MB' => $Bandwith_Allowcation_MB,
            'details' => $details

        ]);

        return response()->json($Mackpackage);
    }

    public function destroy(Request $request)
    {
        $Mackpackage = Mackpackage::findOrFail($request->input('id'));
        $Mackpackage->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        Mackpackage::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Mackpackage deleted successfully']);
    }
}
