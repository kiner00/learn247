<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorSubscription extends Model
{
    use Concerns\HasRecurringPlan;

    const STATUS_PENDING = 'pending';

    const STATUS_ACTIVE = 'active';

    const STATUS_EXPIRED = 'expired';

    const STATUS_CANCELLED = 'cancelled';

    const PLAN_BASIC = 'basic';

    const PLAN_PRO = 'pro';

    const CYCLE_MONTHLY = 'monthly';

    const CYCLE_ANNUAL = 'annual';

    protected $fillable = [
        'user_id',
        'affiliate_id',
        'plan',
        'billing_cycle',
        'coupon_id',
        'xendit_plan_id', 'xendit_customer_id', 'recurring_status',
        'status',
        'xendit_id',
        'xendit_invoice_url',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'billing_cycle' => self::CYCLE_MONTHLY,
    ];

    public function isAnnual(): bool
    {
        return $this->billing_cycle === self::CYCLE_ANNUAL;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
