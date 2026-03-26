<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationQuestionOption extends Model
{
    protected $fillable = ['question_id', 'label', 'is_correct'];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(CertificationQuestion::class, 'question_id');
    }
}
