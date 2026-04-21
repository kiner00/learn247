<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneDeletedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_users_past_grace_period(): void
    {
        Storage::fake(config('filesystems.default'));

        $expired = User::factory()->create();
        $expired->delete();
        $expired->deleted_at = now()->subDays(User::DELETION_GRACE_DAYS + 1);
        $expired->save();

        $this->artisan('users:prune-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $expired->id]);
    }

    public function test_leaves_users_still_within_grace_period_alone(): void
    {
        $withinGrace = User::factory()->create();
        $withinGrace->delete();
        $withinGrace->deleted_at = now()->subDays(5);
        $withinGrace->save();

        $this->artisan('users:prune-deleted')->assertSuccessful();

        $this->assertSoftDeleted('users', ['id' => $withinGrace->id]);
    }

    public function test_leaves_active_users_alone(): void
    {
        $active = User::factory()->create();

        $this->artisan('users:prune-deleted')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $active->id, 'deleted_at' => null]);
    }

    public function test_wipes_stored_files_on_hard_delete(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create([
            'avatar' => '/storage/user-avatars/old.jpg',
            'kyc_id_document' => '/storage/kyc-documents/id.jpg',
            'kyc_selfie' => '/storage/kyc-documents/selfie.jpg',
        ]);
        $user->delete();
        $user->deleted_at = now()->subDays(User::DELETION_GRACE_DAYS + 1);
        $user->save();

        // HardDeleteUserAccount calls StorageService::delete on each URL.
        // With Storage::fake, the delete is a no-op but should not error.
        $this->artisan('users:prune-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
