<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerBillingStatus;

class CustomerBillingStatusController extends Controller
{
    public function index()
    {
        $billingstatuses = CustomerBillingStatus::all();
        return response()->json($billingstatuses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'billingstatus' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);

        $billingstatuses = CustomerBillingStatus::create($request->all());
        return response()->json($billingstatuses, 201);
    }

    public function show(CustomerBillingStatus $CustomerBillingStatus)
    {
        return response()->json($CustomerBillingStatus);
    }

    public function update(Request $request)
    {
        $request->validate([
            'billingstatus' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);
        $id = $request->input('id');
        $billingstatus = $request->input('billingstatus');
        $details = $request->input('details');
        $customerBillingStatus = CustomerBillingStatus::findOrFail($id);
        $customerBillingStatus->update([
            'billingstatus' => $billingstatus,
            'details' => $details
        ]);
        return response()->json($customerBillingStatus);
    }

    public function destroy(Request $request)
    {
        $customerBillingStatus = CustomerBillingStatus::findOrFail($request->input('id'));
        $customerBillingStatus->delete();
        return response()->json(null, 204);
    }

    public function deleteMultiple(Request $request)
    {
        CustomerBillingStatus::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'BillingStatus deleted successfully']);
    }
}
