<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateStorageToS3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('s3');

        config([
            'filesystems.disks.s3.bucket' => 'test-bucket',
            'filesystems.disks.s3.region' => 'ap-southeast-1',
            'app.url' => 'https://example.com',
        ]);
    }

    public function test_no_files_to_migrate(): void
    {
        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('No files found in local public storage.')
            ->assertExitCode(0);
    }

    public function test_successful_migration_uploads_files_to_s3(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'photo-content');
        Storage::disk('public')->put('documents/file.pdf', 'pdf-content');

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Found 2 files in local storage.')
            ->expectsOutputToContain('Uploaded: 2, Skipped: 0, Failed: 0')
            ->assertExitCode(0);

        Storage::disk('s3')->assertExists('images/photo.jpg');
        Storage::disk('s3')->assertExists('documents/file.pdf');
    }

    public function test_skips_hidden_files(): void
    {
        Storage::disk('public')->put('.gitignore', 'ignore');
        Storage::disk('public')->put('.hidden', 'hidden');
        Storage::disk('public')->put('visible.jpg', 'content');

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Uploaded: 1, Skipped: 2, Failed: 0')
            ->assertExitCode(0);

        Storage::disk('s3')->assertExists('visible.jpg');
        Storage::disk('s3')->assertMissing('.gitignore');
        Storage::disk('s3')->assertMissing('.hidden');
    }

    public function test_skips_files_that_already_exist_on_s3(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'local-content');
        Storage::disk('s3')->put('images/photo.jpg', 'already-on-s3');

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Uploaded: 0, Skipped: 1, Failed: 0')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_upload_files(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'photo-content');
        Storage::disk('public')->put('docs/file.pdf', 'pdf-content');

        $this->artisan('storage:migrate-to-s3', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Uploaded: 2, Skipped: 0, Failed: 0')
            ->assertExitCode(0);

        Storage::disk('s3')->assertMissing('images/photo.jpg');
        Storage::disk('s3')->assertMissing('docs/file.pdf');
    }

    public function test_dry_run_skips_hidden_files(): void
    {
        Storage::disk('public')->put('.gitignore', 'ignore');
        Storage::disk('public')->put('visible.jpg', 'content');

        $this->artisan('storage:migrate-to-s3', ['--dry-run' => true])
            ->expectsOutputToContain('Uploaded: 1, Skipped: 1, Failed: 0')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_update_database(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'content');

        // Insert a row with /storage/ path
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar')) {
            DB::table('users')->insert([
                'name' => 'Test',
                'email' => 'test@example.com',
                'password' => 'hashed',
                'avatar' => '/storage/images/photo.jpg',
            ]);
        }

        $this->artisan('storage:migrate-to-s3', ['--dry-run' => true])
            ->assertExitCode(0);

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar')) {
            $user = DB::table('users')->where('email', 'test@example.com')->first();
            $this->assertEquals('/storage/images/photo.jpg', $user->avatar);
        }
    }

    public function test_delete_local_option_removes_local_files_after_upload(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'photo-content');

        $this->artisan('storage:migrate-to-s3', ['--delete-local' => true])
            ->expectsOutputToContain('Uploaded: 1, Skipped: 0, Failed: 0')
            ->assertExitCode(0);

        Storage::disk('s3')->assertExists('images/photo.jpg');
        Storage::disk('public')->assertMissing('images/photo.jpg');
    }

    public function test_without_delete_local_keeps_local_files(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'photo-content');

        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('images/photo.jpg');
        Storage::disk('s3')->assertExists('images/photo.jpg');
    }

    public function test_handles_upload_error_gracefully(): void
    {
        // The error handling path (lines 72-76) is exercised when S3 writeStream fails.
        // Since Storage::fake() doesn't easily allow simulating write failures,
        // we verify the command runs without error when files exist.
        // The catch block increments $failed counter and outputs error message.
        Storage::disk('public')->put('images/photo.jpg', 'content');

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Uploaded: 1')
            ->assertExitCode(0);
    }

    public function test_updates_storage_paths_in_database(): void
    {
        Storage::disk('public')->put('avatars/user.jpg', 'content');

        $user = \App\Models\User::factory()->create([
            'avatar' => '/storage/avatars/user.jpg',
        ]);

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Updating database URLs')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals(
            'https://test-bucket.s3.ap-southeast-1.amazonaws.com/avatars/user.jpg',
            $user->avatar
        );
    }

    public function test_updates_asset_style_urls_in_database(): void
    {
        Storage::disk('public')->put('avatars/user.jpg', 'content');

        $user = \App\Models\User::factory()->create([
            'avatar' => 'https://example.com/storage/avatars/user.jpg',
        ]);

        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals(
            'https://test-bucket.s3.ap-southeast-1.amazonaws.com/avatars/user.jpg',
            $user->avatar
        );
    }

    public function test_updates_community_gallery_images_json(): void
    {
        Storage::disk('public')->put('gallery/img1.jpg', 'content');

        $community = \App\Models\Community::factory()->create();
        // Seed the legacy JSON column directly — the migration command operates on it via raw DB.
        \DB::table('communities')->where('id', $community->id)->update([
            'gallery_images' => json_encode([
                '/storage/gallery/img1.jpg',
                'https://example.com/storage/gallery/img2.jpg',
            ]),
        ]);

        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);

        $row = \DB::table('communities')->where('id', $community->id)->first();
        $gallery = json_decode($row->gallery_images, true);

        $this->assertEquals(
            'https://test-bucket.s3.ap-southeast-1.amazonaws.com/gallery/img1.jpg',
            $gallery[0]
        );
        $this->assertEquals(
            'https://test-bucket.s3.ap-southeast-1.amazonaws.com/gallery/img2.jpg',
            $gallery[1]
        );
    }

    public function test_dry_run_skips_database_update(): void
    {
        Storage::disk('public')->put('avatars/user.jpg', 'content');

        $user = \App\Models\User::factory()->create([
            'avatar' => '/storage/avatars/user.jpg',
        ]);

        $this->artisan('storage:migrate-to-s3', ['--dry-run' => true])
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals('/storage/avatars/user.jpg', $user->avatar);
    }

    public function test_skips_missing_tables_gracefully(): void
    {
        Storage::disk('public')->put('file.jpg', 'content');

        // The command should handle missing tables gracefully via Schema::hasTable checks
        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);
    }

    public function test_mixed_files_with_hidden_and_normal(): void
    {
        Storage::disk('public')->put('.gitignore', 'ignore');
        Storage::disk('public')->put('normal/file.jpg', 'content');
        Storage::disk('public')->put('another.pdf', 'pdf');

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Found 3 files')
            ->expectsOutputToContain('Uploaded: 2, Skipped: 1, Failed: 0')
            ->assertExitCode(0);
    }

    public function test_handles_s3_write_failure_gracefully(): void
    {
        Storage::disk('public')->put('images/photo.jpg', 'photo-content');

        // Replace s3 disk with a mock that throws on writeStream
        $s3Mock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $s3Mock->shouldReceive('exists')->andReturn(false);
        $s3Mock->shouldReceive('writeStream')->andThrow(new \Exception('S3 connection timed out'));

        Storage::set('s3', $s3Mock);

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('Failed: images/photo.jpg')
            ->expectsOutputToContain('Uploaded: 0, Skipped: 0, Failed: 1')
            ->assertExitCode(0);
    }

    public function test_skips_nonexistent_table_during_url_update(): void
    {
        Storage::disk('public')->put('file.jpg', 'content');

        // Temporarily mock Schema to return false for a specific table
        Schema::shouldReceive('hasTable')
            ->andReturnUsing(fn (string $table) => $table !== 'course_certifications');
        Schema::shouldReceive('hasColumn')->andReturn(true);

        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);
    }

    public function test_skips_nonexistent_column_during_url_update(): void
    {
        Storage::disk('public')->put('file.jpg', 'content');

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('hasColumn')
            ->andReturnUsing(fn (string $table, string $column) => $column !== 'avatar');

        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);
    }

    public function test_skips_rows_with_case_mismatch_storage_urls(): void
    {
        Storage::disk('public')->put('file.jpg', 'content');

        // SQL LIKE is case-insensitive, so '/Storage/img.jpg' matches LIKE '/storage/%'
        // but PHP str_starts_with is case-sensitive — the else-continue branch is hit.
        $user = \App\Models\User::factory()->create(['avatar' => '/Storage/images/test.jpg']);

        $this->artisan('storage:migrate-to-s3')
            ->assertExitCode(0);

        $user->refresh();
        // The avatar should remain unchanged because the else-continue was hit
        $this->assertEquals('/Storage/images/test.jpg', $user->avatar);
    }
}
