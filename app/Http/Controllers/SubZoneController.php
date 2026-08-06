<?php

namespace App\Http\Controllers;

use App\Models\Subzone;
use Illuminate\Http\Request;

namespace App\Http\Controllers;

use App\Models\Subzone;
use Illuminate\Http\Request;

class SubZoneController extends Controller
{
    public function index()
    {
        $subzones = Subzone::with('zone')->get(); // Eager load the related zone
        return response()->json($subzones);
    }
    public function Subzonebyzone(Request $request)
    {
        $id = $request->input('id');
        $subzones = Subzone::where('zone_id', $id)->get();
        return response()->json($subzones);
    }
    public function store(Request $request)
    {
        $request->validate([
            'subzone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'zone_id' => 'required|exists:zones,id' // Validate zone_id
        ]);

        $subzone = Subzone::create($request->all());
        return response()->json($subzone, 201);
    }

    public function show(Subzone $subzone)
    {
        return response()->json($subzone->load('zone')); // Load the related zone
    }

    public function update(Request $request)
    {
        $request->validate([
            'subzone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
            'zone_id' => 'required|exists:zones,id' // Validate zone_id
        ]);
        $subzone = Subzone::findOrFail($request->input('id'));
        $subzone->update($request->all());
        return response()->json($subzone);
    }

    public function destroy(Request $request)
    {
        $subzone = Subzone::findOrFail($request->input('id'));
        $subzone->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        Subzone::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Subzones deleted successfully']);
    }
}
