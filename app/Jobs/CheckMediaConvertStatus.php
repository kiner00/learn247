<?php

namespace App\Jobs;

use App\Models\CourseLesson;
use Aws\MediaConvert\MediaConvertClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMediaConvertStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60; // max ~30 minutes of polling (30s intervals)

    public int $timeout = 30;

    public function __construct(
        public CourseLesson $lesson,
        public string $mediaConvertJobId,
        public string $hlsPrefix,
    ) {}

    public function handle(): void
    {
        $region   = config('services.mediaconvert.region');
        $endpoint = config('services.mediaconvert.endpoint');

        $client = new MediaConvertClient([
            'version'  => '2017-08-29',
            'region'   => $region,
            'endpoint' => $endpoint,
        ]);

        $result = $client->getJob(['Id' => $this->mediaConvertJobId]);
        $status = $result['Job']['Status'];

        Log::info('MediaConvert status check', [
            'lesson_id' => $this->lesson->id,
            'mc_job'    => $this->mediaConvertJobId,
            'status'    => $status,
        ]);

        match ($status) {
            'COMPLETE'  => $this->handleComplete(),
            'ERROR'     => $this->handleError($result),
            'CANCELED'  => $this->handleError($result),
            default     => $this->handleProgressing($result),
        };
    }

    private function handleComplete(): void
    {
        // MediaConvert outputs a master playlist named the same as the first output
        // We need to find the .m3u8 master playlist in the HLS prefix
        $this->lesson->update([
            'video_hls_path'          => $this->hlsPrefix . '/video.m3u8',
            'video_transcode_status'  => 'completed',
            'video_transcode_percent' => 100,
        ]);

        Log::info('HLS transcoding completed via MediaConvert', [
            'lesson_id' => $this->lesson->id,
            'hls_path'  => $this->lesson->video_hls_path,
        ]);
    }

    private function handleError(array $result): void
    {
        $errorMessage = $result['Job']['ErrorMessage'] ?? 'Unknown error';

        $this->lesson->update([
            'video_transcode_status'  => 'failed',
            'video_transcode_percent' => 0,
        ]);

        Log::error('MediaConvert transcoding failed', [
            'lesson_id' => $this->lesson->id,
            'mc_job'    => $this->mediaConvertJobId,
            'error'     => $errorMessage,
        ]);
    }

    private function handleProgressing(array $result): void
    {
        $percent = $result['Job']['JobPercentComplete'] ?? 0;

        // Scale to 10-90 range (10 = submitted, 100 = complete)
        $scaledPercent = 10 + (int) ($percent * 0.8);

        $this->lesson->update([
            'video_transcode_percent' => min($scaledPercent, 90),
        ]);

        // Re-dispatch to check again in 30 seconds
        self::dispatch($this->lesson, $this->mediaConvertJobId, $this->hlsPrefix)
            ->delay(now()->addSeconds(30));
    }

    public function failed(\Throwable $exception): void
    {
        $this->lesson->update([
            'video_transcode_status'  => 'failed',
            'video_transcode_percent' => 0,
        ]);

        Log::error('CheckMediaConvertStatus job failed', [
            'lesson_id' => $this->lesson->id,
            'mc_job'    => $this->mediaConvertJobId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
