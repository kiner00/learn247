<?php

namespace Tests\Feature\Services;

use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageServiceTest extends TestCase
{
    private StorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StorageService();
    }

    public function test_upload_stores_file_and_returns_url(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $url = $this->service->upload($file, 'avatars');

        $this->assertNotEmpty($url);
        // The file should exist on the disk
        $files = Storage::disk($disk)->allFiles('avatars');
        $this->assertCount(1, $files);
    }

    public function test_delete_does_nothing_for_null_url(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        // Should not throw
        $this->service->delete(null);
        $this->assertTrue(true);
    }

    public function test_delete_removes_s3_key_path(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/abc.mp4', 'content');

        $this->service->delete('lesson-videos/abc.mp4');

        Storage::disk($disk)->assertMissing('lesson-videos/abc.mp4');
    }

    public function test_delete_handles_local_storage_url(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('community-covers/abc.jpg', 'content');

        $this->service->delete('/storage/community-covers/abc.jpg');

        Storage::disk($disk)->assertMissing('community-covers/abc.jpg');
    }

    public function test_delete_local_storage_url_falls_back_to_public_disk(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::fake('public');
        Storage::disk('public')->put('community-covers/abc.jpg', 'content');

        // File does NOT exist on default disk, so it should fall back to public
        $this->service->delete('/storage/community-covers/abc.jpg');

        Storage::disk('public')->assertMissing('community-covers/abc.jpg');
    }

    public function test_delete_handles_full_http_storage_url(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('course-covers/abc.jpg', 'content');

        $this->service->delete('https://example.com/storage/course-covers/abc.jpg');

        Storage::disk($disk)->assertMissing('course-covers/abc.jpg');
    }

    public function test_delete_http_storage_url_falls_back_to_public_disk(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::fake('public');
        Storage::disk('public')->put('course-covers/abc.jpg', 'content');

        $this->service->delete('https://example.com/storage/course-covers/abc.jpg');

        Storage::disk('public')->assertMissing('course-covers/abc.jpg');
    }

    public function test_delete_handles_s3_full_url(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        config(['filesystems.disks.s3.bucket' => 'my-bucket']);
        Storage::disk($disk)->put('uploads/photo.jpg', 'content');

        $this->service->delete('https://my-bucket.s3.ap-southeast-1.amazonaws.com/uploads/photo.jpg');

        Storage::disk($disk)->assertMissing('uploads/photo.jpg');
    }

    public function test_delete_ignores_s3_url_without_parseable_path(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        config(['filesystems.disks.s3.bucket' => 'my-bucket']);

        // URL with no path segment should not throw
        $this->service->delete('my-bucket');
        $this->assertTrue(true);
    }
}
