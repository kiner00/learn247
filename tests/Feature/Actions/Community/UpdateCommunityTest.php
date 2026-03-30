<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\UpdateCommunity;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_community_basic_fields(): void
    {
        $community = Community::factory()->create();
        $action = app(UpdateCommunity::class);

        $result = $action->execute($community, [
            'name' => 'Updated Name',
            'description' => 'Updated desc',
        ]);

        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('Updated desc', $result->description);
    }

    public function test_update_with_avatar_stores_file(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();
        $action = app(UpdateCommunity::class);
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $result = $action->execute($community, ['name' => $community->name], $avatar);

        $this->assertNotNull($result->avatar);
    }

    public function test_update_with_cover_image_stores_file(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();
        $action = app(UpdateCommunity::class);
        $cover = UploadedFile::fake()->image('cover.jpg');

        $result = $action->execute($community, ['name' => $community->name], null, $cover);

        $this->assertNotNull($result->cover_image);
    }

    public function test_pricing_gate_requires_5_modules(): void
    {
        $owner = User::factory()->create(['bio' => 'Bio', 'avatar' => 'av.jpg']);
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'cover_image' => '/storage/cover.jpg',
            'description' => 'A description',
        ]);
        $action = app(UpdateCommunity::class);

        $this->expectException(ValidationException::class);
        $action->execute($community, ['name' => 'Test', 'price' => 499, 'description' => 'Desc']);
    }

    public function test_pricing_gate_requires_banner(): void
    {
        $owner = User::factory()->create(['bio' => 'Bio', 'avatar' => 'av.jpg']);
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'cover_image' => null,
            'description' => 'A description',
        ]);
        // Create 5 modules
        $course = Course::create(['community_id' => $community->id, 'title' => 'C']);
        for ($i = 1; $i <= 5; $i++) {
            CourseModule::create(['course_id' => $course->id, 'title' => "M{$i}", 'position' => $i]);
        }

        $action = app(UpdateCommunity::class);
        $this->expectException(ValidationException::class);
        $action->execute($community, ['name' => 'Test', 'price' => 499, 'description' => 'Desc']);
    }

    public function test_pricing_gate_requires_description(): void
    {
        $owner = User::factory()->create(['bio' => 'Bio', 'avatar' => 'av.jpg']);
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'cover_image' => '/storage/cover.jpg',
            'description' => null,
        ]);
        $course = Course::create(['community_id' => $community->id, 'title' => 'C']);
        for ($i = 1; $i <= 5; $i++) {
            CourseModule::create(['course_id' => $course->id, 'title' => "M{$i}", 'position' => $i]);
        }

        $action = app(UpdateCommunity::class);
        $this->expectException(ValidationException::class);
        $action->execute($community, ['name' => 'Test', 'price' => 499, 'description' => '']);
    }

    public function test_pricing_gate_requires_complete_profile(): void
    {
        $owner = User::factory()->create(['bio' => null, 'avatar' => null]);
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'cover_image' => '/storage/cover.jpg',
            'description' => 'Desc',
        ]);
        $course = Course::create(['community_id' => $community->id, 'title' => 'C']);
        for ($i = 1; $i <= 5; $i++) {
            CourseModule::create(['course_id' => $course->id, 'title' => "M{$i}", 'position' => $i]);
        }

        $action = app(UpdateCommunity::class);
        $this->expectException(ValidationException::class);
        $action->execute($community, ['name' => 'Test', 'price' => 499, 'description' => 'Desc']);
    }

    public function test_free_community_update_skips_pricing_gate(): void
    {
        $community = Community::factory()->create();
        $action = app(UpdateCommunity::class);

        $result = $action->execute($community, ['name' => 'Free Update', 'price' => 0]);

        $this->assertEquals('Free Update', $result->name);
    }

    public function test_already_paid_community_can_update_name_without_pricing_gate(): void
    {
        $community = Community::factory()->create(['price' => 499]);
        $action = app(UpdateCommunity::class);

        $result = $action->execute($community, ['name' => 'Renamed Paid Community', 'price' => 499]);

        $this->assertEquals('Renamed Paid Community', $result->name);
    }

    public function test_update_with_new_avatar_deletes_old_storage_file(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('community-avatars/old-avatar.jpg', 'dummy');

        $community = Community::factory()->create(['avatar' => '/storage/community-avatars/old-avatar.jpg']);
        $action = app(UpdateCommunity::class);
        $newAvatar = UploadedFile::fake()->image('new-avatar.jpg');

        $result = $action->execute($community, ['name' => $community->name], $newAvatar);

        $this->assertNotNull($result->avatar);
        Storage::disk($disk)->assertMissing('community-avatars/old-avatar.jpg');
    }
}
