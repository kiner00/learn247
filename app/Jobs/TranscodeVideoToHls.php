<?php

namespace App\Jobs;

use App\Models\CourseLesson;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscodeVideoToHls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600; // 1 hour max

    public function __construct(
        public CourseLesson $lesson,
    ) {}

    public function handle(): void
    {
        $lesson = $this->lesson;

        $lesson->update([
            'video_transcode_status'  => 'processing',
            'video_transcode_percent' => 0,
        ]);

        $s3Key = $lesson->video_path;

        if (! $s3Key) {
            Log::warning('TranscodeVideoToHls: video_path is null, skipping', ['lesson_id' => $lesson->id]);
            $lesson->update(['video_transcode_status' => 'failed', 'video_transcode_percent' => 0]);
            return;
        }

        // Handle legacy full URLs
        if (str_starts_with($s3Key, 'http')) {
            $parsed = parse_url($s3Key);
            $s3Key  = ltrim($parsed['path'] ?? '', '/');
        }

        $tempDir    = sys_get_temp_dir() . '/hls-' . Str::uuid();
        $tempSource = $tempDir . '/source.mp4';

        try {
            // Create temp directory
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Download source from S3
            $lesson->update(['video_transcode_percent' => 5]);

            $stream = Storage::readStream($s3Key);
            if (! $stream) {
                throw new \RuntimeException("Cannot read source video from S3: {$s3Key}");
            }

            file_put_contents($tempSource, $stream);
            fclose($stream);

            $lesson->update(['video_transcode_percent' => 10]);

            // Generate HLS with multiple renditions using FFmpeg directly
            $hlsOutputDir = $tempDir . '/hls';
            mkdir($hlsOutputDir, 0755, true);

            $this->transcodeWithFfmpeg($tempSource, $hlsOutputDir, $lesson);

            $lesson->update(['video_transcode_percent' => 85]);

            // Upload all HLS files to S3
            $hlsS3Prefix = 'lesson-videos/hls/' . Str::uuid();
            $this->uploadHlsToS3($hlsOutputDir, $hlsS3Prefix);

            $lesson->update([
                'video_hls_path'          => $hlsS3Prefix . '/master.m3u8',
                'video_transcode_status'  => 'completed',
                'video_transcode_percent' => 100,
            ]);

            Log::info('HLS transcoding completed', ['lesson_id' => $lesson->id, 'hls_path' => $lesson->video_hls_path]);
        } catch (\Throwable $e) {
            Log::error('HLS transcoding failed', [
                'lesson_id' => $lesson->id,
                'error'     => $e->getMessage(),
            ]);

            $lesson->update([
                'video_transcode_status'  => 'failed',
                'video_transcode_percent' => 0,
            ]);

            throw $e;
        } finally {
            // Clean up temp files
            $this->cleanupDirectory($tempDir);
        }
    }

    private function transcodeWithFfmpeg(string $source, string $outputDir, CourseLesson $lesson): void
    {
        $ffmpeg = config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg');

        // Renditions: [width, height, video_bitrate, audio_bitrate, label]
        $renditions = [
            [640,  360,  '800k',  '96k',  '360p'],
            [1280, 720,  '2500k', '128k', '720p'],
            [1920, 1080, '5000k', '192k', '1080p'],
        ];

        // Build FFmpeg command for all renditions in a single pass
        $maps       = [];
        $streamOpts = [];
        $streamMaps = [];

        foreach ($renditions as $i => [$w, $h, $vBitrate, $aBitrate, $label]) {
            $maps[]       = "-map 0:v:0 -map 0:a:0?";
            $streamOpts[] = implode(' ', [
                "-c:v:{$i} libx264 -b:v:{$i} {$vBitrate} -s:v:{$i} {$w}x{$h}",
                "-c:a:{$i} aac -b:a:{$i} {$aBitrate}",
                "-profile:v:{$i} main -level:v:{$i} 4.0",
            ]);
            $streamMaps[] = "v:{$i},a:{$i},name:{$label}";
        }

        $command = implode(' ', [
            escapeshellcmd($ffmpeg),
            '-y -i', escapeshellarg($source),
            implode(' ', $maps),
            implode(' ', $streamOpts),
            '-preset fast',
            '-sc_threshold 0',
            '-g 48 -keyint_min 48',
            '-hls_time 6',
            '-hls_playlist_type vod',
            '-hls_segment_filename', escapeshellarg($outputDir . '/%v/segment_%03d.ts'),
            '-master_pl_name master.m3u8',
            '-var_stream_map', escapeshellarg(implode(' ', $streamMaps)),
            escapeshellarg($outputDir . '/%v/playlist.m3u8'),
        ]);

        // Create rendition directories
        foreach ($renditions as [$w, $h, $vBitrate, $aBitrate, $label]) {
            mkdir($outputDir . '/' . $label, 0755, true);
        }

        $lesson->update(['video_transcode_percent' => 15]);

        // Execute FFmpeg
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new \RuntimeException('Failed to start FFmpeg process');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::error('FFmpeg stderr', ['output' => $stderr]);
            throw new \RuntimeException("FFmpeg exited with code {$exitCode}: " . substr($stderr, -500));
        }

        $lesson->update(['video_transcode_percent' => 80]);
    }

    private function uploadHlsToS3(string $localDir, string $s3Prefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace($localDir . '/', '', $file->getPathname());
            $s3Key        = $s3Prefix . '/' . $relativePath;

            $contentType = match (pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
                'm3u8' => 'application/vnd.apple.mpegurl',
                'ts'   => 'video/MP2T',
                default => 'application/octet-stream',
            };

            Storage::put($s3Key, file_get_contents($file->getPathname()), [
                'ContentType' => $contentType,
                'visibility'  => 'private',
            ]);
        }
    }

    private function cleanupDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    public function failed(\Throwable $exception): void
    {
        $this->lesson->update([
            'video_transcode_status'  => 'failed',
            'video_transcode_percent' => 0,
        ]);

        Log::error('TranscodeVideoToHls job failed permanently', [
            'lesson_id' => $this->lesson->id,
            'error'     => $exception->getMessage(),
        ]);
    }
}
