<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Tag;
use App\Services\Community\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagService;
    }

    public function test_auto_tag_applied_when_event_matches(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);

        $tag = Tag::create([
            'community_id' => $community->id,
            'name' => 'New Member',
            'slug' => 'new-member',
            'color' => '#000',
            'type' => Tag::TYPE_AUTOMATIC,
            'auto_rule' => ['event' => 'member.joined'],
        ]);

        $this->service->applyAutoTags($member, 'member.joined');

        $this->assertTrue($member->tags->contains($tag));
    }

    public function test_auto_tag_not_applied_when_event_does_not_match(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);

        Tag::create([
            'community_id' => $community->id,
            'name' => 'Paid',
            'slug' => 'paid',
            'color' => '#000',
            'type' => Tag::TYPE_AUTOMATIC,
            'auto_rule' => ['event' => 'payment.completed'],
        ]);

        $this->service->applyAutoTags($member, 'member.joined');

        $this->assertCount(0, $member->tags);
    }

    public function test_auto_tag_respects_membership_type_filter(): void
    {
        $community = Community::factory()->create();
        $freeMember = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);
        $paidMember = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
        ]);

        $tag = Tag::create([
            'community_id' => $community->id,
            'name' => 'Paid Member',
            'slug' => 'paid-member',
            'color' => '#000',
            'type' => Tag::TYPE_AUTOMATIC,
            'auto_rule' => [
                'event' => 'member.joined',
                'filter' => ['membership_type' => CommunityMember::MEMBERSHIP_PAID],
            ],
        ]);

        $this->service->applyAutoTags($freeMember, 'member.joined');
        $this->service->applyAutoTags($paidMember, 'member.joined');

        $this->assertFalse($freeMember->tags->contains($tag));
        $this->assertTrue($paidMember->fresh()->tags->contains($tag));
    }

    public function test_auto_tag_respects_course_id_filter(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);

        $tag = Tag::create([
            'community_id' => $community->id,
            'name' => 'Course 5 Student',
            'slug' => 'course-5-student',
            'color' => '#000',
            'type' => Tag::TYPE_AUTOMATIC,
            'auto_rule' => [
                'event' => 'course.enrolled',
                'filter' => ['course_id' => 5],
            ],
        ]);

        // Different course — should NOT tag
        $this->service->applyAutoTags($member, 'course.enrolled', ['course_id' => 99]);
        $this->assertFalse($member->fresh()->tags->contains($tag));

        // Matching course — SHOULD tag
        $this->service->applyAutoTags($member, 'course.enrolled', ['course_id' => 5]);
        $this->assertTrue($member->fresh()->tags->contains($tag));
    }

    public function test_tag_with_null_auto_rule_is_skipped(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);

        Tag::create([
            'community_id' => $community->id,
            'name' => 'Broken',
            'slug' => 'broken',
            'color' => '#000',
            'type' => Tag::TYPE_AUTOMATIC,
            'auto_rule' => null,
        ]);

        $this->service->applyAutoTags($member, 'member.joined');

        $this->assertCount(0, $member->tags);
    }
}
