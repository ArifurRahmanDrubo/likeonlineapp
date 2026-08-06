<?php

namespace App\Http\Controllers;

use App\Models\Upzila;
use Illuminate\Http\Request;

class UpzilaController extends Controller
{
    public function index()
    {
        $upzilas = Upzila::all();
        return response()->json($upzilas);
    }


    public function store(Request $request)
    {
        $request->validate([
            'upzilaname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);

        $upzila = Upzila::create($request->all());

        return response()->json($upzila, 201);
    }

    public function show(Upzila $Upzila)
    {
        return response()->json($Upzila);
    }

    public function update(Request $request)
    {
        $request->validate([
            'upzilaname' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $id = $request->input('id');
        $upzilaname = $request->input('upzilaname');
        $details = $request->input('details');
        $upzila = Upzila::findOrFail($id);
        $upzila->update([
            'upzilaname' => $upzilaname,
            'details' => $details
        ]);

        return response()->json($upzila);
    }

    public function destroy(Request $request)
    {
        $Upzila = Upzila::findOrFail($request->input('id'));
        $Upzila->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        Upzila::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Upzilas deleted successfully']);
    }
}
