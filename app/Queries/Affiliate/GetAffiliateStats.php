<?php

namespace App\Queries\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetAffiliateStats
{
    public function getAffiliates(User $user): Collection
    {
        return Affiliate::where('user_id', $user->id)
            ->with('community:id,name,slug')
            ->latest()
            ->get();
    }

    /**
     * Returns a fully mapped affiliate collection for dashboard display.
     * Single source of truth for affiliate data shape used by Web and API.
     */
    public function mapForDashboard(User $user, CalculateEligibility $eligibility): Collection
    {
        $affiliates = $this->getAffiliates($user);

        $activeRequestsByAffiliate = PayoutRequest::where('user_id', $user->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->get()
            ->keyBy('affiliate_id');

        return $affiliates->map(fn ($a) => [
            'id'                    => $a->id,
            'code'                  => $a->code,
            'status'                => $a->status,
            'is_active'             => $a->isActive(),
            'total_earned'          => (float) $a->total_earned,
            'total_paid'            => (float) $a->total_paid,
            'pending_amount'        => (float) $a->pendingAmount(),
            'eligible_amount'       => $eligibility->forAffiliate($a),
            'payout_request_status' => $activeRequestsByAffiliate->has($a->id)
                ? $activeRequestsByAffiliate->get($a->id)->status
                : null,
            'referral_url'          => url("/ref/{$a->code}"),
            'community'             => ['name' => $a->community->name, 'slug' => $a->community->slug],
            'facebook_pixel_id'     => $a->facebook_pixel_id,
            'tiktok_pixel_id'       => $a->tiktok_pixel_id,
            'google_analytics_id'   => $a->google_analytics_id,
        ]);
    }

    /**
     * @return array{total_earned: float, total_paid: float, total_pending: float, total_conversions: int}
     */
    public function summary(Collection $affiliateIds, ?string $period = 'month'): array
    {
        $from = $this->periodStart($period);

        $base = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        return [
            'total_earned'      => (float) (clone $base)->sum('commission_amount'),
            'total_paid'        => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount'),
            'total_pending'     => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount'),
            'total_conversions' => (clone $base)->count(),
        ];
    }

    public function conversions(Collection $affiliateIds, ?string $period = 'month', int $limit = 50): Collection
    {
        $from = $this->periodStart($period);

        return AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->with(['affiliate.community', 'referredUser:id,name,email'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->toDateString(),
                'community'         => $c->affiliate->community->name,
                'referred_name'     => $c->referredUser?->name,
                'referred_email'    => $c->referredUser?->email,
                'sale_amount'       => (float) $c->sale_amount,
                'commission_amount' => (float) $c->commission_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->toDateString(),
            ]);
    }

    private function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year'  => Carbon::now()->startOfYear(),
            default => null,
        };
    }
}
