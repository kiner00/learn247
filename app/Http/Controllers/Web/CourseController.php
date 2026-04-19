<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\ManageCourse;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Queries\Classroom\GetCourseDetail;
use App\Queries\Classroom\GetCourseList;
use App\Services\Classroom\CourseAccessService;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Community $community, GetCourseList $query): Response
    {
        try {
            $userId        = auth()->id();
            $isSuperAdmin  = auth()->user()?->isSuperAdmin() ?? false;
            $community->loadCount('members');
            $courses    = $query->execute($community, $userId, $isSuperAdmin);
            $affiliate  = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;
            $membership = $userId ? CommunityMember::where('community_id', $community->id)->where('user_id', $userId)->first(['id', 'membership_type']) : null;
            $canManage  = auth()->user()?->can('manage', $community) ?? false;

            $ownerPlan = $community->owner?->creatorPlan() ?? 'free';

            return Inertia::render('Communities/Classroom/Index', compact('community', 'courses', 'affiliate', 'membership', 'canManage', 'ownerPlan'));
        } catch (\Throwable $e) {
            Log::error('CourseController@index failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function store(Request $request, Community $community, ManageCourse $action, PlanLimitService $planLimit): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            if (! $planLimit->canCreateCourse($community->owner, $community)) {
                return back()->withErrors([
                    'plan' => 'Free creators can only have 3 courses per community. Upgrade to Basic or Pro for more courses.',
                ]);
            }

            $data = $request->validate([
                'title'         => ['required', 'string', 'max:255'],
                'description'   => ['nullable', 'string', 'max:2000'],
                'cover_image'   => ['nullable', 'image', 'max:10240'],
                'preview_video'       => ['nullable', 'string', 'max:1000'],
                'preview_video_sound' => ['nullable', 'boolean'],
                'access_type'         => ['required', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
                'price'               => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
            ]);

            $action->store($community, $data, $request->file('cover_image'));

            return back()->with('success', 'Course created!');
        } catch (\Throwable $e) {
            Log::error('CourseController@store failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function update(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title'                => ['required', 'string', 'max:255'],
                'description'          => ['nullable', 'string', 'max:2000'],
                'cover_image'          => ['nullable', 'image', 'max:10240'],
                'preview_video'        => ['nullable', 'string', 'max:1000'],
                'preview_video_sound'  => ['nullable', 'boolean'],
                'remove_preview_video' => ['nullable', 'boolean'],
                'access_type'          => ['required', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
                'price'                        => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
                'affiliate_commission_rate'    => ['nullable', 'integer', 'min:0', 'max:100'],
            ]);

            $action->update($course, $data, $request->file('cover_image'));

            return back()->with('success', 'Course updated!');
        } catch (\Throwable $e) {
            Log::error('CourseController@update failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function trackPreviewPlay(Request $request, Community $community, Course $course): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        $course->increment('preview_play_count');
        $course->increment('preview_watch_seconds', $data['seconds']);

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, Community $community, ManageCourse $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $request->validate([
                'course_ids'   => ['required', 'array'],
                'course_ids.*' => ['required', 'integer', 'exists:courses,id'],
            ]);

            $action->reorder($community, $request->course_ids);

            return back()->with('success', 'Courses reordered!');
        } catch (\Throwable $e) {
            Log::error('CourseController@reorder failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroy(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $action->destroy($course);

            return redirect()->route('communities.classroom', $community)->with('success', 'Course deleted!');
        } catch (\Throwable $e) {
            Log::error('CourseController@destroy failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function togglePublish(Request $request, Community $community, Course $course): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $course->update(['is_published' => ! $course->is_published]);

            $label = $course->is_published ? 'published' : 'set to draft';

            return back()->with('success', "Course {$label}!");
        } catch (\Throwable $e) {
            Log::error('CourseController@togglePublish failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function show(Community $community, Course $course, GetCourseDetail $query, CourseAccessService $access): Response
    {
        try {
            $userId    = auth()->id();
            $canManage = auth()->user()?->can('manage', $community) ?? false;

            if (! $course->is_published && ! $canManage) {
                abort(404);
            }

            $hasAccess = $access->hasAccess(auth()->user(), $community, $course);
            $detail    = $query->execute($course, $userId, $hasAccess);

            return Inertia::render('Communities/Classroom/Show', [
                'community'      => $community,
                'course'         => $course->append([]),
                'hasAccess'      => $hasAccess,
                'enrollment'     => $detail['enrollment'],
                'completedIds'   => $detail['completed_ids'],
                'progress'       => $detail['progress'],
                'lessonComments' => $detail['lesson_comments'],
                'quizAttempts'   => $detail['quiz_attempts'],
                'canManage'      => $canManage,
                'canUploadVideo' => $canManage && ($community->owner?->creatorPlan() === 'pro'),
            ]);
        } catch (\Throwable $e) {
            Log::error('CourseController@show failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }
}
