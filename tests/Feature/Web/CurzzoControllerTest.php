<?php

namespace Tests\Feature\Web;

use App\Contracts\FileStorage;
use App\Models\Community;
use App\Models\Curzzo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class CurzzoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function proOwner(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    // ─── index ───────────────────────────────────────────────────────────────

    public function test_index_renders_settings_page_for_owner(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Curzzo::factory()->count(2)->create(['community_id' => $community->id]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/curzzos")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Communities/Settings/Curzzos')
                ->has('curzzos', 2)
                ->where('isPro', true)
                ->has('modelTiers')
            );
    }

    public function test_index_blocks_non_members_via_membership_middleware(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get("/communities/{$community->slug}/settings/curzzos")
            ->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_index_requires_auth(): void
    {
        $community = Community::factory()->create();

        $this->get("/communities/{$community->slug}/settings/curzzos")
            ->assertRedirect('/login');
    }

    // ─── store ───────────────────────────────────────────────────────────────

    public function test_store_creates_curzzo_with_minimum_fields(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/curzzos", [
                'name' => 'Helper Bot',
                'instructions' => 'Be helpful and concise.',
                'access_type' => 'free',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('curzzos', [
            'community_id' => $community->id,
            'name' => 'Helper Bot',
            'instructions' => 'Be helpful and concise.',
            'access_type' => 'free',
            'position' => 0,
        ]);
    }

    public function test_store_assigns_incremental_position(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Curzzo::factory()->count(3)->create(['community_id' => $community->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/curzzos", [
                'name' => 'Bot 4',
                'instructions' => 'Help out.',
                'access_type' => 'free',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('curzzos', [
            'name' => 'Bot 4',
            'position' => 3,
        ]);
    }

    public function test_store_uploads_avatar_and_cover_via_storage_contract(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('upload')->once()
            ->withArgs(fn ($file, $folder) => $folder === 'curzzo-avatars')
            ->andReturn('https://cdn.test/avatar.png');
        $storage->shouldReceive('upload')->once()
            ->withArgs(fn ($file, $folder) => $folder === 'curzzo-covers')
            ->andReturn('https://cdn.test/cover.png');
        $this->instance(FileStorage::class, $storage);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/curzzos", [
                'name' => 'With Images',
                'instructions' => 'Helpful.',
                'access_type' => 'free',
                'avatar' => UploadedFile::fake()->image('avatar.png'),
                'cover_image' => UploadedFile::fake()->image('cover.png'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('curzzos', [
            'name' => 'With Images',
            'avatar' => 'https://cdn.test/avatar.png',
            'cover_image' => 'https://cdn.test/cover.png',
        ]);
    }

    public function test_store_requires_pro_plan(): void
    {
        $owner = User::factory()->create(); // free plan
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->from("/communities/{$community->slug}/settings/curzzos")
            ->post("/communities/{$community->slug}/curzzos", [
                'name' => 'Bot',
                'instructions' => 'Help.',
                'access_type' => 'free',
            ])
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseMissing('curzzos', ['community_id' => $community->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->from("/communities/{$community->slug}/settings/curzzos")
            ->post("/communities/{$community->slug}/curzzos", [])
            ->assertSessionHasErrors(['name', 'instructions', 'access_type']);
    }

    public function test_store_blocks_non_members_via_membership_middleware(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/curzzos", [
                'name' => 'Sneaky Bot',
                'instructions' => 'Help.',
                'access_type' => 'free',
            ])
            ->assertRedirect("/communities/{$community->slug}/about");

        $this->assertDatabaseMissing('curzzos', ['name' => 'Sneaky Bot']);
    }

    // ─── update ──────────────────────────────────────────────────────────────

    public function test_update_modifies_fields(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'name' => 'Old Name',
        ]);

        $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/curzzos/{$curzzo->id}", [
                'name' => 'New Name',
                'instructions' => 'Updated instructions.',
                'access_type' => 'free',
            ])
            ->assertRedirect();

        $this->assertSame('New Name', $curzzo->fresh()->name);
        $this->assertSame('Updated instructions.', $curzzo->fresh()->instructions);
    }

    public function test_update_returns_404_for_curzzo_from_other_community(): void
    {
        $owner = $this->proOwner();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->create(['community_id' => $communityB->id]);

        $this->actingAs($owner)
            ->patch("/communities/{$communityA->slug}/curzzos/{$curzzoB->id}", [
                'name' => 'Hijack',
            ])
            ->assertNotFound();
    }

    public function test_update_replaces_avatar_when_new_file_uploaded(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'avatar' => 'https://cdn.test/old-avatar.png',
        ]);

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('delete')->once()->with('https://cdn.test/old-avatar.png');
        $storage->shouldReceive('upload')->once()
            ->withArgs(fn ($file, $folder) => $folder === 'curzzo-avatars')
            ->andReturn('https://cdn.test/new-avatar.png');
        $this->instance(FileStorage::class, $storage);

        $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/curzzos/{$curzzo->id}", [
                'avatar' => UploadedFile::fake()->image('new.png'),
            ])
            ->assertRedirect();

        $this->assertSame('https://cdn.test/new-avatar.png', $curzzo->fresh()->avatar);
    }

    public function test_update_removes_avatar_when_remove_flag_set(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'avatar' => 'https://cdn.test/old.png',
        ]);

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('delete')->once()->with('https://cdn.test/old.png');
        $this->instance(FileStorage::class, $storage);

        $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/curzzos/{$curzzo->id}", [
                'remove_avatar' => true,
            ])
            ->assertRedirect();

        $this->assertNull($curzzo->fresh()->avatar);
    }

    // ─── destroy ─────────────────────────────────────────────────────────────

    public function test_destroy_deletes_curzzo_and_files(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'avatar' => 'https://cdn.test/a.png',
            'cover_image' => 'https://cdn.test/c.png',
        ]);

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('delete')->once()->with('https://cdn.test/a.png');
        $storage->shouldReceive('delete')->once()->with('https://cdn.test/c.png');
        $this->instance(FileStorage::class, $storage);

        $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/curzzos/{$curzzo->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('curzzos', ['id' => $curzzo->id]);
    }

    public function test_destroy_returns_404_for_curzzo_from_other_community(): void
    {
        $owner = $this->proOwner();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->create(['community_id' => $communityB->id]);

        $this->actingAs($owner)
            ->delete("/communities/{$communityA->slug}/curzzos/{$curzzoB->id}")
            ->assertNotFound();
    }

    // ─── reorder ─────────────────────────────────────────────────────────────

    public function test_reorder_updates_positions(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $a = Curzzo::factory()->create(['community_id' => $community->id, 'position' => 0]);
        $b = Curzzo::factory()->create(['community_id' => $community->id, 'position' => 1]);
        $c = Curzzo::factory()->create(['community_id' => $community->id, 'position' => 2]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/curzzos/reorder", [
                'ids' => [$c->id, $a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_reorder_validates_ids_array(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->from("/communities/{$community->slug}/settings/curzzos")
            ->post("/communities/{$community->slug}/curzzos/reorder", [])
            ->assertSessionHasErrors('ids');
    }

    // ─── toggleActive ────────────────────────────────────────────────────────

    public function test_toggle_active_flips_is_active(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/curzzos/{$curzzo->id}/toggle-active")
            ->assertRedirect();

        $this->assertFalse($curzzo->fresh()->is_active);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/curzzos/{$curzzo->id}/toggle-active");

        $this->assertTrue($curzzo->fresh()->is_active);
    }

    public function test_toggle_active_returns_404_for_curzzo_from_other_community(): void
    {
        $owner = $this->proOwner();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->create(['community_id' => $communityB->id]);

        $this->actingAs($owner)
            ->post("/communities/{$communityA->slug}/curzzos/{$curzzoB->id}/toggle-active")
            ->assertNotFound();
    }

    // ─── uploadPreviewVideo ──────────────────────────────────────────────────

    public function test_upload_preview_video_rejects_non_pro_users(): void
    {
        $owner = User::factory()->create(); // free
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/curzzos/preview-videos", [
                'filename' => 'video.mp4',
                'content_type' => 'video/mp4',
                'size' => 1024,
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'Preview video uploads require a Pro plan.');
    }

    public function test_upload_preview_video_validates_size_against_plan_max(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $tooBig = (5120 * 1024 * 1024) + 1; // 1 byte over the Pro 5GB cap

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/curzzos/preview-videos", [
                'filename' => 'video.mp4',
                'content_type' => 'video/mp4',
                'size' => $tooBig,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'File too large. Maximum size is 5120MB.');
    }
}
