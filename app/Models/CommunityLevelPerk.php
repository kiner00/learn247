<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityLevelPerk extends Model
{
    protected $fillable = ['community_id', 'level', 'description'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
