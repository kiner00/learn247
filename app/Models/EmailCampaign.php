<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    use HasFactory;

    public const TYPE_BROADCAST = 'broadcast';

    public const TYPE_SEQUENCE = 'sequence';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'community_id', 'name', 'type', 'status',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(EmailBroadcast::class, 'campaign_id');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(EmailSequence::class, 'campaign_id');
    }
}
