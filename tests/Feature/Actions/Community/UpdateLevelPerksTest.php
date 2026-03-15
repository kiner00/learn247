<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\UpdateLevelPerks;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateLevelPerksTest extends TestCase
{
    use RefreshDatabase;

    private UpdateLevelPerks $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateLevelPerks();
    }

    public function test_creates_perks_for_levels(): void
    {
        $community = Community::factory()->create();

        $this->action->execute($community, [
            1 => 'Bronze perk',
            2 => 'Silver perk',
        ]);

        $this->assertDatabaseHas('community_level_perks', [
            'community_id' => $community->id,
            'level'        => 1,
            'description'  => 'Bronze perk',
        ]);
        $this->assertDatabaseHas('community_level_perks', [
            'community_id' => $community->id,
            'level'        => 2,
            'description'  => 'Silver perk',
        ]);
    }

    public function test_updates_existing_perk(): void
    {
        $community = Community::factory()->create();
        CommunityLevelPerk::create([
            'community_id' => $community->id,
            'level'        => 1,
            'description'  => 'Old perk',
        ]);

        $this->action->execute($community, [1 => 'Updated perk']);

        $this->assertDatabaseHas('community_level_perks', [
            'community_id' => $community->id,
            'level'        => 1,
            'description'  => 'Updated perk',
        ]);
        $this->assertDatabaseCount('community_level_perks', 1);
    }

    public function test_deletes_perk_when_description_is_blank(): void
    {
        $community = Community::factory()->create();
        CommunityLevelPerk::create([
            'community_id' => $community->id,
            'level'        => 1,
            'description'  => 'Will be removed',
        ]);

        $this->action->execute($community, [1 => '']);

        $this->assertDatabaseMissing('community_level_perks', [
            'community_id' => $community->id,
            'level'        => 1,
        ]);
    }

    public function test_deletes_perk_when_description_is_null(): void
    {
        $community = Community::factory()->create();
        CommunityLevelPerk::create([
            'community_id' => $community->id,
            'level'        => 3,
            'description'  => 'Will be removed',
        ]);

        $this->action->execute($community, [3 => null]);

        $this->assertDatabaseMissing('community_level_perks', [
            'community_id' => $community->id,
            'level'        => 3,
        ]);
    }
}
