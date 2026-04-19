<?php

namespace App\Jobs;

use App\Contracts\Transcodeable;
use Aws\MediaConvert\MediaConvertClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckMediaConvertStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public int $timeout = 30;

    public function __construct(
        public Model&Transcodeable $target,
        public string $mediaConvertJobId,
        public string $hlsPrefix,
        public ?string $posterKey = null,
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
            'target' => $this->target->getTranscodeIdentifier(),
            'mc_job' => $this->mediaConvertJobId,
            'status' => $status,
        ]);

        match ($status) {
            'COMPLETE' => $this->handleComplete(),
            'ERROR'    => $this->handleError($result),
            'CANCELED' => $this->handleError($result),
            default    => $this->handleProgressing($result),
        };
    }

    private function handleComplete(): void
    {
        $this->target->setHlsPath($this->hlsPrefix . '/video.m3u8');

        if ($this->posterKey) {
            $disk = Storage::disk(config('filesystems.default'));
            $this->target->setPosterPath($disk->exists($this->posterKey) ? $this->posterKey : null);
        }

        $this->target->setTranscodeStatus('completed', 100);

        Log::info('HLS transcoding completed via MediaConvert', [
            'target'   => $this->target->getTranscodeIdentifier(),
            'hls_path' => $this->hlsPrefix . '/video.m3u8',
        ]);
    }

    private function handleError($result): void
    {
        $errorMessage = $result['Job']['ErrorMessage'] ?? 'Unknown error';

        $this->target->setTranscodeStatus('failed', 0);

        Log::error('MediaConvert transcoding failed', [
            'target' => $this->target->getTranscodeIdentifier(),
            'mc_job' => $this->mediaConvertJobId,
            'error'  => $errorMessage,
        ]);
    }

    private function handleProgressing($result): void
    {
        $percent = $result['Job']['JobPercentComplete'] ?? 0;

        $scaledPercent = 10 + (int) ($percent * 0.8);

        $this->target->setTranscodeStatus('processing', min($scaledPercent, 90));

        self::dispatch($this->target, $this->mediaConvertJobId, $this->hlsPrefix, $this->posterKey)
            ->delay(now()->addSeconds(30));
    }

    public function failed(\Throwable $exception): void
    {
        $this->target->setTranscodeStatus('failed', 0);

        Log::error('CheckMediaConvertStatus job failed', [
            'target' => $this->target->getTranscodeIdentifier(),
            'mc_job' => $this->mediaConvertJobId,
            'error'  => $exception->getMessage(),
        ]);
    }
}
