<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MacReseller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MacResellerController extends Controller
{
    public function index()
    {
        return response()->json(MacReseller::with('menus')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'nid' => 'nullable|string',
            'phoneno' => 'nullable|string',
            'mobile' => 'nullable|string',
            'email' => 'nullable|email',
            'reseller_prefix' => 'nullable|string',
            'reseller_code' => 'nullable|string',
            'district' => 'nullable|string',
            'upzila' => 'nullable|string',
            'setprefix' => 'nullable|string',
            'zone' => 'nullable|string',
            'reseller_type' => 'nullable|string',
            'rechargableamount' => 'nullable|numeric',
            'address' => 'nullable|string',
            'bussinessname' => 'nullable|string',
            'tariff' => 'required|string',
            'tariff_id' => 'required|string',
            'disabled_client' => 'nullable',
            'minimumbalance' => 'nullable|numeric',
            'username' => 'required|string',
            'password' => 'required|string',
            'confirm_password' => 'nullable|string',
            'confirm_password' => 'required|string|same:password',
            'menu' => 'nullable|json', // Handle menu data
        ]);
        if ($request->hasFile('macresellerlogo')) {
            $img = $request->file('macresellerlogo');
            $t = time();
            $file_name = $img->getClientOriginalName();
            $img_name = "{$t}-{$file_name}";
            $img_url = "uploads/macresellerlogo-img/{$img_name}";
            $img->move(public_path('uploads/macresellerlogo-img'), $img_name);
            $validated['macresellerlogo'] = $img_url;
        }

        // Create MacReseller
        $macReseller = MacReseller::create([
            'name' => $validated['name'],
            'nid' => $validated['nid'],
            'phoneno' => $validated['phoneno'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'reseller_prefix' => $validated['reseller_prefix'],
            'reseller_code' => $validated['reseller_code'],
            'district' => $validated['district'],
            'upzila' => $validated['upzila'],
            'setprefix' => $validated['setprefix'],
            'zone' => $validated['zone'],
            'reseller_type' => $validated['reseller_type'],
            'rechargableamount' => $validated['rechargableamount'],
            'address' => $validated['address'],
            'bussinessname' => $validated['bussinessname'],
            'tariff' => $validated['tariff'],
            'tariff_id' => $validated['tariff_id'],
            'disabled_client' => $validated['disabled_client'],
            'minimumbalance' => $validated['minimumbalance'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'confirm_password' => $validated['confirm_password'],
            'macresellerlogo' => $validated['macresellerlogo'],
            // Handle avatarImage and macresellerlogo
        ]);
        // Handle menu data

        if ($request->has('menu')) {
            $menuData = json_decode($validated['menu'], true);

            foreach ($menuData as $menu) {
                $parent = Menu::create([
                    'mac_reseller_id' => $macReseller->id,
                    'label' => $menu['label'],
                    'value' => $menu['value'],
                    'checked' => $menu['checked'],
                    'parent_id' => null
                ]);

                if (isset($menu['children'])) {
                    foreach ($menu['children'] as $child) {
                        Menu::create([
                            'mac_reseller_id' => $macReseller->id,
                            'label' => $child['label'],
                            'value' => $child['value'],
                            'checked' => $child['checked'],
                            'parent_id' => $parent->id
                        ]);
                    }
                }
            }
        }

        return response()->json(['message' => 'MacReseller created successfully.'], 201);
    }

    public function show($id)
    {
        $macReseller = MacReseller::with('menus')->findOrFail($id);
        return response()->json($macReseller);
    }

    public function updateMacReseller(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'id' => 'required|integer|exists:mac_resellers,id',
            'name' => 'required|string',
            'nid' => 'nullable|string',
            'phoneno' => 'nullable|string',
            'mobile' => 'nullable|string',
            'email' => 'nullable|email',
            'reseller_prefix' => 'nullable|string',
            'reseller_code' => 'nullable|string',
            'district' => 'nullable|string',
            'upzila' => 'nullable|string',
            'setprefix' => 'nullable|string',
            'zone' => 'nullable|string',
            'reseller_type' => 'nullable|string',
            'rechargableamount' => 'nullable|numeric',
            'address' => 'nullable|string',
            'bussinessname' => 'nullable|string',
            'tariff' => 'required|string',
            'tariff_id' => 'required|string',
            'disabled_client' => 'nullable',
            'minimumbalance' => 'nullable|numeric',


            'menu' => 'nullable|json', // Handle menu data
        ]);
        $id = $request->input('id');
        $macReseller = MacReseller::findOrFail($id);
        if ($request->hasFile('macresellerlogo')) {
            $img = $request->file('macresellerlogo');
            $t = time();
            $file_name = $img->getClientOriginalName();
            $img_name = "{$t}-{$file_name}";
            $img_url = "uploads/macresellerlogo-img/{$img_name}";
            $img->move(public_path('uploads/macresellerlogo-img'), $img_name);
            $validated['macresellerlogo'] = $img_url;

            if ($macReseller && $macReseller->macresellerlogo) {
                @unlink(public_path($macReseller->macresellerlogo));
            }
        } else {
            $validated['macresellerlogo'] = $macReseller->macresellerlogo;
        }

        // Find and update MacReseller


        $macReseller->update([
            'name' => $validated['name'],
            'nid' => $validated['nid'],
            'phoneno' => $validated['phoneno'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'reseller_prefix' => $validated['reseller_prefix'],
            'reseller_code' => $validated['reseller_code'],
            'district' => $validated['district'],
            'upzila' => $validated['upzila'],
            'setprefix' => $validated['setprefix'],
            'zone' => $validated['zone'],
            'reseller_type' => $validated['reseller_type'],
            'rechargableamount' => $validated['rechargableamount'],
            'address' => $validated['address'],
            'bussinessname' => $validated['bussinessname'],
            'tariff' => $validated['tariff'],
            'tariff_id' => $validated['tariff_id'],
            'disabled_client' => $validated['disabled_client'],
            'minimumbalance' => $validated['minimumbalance'],
            'macresellerlogo' => $validated['macresellerlogo'],

        ]);

        // Update password if provided


        // Handle menu data
        if ($request->has('menu')) {
            $menuData = json_decode($validated['menu'], true);

            // First, delete all existing menu items for this reseller
            Menu::where('mac_reseller_id', $macReseller->id)->delete();

            // Add or update menu items
            foreach ($menuData as $menu) {
                $parent = Menu::create([
                    'label' => $menu['label'],
                    'value' => $menu['value'],
                    'checked' => $menu['checked'],
                    'parent_id' => null,
                    'mac_reseller_id' => $macReseller->id,
                ]);

                if (isset($menu['children'])) {
                    foreach ($menu['children'] as $child) {
                        Menu::create([
                            'label' => $child['label'],
                            'value' => $child['value'],
                            'checked' => $child['checked'],
                            'parent_id' => $parent->id,
                            'mac_reseller_id' => $macReseller->id,
                        ]);
                    }
                }
            }
        }

        return response()->json(['message' => 'MacReseller updated successfully.'], 200);
    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $macReseller = MacReseller::findOrFail($id);

        if ($macReseller && $macReseller->macresellerlogo) {
            @unlink(public_path($macReseller->macresellerlogo));
        }
        Menu::where('mac_reseller_id', $macReseller->id)->delete();
        $macReseller->delete();

        return response()->json(['message' => 'MacReseller deleted successfully']);
    }
    public function updateResellerType(Request $request)
    {
        try {
            $id = $request->input('id');
            $macReseller = MacReseller::findOrFail($id);
            $macReseller->update([
                'reseller_type' => $request->input('type'),
            ]);
            return response()->json(['message' => 'MacReseller typeUpdated successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getMacReseller($id)
    {
        try {
            $macReseller = MacReseller::with('menus')->find($id);

            if (!$macReseller) {
                return response()->json([
                    'message' => 'Mac Reseller not found'
                ], 404);
            }

            return response()->json($macReseller, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getMacResellerbyId(Request $request)
    {
        $id = $request->input('id');
        $data = MacReseller::findOrFail($id);
        return response()->json($data);
    }
    public function getMacResellerAllDatabyId(Request $request)
    {
        $id = $request->input('id');
        $data = MacReseller::where('id', $id)->with('tariff')->first();
        return response()->json($data);
    }
}
