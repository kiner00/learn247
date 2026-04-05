<?php

namespace App\Events;

use App\Models\CommunityMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CommunityMember $member,
    ) {}
}
