<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityDirectMessage extends Model
{
    protected $fillable = ['community_id', 'sender_id', 'receiver_id', 'content'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
