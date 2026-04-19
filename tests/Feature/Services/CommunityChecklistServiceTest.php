<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\Course;
use App\Models\Post;
use App\Models\User;
use App\Services\Community\CommunityChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommunityChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommunityChecklistService;
    }

    public function test_returns_five_checklist_items(): void
    {
        $community = Community::factory()->create();

        $checklist = $this->service->compute($community);

        $this->assertCount(5, $checklist);
        $keys = array_column($checklist, 'key');
        $this->assertContains('cover', $keys);
        $this->assertContains('description', $keys);
        $this->assertContains('post', $keys);
        $this->assertContains('course', $keys);
        $this->assertContains('affiliate', $keys);
    }

    // ── cover ─────────────────────────────────────────────────────────────────

    public function test_cover_done_when_cover_image_set(): void
    {
        $community = Community::factory()->create(['cover_image' => '/storage/cover.jpg']);

        $item = $this->findItem($this->service->compute($community), 'cover');

        $this->assertTrue($item['done']);
    }

    public function test_cover_not_done_when_no_cover_image(): void
    {
        $community = Community::factory()->create(['cover_image' => null]);

        $item = $this->findItem($this->service->compute($community), 'cover');

        $this->assertFalse($item['done']);
    }

    // ── description ───────────────────────────────────────────────────────────

    public function test_description_done_when_description_set(): void
    {
        $community = Community::factory()->create(['description' => 'A great community']);

        $item = $this->findItem($this->service->compute($community), 'description');

        $this->assertTrue($item['done']);
    }

    public function test_description_not_done_when_null(): void
    {
        $community = Community::factory()->create(['description' => null]);

        $item = $this->findItem($this->service->compute($community), 'description');

        $this->assertFalse($item['done']);
    }

    public function test_description_not_done_when_whitespace_only(): void
    {
        $community = Community::factory()->create(['description' => '   ']);

        $item = $this->findItem($this->service->compute($community), 'description');

        $this->assertFalse($item['done']);
    }

    public function test_description_not_done_when_empty_string(): void
    {
        $community = Community::factory()->create(['description' => '']);

        $item = $this->findItem($this->service->compute($community), 'description');

        $this->assertFalse($item['done']);
    }

    // ── post ──────────────────────────────────────────────────────────────────

    public function test_post_done_when_post_exists(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Post::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $item = $this->findItem($this->service->compute($community), 'post');

        $this->assertTrue($item['done']);
    }

    public function test_post_not_done_when_no_posts(): void
    {
        $community = Community::factory()->create();

        $item = $this->findItem($this->service->compute($community), 'post');

        $this->assertFalse($item['done']);
    }

    public function test_post_only_checks_this_community(): void
    {
        $owner = User::factory()->create();
        $other = Community::factory()->create(['owner_id' => $owner->id]);
        $community = Community::factory()->create();
        Post::factory()->create(['community_id' => $other->id, 'user_id' => $owner->id]);

        $item = $this->findItem($this->service->compute($community), 'post');

        $this->assertFalse($item['done']);
    }

    // ── course ────────────────────────────────────────────────────────────────

    public function test_course_done_when_course_exists(): void
    {
        $community = Community::factory()->create();
        Course::factory()->create(['community_id' => $community->id]);

        $item = $this->findItem($this->service->compute($community), 'course');

        $this->assertTrue($item['done']);
    }

    public function test_course_not_done_when_no_courses(): void
    {
        $community = Community::factory()->create();

        $item = $this->findItem($this->service->compute($community), 'course');

        $this->assertFalse($item['done']);
    }

    // ── affiliate ─────────────────────────────────────────────────────────────

    public function test_affiliate_done_when_commission_rate_set(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);

        $item = $this->findItem($this->service->compute($community), 'affiliate');

        $this->assertTrue($item['done']);
    }

    public function test_affiliate_not_done_when_commission_rate_null(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => null]);

        $item = $this->findItem($this->service->compute($community), 'affiliate');

        $this->assertFalse($item['done']);
    }

    public function test_affiliate_not_done_when_commission_rate_is_zero(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 0]);

        $item = $this->findItem($this->service->compute($community), 'affiliate');

        $this->assertFalse($item['done']); // 0 is falsy
    }

    // ── All done ──────────────────────────────────────────────────────────────

    public function test_all_items_done_for_fully_configured_community(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'cover_image' => '/storage/cover.jpg',
            'description' => 'A great community',
            'affiliate_commission_rate' => 10,
        ]);
        Post::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        Course::factory()->create(['community_id' => $community->id]);

        $checklist = $this->service->compute($community);

        foreach ($checklist as $item) {
            $this->assertTrue($item['done'], "Expected item '{$item['key']}' to be done");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findItem(array $checklist, string $key): array
    {
        foreach ($checklist as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }
        $this->fail("Checklist item '{$key}' not found.");
    }
}
