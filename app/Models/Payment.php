<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'subscription_id', 'community_id', 'user_id',
        'amount', 'processing_fee', 'platform_fee', 'currency', 'status',
        'provider_reference', 'xendit_event_id', 'metadata', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** Amount the creator actually keeps from this payment. */
    public function creatorNet(): float
    {
        return round((float) $this->amount - (float) $this->processing_fee - (float) $this->platform_fee, 2);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
