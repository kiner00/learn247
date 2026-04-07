<?php

namespace App\Services\Community;

use App\Models\Community;
use App\Models\User;

/**
 * Single source of truth for all plan-based feature gates.
 * Used by Web and API controllers so rules never diverge.
 */
class PlanLimitService
{
    // ── Community limits ──────────────────────────────────────────────────────

    public function communityLimit(string $plan): int
    {
        return match ($plan) {
            'pro'   => PHP_INT_MAX,
            'basic' => 3,
            default => 1,
        };
    }

    public function canCreateCommunity(User $user): bool
    {
        $plan  = $user->creatorPlan();
        $limit = $this->communityLimit($plan);

        return Community::where('owner_id', $user->id)->count() < $limit;
    }

    public function communityLimitError(User $user): string
    {
        $plan    = $user->creatorPlan();
        $limit   = $this->communityLimit($plan);
        $upgrade = $plan === 'free'
            ? 'Upgrade to Basic (3 communities) or Pro (unlimited).'
            : 'Upgrade to Pro for unlimited communities.';
        $noun    = $limit === 1 ? 'community' : 'communities';

        return "Your {$plan} plan allows up to {$limit} {$noun}. {$upgrade}";
    }

    // ── Course limits ─────────────────────────────────────────────────────────

    public function courseLimit(string $plan): int
    {
        return match ($plan) {
            'pro'   => PHP_INT_MAX,
            'basic' => 5,
            default => 3,
        };
    }

    public function canCreateCourse(User $user, Community $community): bool
    {
        $limit = $this->courseLimit($user->creatorPlan());

        return $community->courses()->count() < $limit;
    }

    // ── Feature flags ─────────────────────────────────────────────────────────

    public function canSendAnnouncement(User $user): bool
    {
        return in_array($user->creatorPlan(), ['basic', 'pro']);
    }

    public function canUseEmail(User $user): bool
    {
        return in_array($user->creatorPlan(), ['basic', 'pro']);
    }

    public function canUseBYOK(User $user): bool
    {
        return $user->creatorPlan() === 'pro';
    }

    public function canUploadVideo(User $user): bool
    {
        return $user->creatorPlan() === 'pro';
    }

    public function maxVideoSizeMb(string $plan): int
    {
        return match ($plan) {
            'pro'   => 500,
            default => 0,
        };
    }

    // ── Curzzo limits ──────────────────────────────────────────────────────────

    public function curzzoLimit(string $plan): int
    {
        return match ($plan) {
            'pro'   => 5,
            default => 0,
        };
    }

    public function canCreateCurzzo(User $user, Community $community): bool
    {
        if ($user->creatorPlan() !== 'pro') {
            return false;
        }

        return $community->curzzos()->count() < $this->curzzoLimit('pro');
    }

    // ── Pricing gate (settings page) ──────────────────────────────────────────

    /**
     * Returns the requirements a community must meet before paid pricing
     * can be enabled, plus whether all requirements are currently satisfied.
     */
    public function pricingGate(Community $community): array
    {
        $moduleCount = $community->courses()
            ->withCount(['modules' => fn ($q) => $q->where('is_free', false)])
            ->get()
            ->sum('modules_count');

        $owner = $community->owner;

        $gate = [
            'module_count'       => $moduleCount,
            'has_banner'         => (bool) $community->cover_image,
            'has_description'    => (bool) ($community->description && strlen(trim($community->description)) > 0),
            'profile_complete'   => (bool) ($owner && $owner->name && $owner->bio && $owner->avatar),
            'can_enable_pricing' => false,
        ];

        $gate['can_enable_pricing'] = $moduleCount >= 5
            && $gate['has_banner']
            && $gate['has_description']
            && $gate['profile_complete'];

        return $gate;
    }
}
