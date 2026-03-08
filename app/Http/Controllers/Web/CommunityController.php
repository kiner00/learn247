<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(): Response
    {
        $communities = Community::with('owner')
            ->withCount('members')
            ->latest()
            ->paginate(15);

        return Inertia::render('Communities/Index', compact('communities'));
    }

    public function store(CreateCommunityRequest $request, CreateCommunity $action): RedirectResponse
    {
        $community = $action->execute($request->user(), $request->validated());

        return redirect()->route('communities.show', $community->slug)
            ->with('success', 'Community created!');
    }

    public function show(Community $community): Response
    {
        $this->authorize('view', $community);

        $userId = auth()->id();

        $community->load([
            'owner',
            'posts' => fn ($q) => $q
                ->with([
                    'author:id,name,avatar',
                    'likes',
                    'comments' => fn ($cq) => $cq
                        ->whereNull('parent_id')
                        ->with([
                            'author:id,name,avatar',
                            'likes',
                            'replies' => fn ($rq) => $rq->with(['author:id,name,avatar', 'likes']),
                        ])
                        ->latest(),
                ])
                ->withCount('likes', 'comments')
                ->latest()
                ->take(20),
        ]);
        $community->loadCount('members');

        if ($userId) {
            $community->posts->each(function ($post) use ($userId) {
                $post->user_has_liked = $post->likes->contains('user_id', $userId);
                $post->comments->each(function ($comment) use ($userId) {
                    $comment->user_has_liked = $comment->likes->contains('user_id', $userId);
                    $comment->likes_count    = $comment->likes->count();
                    $comment->replies->each(function ($reply) use ($userId) {
                        $reply->user_has_liked = $reply->likes->contains('user_id', $userId);
                        $reply->likes_count    = $reply->likes->count();
                    });
                });
            });
        }

        // Enrich posts with commenter avatars + last comment timestamp
        $community->posts->each(function ($post) {
            $commenters = $post->comments
                ->sortByDesc('created_at')
                ->unique('user_id')
                ->take(4)
                ->map(fn ($c) => ['name' => $c->author?->name, 'avatar' => $c->author?->avatar])
                ->values();

            $post->commenter_avatars  = $commenters;
            $post->last_comment_at    = $post->comments->max('created_at');
        });

        $membership  = $userId ? $community->members()->where('user_id', $userId)->first() : null;
        $affiliate   = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;
        $adminCount  = $community->members()->where('role', CommunityMember::ROLE_ADMIN)->count();

        // Top 5 members for leaderboard sidebar widget
        $topMembers = CommunityMember::where('community_id', $community->id)
            ->with('user:id,name,username,avatar')
            ->orderByDesc('points')
            ->take(5)
            ->get()
            ->map(fn ($m) => [
                'user_id'  => $m->user_id,
                'name'     => $m->user?->name,
                'username' => $m->user?->username,
                'avatar'   => $m->user?->avatar,
                'points'   => $m->points,
                'level'    => CommunityMember::computeLevel($m->points),
            ]);

        return Inertia::render('Communities/Show', compact(
            'community', 'membership', 'affiliate', 'adminCount', 'topMembers'
        ));
    }

    public function members(Community $community, \Illuminate\Http\Request $request): Response
    {
        $query = $community->members()->with('user:id,name,username,bio');

        if ($request->filter === 'admin') {
            $query->where('role', 'admin');
        }

        $members    = $query->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'moderator' THEN 1 ELSE 2 END")->paginate(20)->withQueryString();
        $totalCount = $community->members()->count();
        $adminCount = $community->members()->where('role', 'admin')->count();
        $affiliate  = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        return Inertia::render('Communities/Members', compact('community', 'members', 'totalCount', 'adminCount', 'affiliate'));
    }

    public function settings(Community $community): Response
    {
        $this->authorize('update', $community);

        $moduleCount = $community->courses()
            ->withCount('modules')
            ->get()
            ->sum('modules_count');

        $owner = $community->owner;

        $pricingGate = [
            'module_count'       => $moduleCount,
            'has_banner'         => (bool) $community->cover_image,
            'has_description'    => (bool) ($community->description && strlen(trim($community->description)) > 0),
            'profile_complete'   => (bool) ($owner && $owner->name && $owner->bio && $owner->avatar),
            'can_enable_pricing' => false, // computed below
        ];

        $pricingGate['can_enable_pricing'] = $moduleCount >= 5
            && $pricingGate['has_banner']
            && $pricingGate['has_description']
            && $pricingGate['profile_complete'];

        return Inertia::render('Communities/Settings', compact('community', 'pricingGate'));
    }

    public function update(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'category'                 => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'                   => ['nullable', 'url', 'max:500'],
            'cover_image'              => ['nullable', 'image', 'max:5120'],
            'price'                    => ['nullable', 'numeric', 'min:0'],
            'currency'                 => ['nullable', 'string', 'in:PHP,USD'],
            'is_private'               => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:85'],
        ]);

        // Pricing gate: block price > 0 unless community meets all requirements
        if (isset($data['price']) && (float) $data['price'] > 0) {
            $moduleCount = $community->courses()->withCount('modules')->get()->sum('modules_count');
            $owner       = $community->owner;

            $errors = [];
            if ($moduleCount < 5) {
                $errors['price'] = "You need at least 5 modules to enable pricing (you have {$moduleCount}).";
            } elseif (empty($community->cover_image) && ! $request->hasFile('cover_image')) {
                $errors['price'] = 'Upload a banner image before enabling pricing.';
            } elseif (empty(trim($data['description'] ?? $community->description ?? ''))) {
                $errors['price'] = 'Add a community description before enabling pricing.';
            } elseif (! ($owner && $owner->name && $owner->bio && $owner->avatar)) {
                $errors['price'] = 'Complete your profile (name, bio, avatar) before enabling pricing.';
            }

            if ($errors) {
                return back()->withErrors($errors);
            }
        }

        if ($request->hasFile('cover_image')) {
            // Delete old stored file if present
            if ($community->cover_image && str_starts_with($community->cover_image, '/storage/')) {
                Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $community->cover_image), '/'));
            }
            $path = $request->file('cover_image')->store('community-covers', 'public');
            $data['cover_image'] = Storage::url($path);
        } else {
            unset($data['cover_image']);
        }

        $community->update($data);

        return back()->with('success', 'Community updated.');
    }

    public function destroy(Community $community): RedirectResponse
    {
        $this->authorize('delete', $community);

        $community->delete();

        return redirect()->route('communities.index')->with('success', 'Community deleted.');
    }

    public function analytics(Community $community): Response
    {
        $this->authorize('viewAnalytics', $community);

        $activeCount = Subscription::where('community_id', $community->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->count();

        $monthlyRevenue = $activeCount * (float) $community->price;

        $totalMembers = $community->members()->count();

        $subscribers = Subscription::where('community_id', $community->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'user'       => ['name' => $s->user?->name, 'email' => $s->user?->email],
                'status'     => $s->status,
                'expires_at' => $s->expires_at?->toDateString(),
                'created_at' => $s->created_at?->toDateString(),
            ]);

        // Revenue breakdown from actual payments and affiliate conversions
        $grossRevenue = (float) Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');

        $conversionBase = AffiliateConversion::whereHas(
            'affiliate', fn ($q) => $q->where('community_id', $community->id)
        );

        $affiliateGross        = (float) (clone $conversionBase)->sum('sale_amount');
        $affiliatePlatformFee  = (float) (clone $conversionBase)->sum('platform_fee');
        $affiliateCommission   = (float) (clone $conversionBase)->sum('commission_amount');
        $affiliateCreator      = (float) (clone $conversionBase)->sum('creator_amount');
        $affiliatePaid         = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $affiliatePending      = (float) (clone $conversionBase)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');

        $nonAffiliateGross       = round($grossRevenue - $affiliateGross, 2);
        $nonAffiliatePlatformFee = round($nonAffiliateGross * 0.15, 2);
        $nonAffiliateCreator     = round($nonAffiliateGross - $nonAffiliatePlatformFee, 2);

        $totalPlatformFee  = round($affiliatePlatformFee + $nonAffiliatePlatformFee, 2);
        $totalCreatorNet   = round($affiliateCreator + $nonAffiliateCreator, 2);

        // ─── Classroom analytics ──────────────────────────────────────────────
        $courses = $community->courses()->with('modules.lessons')->get();

        $courseStats = $courses->map(function ($course) use ($community) {
            $lessonIds  = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $totalLessons = $lessonIds->count();

            // Number of members who completed all lessons
            $completedMembers = $totalLessons > 0
                ? LessonCompletion::whereIn('lesson_id', $lessonIds)
                    ->selectRaw('user_id, count(*) as cnt')
                    ->groupBy('user_id')
                    ->havingRaw('cnt >= ?', [$totalLessons])
                    ->count()
                : 0;

            // Total lesson completions
            $totalCompletions = LessonCompletion::whereIn('lesson_id', $lessonIds)->count();

            // Quiz pass rate
            $quizIds = \App\Models\Quiz::whereIn('lesson_id', $lessonIds)->pluck('id');
            $quizAttempts = QuizAttempt::whereIn('quiz_id', $quizIds)->count();
            $quizPasses   = QuizAttempt::whereIn('quiz_id', $quizIds)->where('passed', true)->count();

            return [
                'id'                => $course->id,
                'title'             => $course->title,
                'total_lessons'     => $totalLessons,
                'total_completions' => $totalCompletions,
                'completed_members' => $completedMembers,
                'quiz_attempts'     => $quizAttempts,
                'quiz_passes'       => $quizPasses,
                'quiz_pass_rate'    => $quizAttempts > 0 ? round($quizPasses / $quizAttempts * 100) : null,
            ];
        });

        return Inertia::render('Communities/Analytics', [
            'community' => $community,
            'stats' => [
                'monthly_revenue'      => $monthlyRevenue,
                'active_subscriptions' => $activeCount,
                'total_members'        => $totalMembers,
                'free_members'         => $totalMembers - $activeCount,
            ],
            'revenue' => [
                'gross'                       => $grossRevenue,
                'platform_fee'                => $totalPlatformFee,
                'affiliate_commission_earned' => $affiliateCommission,
                'affiliate_commission_paid'   => $affiliatePaid,
                'affiliate_commission_pending' => $affiliatePending,
                'creator_net'                 => $totalCreatorNet,
                'has_affiliate_data'          => $affiliateGross > 0,
            ],
            'subscribers'  => $subscribers,
            'course_stats' => $courseStats->values(),
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $action->execute($request->user(), $community);

        return back()->with('success', 'You have joined the community!');
    }

    public function about(Community $community): Response
    {
        $community->load('owner')->loadCount('members');
        $affiliate = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        return Inertia::render('Communities/About', compact('community', 'affiliate'));
    }
}
