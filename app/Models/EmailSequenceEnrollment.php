<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSequenceEnrollment extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sequence_id', 'community_member_id', 'current_step_id',
        'steps_completed', 'status', 'next_send_at', 'enrolled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'next_send_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CommunityMember::class, 'community_member_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(EmailSequenceStep::class, 'current_step_id');
    }
}
