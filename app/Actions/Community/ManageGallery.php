<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

class ManageGallery
{
    public function __construct(private StorageService $storage) {}

    public function addImage(Community $community, UploadedFile $image): string
    {
        $url     = $this->storage->upload($image, 'community-gallery');
        $gallery = $community->gallery_images ?? [];
        $gallery[] = $url;
        $community->update(['gallery_images' => $gallery]);

        return $url;
    }

    public function removeImage(Community $community, int $index): void
    {
        $gallery = $community->gallery_images ?? [];
        if (isset($gallery[$index])) {
            $this->storage->delete($gallery[$index]);
            array_splice($gallery, $index, 1);
            $community->update(['gallery_images' => array_values($gallery)]);
        }
    }
}
