<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'title',
        'cert_title',
        'description',
        'cover_image',
        'pass_score',
        'randomize_questions',
        'price',
        'affiliate_commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'randomize_questions' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function isFree(): bool
    {
        return ! $this->price || $this->price <= 0;
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CertificationPurchase::class, 'certification_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CertificationQuestion::class, 'certification_id')->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CertificationAttempt::class, 'certification_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'certification_id');
    }
}
