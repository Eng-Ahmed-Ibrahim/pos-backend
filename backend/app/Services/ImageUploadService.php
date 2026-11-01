<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageUploadService 
{
    public function upload($file, string $path): string
    {
        $storedPath = $file->store($path, 'public');
        return 'storage/' . $storedPath;
    }

    public function delete(?string $path): void
    {
        if (!$path) return;

        $relativePath = str_replace('storage/', '', $path);
        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}