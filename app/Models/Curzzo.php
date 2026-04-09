<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curzzo extends Model
{
    protected $fillable = [
        'community_id',
        'name',
        'description',
        'avatar',
        'cover_image',
        'preview_video',
        'preview_video_sound',
        'access_type',
        'instructions',
        'personality',
        'model_tier',
        'price',
        'currency',
        'billing_type',
        'affiliate_commission_rate',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'personality'         => 'array',
            'is_active'           => 'boolean',
            'preview_video_sound' => 'boolean',
            'price'               => 'decimal:2',
        ];
    }

    public function isFree(): bool
    {
        return ! $this->price || $this->price <= 0;
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CurzzoMessage::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CurzzoPurchase::class);
    }
}
