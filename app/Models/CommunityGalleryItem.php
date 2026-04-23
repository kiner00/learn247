<?php

namespace App\Models;

use App\Contracts\Transcodeable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CommunityGalleryItem extends Model implements Transcodeable
{
    use HasFactory;

    protected $fillable = [
        'community_id', 'type', 'image_path', 'video_path', 'video_hls_path',
        'poster_path', 'transcode_status', 'transcode_percent',
        'mediaconvert_job_id', 'position', 'autoplay',
    ];

    protected $casts = [
        'transcode_percent' => 'integer',
        'position' => 'integer',
        'autoplay' => 'boolean',
    ];

    protected $appends = ['url', 'poster_url', 'video_ready'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->type === 'image') {
            return $this->resolveStorageUrl($this->image_path);
        }

        return $this->resolveStorageUrl($this->poster_path);
    }

    public function getPosterUrlAttribute(): ?string
    {
        return $this->resolveStorageUrl($this->poster_path);
    }

    /**
     * Build a public URL whether $value is a raw S3 key, a legacy /storage/ path,
     * or already a fully-qualified URL (back-compat for pre-migration rows).
     */
    private function resolveStorageUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        return Storage::url($value);
    }

    public function getVideoReadyAttribute(): bool
    {
        return $this->type === 'video'
            && $this->transcode_status === 'completed'
            && filled($this->video_hls_path);
    }

    public function getVideoPath(): ?string
    {
        return $this->video_path;
    }

    public function setTranscodeStatus(string $status, int $percent): void
    {
        $this->update([
            'transcode_status' => $status,
            'transcode_percent' => $percent,
        ]);
    }

    public function setHlsPath(string $path): void
    {
        $this->update(['video_hls_path' => $path]);
    }

    public function setPosterPath(?string $path): void
    {
        $this->update(['poster_path' => $path]);
    }

    public function getHlsPathPrefix(): string
    {
        return 'gallery-videos/hls';
    }

    public function getTranscodeIdentifier(): string
    {
        return "gallery-item:{$this->id}";
    }
}
