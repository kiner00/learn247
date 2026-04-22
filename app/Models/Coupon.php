<?php

namespace App\Models;

use App\Support\CreatorPlanPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    const TYPE_PLAN_GRANT = 'plan_grant';

    const TYPE_DISCOUNT = 'discount';

    const APPLIES_TO_MONTHLY = 'monthly';

    const APPLIES_TO_ANNUAL = 'annual';

    const APPLIES_TO_BOTH = 'both';

    const PLAN_BOTH = 'both';

    /**
     * The baseline annual discount (2 months free = ~16.67%). Discount-type
     * coupons that apply to annual must beat this threshold to be worth using.
     */
    public const ANNUAL_BASELINE_DISCOUNT_PERCENT = 16.67;

    protected $fillable = [
        'code',
        'type',
        'plan',
        'applies_to',
        'discount_percent',
        'duration_months',
        'max_redemptions',
        'times_redeemed',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'discount_percent' => 'decimal:2',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isRedeemable(): bool
    {
        return $this->is_active
            && $this->times_redeemed < $this->max_redemptions
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasBeenRedeemedBy(int $userId): bool
    {
        return $this->redemptions()->where('user_id', $userId)->exists();
    }

    public function isPlanGrant(): bool
    {
        return $this->type === self::TYPE_PLAN_GRANT;
    }

    public function isDiscount(): bool
    {
        return $this->type === self::TYPE_DISCOUNT;
    }

    public function appliesToPlan(string $plan): bool
    {
        return $this->plan === self::PLAN_BOTH || $this->plan === $plan;
    }

    public function appliesToCycle(string $cycle): bool
    {
        if ($this->applies_to === null) {
            return false;
        }

        return $this->applies_to === self::APPLIES_TO_BOTH || $this->applies_to === $cycle;
    }

    /**
     * Compute the price this coupon would charge for the given plan+cycle.
     * The baseline for annual is monthly_price × 12 (the coupon overrules the
     * default 2-months-free built into the annual sticker price).
     */
    public function computeDiscountedPrice(string $plan, string $cycle): float
    {
        $monthlyPrice = CreatorPlanPricing::priceFor($plan, CreatorSubscription::CYCLE_MONTHLY);
        $base = $cycle === CreatorSubscription::CYCLE_ANNUAL ? $monthlyPrice * 12 : $monthlyPrice;

        $multiplier = 1 - ((float) $this->discount_percent / 100);

        return round($base * $multiplier, 2);
    }
}
