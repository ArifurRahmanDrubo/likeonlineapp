<?php

namespace App\Http\Controllers;

use DateTime;
use Carbon\Carbon;
use App\Models\NewRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class NewRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->role && $user->role->name === 'client') {
            // Fetch requests only for the authenticated user (client)
            $newRequest = NewRequest::where('user_id', $user->id)->get();
            return response()->json($newRequest);
        } else {
            // Fetch all requests for non-client users
            $newRequest = NewRequest::all();
            return response()->json($newRequest);
        }
    }
    public function queryData(Request $request)
    {
        // Parse and format the dates from the request
        $todate = $request->input('todate');
        $fromdate = $request->input('fromdate');

        $toDate = $todate ? Carbon::parse($todate)->format('Y-m-d') : null;
        $fromDate = $fromdate ? Carbon::parse($fromdate)->format('Y-m-d') : null;

        $createdby = $request->input('createdby');
        $setupby = $request->input('setupby');
        $setupstatus = $request->input('setupstatus');

        // Start building the query only if filters are applied
        $query = Newrequest::query();

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        if (!empty($createdby)) {
            $query->where('createdby', $createdby);
        }

        if (!empty($setupby)) {
            $query->where('assign_to', $setupby);
        }

        if (!empty($setupstatus)) {
            $query->where('status', $setupstatus);
        }

        // If no filters are applied, return an error response
        if (!$request->hasAny(['todate', 'fromdate', 'createdby', 'setupby', 'setupstatus'])) {
            return response()->json([
                'message' => 'No filters were applied. Please provide at least one filter.'
            ], 400);
        }

        // Fetch the filtered results
        $data = $query->get();



        // Return the filtered results
        return response()->json($data);
    }

    public function store(Request $request)
    {

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'nid' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:255',
            'dateofbirth' => 'nullable',
            'registrationno' => 'nullable|string|max:255',
            'fathername' => 'nullable|string|max:255',
            'mothername' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'linkidn' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'district' => 'nullable|string|max:255',
            'upzila' => 'nullable|string|max:255',
            'roadnumber' => 'nullable|string|max:255',
            'housenumber' => 'nullable|string|max:255',
            'praddress' => 'nullable|string|max:255',
            'paraddress' => 'nullable|string|max:255',
            'subzone' => 'nullable|string|max:255',
            'zone' => 'required|string|max:255',
            'connectiontype' => 'required|string|max:255',
            'package' => 'required|string|max:255',
            'referencecontact' => 'nullable|string|max:255',
            'clienttype' => 'required|string|max:255',
            'commiteddate' => 'required',
            'referenceby' => 'nullable|string|max:255',
            'billingstatus' => 'required|string|max:255',
            'monthlybill' => 'required|numeric'
        ]);
        $validatedData['createdby'] = Auth::user()->name;



        $newRequest = NewRequest::find($request->input('id'));

        // Upload images to Cloudinary (update replaces the old image, create uploads new)
        foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
            if ($request->hasFile($field)) {
                $imageData = $newRequest
                    ? cloudinary_update($request->file($field), $newRequest->{$field . '_public_id'}, 'new_requests')
                    : cloudinary_upload($request->file($field), 'new_requests');
                $validatedData[$field] = $imageData['url'];
                $validatedData[$field . '_public_id'] = $imageData['public_id'];
            }
        }

        if ($newRequest) {
            $newRequest->update($validatedData);
            return response()->json(['message' => 'New Request updated successfully']);
        } else {
            NewRequest::create($validatedData);
            return response()->json(['message' => 'New Request created successfully']);
        }
    }
    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $NewRequest = NewRequest::find($id);
        if ($NewRequest) {
            // Delete associated images from Cloudinary before deleting the record
            foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                if ($NewRequest->{$field . '_public_id'}) {
                    cloudinary_delete($NewRequest->{$field . '_public_id'});
                }
            }
            $NewRequest->delete();
            return response()->json(['message' => 'NewRequests deleted successfully']);
        } else {
            return response()->json(['message' => 'NewRequests Not Found']);
        }
    }
    public function deleteMultiple(Request $request)
    {
        $newRequests = Newrequest::whereIn('id', $request->ids)->get();
        foreach ($newRequests as $newRequest) {
            // Delete associated images from Cloudinary before deleting the record
            foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                if ($newRequest->{$field . '_public_id'}) {
                    cloudinary_delete($newRequest->{$field . '_public_id'});
                }
            }
            $newRequest->delete();
        }
        return response()->json(['message' => 'NewRequests deleted successfully']);
    }

    public function assignto(Request $request)
    {
        $id = $request->input('id');
        $selectedEmployees = $request->input('selectedEmployees');
        $NewRequest = NewRequest::findOrFail($id);
        if ($NewRequest) {
            $NewRequest->update([
                'assign_to' => $selectedEmployees,
                'status' => 'Processing',
            ]);
            return response()->json(['message' => 'AssignEmployee successfully']);
        }
    }
    public function completed(Request $request)
    {
        $id = $request->input('id');
        $NewRequest = NewRequest::findOrFail($id);
        if ($NewRequest) {
            $NewRequest->update([
                'status' => 'Completed',
            ]);
            return response()->json(['message' => 'AssignEmployee successfully']);
        }
    }
}
