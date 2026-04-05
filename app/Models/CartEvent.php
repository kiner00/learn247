<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartEvent extends Model
{
    use HasFactory;

    public const TYPE_CHECKOUT_STARTED   = 'checkout_started';
    public const TYPE_PAYMENT_COMPLETED  = 'payment_completed';
    public const TYPE_ABANDONED          = 'abandoned';

    protected $fillable = [
        'community_id', 'user_id', 'email', 'event_type',
        'reference_type', 'reference_id', 'metadata', 'abandoned_email_sent',
    ];

    protected function casts(): array
    {
        return [
            'metadata'             => 'array',
            'abandoned_email_sent' => 'boolean',
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
}
