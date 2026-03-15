<?php

namespace App\Actions\Community;

use App\Models\Community;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManageGallery
{
    public function addImage(Community $community, UploadedFile $image): string
    {
        $path    = $image->store('community-gallery', 'public');
        $url     = Storage::url($path);
        $gallery = $community->gallery_images ?? [];
        $gallery[] = $url;
        $community->update(['gallery_images' => $gallery]);

        return $url;
    }

    public function removeImage(Community $community, int $index): void
    {
        $gallery = $community->gallery_images ?? [];
        if (isset($gallery[$index])) {
            $path = ltrim(str_replace('/storage/', '', $gallery[$index]), '/');
            Storage::disk('public')->delete($path);
            array_splice($gallery, $index, 1);
            $community->update(['gallery_images' => array_values($gallery)]);
        }
    }
}
