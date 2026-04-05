<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    public const TYPE_MANUAL    = 'manual';
    public const TYPE_AUTOMATIC = 'automatic';

    protected $fillable = [
        'community_id', 'name', 'slug', 'color', 'type', 'auto_rule',
    ];

    protected function casts(): array
    {
        return [
            'auto_rule' => 'array',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(CommunityMember::class, 'community_member_tag')
                    ->withPivot('tagged_at');
    }
}
