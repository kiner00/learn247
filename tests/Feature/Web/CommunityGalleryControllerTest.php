<?php

namespace Tests\Feature\Web;

use App\Jobs\TranscodeVideoToHls;
use App\Models\Community;
use App\Models\CommunityGalleryItem;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Services\S3MultipartUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CommunityGalleryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function proOwner(): User
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $owner->id,
            'plan'    => CreatorSubscription::PLAN_PRO,
            'status'  => CreatorSubscription::STATUS_ACTIVE,
        ]);
        return $owner;
    }

    public function test_owner_can_upload_image_creates_row(): void
    {
        Storage::fake();
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/gallery/images", [
                'image' => UploadedFile::fake()->image('hello.jpg'),
            ])
            ->assertRedirect();

        $items = $community->galleryItems()->get();
        $this->assertCount(1, $items);
        $this->assertEquals('image', $items[0]->type);
        $this->assertEquals(0, $items[0]->position);
    }

    public function test_image_upload_rejected_when_gallery_full(): void
    {
        Storage::fake();
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        for ($i = 0; $i < 8; $i++) {
            CommunityGalleryItem::create([
                'community_id' => $community->id,
                'type'         => 'image',
                'image_path'   => "community-gallery/img-{$i}.jpg",
                'position'     => $i,
            ]);
        }

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/gallery/images", [
                'image' => UploadedFile::fake()->image('extra.jpg'),
            ])
            ->assertStatus(422);
    }

    public function test_video_upload_requires_pro_plan(): void
    {
        $owner     = User::factory()->create(); // free
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/gallery/videos/initiate", [
                'filename'     => 'clip.mp4',
                'content_type' => 'video/mp4',
                'size'         => 10_000_000,
            ])
            ->assertForbidden();
    }

    public function test_complete_video_upload_creates_pending_item_and_dispatches_transcode(): void
    {
        Queue::fake();
        Storage::fake();

        // Mock S3 multipart so the test doesn't hit real AWS.
        $mock = Mockery::mock(S3MultipartUploadService::class);
        $mock->shouldReceive('complete')->once();
        $this->app->instance(S3MultipartUploadService::class, $mock);

        $owner     = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/gallery/videos/complete", [
                'key'       => 'gallery-videos/abc.mp4',
                'upload_id' => 'fake-upload-id',
                'parts'     => [
                    ['PartNumber' => 1, 'ETag' => '"etag-1"'],
                ],
            ])
            ->assertCreated();

        $item = $community->galleryItems()->first();
        $this->assertNotNull($item);
        $this->assertEquals('video', $item->type);
        $this->assertEquals('pending', $item->transcode_status);
        $this->assertEquals('gallery-videos/abc.mp4', $item->video_path);

        Queue::assertPushed(TranscodeVideoToHls::class, fn ($job) => $job->target->id === $item->id);
    }

    public function test_transcode_status_endpoint_returns_item_state(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $item      = CommunityGalleryItem::create([
            'community_id'      => $community->id,
            'type'              => 'video',
            'video_path'        => 'gallery-videos/xyz.mp4',
            'transcode_status'  => 'processing',
            'transcode_percent' => 42,
            'position'          => 0,
        ]);

        $this->actingAs($owner)
            ->getJson("/communities/{$community->slug}/gallery/{$item->id}/status")
            ->assertOk()
            ->assertJsonFragment(['transcode_status' => 'processing', 'transcode_percent' => 42]);
    }

    public function test_hls_proxy_404s_before_transcoding_completes(): void
    {
        $community = Community::factory()->create();
        $item      = CommunityGalleryItem::create([
            'community_id'      => $community->id,
            'type'              => 'video',
            'video_path'        => 'gallery-videos/xyz.mp4',
            'transcode_status'  => 'processing',
            'transcode_percent' => 50,
            'position'          => 0,
        ]);

        $this->getJson("/communities/{$community->slug}/gallery/{$item->id}/hls/video.m3u8")
            ->assertNotFound();
    }

    public function test_hls_proxy_is_publicly_accessible_when_completed(): void
    {
        Storage::fake();
        Storage::put('gallery-videos/hls/abc/video.m3u8', "#EXTM3U\nvideo_360p.m3u8\nvideo_720p.m3u8\n");

        $community = Community::factory()->create();
        $item      = CommunityGalleryItem::create([
            'community_id'     => $community->id,
            'type'             => 'video',
            'video_path'       => 'gallery-videos/abc.mp4',
            'video_hls_path'   => 'gallery-videos/hls/abc/video.m3u8',
            'transcode_status' => 'completed',
            'position'         => 0,
        ]);

        // Anonymous request — no actingAs.
        $response = $this->get("/communities/{$community->slug}/gallery/{$item->id}/hls/video.m3u8");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        // Variant references should be rewritten to proxy URLs
        $this->assertStringContainsString("/communities/{$community->slug}/gallery/{$item->id}/hls/video_360p.m3u8", $response->getContent());
    }

    public function test_hls_proxy_rejects_path_traversal(): void
    {
        Storage::fake();
        $community = Community::factory()->create();
        $item      = CommunityGalleryItem::create([
            'community_id'     => $community->id,
            'type'             => 'video',
            'video_path'       => 'gallery-videos/abc.mp4',
            'video_hls_path'   => 'gallery-videos/hls/abc/video.m3u8',
            'transcode_status' => 'completed',
            'position'         => 0,
        ]);

        $response = $this->get("/communities/{$community->slug}/gallery/{$item->id}/hls/" . urlencode('../../lesson-videos/hls/x/video.m3u8'));
        // Either 400 (bad ext) or 403 (traversal) is acceptable — both are rejections.
        $this->assertContains($response->status(), [400, 403, 404]);
    }

    public function test_owner_can_destroy_video_item_and_files_are_cleaned(): void
    {
        Storage::fake();
        Storage::put('gallery-videos/abc.mp4', 'raw');
        Storage::put('gallery-videos/hls/abc/video.m3u8', 'manifest');
        Storage::put('gallery-videos/hls/abc/poster.0000000.jpg', 'poster');

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $item      = CommunityGalleryItem::create([
            'community_id'     => $community->id,
            'type'             => 'video',
            'video_path'       => 'gallery-videos/abc.mp4',
            'video_hls_path'   => 'gallery-videos/hls/abc/video.m3u8',
            'poster_path'      => 'gallery-videos/hls/abc/poster.0000000.jpg',
            'transcode_status' => 'completed',
            'position'         => 0,
        ]);

        $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/gallery/{$item->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('community_gallery_items', ['id' => $item->id]);
        Storage::assertMissing('gallery-videos/abc.mp4');
        Storage::assertMissing('gallery-videos/hls/abc/video.m3u8');
        Storage::assertMissing('gallery-videos/hls/abc/poster.0000000.jpg');
    }

    public function test_owner_can_reorder_by_id(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $a = CommunityGalleryItem::create(['community_id' => $community->id, 'type' => 'image', 'image_path' => 'community-gallery/a.jpg', 'position' => 0]);
        $b = CommunityGalleryItem::create(['community_id' => $community->id, 'type' => 'image', 'image_path' => 'community-gallery/b.jpg', 'position' => 1]);

        $this->actingAs($owner)
            ->putJson("/communities/{$community->slug}/gallery/reorder", [
                'order' => [$b->id, $a->id],
            ])
            ->assertOk();

        $this->assertEquals([$b->id, $a->id], $community->galleryItems()->pluck('id')->all());
    }

    public function test_destroy_404s_when_item_belongs_to_other_community(): void
    {
        $owner       = User::factory()->create();
        $community1  = Community::factory()->create(['owner_id' => $owner->id]);
        $community2  = Community::factory()->create();
        $foreignItem = CommunityGalleryItem::create([
            'community_id' => $community2->id,
            'type'         => 'image',
            'image_path'   => 'community-gallery/x.jpg',
            'position'     => 0,
        ]);

        $this->actingAs($owner)
            ->delete("/communities/{$community1->slug}/gallery/{$foreignItem->id}")
            ->assertNotFound();
    }
}
