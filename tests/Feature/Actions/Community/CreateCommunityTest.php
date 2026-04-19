<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\CreateCommunity;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateCommunityTest extends TestCase
{
    use RefreshDatabase;

    private CreateCommunity $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(CreateCommunity::class);
    }

    public function test_creates_community_with_provided_slug(): void
    {
        $user = User::factory()->create();
        $community = $this->action->execute($user, [
            'name' => 'Test Community',
            'slug' => 'test-community',
            'description' => 'A test',
            'is_private' => false,
            'price' => 0,
            'currency' => 'PHP',
        ]);

        $this->assertInstanceOf(Community::class, $community);
        $this->assertEquals('test-community', $community->slug);
        $this->assertEquals($user->id, $community->owner_id);
        $this->assertDatabaseHas('communities', ['slug' => 'test-community', 'owner_id' => $user->id]);
    }

    public function test_auto_generates_slug_from_name_when_not_provided(): void
    {
        $user = User::factory()->create();
        $community = $this->action->execute($user, ['name' => 'Hello World Community']);

        $this->assertEquals('hello-world-community', $community->slug);
    }

    public function test_owner_is_added_as_admin_member(): void
    {
        $user = User::factory()->create();
        $community = $this->action->execute($user, ['name' => 'My Community']);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_ADMIN,
        ]);
    }

    public function test_creates_community_with_default_values(): void
    {
        $user = User::factory()->create();
        $community = $this->action->execute($user, ['name' => 'Minimal']);

        $this->assertFalse($community->is_private);
        $this->assertEquals(0, (float) $community->price);
        $this->assertEquals('PHP', $community->currency);
        $this->assertNull($community->description);
    }

    public function test_creates_paid_community(): void
    {
        $user = User::factory()->create();
        $community = $this->action->execute($user, [
            'name' => 'Paid Community',
            'price' => 499.00,
            'is_private' => true,
        ]);

        $this->assertEquals(499.00, (float) $community->price);
        $this->assertTrue($community->is_private);
    }

    public function test_creates_community_with_cover_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $cover = UploadedFile::fake()->image('cover.jpg', 1200, 400);
        $community = $this->action->execute($user, ['name' => 'With Cover'], null, $cover);

        $this->assertNotNull($community->cover_image);
        $this->assertStringContainsString('community-covers', $community->cover_image);
    }

    public function test_creates_community_with_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $community = $this->action->execute($user, ['name' => 'With Avatar'], $avatar);

        $this->assertNotNull($community->avatar);
        $this->assertStringContainsString('community-avatars', $community->avatar);
    }
}
