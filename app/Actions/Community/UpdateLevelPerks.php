<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\CommunityLevelPerk;

class UpdateLevelPerks
{
    public function execute(Community $community, array $perks): void
    {
        foreach ($perks as $level => $description) {
            if (blank($description)) {
                CommunityLevelPerk::where('community_id', $community->id)->where('level', $level)->delete();
            } else {
                CommunityLevelPerk::updateOrCreate(
                    ['community_id' => $community->id, 'level' => $level],
                    ['description' => $description],
                );
            }
        }
    }
}
