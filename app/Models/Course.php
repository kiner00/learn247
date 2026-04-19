<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    use HasFactory;

    public const ACCESS_FREE = 'free';

    public const ACCESS_INCLUSIVE = 'inclusive';

    public const ACCESS_PAID_ONCE = 'paid_once';

    public const ACCESS_PAID_MONTHLY = 'paid_monthly';

    public const ACCESS_MEMBER_ONCE = 'member_once';

    protected $fillable = ['community_id', 'title', 'description', 'cover_image', 'preview_video', 'preview_play_count', 'preview_watch_seconds', 'preview_video_sound', 'position', 'access_type', 'price', 'affiliate_commission_rate', 'is_published'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_published' => 'boolean',
            'preview_video_sound' => 'boolean',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(CourseLesson::class, CourseModule::class, 'course_id', 'module_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }
}
