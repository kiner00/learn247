<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StartTrialMembership
{
    public function __construct(private readonly JoinCommunity $join) {}

    /** @throws ValidationException */
    public function execute(User $user, Community $community): CommunityMember
    {
        if (! $community->hasTrial()) {
            throw ValidationException::withMessages([
                'community' => 'This community does not offer a free trial.',
            ]);
        }

        $expiresAt = $community->trialExpiresAtFor(now());

        if ($expiresAt === null) {
            throw ValidationException::withMessages([
                'community' => 'Trial is no longer available.',
            ]);
        }

        return $this->join->executeAsTrial($user, $community, $expiresAt);
    }
}
