<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetup;
use App\Models\SystemPermission;
use Illuminate\Http\Request;

class InvoiceSetupController extends Controller
{
    public function index()
    {
        $system = SystemPermission::first();

        $company_name_enabled = $system && $system->company_name_invoice === 'enable' ? true : false;

        $InvoiceSetups = InvoiceSetup::first();

        if (!$InvoiceSetups) {
            return response()->json(null, 200);
        }

        if (!$company_name_enabled) {
            // Remove the company_name field from the response if it's disabled
            $InvoiceSetups->makeHidden(['company_name']);
        }

        return response()->json($InvoiceSetups);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
            'invoice_title' => 'nullable|max:255',
            'invoice_note' => 'nullable|string|max:1000',

        ]);
        $id = $request->input('id');
        $invoicesetup = InvoiceSetup::find($id);

        if ($request->hasFile('image')) {
            // Upload to Cloudinary (update replaces the old image, create uploads new)
            $imageData = $invoicesetup && $invoicesetup->image_public_id
                ? cloudinary_update($request->file('image'), $invoicesetup->image_public_id, 'invoice_setup')
                : cloudinary_upload($request->file('image'), 'invoice_setup');
            $data['image'] = $imageData['url'];
            $data['image_public_id'] = $imageData['public_id'];
        }

        if ($invoicesetup) {
            $invoicesetup->update($data);
        } else {
            InvoiceSetup::create($data);
        }

        return response()->json(['message' => ' Updated successfully']);
    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $invoiceSetup = InvoiceSetup::find($id);
        if ($invoiceSetup) {
            // Delete associated image from Cloudinary before deleting the record
            if ($invoiceSetup->image_public_id) {
                cloudinary_delete($invoiceSetup->image_public_id);
            }
            $invoiceSetup->delete();
            return response()->json(['message' => 'Invoice setup deleted successfully']);
        }
        return response()->json(['message' => 'Invoice setup not found'], 404);
    }
}
