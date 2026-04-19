<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityInvite extends Model
{
    protected $fillable = ['community_id', 'email', 'token', 'accepted_at', 'expires_at', 'free_access_months'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at);
    }

    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }
}
