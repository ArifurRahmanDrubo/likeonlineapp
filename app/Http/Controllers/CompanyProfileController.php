<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyProfile;

class CompanyProfileController extends Controller
{
    public function index()
    {
        $companyProfiles = CompanyProfile::first();
        return response()->json($companyProfiles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'mobile1' => 'nullable|string|max:15',
            'mobile2' => 'nullable|string|max:15',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',

        ]);
        $id = $request->input('id');
        $company = CompanyProfile::find($id);
        if ($request->hasFile('image')) {
            // Upload to Cloudinary (update replaces the old image, create uploads new)
            $imageData = $company && $company->image_public_id
                ? cloudinary_update($request->file('image'), $company->image_public_id, 'company_profiles')
                : cloudinary_upload($request->file('image'), 'company_profiles');
            $data['image'] = $imageData['url'];
            $data['image_public_id'] = $imageData['public_id'];
        }
        if ($company) {
            $company->update($data);
        } else {
            CompanyProfile::create($data);
        }

        return response()->json(['message' => 'Company profile Updated successfully']);
    }
    // public function update(Request $request)
    // {
    //     $id = $request->input('id');
    //     $companyProfile = CompanyProfile::find($id);
    //     if ($companyProfile) {
    //         $request->validate([
    //             'title' => 'required|string|max:255',
    //             'address' => 'nullable|string|max:255',
    //             'mobile' => 'nullable|string|max:15',
    //             'email' => 'nullable|email|max:255',
    //             'website' => 'nullable|url|max:255',
    //             'remarks' => 'nullable|string|max:1000',
    //         ]);

    //         $companyProfile->update($request->all());
    //         return response()->json($companyProfile);
    //     } else {
    //         return response()->json(['message' => 'Company profile not found'], 404);
    //     }
    // }
    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $companyProfile = CompanyProfile::find($id);
        if ($companyProfile) {
            // Delete associated image from Cloudinary before deleting the record
            if ($companyProfile->image_public_id) {
                cloudinary_delete($companyProfile->image_public_id);
            }
            $companyProfile->delete();
            return response()->json(['message' => 'Company profile deleted successfully']);
        } else {
            return response()->json(['message' => 'Company profile not found'], 404);
        }
    }
}
