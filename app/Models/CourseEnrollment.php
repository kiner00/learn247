<?php

namespace App\Models;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    use Concerns\HasRecurringPlan;
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';

    protected $fillable = [
        'user_id', 'course_id', 'affiliate_id', 'xendit_id', 'status', 'paid_at', 'expires_at',
        'xendit_plan_id', 'xendit_customer_id', 'recurring_status',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
