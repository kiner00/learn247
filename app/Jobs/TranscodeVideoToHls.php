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
use Illuminate\Support\Str;

class TranscodeVideoToHls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 120;

    public function __construct(public Model&Transcodeable $target) {}

    public function handle(): void
    {
        $target = $this->target;

        $target->setTranscodeStatus('processing', 0);

        $s3Key = $target->getVideoPath();

        if (! $s3Key) {
            Log::warning('TranscodeVideoToHls: video_path is null, skipping', [
                'target' => $target->getTranscodeIdentifier(),
            ]);
            $target->setTranscodeStatus('failed', 0);
            return;
        }

        // Handle legacy full URLs
        if (str_starts_with($s3Key, 'http')) {
            $parsed = parse_url($s3Key);
            $s3Key  = ltrim($parsed['path'] ?? '', '/');
        }

        try {
            $bucket   = config('filesystems.disks.s3.bucket');
            $region   = config('services.mediaconvert.region');
            $endpoint = config('services.mediaconvert.endpoint');
            $roleArn  = config('services.mediaconvert.role_arn');
            $queue    = config('services.mediaconvert.queue');

            $hlsPrefix = $target->getHlsPathPrefix() . '/' . Str::uuid();
            $posterKey = $hlsPrefix . '/poster.0000000.jpg';

            $client = new MediaConvertClient([
                'version'  => '2017-08-29',
                'region'   => $region,
                'endpoint' => $endpoint,
            ]);

            $result = $client->createJob([
                'Role'     => $roleArn,
                'Queue'    => $queue === 'Default' ? ('arn:aws:mediaconvert:' . $region . ':' . $this->getAccountId($roleArn) . ':queues/Default') : $queue,
                'Settings' => [
                    'Inputs' => [
                        [
                            'FileInput'      => "s3://{$bucket}/{$s3Key}",
                            'AudioSelectors' => [
                                'Audio Selector 1' => ['DefaultSelection' => 'DEFAULT'],
                            ],
                            'VideoSelector' => [],
                        ],
                    ],
                    'OutputGroups' => [
                        $this->hlsOutputGroup($bucket, $hlsPrefix),
                        $this->posterOutputGroup($bucket, $hlsPrefix),
                    ],
                ],
            ]);

            $mcJobId = $result['Job']['Id'];

            Log::info('MediaConvert job submitted', [
                'target'           => $target->getTranscodeIdentifier(),
                'mediaconvert_job' => $mcJobId,
                'hls_prefix'       => $hlsPrefix,
            ]);

            $target->setTranscodeStatus('processing', 10);

            CheckMediaConvertStatus::dispatch($target, $mcJobId, $hlsPrefix, $posterKey)
                ->delay(now()->addSeconds(30));
        } catch (\Throwable $e) {
            Log::error('MediaConvert job submission failed', [
                'target' => $target->getTranscodeIdentifier(),
                'error'  => $e->getMessage(),
            ]);

            $target->setTranscodeStatus('failed', 0);

            throw $e;
        }
    }

    private function hlsOutputGroup(string $bucket, string $hlsPrefix): array
    {
        return [
            'Name'                => 'HLS',
            'OutputGroupSettings' => [
                'Type'             => 'HLS_GROUP_SETTINGS',
                'HlsGroupSettings' => [
                    'Destination'      => "s3://{$bucket}/{$hlsPrefix}/video",
                    'SegmentLength'    => 6,
                    'MinSegmentLength' => 0,
                ],
            ],
            'Outputs' => [
                $this->hlsRendition('_360p', 640, 360, 800000, 96000, 'MAIN'),
                $this->hlsRendition('_720p', 1280, 720, 2500000, 128000, 'MAIN'),
                $this->hlsRendition('_1080p', 1920, 1080, 5000000, 192000, 'HIGH'),
            ],
        ];
    }

    private function hlsRendition(string $name, int $w, int $h, int $videoBitrate, int $audioBitrate, string $profile): array
    {
        return [
            'NameModifier'      => $name,
            'ContainerSettings' => ['Container' => 'M3U8'],
            'VideoDescription'  => [
                'Width'         => $w,
                'Height'        => $h,
                'CodecSettings' => [
                    'Codec'        => 'H_264',
                    'H264Settings' => [
                        'RateControlMode' => 'CBR',
                        'Bitrate'         => $videoBitrate,
                        'CodecProfile'    => $profile,
                        'CodecLevel'      => 'LEVEL_4',
                    ],
                ],
            ],
            'AudioDescriptions' => [
                [
                    'AudioSourceName' => 'Audio Selector 1',
                    'CodecSettings'   => [
                        'Codec'       => 'AAC',
                        'AacSettings' => [
                            'Bitrate'    => $audioBitrate,
                            'CodingMode' => 'CODING_MODE_2_0',
                            'SampleRate' => 48000,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function posterOutputGroup(string $bucket, string $hlsPrefix): array
    {
        return [
            'Name'                => 'Poster',
            'OutputGroupSettings' => [
                'Type'                    => 'FILE_GROUP_SETTINGS',
                'FileGroupSettings'       => [
                    'Destination' => "s3://{$bucket}/{$hlsPrefix}/poster",
                ],
            ],
            'Outputs' => [
                [
                    'NameModifier'      => '',
                    'ContainerSettings' => ['Container' => 'RAW'],
                    'VideoDescription'  => [
                        'CodecSettings' => [
                            'Codec'                => 'FRAME_CAPTURE',
                            'FrameCaptureSettings' => [
                                'FramerateNumerator'   => 1,
                                'FramerateDenominator' => 2,
                                'MaxCaptures'          => 1,
                                'Quality'              => 80,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getAccountId(string $roleArn): string
    {
        preg_match('/arn:aws:iam::(\d+):/', $roleArn, $matches);

        return $matches[1] ?? '';
    }

    public function failed(\Throwable $exception): void
    {
        $this->target->setTranscodeStatus('failed', 0);

        Log::error('TranscodeVideoToHls job failed permanently', [
            'target' => $this->target->getTranscodeIdentifier(),
            'error'  => $exception->getMessage(),
        ]);
    }
}
