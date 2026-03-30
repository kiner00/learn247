<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'plan',
        'duration_months',
        'max_redemptions',
        'times_redeemed',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
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
}
