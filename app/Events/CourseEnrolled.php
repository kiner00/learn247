<?php

namespace App\Events;

use App\Models\CommunityMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseEnrolled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CommunityMember $member,
        public readonly int $courseId,
    ) {}
}
