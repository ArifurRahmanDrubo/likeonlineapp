<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\EquipmentUse;
use Illuminate\Http\Request;

class EquipmentUseController extends Controller
{
    public function index()
    {
        $EquipmentUses = EquipmentUse::with('product')->get();
        return response()->json($EquipmentUses);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        EquipmentUse::create($request->all());
        $product = Product::find($request->input('product_id'));
        $product->stock -= $request->input('qty');
        $product->save();
        return response()->json([
            'message' => 'EquipmentUse Create Succesfull'
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */

    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $equipment = EquipmentUse::findOrFail($id);
        if ($equipment) {
            $equipment->delete();
            return response()->json([
                'message' => 'EquipmentUse Delete Succesfull'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Something Wrong'
            ], 200);
        }
    }
    public function deleteMultiple(Request $request)
    {
        EquipmentUse::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Categories deleted successfully']);
    }
}
