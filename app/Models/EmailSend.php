<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSend extends Model
{
    use HasFactory;

    public const STATUS_QUEUED     = 'queued';
    public const STATUS_SENT       = 'sent';
    public const STATUS_DELIVERED  = 'delivered';
    public const STATUS_BOUNCED    = 'bounced';
    public const STATUS_COMPLAINED = 'complained';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'broadcast_id', 'community_id', 'community_member_id',
        'resend_email_id', 'status',
        'opened_at', 'clicked_at', 'bounced_at', 'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'  => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(EmailBroadcast::class, 'broadcast_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CommunityMember::class, 'community_member_id');
    }
}
