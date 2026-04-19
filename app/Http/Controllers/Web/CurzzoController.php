<?php

namespace App\Http\Controllers\Web;

use App\Contracts\FileStorage;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Curzzo;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CurzzoController extends Controller
{
    public function index(Community $community): Response
    {
        $this->authorize('update', $community);

        $curzzos = $community->curzzos()->orderBy('position')->get();

        $modelTiers = collect(config('curzzos.tiers'))->map(fn ($tier, $key) => [
            'value' => $key,
            'label' => $tier['label'],
            'description' => $tier['description'],
        ])->values();

        return Inertia::render('Communities/Settings/Curzzos', [
            'community' => $community,
            'isPro' => auth()->user()->creatorPlan() === 'pro',
            'curzzos' => $curzzos,
            'modelTiers' => $modelTiers,
        ]);
    }

    public function store(Request $request, Community $community, PlanLimitService $planLimit, FileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $planLimit->canCreateCurzzo($request->user(), $community)) {
            return back()->withErrors([
                'plan' => 'Curzzos require a Pro plan (max 5 per community).',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'instructions' => ['required', 'string', 'max:20000'],
            'personality' => ['nullable', 'array'],
            'personality.tone' => ['nullable', 'string', 'in:friendly,professional,casual,formal'],
            'personality.expertise' => ['nullable', 'string', 'max:5000'],
            'personality.response_style' => ['nullable', 'string', 'in:concise,detailed,conversational'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'preview_video' => ['nullable', 'string', 'max:1000'],
            'preview_video_sound' => ['nullable', 'boolean'],
            'access_type' => ['required', 'string', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
            'model_tier' => ['sometimes', 'string', Rule::in(array_keys(config('curzzos.tiers')))],
            'price' => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
            'currency' => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type' => ['nullable', 'string', 'in:one_time,monthly'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $storage->upload($request->file('avatar'), 'curzzo-avatars');
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $storage->upload($request->file('cover_image'), 'curzzo-covers');
        }

        if (! empty($data['preview_video'])) {
            $data['preview_video'] = Storage::disk('s3')->url($data['preview_video']);
        }

        $data['community_id'] = $community->id;
        $data['position'] = $community->curzzos()->count();

        Curzzo::create($data);

        return back()->with('success', 'Curzzo created!');
    }

    public function update(Request $request, Community $community, Curzzo $curzzo, FileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'instructions' => ['sometimes', 'required', 'string', 'max:20000'],
            'personality' => ['nullable', 'array'],
            'personality.tone' => ['nullable', 'string', 'in:friendly,professional,casual,formal'],
            'personality.expertise' => ['nullable', 'string', 'max:5000'],
            'personality.response_style' => ['nullable', 'string', 'in:concise,detailed,conversational'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'preview_video' => ['nullable', 'string', 'max:1000'],
            'preview_video_sound' => ['nullable', 'boolean'],
            'access_type' => ['sometimes', 'required', 'string', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
            'model_tier' => ['sometimes', 'string', Rule::in(array_keys(config('curzzos.tiers')))],
            'remove_avatar' => ['sometimes', 'boolean'],
            'remove_cover_image' => ['sometimes', 'boolean'],
            'remove_preview_video' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type' => ['nullable', 'string', 'in:one_time,monthly'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Avatar
        if ($request->hasFile('avatar')) {
            $storage->delete($curzzo->avatar);
            $data['avatar'] = $storage->upload($request->file('avatar'), 'curzzo-avatars');
        } elseif (! empty($data['remove_avatar'])) {
            $storage->delete($curzzo->avatar);
            $data['avatar'] = null;
        }
        unset($data['remove_avatar']);

        // Cover image
        if ($request->hasFile('cover_image')) {
            $storage->delete($curzzo->cover_image);
            $data['cover_image'] = $storage->upload($request->file('cover_image'), 'curzzo-covers');
        } elseif (! empty($data['remove_cover_image'])) {
            $storage->delete($curzzo->cover_image);
            $data['cover_image'] = null;
        }
        unset($data['remove_cover_image']);

        // Preview video
        if (! empty($data['remove_preview_video'])) {
            $data['preview_video'] = null;
        } elseif (! empty($data['preview_video']) && $data['preview_video'] !== $curzzo->preview_video) {
            $data['preview_video'] = Storage::disk('s3')->url($data['preview_video']);
        }
        unset($data['remove_preview_video']);

        $curzzo->update($data);

        return back()->with('success', 'Curzzo updated!');
    }

    public function destroy(Community $community, Curzzo $curzzo, FileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $storage->delete($curzzo->avatar);
        $storage->delete($curzzo->cover_image);
        $curzzo->delete();

        return back()->with('success', 'Curzzo deleted!');
    }

    public function reorder(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($request->ids as $position => $id) {
            $community->curzzos()->where('id', $id)->update(['position' => $position]);
        }

        return back();
    }

    public function toggleActive(Community $community, Curzzo $curzzo): RedirectResponse
    {
        $this->authorize('update', $community);
        abort_unless($curzzo->community_id === $community->id, 404);

        $curzzo->update(['is_active' => ! $curzzo->is_active]);

        return back();
    }

    public function uploadPreviewVideo(Request $request, Community $community, PlanLimitService $planLimit): JsonResponse
    {
        try {
            $this->authorize('update', $community);

            $owner = $community->owner;
            if (! $planLimit->canUploadVideo($owner)) {
                return response()->json(['error' => 'Preview video uploads require a Pro plan.'], 403);
            }

            $request->validate([
                'filename' => ['required', 'string', 'max:255'],
                'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm'],
                'size' => ['required', 'integer', 'min:1'],
            ]);

            $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;

            if ($request->size > $maxBytes) {
                return response()->json([
                    'error' => 'File too large. Maximum size is '.$planLimit->maxVideoSizeMb($owner->creatorPlan()).'MB.',
                ], 422);
            }

            $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
            $key = 'curzzo-previews/'.Str::uuid().'.'.$extension;

            $client = Storage::disk('s3')->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $key,
                'ContentType' => $request->content_type,
            ]);

            $presigned = $client->createPresignedRequest($command, '+30 minutes');

            return response()->json([
                'upload_url' => (string) $presigned->getUri(),
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::error('CurzzoController@uploadPreviewVideo failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }
}
