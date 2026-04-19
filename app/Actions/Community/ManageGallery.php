<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityGalleryItem;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManageGallery
{
    public function __construct(private StorageService $storage) {}

    public function addImage(Community $community, UploadedFile $image): string
    {
        $url = $this->storage->upload($image, 'community-gallery');

        CommunityGalleryItem::create([
            'community_id' => $community->id,
            'type' => 'image',
            'image_path' => $this->keyFromUrl($url),
            'position' => $this->nextPosition($community),
        ]);

        return $url;
    }

    public function removeImage(Community $community, int $index): void
    {
        $item = $community->galleryItems()->skip($index)->first();
        if (! $item) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default'));
        if ($item->image_path) {
            $disk->delete($item->image_path);
        }

        $item->delete();
    }

    public function nextPosition(Community $community): int
    {
        return (int) ($community->galleryItems()->max('position') ?? -1) + 1;
    }

    private function keyFromUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        }
        if (str_starts_with($url, '/storage/')) {
            return substr($url, strlen('/storage/'));
        }

        return ltrim($url, '/');
    }
}
