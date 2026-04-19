<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    const TYPE_LIKE = 'like';

    const TYPE_HANDSHAKE = 'handshake';

    const TYPE_TROPHY = 'trophy';

    protected $fillable = ['user_id', 'likeable_type', 'likeable_id', 'type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}
