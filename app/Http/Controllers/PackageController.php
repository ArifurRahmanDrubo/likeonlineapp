<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::all();
        return response()->json($packages);
    }


    public function store(Request $request)
    {
        $request->validate([
            'packagename' => 'required|max:255',
            'packagetype' => 'required|max:255',
            'bandwithallowcationmb' => 'required|integer|max:255',
            'price' => 'required|max:255',
            'description' => 'nullable|string',
        ]);

        $package = Package::create($request->all());

        return response()->json($package, 201);
    }

    public function show(Package $package)
    {
        return response()->json($package);
    }

    public function update(Request $request)
    {
        $request->validate([
            'packagename' => 'required|max:255',
            'packagetype' => 'required|max:255',
            'bandwithallowcationmb' => 'required|integer|max:255',
            'price' => 'required|max:255',
            'description' => 'nullable|string',
        ]);
        $id = $request->input('id');
        $packagename = $request->input('packagename');
        $packagetype = $request->input('packagetype');
        $bandwithallowcationmb = $request->input('bandwithallowcationmb');
        $price = $request->input('price');
        $description = $request->input('description');
        $package = Package::findOrFail($id);
        $package->update([
            'packagename' => $packagename,
            'packagetype' => $packagetype,
            'bandwithallowcationmb' => $bandwithallowcationmb,
            'price' => $price,
            'description' => $description,
        ]);

        return response()->json($package);
    }

    public function destroy(Request $request)
    {
        $package = Package::findOrFail($request->input('id'));
        $package->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        Package::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Packages deleted successfully']);
    }
}
