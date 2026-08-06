<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use App\Models\CustomerNewLine;
use App\Models\Package;

class WebCustomerController extends Controller
{
    public function webpackage(Request $request)
    {
        $corporatepackage = Package::where('packagetype', 'Corporate')->get();

        $homepackage = Package::where('packagetype', 'Home')->get();
        return response()->json([
            'homepackage' => $homepackage,
            'corporatepackage' => $corporatepackage,
        ]);
    }
    public function index()
    {
        $contacts = Contact::all();
        return response()->json($contacts);
    }
    public function storeContact(Request $request)
    {
        // Validate request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:50',
            'subject' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:255',
        ]);

        // Create a new Contact
        $contact = Contact::create($validatedData);

        // Return a response (could be a redirect, JSON, or a view)
        return response()->json([
            'message' => 'Contact created successfully',
            'contact' => $contact,
        ], 201);
    }
    public function destroy(Request $request)
    {
        $contact = Contact::findOrFail($request->input('id'));
        $contact->delete();

        return response()->json(null, 204);
    }
    public function deleteMultiple(Request $request)
    {
        Contact::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Contacts deleted successfully']);
    }
    public function storeCustomerNewLine(Request $request)
    {
        // Validate request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'location' => 'required|string|max:100',
            'package' => 'required|string|max:100',
            'otc' => 'required|string|max:100',

        ]);

        // Create a new CustomerNewLine
        $customerNewLine = CustomerNewLine::create($validatedData);

        // Return a response (could be a redirect, JSON, or a view)
        return response()->json([
            'message' => 'Customer New Line created successfully',
            'customerNewLine' => $customerNewLine,
        ], 201);
    }
    public function getNewLineRequest()
    {
        $CustomerNewLines = CustomerNewLine::all();
        return response()->json($CustomerNewLines);
    }
    public function delete(Request $request)
    {
        $CustomerNewLine = CustomerNewLine::findOrFail($request->input('id'));
        $CustomerNewLine->delete();

        return response()->json(null, 204);
    }
    public function deleteMultipleNewline(Request $request)
    {
        CustomerNewLine::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'CustomerNewLines deleted successfully']);
    }
}
