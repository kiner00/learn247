<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workflow extends Model
{
    use HasFactory;

    public const TRIGGER_MEMBER_JOINED     = 'member_joined';
    public const TRIGGER_SUBSCRIPTION_PAID = 'subscription_paid';
    public const TRIGGER_COURSE_ENROLLED   = 'course_enrolled';

    public const TRIGGERS = [
        self::TRIGGER_MEMBER_JOINED,
        self::TRIGGER_SUBSCRIPTION_PAID,
        self::TRIGGER_COURSE_ENROLLED,
    ];

    public const ACTION_APPLY_TAG = 'apply_tag';

    public const ACTIONS = [
        self::ACTION_APPLY_TAG,
    ];

    protected $fillable = [
        'community_id',
        'name',
        'trigger_event',
        'trigger_filter',
        'action_type',
        'action_config',
        'is_active',
        'run_count',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_filter' => 'array',
            'action_config'  => 'array',
            'is_active'      => 'boolean',
            'last_run_at'    => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
