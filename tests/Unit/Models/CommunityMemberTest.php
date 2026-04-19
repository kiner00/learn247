<?php

namespace Tests\Unit\Models;

use App\Models\CommunityMember;
use PHPUnit\Framework\TestCase;

class CommunityMemberTest extends TestCase
{
    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_ADMIN;

        $this->assertTrue($member->isAdmin());
    }

    public function test_is_admin_returns_false_for_non_admin_role(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_MEMBER;

        $this->assertFalse($member->isAdmin());
    }

    public function test_is_moderator_returns_true_for_moderator_role(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_MODERATOR;

        $this->assertTrue($member->isModerator());
    }

    public function test_is_moderator_returns_false_for_non_moderator_role(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_ADMIN;

        $this->assertFalse($member->isModerator());
    }

    public function test_can_moderate_returns_true_for_admin(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_ADMIN;

        $this->assertTrue($member->canModerate());
    }

    public function test_can_moderate_returns_true_for_moderator(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_MODERATOR;

        $this->assertTrue($member->canModerate());
    }

    public function test_can_moderate_returns_false_for_regular_member(): void
    {
        $member = new CommunityMember;
        $member->role = CommunityMember::ROLE_MEMBER;

        $this->assertFalse($member->canModerate());
    }

    public function test_roles_constant_contains_all_three_roles(): void
    {
        $this->assertContains(CommunityMember::ROLE_ADMIN, CommunityMember::ROLES);
        $this->assertContains(CommunityMember::ROLE_MODERATOR, CommunityMember::ROLES);
        $this->assertContains(CommunityMember::ROLE_MEMBER, CommunityMember::ROLES);
        $this->assertCount(3, CommunityMember::ROLES);
    }
}
