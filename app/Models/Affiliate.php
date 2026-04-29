<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const SCOPE_COMMUNITY = 'community';

    public const SCOPE_CREATOR_PLAN = 'creator_plan';

    protected $fillable = [
        'community_id', 'user_id', 'scope', 'code', 'status', 'total_earned', 'total_paid', 'payout_method', 'payout_details',
        'facebook_pixel_id', 'tiktok_pixel_id', 'google_analytics_id',
    ];

    protected $attributes = [
        'scope' => self::SCOPE_COMMUNITY,
    ];

    protected function casts(): array
    {
        return [
            'total_earned' => 'decimal:2',
            'total_paid' => 'decimal:2',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCreatorPlan(): bool
    {
        return $this->scope === self::SCOPE_CREATOR_PLAN;
    }

    public function pendingAmount(): float
    {
        return (float) $this->total_earned - (float) $this->total_paid;
    }

    public function scopeCommunityScope($query)
    {
        return $query->where('scope', self::SCOPE_COMMUNITY);
    }

    public function scopeCreatorPlan($query)
    {
        return $query->where('scope', self::SCOPE_CREATOR_PLAN);
    }
}
