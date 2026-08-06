<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $department = Department::all();
        return response()->json($department);
    }


    public function store(Request $request)
    {
        $request->validate([
            'departmenttype' => 'required|max:255',
            'details' => 'nullable|string',
        ]);

        $department = Department::create($request->all());

        return response()->json($department, 201);
    }

    public function show(Department $department)
    {
        return response()->json($department);
    }

    public function update(Request $request)
    {
        $request->validate([
            'departmenttype' => 'required|max:255',
            'details' => 'nullable|string',
        ]);
        $id = $request->input('id');
        $departmenttype = $request->input('departmenttype');
        $details = $request->input('details');
        $department = Department::findOrFail($id);
        $department->update([
            'departmenttype' => $departmenttype,
            'details' => $details
        ]);

        return response()->json($department);
    }

    public function destroy(Request $request)
    {
        $department = Department::findOrFail($request->input('id'));
        $department->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        Department::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => ' Departments deleted successfully']);
    }
}
