<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\GenerateLandingPage;
use App\Actions\Community\RegenerateLandingSection;
use App\Actions\Community\UpdateLandingPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLandingPageRequest;
use App\Models\Community;
use App\Queries\Community\GetInvitedByAffiliate;
use App\Services\Community\PlanLimitService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    public function show(Request $request, Community $community, GetInvitedByAffiliate $invitedByQuery): Response|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $community->load('owner')->loadCount('members');

        $membership = auth()->id() ? $community->members()->where('user_id', auth()->id())->first() : null;
        $ownerIsPro = in_array($community->owner?->creatorPlan(), ['basic', 'pro']);
        $isOwner = auth()->id() === $community->owner_id;
        $refCode = $request->query('ref') ?? $request->cookie('ref_code');

        if (! $isOwner && empty($community->landing_page)) {
            $redirect = route('communities.about', $community->slug);
            if ($refCode) {
                Cookie::queue('ref_code', $refCode, 60 * 24 * 30);
                $redirect .= '?modal=true';
            }

            return redirect($redirect);
        }

        $invitedBy = (! $membership && ! $isOwner)
            ? $invitedByQuery->execute($community, $refCode)
            : null;

        $affiliate = $invitedBy
            ? $community->affiliates()->where('code', $refCode)->first()
            : (auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null);

        $allPublished = $community->courses()->where('is_published', true)->get();
        $selectedIds = $community->landing_page['included_courses_selected'] ?? null;
        $filtered = $selectedIds !== null
            ? $allPublished->whereIn('id', $selectedIds)->values()
            : $allPublished->where('access_type', 'inclusive')->values();
        $courses = $filtered->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'description' => $c->description,
            'cover_image' => $c->cover_image,
            'preview_video' => $c->preview_video,
            'preview_video_sound' => (bool) $c->preview_video_sound,
            'access_type' => $c->access_type,
            'price' => $c->price,
        ]);
        $allCourses = $isOwner ? $allPublished->values() : [];
        $certifications = $community->certifications()
            ->withCount('questions')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'cert_title' => $c->cert_title,
                'description' => $c->description,
                'cover_image' => $c->cover_image ?: null,
                'price' => (float) ($c->price ?? 0),
                'questions_count' => $c->questions_count,
            ]);
        $allCurzzos = $community->curzzos()
            ->where('is_active', true)
            ->select('id', 'name', 'description', 'avatar', 'cover_image', 'preview_video', 'preview_video_sound', 'access_type', 'price', 'currency', 'billing_type')
            ->orderBy('position')
            ->get();
        $selectedCurzzoIds = $community->landing_page['curzzos_selected'] ?? null;
        $curzzos = $selectedCurzzoIds !== null
            ? $allCurzzos->whereIn('id', $selectedCurzzoIds)->values()
            : $allCurzzos;
        $lp = $community->landing_page ?? [];
        $brand = $community->brand_context ?? [];
        $ogTitle = $lp['hero_headline'] ?? $community->name;
        $ogDesc = $brand['social_share_description']
            ?? $lp['hero_subheadline']
            ?? $community->description
            ?? '';
        $ogImage = $lp['hero_image'] ?? $community->cover_image ?? null;

        View::share('ogMeta', [
            'title' => $ogTitle,
            'description' => Str::limit(strip_tags($ogDesc), 200),
            'image' => $ogImage,
            'url' => url("/communities/{$community->slug}/landing"),
        ]);

        $inertia = Inertia::render('Communities/Landing', compact(
            'community', 'affiliate', 'invitedBy', 'membership', 'ownerIsPro', 'isOwner', 'courses', 'allCourses', 'certifications', 'curzzos', 'allCurzzos'
        ));

        if ($request->query('ref') && ! $request->cookie('ref_code')) {
            return $inertia->toResponse($request)->withCookie(cookie('ref_code', $refCode, 60 * 24 * 30));
        }

        return $inertia;
    }

    public function update(UpdateLandingPageRequest $request, Community $community, UpdateLandingPage $action): JsonResponse
    {
        return response()->json($action->execute($community, $request->validated()));
    }

    public function generate(Request $request, Community $community, GenerateLandingPage $action): JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        try {
            $copy = $action->execute($community, $user);

            return response()->json($copy);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('LandingPageController@generate failed', [
                'community' => $community->slug,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function regenerateSection(Request $request, Community $community, RegenerateLandingSection $action): JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        $request->validate([
            'section' => 'required|string|in:hero,social_proof,benefits,for_you,creator,testimonials,faq,cta_section,offer_stack,guarantee,price_justification',
        ]);

        try {
            $result = $action->execute($community, $request->input('section'));

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('LandingPageController@regenerateSection failed', [
                'community' => $community->slug,
                'section' => $request->input('section'),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadImage(Request $request, Community $community): JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        $request->validate(['image' => 'required|image|max:15360']);

        $url = app(StorageService::class)->upload($request->file('image'), 'landing-images');

        return response()->json(['url' => $url]);
    }

    public function uploadVideo(Request $request, Community $community, PlanLimitService $planLimit): JsonResponse
    {
        $user = $request->user();

        if ($community->owner_id !== $user->id && ! $user->is_super_admin) {
            abort(403);
        }

        $owner = $community->owner;
        if (! $user->is_super_admin && ! $planLimit->canUploadVideo($owner)) {
            return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
        }

        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/x-msvideo'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;
        if (! $user->is_super_admin && $request->size > $maxBytes) {
            return response()->json([
                'error' => 'File too large. Maximum size is '.$planLimit->maxVideoSizeMb($owner->creatorPlan()).'MB.',
            ], 422);
        }

        $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
        $key = 'landing-videos/'.Str::uuid().'.'.$extension;

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
            'url' => Storage::disk('s3')->url($key),
        ]);
    }
}
