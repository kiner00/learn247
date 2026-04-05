<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDailyStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'community_id', 'date', 'sent', 'delivered', 'opened',
        'clicked', 'bounced', 'complained', 'unsubscribed',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
