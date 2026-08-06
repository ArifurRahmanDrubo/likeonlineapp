<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index()
    {
        $zones = Zone::all();
        return response()->json($zones);
    }

    public function store(Request $request)
    {
        $request->validate([
            'zone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);

        $zone = Zone::create($request->all());
        return response()->json($zone, 201);
    }

    public function show(Zone $zone)
    {
        return response()->json($zone);
    }

    public function update(Request $request)
    {
        $request->validate([
            'zone_name' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);
        $zone = Zone::findOrFail($request->input('id'));
        $zone->update($request->all());
        return response()->json($zone);
    }

    public function destroy(Request $request)
    {
        $zone = Zone::findOrFail($request->input('id'));
        $zone->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        Zone::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Zones deleted successfully']);
    }
}
