<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationAttempt extends Model
{
    protected $fillable = [
        'certification_id',
        'user_id',
        'answers',
        'score',
        'passed',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers'      => 'array',
            'passed'       => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(CourseCertification::class, 'certification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
