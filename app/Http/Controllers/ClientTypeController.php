<?php

namespace App\Http\Controllers;

use App\Models\ClientType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientTypeController extends Controller
{
    // Display a listing of the resource.
    public function index()
    {
        $clientTypes = ClientType::all();
        return response()->json($clientTypes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_type' => 'required|string|max:255',
            'details'     => 'nullable|string',
            'image'       => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            // Cloudinary-তে আপলোড করা
            $imageData = cloudinary_upload($request->file('image'), 'clientType-img');

            $data['image']     = $imageData['url'];
            $data['public_id'] = $imageData['public_id'];
        }

        $clientType = ClientType::create($data);

        return response()->json($clientType, 201);
    }

    public function update(Request $request)
    {
        $id = $request->input('id');
        $clientType = ClientType::find($id);

        if (!$clientType) {
            return response()->json(['error' => 'ClientType not found.'], 404);
        }

        $request->validate([
            'client_type' => 'required|string|max:255',
            'details'     => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        try {
            $data = [
                'client_type' => $request->input('client_type'),
                'details'     => $request->input('details'),
            ];

            // যদি নতুন ইমেজ সিলেক্ট করা হয়
            if ($request->hasFile('image')) {
                // পুরনো ইমেজ ডিলিট করে নতুন ইমেজ আপলোড করবে
                $imageData = cloudinary_update(
                    $request->file('image'),
                    $clientType->public_id, // পুরনো public_id
                    'clientType-img'
                );

                $data['image']     = $imageData['url'];
                $data['public_id'] = $imageData['public_id'];
            }

            $clientType->update($data);

            return response()->json($clientType);
        } catch (\Exception $e) {
            Log::error('Failed to update ClientType: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update ClientType.', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        $clientType = ClientType::find($request->input('id'));

        if (!$clientType) {
            return response()->json(['error' => 'ClientType not found.'], 404);
        }

        // Cloudinary থেকে ইমেজ রিমুভ করা
        if ($clientType->public_id) {
            cloudinary_delete($clientType->public_id);
        }

        $clientType->delete();

        return response()->json(['message' => 'ClientType deleted successfully.'], 200);
    }

    public function deleteMultiple(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
        ]);

        $clientTypes = ClientType::whereIn('id', $request->ids)->get();

        foreach ($clientTypes as $clientType) {
            // প্রত্যেকটি রেকর্ড মুছে ফেলার আগে Cloudinary থেকে ইমেজ রিমুভ করবে
            if ($clientType->public_id) {
                cloudinary_delete($clientType->public_id);
            }
            $clientType->delete();
        }

        return response()->json(['message' => 'ClientTypes deleted successfully']);
    }
}