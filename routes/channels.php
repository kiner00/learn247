<?php

use App\Models\CommunityMember;
use Illuminate\Support\Facades\Broadcast;

// Community chat — only members can join
Broadcast::channel('community.{communityId}.chat', function ($user, $communityId) {
    $member = CommunityMember::where('community_id', $communityId)
        ->where('user_id', $user->id)
        ->first();

    if (! $member) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// Direct messages — only the recipient can listen
Broadcast::channel('dm.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
