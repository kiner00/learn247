<?php

namespace App\Models;

use App\Contracts\Transcodeable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseLesson extends Model implements Transcodeable
{
    use HasFactory;

    protected $fillable = ['module_id', 'title', 'content', 'embed_html', 'video_url', 'video_path', 'video_hls_path', 'video_transcode_status', 'video_transcode_percent', 'video_play_count', 'video_watch_seconds', 'position', 'cta_label', 'cta_url'];

    protected function casts(): array
    {
        return [
            'video_transcode_percent' => 'integer',
        ];
    }

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

    public function getVideoPath(): ?string
    {
        return $this->video_path;
    }

    public function setTranscodeStatus(string $status, int $percent): void
    {
        $this->update([
            'video_transcode_status'  => $status,
            'video_transcode_percent' => $percent,
        ]);
    }

    public function setHlsPath(string $path): void
    {
        $this->update(['video_hls_path' => $path]);
    }

    public function setPosterPath(?string $path): void
    {
        // Course lessons don't store posters; ignore.
    }

    public function getHlsPathPrefix(): string
    {
        return 'lesson-videos/hls';
    }

    public function getTranscodeIdentifier(): string
    {
        return "lesson:{$this->id}";
    }
}
