<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\TranscodeVideoToHls;
use App\Models\Community;
use App\Models\CommunityGalleryItem;
use App\Services\Community\PlanLimitService;
use App\Services\HlsManifestRewriter;
use App\Services\S3MultipartUploadService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunityGalleryController extends Controller
{
    private const MAX_ITEMS = 8;

    public function storeImage(Request $request, Community $community, StorageService $storage): RedirectResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'image' => ['required', 'image', 'max:15360'],
        ]);

        $this->ensureCapacity($community);

        $url = $storage->upload($request->file('image'), 'community-gallery');

        CommunityGalleryItem::create([
            'community_id' => $community->id,
            'type'         => 'image',
            'image_path'   => $this->keyFromStoredUrl($url),
            'position'     => $this->nextPosition($community),
        ]);

        return back()->with('success', 'Image added!');
    }

    public function initiateVideoUpload(Request $request, Community $community, PlanLimitService $planLimit, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('update', $community);

        $owner = $community->owner;
        if (! $planLimit->canUploadVideo($owner)) {
            return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
        }

        $request->validate([
            'filename'     => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm'],
            'size'         => ['required', 'integer', 'min:1'],
        ]);

        $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;
        if ($request->size > $maxBytes) {
            return response()->json([
                'error' => 'File too large. Maximum is ' . $planLimit->maxVideoSizeMb($owner->creatorPlan()) . ' MB.',
            ], 422);
        }

        if ($community->galleryItems()->count() >= self::MAX_ITEMS) {
            return response()->json(['error' => 'Gallery is full (8 items).'], 422);
        }

        return response()->json($multipart->initiate($request->filename, $request->content_type, 'gallery-videos'));
    }

    public function getVideoPartUrl(Request $request, Community $community, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'key'         => ['required', 'string'],
            'upload_id'   => ['required', 'string'],
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        return response()->json([
            'url' => $multipart->partUrl($request->key, $request->upload_id, $request->part_number),
        ]);
    }

    public function completeVideoUpload(Request $request, Community $community, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'key'                => ['required', 'string'],
            'upload_id'          => ['required', 'string'],
            'parts'              => ['required', 'array', 'min:1'],
            'parts.*.PartNumber' => ['required', 'integer'],
            'parts.*.ETag'       => ['required', 'string'],
        ]);

        $multipart->complete($request->key, $request->upload_id, $request->parts);

        $item = CommunityGalleryItem::create([
            'community_id'      => $community->id,
            'type'              => 'video',
            'video_path'        => $request->key,
            'transcode_status'  => 'pending',
            'transcode_percent' => 0,
            'position'          => $this->nextPosition($community),
        ]);

        TranscodeVideoToHls::dispatch($item);

        return response()->json([
            'item' => $this->presentItem($item->fresh()),
        ], 201);
    }

    public function abortVideoUpload(Request $request, Community $community, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'key'       => ['required', 'string'],
            'upload_id' => ['required', 'string'],
        ]);

        $multipart->abort($request->key, $request->upload_id);

        return response()->json(['ok' => true]);
    }

    public function transcodeStatus(Community $community, CommunityGalleryItem $item): JsonResponse
    {
        $this->ensureItemBelongsToCommunity($community, $item);

        return response()->json($this->presentItem($item));
    }

    public function hlsFile(Community $community, CommunityGalleryItem $item, string $file, HlsManifestRewriter $hls)
    {
        $this->ensureItemBelongsToCommunity($community, $item);

        if ($item->type !== 'video' || ! $item->video_hls_path || $item->transcode_status !== 'completed') {
            abort(404);
        }

        return $hls->serve(
            dirname($item->video_hls_path),
            $file,
            fn (string $relative) => route('communities.gallery.hls', [
                'community' => $community,
                'item'      => $item->id,
                'file'      => $relative,
            ]),
        );
    }

    public function destroy(Request $request, Community $community, CommunityGalleryItem $item): RedirectResponse
    {
        $this->authorize('update', $community);
        $this->ensureItemBelongsToCommunity($community, $item);

        $disk = Storage::disk(config('filesystems.default'));

        if ($item->type === 'image' && $item->image_path) {
            $disk->delete($item->image_path);
        }

        if ($item->type === 'video') {
            if ($item->video_path) {
                $disk->delete($item->video_path);
            }
            if ($item->video_hls_path) {
                $prefix = dirname($item->video_hls_path);
                $disk->deleteDirectory($prefix);
            }
            if ($item->poster_path) {
                $disk->delete($item->poster_path);
            }
        }

        $item->delete();

        return back()->with('success', 'Removed from gallery.');
    }

    public function reorder(Request $request, Community $community): JsonResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $itemIds = $community->galleryItems()->pluck('id')->all();
        $order   = $request->input('order');

        if (count($order) !== count($itemIds) || array_diff($order, $itemIds)) {
            return response()->json(['error' => 'Invalid order.'], 422);
        }

        foreach ($order as $position => $id) {
            CommunityGalleryItem::where('id', $id)
                ->where('community_id', $community->id)
                ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Gallery reordered.']);
    }

    private function ensureCapacity(Community $community): void
    {
        if ($community->galleryItems()->count() >= self::MAX_ITEMS) {
            abort(422, 'Gallery is full (8 items).');
        }
    }

    private function ensureItemBelongsToCommunity(Community $community, CommunityGalleryItem $item): void
    {
        if ($item->community_id !== $community->id) {
            abort(404);
        }
    }

    private function nextPosition(Community $community): int
    {
        return (int) ($community->galleryItems()->max('position') ?? -1) + 1;
    }

    /**
     * Convert a Storage::url() result back to its raw S3 key.
     * StorageService::upload returns a public URL — we keep the relative key
     * for consistency with the multipart-upload code path.
     */
    private function keyFromStoredUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        }

        if (str_starts_with($url, '/storage/')) {
            return substr($url, strlen('/storage/'));
        }

        return ltrim($url, '/');
    }

    private function presentItem(CommunityGalleryItem $item): array
    {
        return [
            'id'                => $item->id,
            'type'              => $item->type,
            'url'               => $item->url,
            'poster_url'        => $item->poster_url,
            'hls_url'           => $item->video_ready
                ? route('communities.gallery.hls', [
                    'community' => $item->community->slug,
                    'item'      => $item->id,
                    'file'      => 'video.m3u8',
                ])
                : null,
            'transcode_status'  => $item->transcode_status,
            'transcode_percent' => $item->transcode_percent,
            'video_ready'       => $item->video_ready,
            'position'          => $item->position,
        ];
    }
}
