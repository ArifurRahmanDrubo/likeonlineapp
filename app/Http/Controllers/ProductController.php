<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Products = Product::all();
        return response()->json($Products);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',

        ]);

        Product::create($request->all());
        return response()->json([
            'message' => 'Product Create Succesfull'
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = $request->input('id');
        $category = Product::findOrFail($id);
        if ($category) {
            $category->update([
                'name' => $request->input('name'),

                'category_id' => $request->input('category_id'),
            ]);
            return response()->json([
                'message' => 'Product Update Succesfull'
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
        $category = Product::findOrFail($id);
        if ($category) {
            $category->delete();
            return response()->json([
                'message' => 'Product Delete Succesfull'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Something Wrong'
            ], 200);
        }
    }
    public function deleteMultiple(Request $request)
    {
        Product::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Categories deleted successfully']);
    }
}
