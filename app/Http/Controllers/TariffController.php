<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Tariff;
use Illuminate\Http\Request;
use App\Models\TariffPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TariffController extends Controller
{
    public function index()
    {
        $tariffs = Tariff::with('packages')->get();
        $data = $tariffs->map(function ($tariff) {
            return [
                'id' => $tariff->id,
                'TariffName' => $tariff->tariff_name,
                'AssignedMACResellers' => $tariff->assigned_mac_resellers,
                'TariffPackages' => $tariff->tariff_packages,  // Uses accessor
                'TariffServers' => $tariff->tariff_servers,    // Uses accessor
                'TariffProfiles' => $tariff->tariff_profiles,  // Uses accessor
                'CreatedBy' => $tariff->created_by,
                'CreatedOn' => $tariff->created_on, // Format date as needed

            ];
        });

        return response()->json([
            'Data' => $data
        ]);
    }

    public function store(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'tariff_name' => 'required|string',
            'assigned_mac_resellers' => 'nullable|string',
            'packages' => 'required|array',
            'packages.*.package_name' => 'required|string',
            'packages.*.server' => 'required|string',
            'packages.*.server_id' => 'required|integer',
            'packages.*.protocol' => 'required|string',
            'packages.*.profile' => 'required|string',
            'packages.*.package_rate' => 'required|numeric',
            'packages.*.validity_days' => 'required|integer',
            'packages.*.minimum_activation_days' => 'required|integer',
        ]);

        // Create Tariff
        $currentDate = Carbon::now()->format('d F Y');
        $user = Auth::user();
        $tariff = Tariff::create([
            'tariff_name' => $validated['tariff_name'],
            'assigned_mac_resellers' => $validated['assigned_mac_resellers'] ?? null,
            'created_by' => $user->name,
            'created_on' => $currentDate,

        ]);

        // Create Tariff Packages
        foreach ($validated['packages'] as $package) {
            $tariff->packages()->create($package);
        }

        // Return the created tariff with packages
        return response()->json($tariff->load('packages'), 201);
    }
    public function updatetariffs(Request $request,)
    {
        // Validate request
        $validated = $request->validate([
            'tariff_name' => 'required|string',
            'tariff_id' => 'required|integer|exists:tariffs,id', // Ensure tariff exists
            'packages' => 'required|array',
            'packages.*.id' => 'nullable|integer|exists:tariff_packages,id', // Package ID optional but must exist if provided
            'packages.*.package_name' => 'required|string',
            'packages.*.server' => 'required|string',
            'packages.*.server_id' => 'required|integer',
            'packages.*.protocol' => 'required|string',
            'packages.*.profile' => 'required|string',
            'packages.*.package_rate' => 'required|numeric',
            'packages.*.validity_days' => 'required|integer',
            'packages.*.minimum_activation_days' => 'required|integer',
        ]);

        // Find the tariff
        $tariff = Tariff::findOrFail($validated['tariff_id']);

        // Update the tariff name
        $tariff->update([
            'tariff_name' => $validated['tariff_name'],
        ]);

        // Retrieve the existing package IDs for comparison
        $existingPackageIds = $tariff->packages()->pluck('id')->toArray();

        // Track IDs of packages that need to be deleted
        $packageIdsToDelete = array_diff($existingPackageIds, array_column($validated['packages'], 'id'));

        // Delete packages that are no longer present
        if (!empty($packageIdsToDelete)) {
            TariffPackage::whereIn('id', $packageIdsToDelete)->delete();
        }

        // Update or create packages
        foreach ($validated['packages'] as $packageData) {
            if (isset($packageData['id'])) {
                // Update existing package
                $package = TariffPackage::findOrFail($packageData['id']);
                $package->update($packageData);
            } else {
                // Create new package
                $tariff->packages()->create($packageData);
            }
        }

        // Return the updated tariff with packages
        return response()->json($tariff->load('packages'), 200);
    }
    public function getTariffByid(Request $request)
    {
        $id = $request->input('id');
        $tariff = Tariff::with('packages')->findOrFail($id);
        return response()->json($tariff);
    }
    public function deleteTariff(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'tariff_id' => 'required|integer|exists:tariffs,id', // Ensure tariff exists
        ]);

        // Find the tariff
        $tariff = Tariff::findOrFail($validated['tariff_id']);

        // Delete associated packages
        $tariff->packages()->delete();

        // Delete the tariff
        $tariff->delete();

        // Return a response indicating success
        return response()->json(['message' => 'Tariff  deleted successfully.'], 200);
    }
}
