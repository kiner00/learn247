<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'issued_at'];

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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
