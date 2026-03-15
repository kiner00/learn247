<?php

namespace Tests\Feature\Actions\Account;

use App\Actions\Account\UpdateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private UpdateProfile $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateProfile();
    }

    public function test_update_basic_fields_concatenates_first_and_last_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $result = $this->action->execute($user, [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ]);

        $this->assertEquals('Jane Doe', $result->name);
    }

    public function test_update_with_avatar_stores_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => null]);

        $avatar = UploadedFile::fake()->image('avatar.jpg');
        $result = $this->action->execute($user, [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ], $avatar);

        $this->assertNotNull($result->avatar);
        $this->assertStringContainsString('/storage/', $result->avatar);
    }

    public function test_update_with_new_avatar_deletes_old_storage_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('user-avatars/old-avatar.jpg', 'dummy');

        $user = User::factory()->create(['avatar' => '/storage/user-avatars/old-avatar.jpg']);

        $newAvatar = UploadedFile::fake()->image('new-avatar.jpg');
        $result = $this->action->execute($user, [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ], $newAvatar);

        $this->assertNotNull($result->avatar);
        Storage::disk('public')->assertMissing('user-avatars/old-avatar.jpg');
    }
}
