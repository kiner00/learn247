<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_FREE = 'free';

    public const VISIBILITY_PAID = 'paid';

    protected $fillable = [
        'community_id', 'created_by', 'title', 'description',
        'start_at', 'end_at', 'timezone', 'url', 'cover_image', 'visibility',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
