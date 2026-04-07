<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurzzoTopup extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';

    protected $fillable = [
        'user_id',
        'community_id',
        'xendit_id',
        'status',
        'messages',
        'messages_used',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at'    => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Remaining messages. 0 with messages=0 means unlimited (day pass).
     */
    public function remainingMessages(): int
    {
        if ($this->messages === 0) {
            return PHP_INT_MAX; // unlimited day pass
        }

        return max(0, $this->messages - $this->messages_used);
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_PAID) {
            return false;
        }

        // Day pass: check expiry
        if ($this->messages === 0) {
            return $this->expires_at && $this->expires_at->isFuture();
        }

        // Message pack: check remaining
        return $this->remainingMessages() > 0;
    }
}
