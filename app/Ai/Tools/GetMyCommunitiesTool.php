<?php

namespace App\Ai\Tools;

use App\Models\CommunityMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetMyCommunitiesTool implements Tool
{
    public function __construct(private int $userId) {}

    public function description(): string
    {
        return 'Get all communities the current user is a member of, including their role, membership type, points, level, and membership expiry.';
    }

    public function handle(Request $request): string
    {
        $memberships = CommunityMember::where('user_id', $this->userId)
            ->with('community:id,name,slug,description,category,price,billing_type')
            ->get();

        if ($memberships->isEmpty()) {
            return 'The user is not a member of any community.';
        }

        $result = [];
        foreach ($memberships as $m) {
            $result[] = [
                'community'       => $m->community->name,
                'slug'            => $m->community->slug,
                'category'        => $m->community->category,
                'role'            => $m->role,
                'membership_type' => $m->membership_type,
                'points'          => $m->points,
                'level'           => CommunityMember::computeLevel($m->points),
                'expires_at'      => $m->expires_at?->toDateString(),
                'joined_at'       => $m->joined_at?->toDateString(),
            ];
        }

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
