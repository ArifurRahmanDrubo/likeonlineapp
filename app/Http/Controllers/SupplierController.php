<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Supplier = Supplier::all();
        return response()->json($Supplier);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',

        ]);

        Supplier::create($request->all());
        return response()->json([
            'message' => 'Supplier Create Succesfull'
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = $request->input('id');
        $category = Supplier::findOrFail($id);
        if ($category) {
            $category->update([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'mobile' => $request->input('mobile'),
            ]);
            return response()->json([
                'message' => 'Supplier Update Succesfull'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Something Wrong'
            ], 200);
        }
    }
    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $category = Supplier::findOrFail($id);
        if ($category) {
            $category->delete();
            return response()->json([
                'message' => 'Supplier Delete Succesfull'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Something Wrong'
            ], 200);
        }
    }
    public function deleteMultiple(Request $request)
    {
        Supplier::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Categories deleted successfully']);
    }
}
