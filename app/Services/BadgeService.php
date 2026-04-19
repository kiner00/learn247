<?php

namespace App\Services;

use App\Contracts\BadgeEvaluator;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\Badge\BadgeConditionChecker;
use App\Services\Badge\BadgeTokenAwarder;

class BadgeService implements BadgeEvaluator
{
    public function __construct(
        private BadgeConditionChecker $conditionChecker,
        private BadgeTokenAwarder $tokenAwarder,
    ) {}

    public function evaluate(User $user, ?int $communityId = null): void
    {
        $badges = Badge::where(function ($q) use ($communityId) {
            $q->whereNull('community_id');
            if ($communityId) {
                $q->orWhere('community_id', $communityId);
            }
        })->get();

        foreach ($badges as $badge) {
            if ($this->alreadyEarned($user->id, $badge->id, $communityId)) {
                continue;
            }

            if ($this->conditionChecker->conditionMet($user, $badge, $communityId)) {
                UserBadge::create([
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'community_id' => $communityId,
                    'earned_at' => now(),
                ]);

                $this->tokenAwarder->award($user, $badge);
            }
        }
    }

    private function alreadyEarned(int $userId, int $badgeId, ?int $communityId): bool
    {
        return UserBadge::where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->where('community_id', $communityId)
            ->exists();
    }

    /**
     * Seed all platform-wide badges. Safe to run multiple times.
     */
    public static function seedDefaults(): void
    {
        $badges = [
            ['key' => 'early_bird', 'type' => 'member', 'name' => 'Early Bird', 'icon' => '🐦', 'description' => 'Among the first 100,000 members to achieve an affiliate sale', 'how_to_earn' => 'Achieve 1 affiliate sale while being among the first 100,000 members to do so. Rewards 1 CRZ token.', 'condition_type' => 'early_bird', 'condition_value' => 1, 'sort_order' => 5],
            ['key' => 'early_builder', 'type' => 'creator', 'name' => 'Early Builder', 'icon' => '🏗️', 'description' => 'Among the first 1,000 community creators with 10 paying members', 'how_to_earn' => 'Be one of the first 1,000 community creators to reach 10 paying members. Rewards 10 CRZ tokens.', 'condition_type' => 'early_builder', 'condition_value' => 1, 'sort_order' => 195],
            ['key' => 'pioneer_member', 'type' => 'member', 'name' => 'The Pioneer Member', 'icon' => '🏅', 'description' => 'Early Adopter — one of the first 100,000 members', 'how_to_earn' => 'Be one of the first 100,000 members to join the platform.', 'condition_type' => 'pioneer_member', 'condition_value' => 1, 'sort_order' => 10],
            ['key' => 'insight_architect', 'type' => 'member', 'name' => 'Insight Architect', 'icon' => '💡', 'description' => 'Your content gets recognized as genuinely helpful', 'how_to_earn' => 'Log in and engage for 7 consecutive days, or receive a "Helpful" reaction on your content.', 'condition_type' => 'helpful_reaction', 'condition_value' => 1, 'sort_order' => 20],
            ['key' => 'seven_day_streak', 'type' => 'member', 'name' => 'Seven-Day Streak', 'icon' => '🔥', 'description' => 'A week of consistent engagement', 'how_to_earn' => 'Log in and engage (post, comment, or like) for 7 consecutive days.', 'condition_type' => 'seven_day_streak', 'condition_value' => 7, 'sort_order' => 30],
            ['key' => 'the_heartthrob', 'type' => 'member', 'name' => 'The Heartthrob', 'icon' => '❤️', 'description' => 'Your answer solved someone\'s problem', 'how_to_earn' => 'Provide the "Accepted Solution" or most-liked reply on a Question-styled post.', 'condition_type' => 'solution_accepted', 'condition_value' => 1, 'sort_order' => 40],
            ['key' => 'silent_guardian', 'type' => 'member', 'name' => 'Silent Guardian', 'icon' => '🛡️', 'description' => 'You help others find answers without seeking the spotlight', 'how_to_earn' => 'Receive a "Solution Accepted" reaction on 5 replies.', 'condition_type' => 'solution_accepted', 'condition_value' => 5, 'sort_order' => 50],
            ['key' => 'course_crusader', 'type' => 'member', 'name' => 'Course Crusader', 'icon' => '⚔️', 'description' => 'You finished what you started — fast', 'how_to_earn' => 'Complete 100% of your first course within 30 days of purchase.', 'condition_type' => 'course_crusader', 'condition_value' => 1, 'sort_order' => 60],
            ['key' => 'social_connector', 'type' => 'member', 'name' => 'Social Connector', 'icon' => '🤝', 'description' => 'You\'ve built real connections on the platform', 'how_to_earn' => 'Follow 10 other members and have 10 members follow you back.', 'condition_type' => 'social_connector', 'condition_value' => 10, 'sort_order' => 70],
            ['key' => 'affiliate', 'type' => 'member', 'name' => 'Affiliate', 'icon' => '🔗', 'description' => 'You\'ve successfully referred paying members', 'how_to_earn' => 'Successfully refer 5 new paying members to a community.', 'condition_type' => 'affiliate_referrals', 'condition_value' => 5, 'sort_order' => 80],
            ['key' => 'affiliate_10k', 'type' => 'member', 'name' => 'Affiliate 10k', 'icon' => '💰', 'description' => 'Earned ₱10,000 in affiliate commissions', 'how_to_earn' => 'Earn a cumulative total of ₱10,000 in affiliate commissions.', 'condition_type' => 'affiliate_commission', 'condition_value' => 10000, 'sort_order' => 90],
            ['key' => 'affiliate_50k', 'type' => 'member', 'name' => 'Affiliate 50k', 'icon' => '💎', 'description' => 'Earned ₱50,000 in affiliate commissions', 'how_to_earn' => 'Earn a cumulative total of ₱50,000 in affiliate commissions.', 'condition_type' => 'affiliate_commission', 'condition_value' => 50000, 'sort_order' => 100],
            ['key' => 'affiliate_100k', 'type' => 'member', 'name' => 'Affiliate 100k', 'icon' => '🏆', 'description' => 'Earned ₱100,000 in affiliate commissions', 'how_to_earn' => 'Earn a cumulative total of ₱100,000 in affiliate commissions.', 'condition_type' => 'affiliate_commission', 'condition_value' => 100000, 'sort_order' => 110],
            ['key' => 'affiliate_500k', 'type' => 'member', 'name' => 'Affiliate 500k', 'icon' => '👑', 'description' => 'Earned ₱500,000 in affiliate commissions', 'how_to_earn' => 'Earn a cumulative total of ₱500,000 in affiliate commissions.', 'condition_type' => 'affiliate_commission', 'condition_value' => 500000, 'sort_order' => 120],
            ['key' => 'affiliate_1m', 'type' => 'member', 'name' => 'Affiliate 1M', 'icon' => '🌟', 'description' => 'Earned ₱1,000,000 in affiliate commissions', 'how_to_earn' => 'Earn a cumulative total of ₱1,000,000 in affiliate commissions.', 'condition_type' => 'affiliate_commission', 'condition_value' => 1000000, 'sort_order' => 130],
            ['key' => 'pioneer_creator', 'type' => 'creator', 'name' => 'The Pioneer Creator', 'icon' => '🚀', 'description' => 'Early Adopter — one of the first 1,000 creators with 100+ paid subscribers', 'how_to_earn' => 'Be one of the first 1,000 creators to reach 100+ active paid subscribers.', 'condition_type' => 'pioneer_creator', 'condition_value' => 1, 'sort_order' => 200],
            ['key' => 'community_architect', 'type' => 'creator', 'name' => 'Community Architect', 'icon' => '🏛️', 'description' => 'Your classroom produces real results', 'how_to_earn' => 'Have 50 or more certified course completions across your communities.', 'condition_type' => 'certified_completions', 'condition_value' => 50, 'sort_order' => 210],
            ['key' => 'affiliate_overlord', 'type' => 'creator', 'name' => 'Affiliate Overlord', 'icon' => '⚡', 'description' => 'You\'ve built your own sales army', 'how_to_earn' => 'Recruit and activate 10 affiliates who each generate at least 1 sale.', 'condition_type' => 'affiliate_overlord', 'condition_value' => 10, 'sort_order' => 220],
            ['key' => 'the_curator', 'type' => 'creator', 'name' => 'The Curator', 'icon' => '📌', 'description' => 'You champion your community\'s best content', 'how_to_earn' => 'Pin 50 high-quality member posts to the featured section.', 'condition_type' => 'pinned_posts', 'condition_value' => 50, 'sort_order' => 230],
            ['key' => 'revenue_titan', 'type' => 'creator', 'name' => 'Revenue Titan', 'icon' => '💰', 'description' => 'Your community has generated serious income', 'how_to_earn' => 'Cross the ₱100,000 total payout milestone as a community creator.', 'condition_type' => 'total_payout', 'condition_value' => 100000, 'sort_order' => 240],
            ['key' => 'bridge_builder', 'type' => 'creator', 'name' => 'Bridge Builder', 'icon' => '🌉', 'description' => 'You grow the ecosystem by collaborating with peers', 'how_to_earn' => 'Host a collaborative event (live stream or workshop) with another platform creator.', 'condition_type' => 'bridge_builder', 'condition_value' => 1, 'sort_order' => 250],
        ];

        foreach ($badges as $b) {
            Badge::updateOrCreate(
                ['key' => $b['key']],
                array_merge($b, ['community_id' => null])
            );
        }
    }
}
