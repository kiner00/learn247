<?php

namespace App\Queries\Classroom;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonCompletion;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class GetCourseList
{
    public function execute(Community $community, ?int $userId, bool $isSuperAdmin = false): Collection
    {
        $isOwner = $isSuperAdmin || ($userId && $userId === $community->owner_id);

        // Any active community member (free or paid) counts as a member for free-course access
        $isMember = $userId && CommunityMember::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        // Paid membership = active subscription (used for inclusive courses)
        $isPaidMember = $userId && Subscription::where('community_id', $community->id)
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

        // member_once: user who has ever paid (any non-pending subscription)
        $wasEverMember = $userId && Subscription::where('community_id', $community->id)
            ->where('user_id', $userId)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])
            ->exists();

        $query = $community->courses()->with('modules.lessons')
            ->orderByRaw("CASE access_type WHEN 'free' THEN 0 WHEN 'inclusive' THEN 1 WHEN 'member_once' THEN 2 WHEN 'paid_once' THEN 3 WHEN 'paid_monthly' THEN 4 ELSE 5 END")
            ->orderBy('position');

        if (! $isOwner) {
            $query->where('is_published', true);
        }

        $courses = $query->get();

        // Batch-load all lesson completions in a single query instead of one per course
        $allLessonIds = $courses->flatMap(fn ($c) => $c->modules->flatMap(fn ($m) => $m->lessons->pluck('id')));
        $completedLessonIds = ($userId && $allLessonIds->isNotEmpty())
            ? LessonCompletion::where('user_id', $userId)
                ->whereIn('lesson_id', $allLessonIds)
                ->pluck('lesson_id')
                ->flip()
            : collect();

        return $courses->map(function ($course) use ($userId, $isOwner, $isMember, $isPaidMember, $paidEnrollmentIds, $wasEverMember, $completedLessonIds) {
            $hasAccess = $this->resolveAccess($course, $isOwner, $isMember, $isPaidMember, $paidEnrollmentIds, $wasEverMember);

            $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $total     = $lessonIds->count();
            $completed = ($hasAccess && $userId && $total > 0)
                ? $lessonIds->filter(fn ($id) => $completedLessonIds->has($id))->count()
                : 0;

            $result = [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
                'cover_image'    => $course->cover_image,
                'preview_video'       => $course->preview_video,
                'preview_video_sound' => (bool) $course->preview_video_sound,
                'position'    => $course->position,
                'access_type'              => $course->access_type,
                'price'                    => $course->price,
                'affiliate_commission_rate'=> $course->affiliate_commission_rate,
                'is_published' => $course->is_published,
                'total'       => $total,
                'completed'   => $completed,
                'progress'    => $total > 0 && $hasAccess ? round($completed / $total * 100) : 0,
                'has_access'  => $hasAccess,
            ];

            if ($isOwner) {
                $result['preview_play_count']    = $course->preview_play_count ?? 0;
                $result['preview_watch_seconds'] = $course->preview_watch_seconds ?? 0;
            }

            return $result;
        });
    }

    private function resolveAccess(Course $course, bool $isOwner, bool $isMember, bool $isPaidMember, $paidEnrollmentIds, bool $wasEverMember = false): bool
    {
        if ($isOwner) {
            return true;
        }

        if ($course->access_type === Course::ACCESS_FREE) {
            return true; // free courses are public — no membership required
        }

        if ($course->access_type === Course::ACCESS_INCLUSIVE) {
            return $isPaidMember;
        }

        if (in_array($course->access_type, [Course::ACCESS_PAID_ONCE, Course::ACCESS_PAID_MONTHLY])) {
            return $paidEnrollmentIds->has($course->id);
        }

        if ($course->access_type === Course::ACCESS_MEMBER_ONCE) {
            return $wasEverMember;
        }

        return false;
    }
}
