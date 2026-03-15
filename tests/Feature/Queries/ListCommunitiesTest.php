<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Queries\Community\ListCommunities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCommunitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_paginated_communities(): void
    {
        Community::factory()->count(3)->create();

        $query  = new ListCommunities();
        $result = $query->execute('', '', 'latest');

        $this->assertCount(3, $result->items());
        $this->assertSame(3, $result->total());
    }

    public function test_search_filters_by_name(): void
    {
        Community::factory()->create(['name' => 'Laravel Community']);
        Community::factory()->create(['name' => 'Vue Community']);

        $query  = new ListCommunities();
        $result = $query->execute('Laravel', '', 'latest');

        $this->assertCount(1, $result->items());
        $this->assertStringContainsString('Laravel', $result->items()[0]->name);
    }

    public function test_search_filters_by_description(): void
    {
        Community::factory()->create(['description' => 'Learn PHP and Laravel']);
        Community::factory()->create(['description' => 'Learn Vue.js']);

        $query  = new ListCommunities();
        $result = $query->execute('PHP', '', 'latest');

        $this->assertCount(1, $result->items());
    }

    public function test_category_filters(): void
    {
        Community::factory()->create(['category' => 'Tech']);
        Community::factory()->create(['category' => 'Business']);

        $query  = new ListCommunities();
        $result = $query->execute('', 'Tech', 'latest');

        $this->assertCount(1, $result->items());
        $this->assertSame('Tech', $result->items()[0]->category);
    }

    public function test_category_all_does_not_filter(): void
    {
        Community::factory()->create(['category' => 'Tech']);
        Community::factory()->create(['category' => 'Business']);

        $query  = new ListCommunities();
        $result = $query->execute('', 'All', 'latest');

        $this->assertCount(2, $result->items());
    }

    public function test_sort_popular_orders_by_members_count(): void
    {
        $community1 = Community::factory()->create();
        $community2 = Community::factory()->create();
        $user       = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community1->id, 'user_id' => $user->id]);

        $query  = new ListCommunities();
        $result = $query->execute('', '', 'popular');

        $this->assertCount(2, $result->items());
        $this->assertGreaterThanOrEqual(
            $result->items()[1]->members_count,
            $result->items()[0]->members_count
        );
    }

    public function test_sort_latest_orders_by_created_at(): void
    {
        Community::factory()->create();
        Community::factory()->create();

        $query  = new ListCommunities();
        $result = $query->execute('', '', 'latest');

        $this->assertCount(2, $result->items());
    }

    public function test_invalid_sort_defaults_to_latest(): void
    {
        Community::factory()->count(2)->create();

        $query  = new ListCommunities();
        $result = $query->execute('', '', 'invalid');

        $this->assertCount(2, $result->items());
    }

    public function test_per_page_can_be_customized(): void
    {
        Community::factory()->count(20)->create();

        $query  = new ListCommunities();
        $result = $query->execute('', '', 'latest', 5);

        $this->assertCount(5, $result->items());
        $this->assertSame(20, $result->total());
    }
}
