<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();
        return response()->json($categories);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',

        ]);

        Category::create($request->all());
        return response()->json([
            'message' => 'Category Create Succesfull'
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $id = $request->input('id');
        $category = Category::findOrFail($id);
        if ($category) {
            $category->update([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);
            return response()->json([
                'message' => 'Category Update Succesfull'
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
        $category = Category::findOrFail($id);
        if ($category) {
            $category->delete();
            return response()->json([
                'message' => 'Category Delete Succesfull'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Something Wrong'
            ], 200);
        }
    }
    public function deleteMultiple(Request $request)
    {
        Category::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Categories deleted successfully']);
    }
}
