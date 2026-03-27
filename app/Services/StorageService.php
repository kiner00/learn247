<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    /**
     * Store an uploaded file and return its public URL.
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        $path = $file->store($folder, config('filesystems.default'));

        return Storage::url($path);
    }

    /**
     * Delete a previously stored file by its URL.
     * Handles both old local (/storage/...) and S3 URLs.
     */
    public function delete(?string $url): void
    {
        if (! $url) {
            return;
        }

        $disk = Storage::disk();

        // Old local storage URL: /storage/community-covers/abc.jpg
        if (str_starts_with($url, '/storage/')) {
            $path = ltrim(str_replace('/storage/', '', $url), '/');
            // Try deleting from current disk first, fallback to local public
            if ($disk->exists($path)) {
                $disk->delete($path);
            } else {
                Storage::disk('public')->delete($path);
            }
            return;
        }

        // Old asset() URL: http://domain/storage/course-covers/abc.jpg
        if (str_contains($url, '/storage/')) {
            $path = substr($url, strpos($url, '/storage/') + 9);
            if ($disk->exists($path)) {
                $disk->delete($path);
            } else {
                Storage::disk('public')->delete($path);
            }
            return;
        }

        // S3 URL: extract path from full URL
        $bucket = config('filesystems.disks.s3.bucket');
        if ($bucket && str_contains($url, $bucket)) {
            // URL format: https://bucket.s3.region.amazonaws.com/path
            $parsed = parse_url($url);
            if (isset($parsed['path'])) {
                $path = ltrim($parsed['path'], '/');
                $disk->delete($path);
            }
        }
    }
}
