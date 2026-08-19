<?php

namespace App\Http\Controllers;

use App\Models\WebPackage;
use Illuminate\Http\Request;

class WebPackageController extends Controller
{
    /**
     * Admin: list all web packages (ordered by sort_order, then id).
     */
    public function index()
    {
        return response()->json(WebPackage::orderBy('sort_order')->orderBy('id')->get());
    }

    /**
     * Admin: create a new web package.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePackage($request);
        $webPackage = WebPackage::create($validated);

        return response()->json($webPackage, 201);
    }

    /**
     * Admin: show a single web package.
     */
    public function show($id)
    {
        return response()->json(WebPackage::findOrFail($id));
    }

    /**
     * Admin: update an existing web package (id sent in the request body).
     */
    public function update(Request $request)
    {
        $validated = $this->validatePackage($request);
        $webPackage = WebPackage::findOrFail($request->input('id'));
        $webPackage->update($validated);

        return response()->json($webPackage);
    }

    /**
     * Admin: delete a web package (id sent in the request body).
     */
    public function destroy(Request $request)
    {
        $webPackage = WebPackage::findOrFail($request->input('id'));
        $webPackage->delete();

        return response()->json(null, 204);
    }

    /**
     * Public: active packages for the public website, sorted by sort_order
     * then id so admins can control the display order.
     */
    public function getPublicPackages()
    {
        $packages = WebPackage::where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($packages);
    }

    protected function validatePackage(Request $request)
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|string|max:50',
            'package_type' => 'required|in:home,corporate,upcoming',
            'button_label' => 'nullable|string|max:50',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'status' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
    }
}
