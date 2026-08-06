<?php
namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CloudinaryService
{
    /**
     * ১. নতুন ইমেজ আপলোড করা
     */
    public static function upload($file, string $folder = 'uploads'): array
    {
        $uploaded = $file->storeOnCloudinary($folder);

        return [
            'url'       => $uploaded->getSecurePath(),
            'public_id' => $uploaded->getPublicId(),
        ];
    }

    /**
     * ২. পুরনো ইমেজ ডিলিট করা
     */
    public static function delete(?string $publicId): bool
    {
        if ($publicId) {
            Cloudinary::destroy($publicId);
            return true;
        }
        return false;
    }

    /**
     * ৩. ইমেজ আপডেট করা (পুরনো ডিলিট + নতুন আপলোড)
     */
    public static function update($newFile, ?string $oldPublicId, string $folder = 'uploads'): array
    {
        // পুরনো ইমেজ থাকলে ক্লাউডিনারি থেকে ডিলিট করবে
        self::delete($oldPublicId);

        // নতুন ইমেজ আপলোড করবে
        return self::upload($newFile, $folder);
    }
}