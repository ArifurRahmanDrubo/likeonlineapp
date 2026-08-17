<?php
use App\Services\CloudinaryService;

if (!function_exists('cloudinary_upload')) {
    function cloudinary_upload($file, $folder = 'uploads') {
        return CloudinaryService::upload($file, $folder);
    }
}

if (!function_exists('cloudinary_update')) {
    function cloudinary_update($newFile, $oldPublicId, $folder = 'uploads') {
        return CloudinaryService::update($newFile, $oldPublicId, $folder);
    }
}

if (!function_exists('cloudinary_delete')) {
    function cloudinary_delete($publicId) {
        return CloudinaryService::delete($publicId);
    }
}

function isValidBangladeshiNumber($number)
{
    return preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', $number);
}

