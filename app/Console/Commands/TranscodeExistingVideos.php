<?php

namespace App\Console\Commands;

use App\Jobs\TranscodeVideoToHls;
use App\Models\CourseLesson;
use Illuminate\Console\Command;

class TranscodeExistingVideos extends Command
{
    protected $signature = 'videos:transcode-existing';

    protected $description = 'Dispatch transcoding jobs for all uploaded videos that have not been transcoded yet';

    public function handle(): int
    {
        $lessons = CourseLesson::query()
            ->whereNotNull('video_path')
            ->where('video_path', '!=', '')
            ->where(function ($q) {
                $q->whereNull('video_hls_path')
                    ->orWhere('video_transcode_status', 'failed');
            })
            ->where(function ($q) {
                $q->whereNull('video_transcode_status')
                    ->orWhereIn('video_transcode_status', ['failed', 'pending']);
            })
            ->get();

        if ($lessons->isEmpty()) {
            $this->info('No videos need transcoding.');

            return self::SUCCESS;
        }

        $this->info("Found {$lessons->count()} video(s) to transcode.");

        foreach ($lessons as $lesson) {
            TranscodeVideoToHls::dispatch($lesson);
            $this->line("  Dispatched: Lesson #{$lesson->id} — {$lesson->video_path}");
        }

        $this->info('All jobs dispatched. Check your queue worker and AWS MediaConvert console.');

        return self::SUCCESS;
    }
}
