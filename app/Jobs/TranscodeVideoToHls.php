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
use Illuminate\Support\Str;

class TranscodeVideoToHls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

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

        try {
            $bucket    = config('filesystems.disks.s3.bucket');
            $region    = config('services.mediaconvert.region');
            $endpoint  = config('services.mediaconvert.endpoint');
            $roleArn   = config('services.mediaconvert.role_arn');
            $queue     = config('services.mediaconvert.queue');

            $hlsPrefix = 'lesson-videos/hls/' . Str::uuid();

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
                            'FileInput' => "s3://{$bucket}/{$s3Key}",
                            'AudioSelectors' => [
                                'Audio Selector 1' => [
                                    'DefaultSelection' => 'DEFAULT',
                                ],
                            ],
                            'VideoSelector' => [],
                        ],
                    ],
                    'OutputGroups' => [
                        [
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
                                // 360p
                                [
                                    'NameModifier' => '_360p',
                                    'ContainerSettings' => [
                                        'Container' => 'M3U8',
                                    ],
                                    'VideoDescription' => [
                                        'Width'  => 640,
                                        'Height' => 360,
                                        'CodecSettings' => [
                                            'Codec'        => 'H_264',
                                            'H264Settings' => [
                                                'RateControlMode' => 'CBR',
                                                'Bitrate'         => 800000,
                                                'CodecProfile'    => 'MAIN',
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
                                                    'Bitrate'    => 96000,
                                                    'CodingMode' => 'CODING_MODE_2_0',
                                                    'SampleRate' => 48000,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                // 720p
                                [
                                    'NameModifier' => '_720p',
                                    'ContainerSettings' => [
                                        'Container' => 'M3U8',
                                    ],
                                    'VideoDescription' => [
                                        'Width'  => 1280,
                                        'Height' => 720,
                                        'CodecSettings' => [
                                            'Codec'        => 'H_264',
                                            'H264Settings' => [
                                                'RateControlMode' => 'CBR',
                                                'Bitrate'         => 2500000,
                                                'CodecProfile'    => 'MAIN',
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
                                                    'Bitrate'    => 128000,
                                                    'CodingMode' => 'CODING_MODE_2_0',
                                                    'SampleRate' => 48000,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                // 1080p
                                [
                                    'NameModifier' => '_1080p',
                                    'ContainerSettings' => [
                                        'Container' => 'M3U8',
                                    ],
                                    'VideoDescription' => [
                                        'Width'  => 1920,
                                        'Height' => 1080,
                                        'CodecSettings' => [
                                            'Codec'        => 'H_264',
                                            'H264Settings' => [
                                                'RateControlMode' => 'CBR',
                                                'Bitrate'         => 5000000,
                                                'CodecProfile'    => 'HIGH',
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
                                                    'Bitrate'    => 192000,
                                                    'CodingMode' => 'CODING_MODE_2_0',
                                                    'SampleRate' => 48000,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $mcJobId = $result['Job']['Id'];

            Log::info('MediaConvert job submitted', [
                'lesson_id'        => $lesson->id,
                'mediaconvert_job' => $mcJobId,
                'hls_prefix'       => $hlsPrefix,
            ]);

            $lesson->update([
                'video_transcode_percent' => 10,
            ]);

            // Dispatch polling job to check when MediaConvert finishes
            CheckMediaConvertStatus::dispatch($lesson, $mcJobId, $hlsPrefix)
                ->delay(now()->addSeconds(30));

        } catch (\Throwable $e) {
            Log::error('MediaConvert job submission failed', [
                'lesson_id' => $lesson->id,
                'error'     => $e->getMessage(),
            ]);

            $lesson->update([
                'video_transcode_status'  => 'failed',
                'video_transcode_percent' => 0,
            ]);

            throw $e;
        }
    }

    private function getAccountId(string $roleArn): string
    {
        // Extract AWS account ID from role ARN: arn:aws:iam::123456789012:role/...
        preg_match('/arn:aws:iam::(\d+):/', $roleArn, $matches);

        return $matches[1] ?? '';
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
