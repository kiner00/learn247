<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'user_id', 'certification_id', 'issued_at', 'cert_title', 'description', 'cover_image'];

    protected $casts = ['issued_at' => 'datetime'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($cert) => $cert->uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(CourseCertification::class, 'certification_id');
    }
}
