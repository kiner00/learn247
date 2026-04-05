<?php

namespace App\Events;

use App\Models\CommunityMember;
use App\Models\Tag;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberTagged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CommunityMember $member,
        public readonly Tag $tag,
    ) {}
}
