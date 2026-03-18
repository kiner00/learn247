<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Community extends Model
{
    use HasFactory, SoftDeletes;

    public const BILLING_MONTHLY  = 'monthly';
    public const BILLING_ONE_TIME = 'one_time';

    protected $fillable = [
        'name', 'slug', 'owner_id', 'description', 'category',
        'avatar', 'cover_image', 'gallery_images', 'is_private', 'price', 'currency',
        'billing_type', 'affiliate_commission_rate',
        'facebook_pixel_id', 'tiktok_pixel_id', 'google_analytics_id',
        'deletion_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'is_private'                => 'boolean',
            'price'                     => 'decimal:2',
            'affiliate_commission_rate' => 'integer',
            'gallery_images'            => 'array',
            'deletion_requested_at'     => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class)->orderBy('position');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('start_at');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CommunityInvite::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isFree(): bool
    {
        return $this->price <= 0;
    }

    public function hasAffiliateProgram(): bool
    {
        return $this->affiliate_commission_rate !== null && $this->affiliate_commission_rate > 0;
    }

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    public function activeSubscribersCount(): int
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->count();
    }
}
