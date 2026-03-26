<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificationQuestion extends Model
{
    protected $fillable = ['certification_id', 'question', 'type', 'position'];

    public function certification(): BelongsTo
    {
        return $this->belongsTo(CourseCertification::class, 'certification_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CertificationQuestionOption::class, 'question_id');
    }
}
