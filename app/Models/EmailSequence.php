<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailSequence extends Model
{
    use HasFactory;

    // Trigger events
    public const TRIGGER_MEMBER_JOINED          = 'member.joined';
    public const TRIGGER_FREE_SUBSCRIBED        = 'free.subscribed';
    public const TRIGGER_SUBSCRIPTION_PAID      = 'subscription.paid';
    public const TRIGGER_SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    public const TRIGGER_COURSE_ENROLLED        = 'course.enrolled';
    public const TRIGGER_COURSE_COMPLETED       = 'course.completed';
    public const TRIGGER_CART_ABANDONED         = 'cart.abandoned';
    public const TRIGGER_TAG_ADDED             = 'tag.added';
    public const TRIGGER_MEMBER_INACTIVE        = 'member.inactive';
    public const TRIGGER_CERTIFICATION_EARNED   = 'certification.earned';
    public const TRIGGER_FIRST_POST             = 'member.first_post';

    public const TRIGGERS = [
        self::TRIGGER_MEMBER_JOINED,
        self::TRIGGER_FREE_SUBSCRIBED,
        self::TRIGGER_SUBSCRIPTION_PAID,
        self::TRIGGER_SUBSCRIPTION_CANCELLED,
        self::TRIGGER_COURSE_ENROLLED,
        self::TRIGGER_COURSE_COMPLETED,
        self::TRIGGER_CART_ABANDONED,
        self::TRIGGER_TAG_ADDED,
        self::TRIGGER_MEMBER_INACTIVE,
        self::TRIGGER_CERTIFICATION_EARNED,
        self::TRIGGER_FIRST_POST,
    ];

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'campaign_id', 'community_id', 'trigger_event', 'trigger_filter', 'status',
    ];

    protected function casts(): array
    {
        return [
            'trigger_filter' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'campaign_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(EmailSequenceStep::class, 'sequence_id')->orderBy('position');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(EmailSequenceEnrollment::class, 'sequence_id');
    }
}
