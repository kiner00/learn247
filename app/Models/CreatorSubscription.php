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

    protected $fillable = [
        'user_id',
        'plan',
        'xendit_plan_id', 'xendit_customer_id', 'recurring_status',
        'status',
        'xendit_id',
        'xendit_invoice_url',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
