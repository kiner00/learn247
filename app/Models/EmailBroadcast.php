<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailBroadcast extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'campaign_id', 'community_id', 'subject', 'html_body',
        'from_email', 'from_name', 'reply_to',
        'scheduled_at', 'sent_at',
        'total_recipients', 'total_sent', 'total_failed',
        'filter_tags', 'filter_exclude_tags', 'filter_registered_days',
        'filter_membership_type', 'status',
    ];

    protected function casts(): array
    {
        return [
            'filter_tags' => 'array',
            'filter_exclude_tags' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
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

    public function sends(): HasMany
    {
        return $this->hasMany(EmailSend::class, 'broadcast_id');
    }
}
