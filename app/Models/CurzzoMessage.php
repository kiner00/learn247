<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurzzoMessage extends Model
{
    protected $fillable = [
        'curzzo_id',
        'community_id',
        'user_id',
        'role',
        'content',
        'conversation_id',
    ];

    public function curzzo(): BelongsTo
    {
        return $this->belongsTo(Curzzo::class);
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
