<?php

namespace App\Http\Controllers;

use App\Models\MacReseller;
use Illuminate\Http\Request;
use App\Models\ResellerDepartment;
use Illuminate\Support\Facades\Auth;

class ResellerDepartmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $department = ResellerDepartment::where('mac_reseller_id')->get();
        return response()->json($department);
    }


    public function store(Request $request)
    {
        $request->validate([
            'departmenttype' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $department = ResellerDepartment::create([
            'departmenttype' => $request->input('departmenttype'),
            'details' => $reseller->id,
        ]);

        return response()->json($department, 201);
    }



    public function update(Request $request)
    {
        $request->validate([
            'departmenttype' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $departmenttype = $request->input('departmenttype');
        $details = $request->input('details');
        $department = ResellerDepartment::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $department->update([
            'departmenttype' => $departmenttype,
            'details' => $details
        ]);

        return response()->json($department);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        $id = $request->input('id');
        $department = ResellerDepartment::where('mac_reseller_id', $reseller->id)->where('id', $id)->first();
        $department->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        $reseller = MacReseller::where('user_id', $user->id);
        ResellerDepartment::where('mac_reseller_id', $reseller->id)->whereIn('id', $request->ids)->delete();
        return response()->json(['message' => ' Departments deleted successfully']);
    }
}
