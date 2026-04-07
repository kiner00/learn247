<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurzzoPurchase extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';

    protected $fillable = [
        'user_id',
        'curzzo_id',
        'affiliate_id',
        'xendit_id',
        'status',
        'paid_at',
        'expires_at',
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

    public function curzzo(): BelongsTo
    {
        return $this->belongsTo(Curzzo::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
