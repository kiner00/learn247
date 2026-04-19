<?php

namespace Tests\Feature\Queries\Admin;

use App\Models\Community;
use App\Models\User;
use App\Queries\Admin\CreatorAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorAnalyticsOrphanTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_without_owner_is_skipped(): void
    {
        // Create a community with a valid owner, then add a global scope
        // that forces owner to be null for our community, simulating orphan.
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        // Add a global scope on User that excludes our owner from eager loads,
        // making $community->owner return null.
        User::addGlobalScope('hide_orphan_owner', function ($query) use ($owner) {
            $query->where('users.id', '!=', $owner->id);
        });

        $result = (new CreatorAnalytics)->execute();

        $this->assertEmpty($result['creators']);

        // Clear booted models to remove the global scope for subsequent tests
        User::clearBootedModels();
    }
}
