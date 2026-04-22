<?php

namespace Tests\Feature\Api;

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

    public function test_index_returns_curzzos_for_owner(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Curzzo::factory()->count(3)->create(['community_id' => $community->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/curzzos")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'community_id', 'name', 'access_type', 'is_active', 'is_free', 'position'],
                ],
            ]);
    }

    public function test_index_forbidden_for_non_owner(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/curzzos")
            ->assertForbidden();
    }

    public function test_index_requires_auth(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/api/v1/communities/{$community->slug}/curzzos")
            ->assertUnauthorized();
    }

    // ─── store ───────────────────────────────────────────────────────────────

    public function test_store_creates_curzzo(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos", [
                'name' => 'API Bot',
                'instructions' => 'Be helpful.',
                'access_type' => 'free',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'API Bot')
            ->assertJsonPath('data.community_id', $community->id);

        $this->assertDatabaseHas('curzzos', [
            'community_id' => $community->id,
            'name' => 'API Bot',
        ]);
    }

    public function test_store_returns_validation_errors(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'instructions', 'access_type']);
    }

    public function test_store_rejects_non_pro_users(): void
    {
        $owner = User::factory()->create(); // free
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos", [
                'name' => 'Bot',
                'instructions' => 'Help.',
                'access_type' => 'free',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_store_forbidden_for_non_owner(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos", [
                'name' => 'Sneaky',
                'instructions' => 'Help.',
                'access_type' => 'free',
            ])
            ->assertForbidden();
    }

    public function test_store_uploads_avatar_via_storage_contract(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('upload')->once()
            ->withArgs(fn ($file, $folder) => $folder === 'curzzo-avatars')
            ->andReturn('https://cdn.test/api-avatar.png');
        $this->instance(FileStorage::class, $storage);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos", [
                'name' => 'With Avatar',
                'instructions' => 'Help.',
                'access_type' => 'free',
                'avatar' => UploadedFile::fake()->image('a.png'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.avatar', 'https://cdn.test/api-avatar.png');
    }

    // ─── update ──────────────────────────────────────────────────────────────

    public function test_update_modifies_fields(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'name' => 'Old',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}", [
                'name' => 'New',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New');

        $this->assertSame('New', $curzzo->fresh()->name);
    }

    public function test_update_returns_404_for_curzzo_from_other_community(): void
    {
        $owner = $this->proOwner();
        $a = Community::factory()->create(['owner_id' => $owner->id]);
        $b = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->create(['community_id' => $b->id]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/communities/{$a->slug}/curzzos/{$curzzoB->id}", ['name' => 'X'])
            ->assertNotFound();
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
        $storage->shouldReceive('delete')->twice();
        $this->instance(FileStorage::class, $storage);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('curzzos', ['id' => $curzzo->id]);
    }

    // ─── reorder ─────────────────────────────────────────────────────────────

    public function test_reorder_updates_positions(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $a = Curzzo::factory()->create(['community_id' => $community->id, 'position' => 0]);
        $b = Curzzo::factory()->create(['community_id' => $community->id, 'position' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/reorder", [
                'ids' => [$b->id, $a->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    // ─── toggleActive ────────────────────────────────────────────────────────

    public function test_toggle_active_flips_state_and_returns_resource(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id, 'is_active' => true]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($curzzo->fresh()->is_active);
    }

    // ─── uploadPreviewVideo ──────────────────────────────────────────────────

    public function test_upload_preview_video_rejects_non_pro_users(): void
    {
        $owner = User::factory()->create(); // free
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/preview-videos", [
                'filename' => 'v.mp4',
                'content_type' => 'video/mp4',
                'size' => 1024,
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'Preview video uploads require a Pro plan.');
    }

    public function test_upload_preview_video_rejects_oversize(): void
    {
        $owner = $this->proOwner();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/preview-videos", [
                'filename' => 'v.mp4',
                'content_type' => 'video/mp4',
                'size' => (5120 * 1024 * 1024) + 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'File too large. Maximum size is 5120MB.');
    }
}
