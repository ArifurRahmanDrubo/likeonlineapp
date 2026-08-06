<?php

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\Request;



class BoxController extends Controller
{
    public function index()
    {
        $boxes = Box::with('subzone', 'zone')->get();
        return response()->json($boxes);
    }
    public function store(Request $request)
    {
        $request->validate([
            'box_name' => 'required|string',
            'details' => 'nullable|string',
            'subzone_id' => 'nullable|exists:subzones,id',
            'zone_id' => 'nullable|exists:zones,id'
        ]);

        $box = box::create($request->all());
        return response()->json($box, 201);
    }



    public function update(Request $request)
    {
        $request->validate([
            'box_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'zone_id' => 'required|exists:zones,id' // Validate zone_id
        ]);
        $box = box::where('id', $request->input('id'))->first();

        $box->update($request->all());
        return response()->json($box);
    }

    public function destroy(Request $request)
    {
        $box = box::where('id', $request->input('id'))->first();
        $box->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        box::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'boxs deleted successfully']);
    }







    // Other methods like edit, update, delete can be added as needed
}
