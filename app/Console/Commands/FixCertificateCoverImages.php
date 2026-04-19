<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixCertificateCoverImages extends Command
{
    protected $signature = 'certificates:fix-covers
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix certificate cover_image URLs that were not migrated to S3 or have stale paths';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        $fixed = 0;
        $synced = 0;

        // 1. Fix old /storage/ paths → S3 URLs (missed by MigrateStorageToS3)
        $fixed += $this->fixLocalPaths($isDryRun);

        // 2. Sync cover_image from CourseCertification → Certificate for any mismatches
        $synced += $this->syncFromCertifications($isDryRun);

        $this->newLine();
        $this->info("Done! Fixed local paths: {$fixed}, Synced from certification: {$synced}");

        return 0;
    }

    private function fixLocalPaths(bool $isDryRun): int
    {
        if (! Schema::hasTable('certificates')) {
            $this->warn('certificates table not found.');

            return 0;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        if (! $bucket || ! $region) {
            $this->info('S3 not configured — skipping local path fix.');

            return 0;
        }

        $s3BaseUrl = "https://{$bucket}.s3.{$region}.amazonaws.com";
        $appUrl = rtrim(config('app.url'), '/');

        $rows = DB::table('certificates')
            ->whereNotNull('cover_image')
            ->where(function ($q) use ($appUrl) {
                $q->where('cover_image', 'LIKE', '/storage/%')
                    ->orWhere('cover_image', 'LIKE', $appUrl.'/storage/%');
            })
            ->get(['id', 'uuid', 'cover_image']);

        if ($rows->isEmpty()) {
            $this->info('No certificates with old /storage/ paths found.');

            return 0;
        }

        $this->info("Found {$rows->count()} certificate(s) with old local paths.");

        $count = 0;
        foreach ($rows as $row) {
            $value = $row->cover_image;

            if (str_starts_with($value, '/storage/')) {
                $newValue = $s3BaseUrl.'/'.substr($value, 9);
            } elseif (str_starts_with($value, $appUrl.'/storage/')) {
                $newValue = $s3BaseUrl.'/'.substr($value, strlen($appUrl.'/storage/'));
            } else {
                continue;
            }

            $this->line("  [{$row->uuid}] {$value} → {$newValue}");

            if (! $isDryRun) {
                DB::table('certificates')->where('id', $row->id)->update(['cover_image' => $newValue]);
            }

            $count++;
        }

        return $count;
    }

    private function syncFromCertifications(bool $isDryRun): int
    {
        // Find certificates where cover_image doesn't match their parent certification
        $rows = DB::table('certificates as c')
            ->join('course_certifications as cc', 'c.certification_id', '=', 'cc.id')
            ->whereNotNull('cc.cover_image')
            ->where(function ($q) {
                $q->whereNull('c.cover_image')
                    ->orWhereColumn('c.cover_image', '!=', 'cc.cover_image');
            })
            ->select('c.id', 'c.uuid', 'c.cover_image as cert_cover', 'cc.cover_image as source_cover')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('All certificate cover images match their certification source.');

            return 0;
        }

        $this->info("Found {$rows->count()} certificate(s) with mismatched cover images.");

        $count = 0;
        foreach ($rows as $row) {
            $this->line("  [{$row->uuid}] ".($row->cert_cover ?: '(empty)')." → {$row->source_cover}");

            if (! $isDryRun) {
                DB::table('certificates')->where('id', $row->id)->update(['cover_image' => $row->source_cover]);
            }

            $count++;
        }

        return $count;
    }
}
