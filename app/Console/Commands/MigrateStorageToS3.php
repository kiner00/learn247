<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateStorageToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3
                            {--dry-run : List files without uploading}
                            {--delete-local : Delete local files after successful upload}';

    protected $description = 'Migrate all files from local public storage to S3 and update database URLs';

    private int $uploaded = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $deleteLocal = $this->option('delete-local');

        $local = Storage::disk('public');
        $s3 = Storage::disk('s3');

        $files = $local->allFiles();

        if (empty($files)) {
            $this->info('No files found in local public storage.');
            return 0;
        }

        $this->info(sprintf('Found %d files in local storage.', count($files)));

        if ($isDryRun) {
            $this->warn('DRY RUN — no files will be uploaded.');
        }

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $path) {
            // Skip .gitignore and hidden files
            if (str_starts_with(basename($path), '.')) {
                $this->skipped++;
                $bar->advance();
                continue;
            }

            if (!$isDryRun) {
                try {
                    if ($s3->exists($path)) {
                        $this->skipped++;
                        $bar->advance();
                        continue;
                    }

                    $stream = $local->readStream($path);
                    $s3->writeStream($path, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if ($deleteLocal && $s3->exists($path)) {
                        $local->delete($path);
                    }

                    $this->uploaded++;
                } catch (\Exception $e) {
                    $this->failed++;
                    $this->newLine();
                    $this->error("Failed: {$path} — {$e->getMessage()}");
                }
            } else {
                $this->uploaded++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Update database URLs
        if (!$isDryRun) {
            $this->info('Updating database URLs...');
            $this->updateDatabaseUrls();
        }

        $this->info("Done! Uploaded: {$this->uploaded}, Skipped: {$this->skipped}, Failed: {$this->failed}");

        return 0;
    }

    private function updateDatabaseUrls(): void
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        $s3BaseUrl = "https://{$bucket}.s3.{$region}.amazonaws.com";

        // Patterns to find: /storage/path or https://domain/storage/path
        $tables = [
            ['table' => 'users',       'columns' => ['avatar']],
            ['table' => 'communities', 'columns' => ['avatar', 'cover_image']],
            ['table' => 'courses',     'columns' => ['cover_image']],
            ['table' => 'course_lessons', 'columns' => ['video_path']],
            ['table' => 'posts',       'columns' => ['image']],
            ['table' => 'events',      'columns' => ['cover_image']],
            ['table' => 'course_certifications', 'columns' => ['cover_image']],
            ['table' => 'certificates', 'columns' => ['cover_image']],
        ];

        $totalUpdated = 0;

        foreach ($tables as $config) {
            $table = $config['table'];

            // Check table exists
            if (!\Schema::hasTable($table)) {
                continue;
            }

            $appUrl = rtrim(config('app.url'), '/');

            foreach ($config['columns'] as $column) {
                if (!\Schema::hasColumn($table, $column)) {
                    continue;
                }

                $oldPrefix = $appUrl . '/storage/';

                // Update /storage/xxx and asset-style URLs to S3 URLs
                $rows = \DB::table($table)
                    ->where(function ($q) use ($column, $oldPrefix) {
                        $q->where($column, 'LIKE', '/storage/%')
                          ->orWhere($column, 'LIKE', $oldPrefix . '%');
                    })
                    ->get(['id', $column]);

                $count = 0;
                foreach ($rows as $row) {
                    $value = $row->{$column};
                    if (str_starts_with($value, '/storage/')) {
                        $newValue = $s3BaseUrl . '/' . substr($value, 9);
                    } elseif (str_starts_with($value, $oldPrefix)) {
                        $newValue = $s3BaseUrl . '/' . substr($value, strlen($oldPrefix));
                    } else {
                        continue;
                    }

                    \DB::table($table)->where('id', $row->id)->update([$column => $newValue]);
                    $count++;
                }

                if ($count > 0) {
                    $this->line("  {$table}.{$column}: updated {$count} rows → S3");
                    $totalUpdated += $count;
                }
            }

            // Handle gallery_images JSON column for communities
            if ($table === 'communities' && \Schema::hasColumn($table, 'gallery_images')) {
                $communities = \DB::table('communities')
                    ->whereNotNull('gallery_images')
                    ->where('gallery_images', '!=', '[]')
                    ->get(['id', 'gallery_images']);

                foreach ($communities as $community) {
                    $gallery = json_decode($community->gallery_images, true);
                    if (!is_array($gallery)) continue;

                    $changed = false;
                    foreach ($gallery as &$url) {
                        if (str_starts_with($url, '/storage/')) {
                            $url = $s3BaseUrl . '/' . ltrim(str_replace('/storage/', '', $url), '/');
                            $changed = true;
                        } elseif (str_starts_with($url, $appUrl . '/storage/')) {
                            $url = $s3BaseUrl . '/' . substr($url, strlen($appUrl . '/storage/'));
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        \DB::table('communities')
                            ->where('id', $community->id)
                            ->update(['gallery_images' => json_encode(array_values($gallery))]);
                        $totalUpdated++;
                        $this->line("  communities.gallery_images: updated community #{$community->id}");
                    }
                }
            }
        }

        $this->info("Database: {$totalUpdated} total URL updates.");
    }
}
