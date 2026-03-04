<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunityMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id'      => User::factory(),
            'role'         => CommunityMember::ROLE_MEMBER,
            'joined_at'    => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => CommunityMember::ROLE_ADMIN]);
    }

    public function moderator(): static
    {
        return $this->state(['role' => CommunityMember::ROLE_MODERATOR]);
    }
}
