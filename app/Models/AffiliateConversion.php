<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateConversion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';

    protected $fillable = [
        'affiliate_id', 'subscription_id', 'course_enrollment_id', 'payment_id', 'referred_user_id',
        'sale_amount', 'platform_fee', 'commission_amount', 'creator_amount',
        'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount'       => 'decimal:2',
            'platform_fee'      => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'creator_amount'    => 'decimal:2',
            'paid_at'           => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
