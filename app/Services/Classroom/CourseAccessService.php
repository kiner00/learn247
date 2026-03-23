<?php

namespace App\Services\Classroom;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;

class CourseAccessService
{
    public function hasAccess(?User $user, Community $community, Course $course): bool
    {
        if ($course->access_type === Course::ACCESS_FREE) {
            return true; // free courses are public — no auth required
        }

        if (! $user) {
            return false;
        }

        if ($user->id === $community->owner_id || $user->isSuperAdmin()) {
            return true;
        }

        if ($course->access_type === Course::ACCESS_INCLUSIVE) {
            return Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        }

        if (in_array($course->access_type, [Course::ACCESS_PAID_ONCE, Course::ACCESS_PAID_MONTHLY])) {
            return CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', CourseEnrollment::STATUS_PAID)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        }

        return false;
    }
}
