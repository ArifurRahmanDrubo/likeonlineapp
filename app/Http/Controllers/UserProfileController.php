<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserProfileController extends Controller
{


    public function getUserProfile()
    {
        $user = Auth::user();
        $role = $user->role->name;
        $userId = Auth::id();
        $userProfile = UserProfile::where('user_id', $userId)->first();

        return response()->json(['userProfile' => $userProfile, 'user' => $user,]);
    }
    public function UserProfile(Request $request)
    {
        $data = $request->all();
        $userId = Auth::id();

        // Check if user profile exists
        $userProfile = UserProfile::where('user_id', $userId)->first();

        if ($request->hasFile('image')) {
            // Upload to Cloudinary (update replaces the old image, create uploads new)
            $imageData = $userProfile && $userProfile->image_public_id
                ? cloudinary_update($request->file('image'), $userProfile->image_public_id, 'user_profiles')
                : cloudinary_upload($request->file('image'), 'user_profiles');
            $data['image'] = $imageData['url'];
            $data['image_public_id'] = $imageData['public_id'];
        }

        if ($userProfile) {
            // Update existing profile
            $userProfile->update($data);
            return response()->json(['message' => 'Profile updated successfully']);
        } else {
            // Create new profile
            $data['user_id'] = $userId;
            UserProfile::create($data);
            return response()->json(['message' => 'Profile created successfully']);
        }
    }
    public function Userdelete(Request $request)
    {
        $id = Auth::id();
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Delete the profile image from Cloudinary before deleting the user
        $userProfile = UserProfile::where('user_id', $id)->first();
        if ($userProfile && $userProfile->image_public_id) {
            cloudinary_delete($userProfile->image_public_id);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
