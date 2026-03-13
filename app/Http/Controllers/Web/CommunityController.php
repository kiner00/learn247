<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\CreateCommunity;
use App\Actions\Community\JoinCommunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCommunityRequest;
use App\Mail\AnnouncementMail;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Post;
use App\Models\QuizAttempt;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function index(Request $request): Response
    {
        $search   = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();

        $sort = in_array($request->input('sort'), ['popular', 'latest']) ? $request->input('sort') : 'latest';

        $communities = Community::with('owner')
            ->withCount('members')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($category && $category !== 'All', fn ($q) => $q->where('category', $category))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('members_count'))
            ->when($sort === 'latest',  fn ($q) => $q->latest())
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Communities/Index', [
            'communities' => $communities,
            'filters'     => ['search' => $search, 'category' => $category ?: 'All', 'sort' => $sort],
        ]);
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
                ->orderByDesc('is_pinned')
                ->latest()
                ->take(20),
        ]);
        $community->loadCount('members');

        $community->posts->each(function ($post) use ($userId) {
            $post->reactions      = $this->reactionCounts($post->likes);
            $post->user_reaction  = $userId ? $post->likes->firstWhere('user_id', $userId)?->type : null;
            $post->user_has_liked = (bool) $post->user_reaction; // backward compat
            $post->comments->each(function ($comment) use ($userId) {
                $comment->reactions      = $this->reactionCounts($comment->likes);
                $comment->user_reaction  = $userId ? $comment->likes->firstWhere('user_id', $userId)?->type : null;
                $comment->user_has_liked = (bool) $comment->user_reaction;
                $comment->likes_count    = $comment->likes->count();
                $comment->replies->each(function ($reply) use ($userId) {
                    $reply->reactions      = $this->reactionCounts($reply->likes);
                    $reply->user_reaction  = $userId ? $reply->likes->firstWhere('user_id', $userId)?->type : null;
                    $reply->user_has_liked = (bool) $reply->user_reaction;
                    $reply->likes_count    = $reply->likes->count();
                });
            });
        });

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

        // Auto-create affiliate for existing paid subscribers who don't have one yet
        if (! $affiliate && $userId && $community->hasAffiliateProgram()) {
            $isActiveSubscriber = Subscription::where('user_id', $userId)
                ->where('community_id', $community->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where('expires_at', '>', now())
                ->exists();

            if ($isActiveSubscriber) {
                do { $code = Str::random(12); } while (Affiliate::where('code', $code)->exists());
                $affiliate = Affiliate::create([
                    'community_id' => $community->id,
                    'user_id'      => $userId,
                    'code'         => $code,
                    'status'       => Affiliate::STATUS_ACTIVE,
                ]);
            }
        }
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

        // Owner onboarding checklist
        $checklist = null;
        if ($userId && $community->owner_id === $userId) {
            $hasPost    = Post::where('community_id', $community->id)->exists();
            $courseCount = $community->courses()->count();
            $checklist = [
                ['key' => 'cover',       'label' => 'Upload a banner image',    'done' => (bool) $community->cover_image],
                ['key' => 'description', 'label' => 'Add a community description', 'done' => (bool) trim($community->description ?? '')],
                ['key' => 'post',        'label' => 'Write your first post',    'done' => $hasPost],
                ['key' => 'course',      'label' => 'Create a course',          'done' => $courseCount > 0],
                ['key' => 'affiliate',   'label' => 'Set affiliate commission', 'done' => (bool) $community->affiliate_commission_rate],
            ];
        }

        return Inertia::render('Communities/Show', compact(
            'community', 'membership', 'affiliate', 'adminCount', 'topMembers', 'checklist'
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

        $levelPerks = \App\Models\CommunityLevelPerk::where('community_id', $community->id)
            ->pluck('description', 'level')
            ->toArray();

        return Inertia::render('Communities/Settings', compact('community', 'pricingGate', 'levelPerks'));
    }

    public function update(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'category'                 => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'                   => ['nullable', 'image', 'max:5120'],
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

        if ($request->hasFile('avatar')) {
            if ($community->avatar && str_starts_with($community->avatar, '/storage/')) {
                Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $community->avatar), '/'));
            }
            $path = $request->file('avatar')->store('community-avatars', 'public');
            $data['avatar'] = Storage::url($path);
        } else {
            unset($data['avatar']);
        }

        $community->update($data);

        return back()->with('success', 'Community updated.');
    }

    public function addGalleryImage(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);
        $request->validate(['image' => ['required', 'image', 'max:5120']]);

        $path    = $request->file('image')->store('community-gallery', 'public');
        $url     = Storage::url($path);
        $gallery = $community->gallery_images ?? [];
        $gallery[] = $url;
        $community->update(['gallery_images' => $gallery]);

        return back()->with('success', 'Image added!');
    }

    public function removeGalleryImage(Request $request, Community $community, int $index): RedirectResponse
    {
        $this->authorize('update', $community);

        $gallery = $community->gallery_images ?? [];
        if (isset($gallery[$index])) {
            $path = ltrim(str_replace('/storage/', '', $gallery[$index]), '/');
            Storage::disk('public')->delete($path);
            array_splice($gallery, $index, 1);
            $community->update(['gallery_images' => array_values($gallery)]);
        }

        return back()->with('success', 'Image removed!');
    }

    public function updateLevelPerks(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'perks'   => ['nullable', 'array'],
            'perks.*' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data['perks'] ?? [] as $level => $description) {
            if (blank($description)) {
                \App\Models\CommunityLevelPerk::where('community_id', $community->id)->where('level', $level)->delete();
            } else {
                \App\Models\CommunityLevelPerk::updateOrCreate(
                    ['community_id' => $community->id, 'level' => $level],
                    ['description'  => $description],
                );
            }
        }

        return back()->with('success', 'Level perks saved.');
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

        [$eligibleNow, $lockedAmount, $nextEligibleDate] = PayoutRequestController::ownerEligibility($community);

        $pendingPayoutRequest = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->latest()
            ->first();

        $payoutHistory = \App\Models\OwnerPayout::where('community_id', $community->id)
            ->latest('paid_at')
            ->get()
            ->map(fn ($p) => [
                'amount'     => $p->amount,
                'status'     => $p->status,
                'paid_at'    => $p->paid_at?->toDateString(),
                'reference'  => $p->xendit_reference,
            ]);

        return Inertia::render('Communities/Analytics', [
            'community' => $community,
            'stats' => [
                'monthly_revenue'      => $monthlyRevenue,
                'active_subscriptions' => $activeCount,
                'total_members'        => $totalMembers,
                'free_members'         => $totalMembers - $activeCount,
            ],
            'revenue' => [
                'gross'                        => $grossRevenue,
                'platform_fee'                 => $totalPlatformFee,
                'affiliate_commission_earned'  => $affiliateCommission,
                'affiliate_commission_paid'    => $affiliatePaid,
                'affiliate_commission_pending' => $affiliatePending,
                'creator_net'                  => $totalCreatorNet,
                'has_affiliate_data'           => $affiliateGross > 0,
            ],
            'payout' => [
                'eligible_now'      => $eligibleNow,
                'locked_amount'     => $lockedAmount,
                'next_eligible_date'=> $nextEligibleDate,
                'pending_request'   => $pendingPayoutRequest ? [
                    'amount'     => $pendingPayoutRequest->amount,
                    'created_at' => $pendingPayoutRequest->created_at->toDateString(),
                ] : null,
            ],
            'payout_history' => $payoutHistory,
            'subscribers'  => $subscribers,
            'course_stats' => $courseStats->values(),
        ]);
    }

    public function join(Request $request, Community $community, JoinCommunity $action): RedirectResponse
    {
        $user        = $request->user();
        $beforeCount = $community->members()->count();

        $action->execute($user, $community);

        $afterCount = $beforeCount + 1;

        // Notify community owner about new member
        if ($community->owner_id !== $user->id) {
            Notification::create([
                'user_id'      => $community->owner_id,
                'actor_id'     => $user->id,
                'community_id' => $community->id,
                'type'         => 'new_member',
                'data'         => ['message' => "{$user->name} joined {$community->name}"],
            ]);
        }

        // Notify owner if a milestone threshold was crossed
        $milestones = [100, 500, 1_000, 10_000, 100_000, 1_000_000];
        $labels     = [100 => '100 🥉', 500 => '500 🥈', 1_000 => '1k 🥇', 10_000 => '10k 💎', 100_000 => '100k 🏆', 1_000_000 => '1M 🌟'];
        foreach ($milestones as $milestone) {
            if ($beforeCount < $milestone && $afterCount >= $milestone) {
                Notification::create([
                    'user_id'      => $community->owner_id,
                    'actor_id'     => null,
                    'community_id' => $community->id,
                    'type'         => 'milestone',
                    'data'         => [
                        'milestone' => $milestone,
                        'message'   => "🎉 {$community->name} just hit {$labels[$milestone]} members!",
                    ],
                ]);
                break;
            }
        }

        return back()->with('success', 'You have joined the community!');
    }

    public function announce(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $members = $community->members()->with('user:id,name,email')->get();
        $sender  = $request->user();

        foreach ($members as $membership) {
            if ($membership->user && $membership->user->email) {
                Mail::to($membership->user->email)
                    ->queue(new AnnouncementMail($community, $sender, $data['subject'], $data['message']));
            }
        }

        return back()->with('success', "Announcement sent to {$members->count()} members.");
    }

    public function about(Request $request, Community $community): Response
    {
        $community->load('owner')->loadCount('members');
        $affiliate = auth()->id() ? $community->affiliates()->where('user_id', auth()->id())->first() : null;

        $recentMembers = $community->members()
            ->with('user:id,name,avatar')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($m) => ['name' => $m->user?->name, 'avatar' => $m->user?->avatar])
            ->filter(fn ($m) => $m['name'])
            ->values();

        // Resolve who invited this visitor (from ref_code cookie)
        $invitedBy = null;
        $refCode = $request->cookie('ref_code');
        if ($refCode) {
            $refAffiliate = Affiliate::where('code', $refCode)
                ->where('community_id', $community->id)
                ->where('status', Affiliate::STATUS_ACTIVE)
                ->with('user:id,name,avatar')
                ->first();
            if ($refAffiliate) {
                $invitedBy = [
                    'name'   => $refAffiliate->user->name,
                    'avatar' => $refAffiliate->user->avatar,
                    'code'   => $refCode,
                ];
            }
        }

        $membership = auth()->id()
            ? $community->members()->where('user_id', auth()->id())->first()
            : null;

        return Inertia::render('Communities/About', compact('community', 'affiliate', 'invitedBy', 'membership', 'recentMembers'));
    }

    private function reactionCounts(\Illuminate\Support\Collection $likes): array
    {
        $grouped = $likes->groupBy('type');
        return [
            'like'      => $grouped->get('like',      collect())->count(),
            'handshake' => $grouped->get('handshake', collect())->count(),
            'trophy'    => $grouped->get('trophy',    collect())->count(),
        ];
    }
}
