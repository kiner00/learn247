<?php

namespace App\Services\Community;

use App\Models\Community;
use App\Models\Post;

/**
 * Computes the owner onboarding checklist for a community.
 * Extracted from CommunityController::show() so the API can return
 * the same checklist data without duplicating the queries.
 */
class CommunityChecklistService
{
    public function compute(Community $community): array
    {
        $hasPost = Post::where('community_id', $community->id)->exists();
        $courseCount = $community->courses()->count();

        return [
            ['key' => 'cover',       'label' => 'Upload a banner image',       'done' => (bool) $community->cover_image],
            ['key' => 'description', 'label' => 'Add a community description', 'done' => (bool) trim($community->description ?? '')],
            ['key' => 'post',        'label' => 'Write your first post',       'done' => $hasPost],
            ['key' => 'course',      'label' => 'Create a course',             'done' => $courseCount > 0],
            ['key' => 'affiliate',   'label' => 'Set affiliate commission',    'done' => (bool) $community->affiliate_commission_rate],
        ];
    }
}
