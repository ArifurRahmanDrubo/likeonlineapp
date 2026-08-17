<?php

namespace App\Http\Controllers;

use App\Models\ResignRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResignruleController extends Controller
{
    public function index()
    {
        $Resignrules = ResignRule::all();
        return response()->json($Resignrules);
    }


    public function store(Request $request)
    {
        $request->validate([
            'resignrule' => 'required|max:255',
            'details' => 'nullable|string',
        ]);

        $Resignrule = ResignRule::create($request->all());

        return response()->json($Resignrule, 201);
    }

    public function show(ResignRule $ResignRule)
    {
        return response()->json($ResignRule);
    }

    public function update(Request $request)
    {
        $request->validate([
            'resignrule' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $id = $request->input('id');
        $resignrule = $request->input('resignrule');
        $details = $request->input('details');
        $Resignrule = ResignRule::findOrFail($id);
        $Resignrule->update([
            'Resignrulename' => $resignrule,
            'details' => $details
        ]);

        return response()->json($Resignrule);
    }

public function destroy(Request $request)
{


    $ResignRule = ResignRule::find($request->input('id'));

    if (!$ResignRule) {
        return response()->json([
            'message' => 'Resign rule not found.',
            'id_received' => $request->input('id'),
        ], 404);
    }

    $ResignRule->delete();

    return response()->json(null, 204);
}
    public function deleteMultiple(Request $request)
    {
        ResignRule::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Resignrules deleted successfully']);
    }
}
