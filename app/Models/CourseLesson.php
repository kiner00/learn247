<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseLesson extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'title', 'content', 'video_url', 'video_path', 'position', 'cta_label', 'cta_url'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class, 'lesson_id');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class, 'lesson_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'lesson_id')->whereNull('parent_id')->with(['author:id,name,username,avatar', 'replies.author:id,name,username,avatar'])->latest();
    }
}
