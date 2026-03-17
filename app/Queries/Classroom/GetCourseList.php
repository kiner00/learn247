<?php

namespace App\Queries\Classroom;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonCompletion;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class GetCourseList
{
    public function execute(Community $community, ?int $userId): Collection
    {
        $isOwner = $userId && $userId === $community->owner_id;

        $isMember = $userId && Subscription::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        $paidEnrollmentIds = $userId
            ? CourseEnrollment::where('user_id', $userId)
                ->where('status', CourseEnrollment::STATUS_PAID)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('course_id')
                ->flip()
            : collect();

        return $community->courses()->with('modules.lessons')
            ->orderByRaw("CASE access_type WHEN 'free' THEN 0 WHEN 'inclusive' THEN 1 WHEN 'paid_once' THEN 2 WHEN 'paid_monthly' THEN 3 ELSE 4 END")
            ->orderBy('position')
            ->get()->map(function ($course) use ($userId, $isOwner, $isMember, $paidEnrollmentIds) {
            $hasAccess = $this->resolveAccess($course, $isOwner, $isMember, $paidEnrollmentIds);

            $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $total     = $lessonIds->count();
            $completed = ($hasAccess && $userId && $total > 0)
                ? LessonCompletion::where('user_id', $userId)->whereIn('lesson_id', $lessonIds)->count()
                : 0;

            return [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
                'cover_image' => $course->cover_image,
                'position'    => $course->position,
                'access_type'              => $course->access_type,
                'price'                    => $course->price,
                'affiliate_commission_rate'=> $course->affiliate_commission_rate,
                'total'       => $total,
                'completed'   => $completed,
                'progress'    => $total > 0 && $hasAccess ? round($completed / $total * 100) : 0,
                'has_access'  => $hasAccess,
            ];
        });
    }

    private function resolveAccess(Course $course, bool $isOwner, bool $isMember, $paidEnrollmentIds): bool
    {
        if ($course->access_type === Course::ACCESS_FREE) {
            return true;
        }

        if ($isOwner) {
            return true;
        }

        if ($course->access_type === Course::ACCESS_INCLUSIVE) {
            return $isMember;
        }

        if (in_array($course->access_type, [Course::ACCESS_PAID_ONCE, Course::ACCESS_PAID_MONTHLY])) {
            return $paidEnrollmentIds->has($course->id);
        }

        return false;
    }
}
