<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\CompleteLesson;
use App\Actions\Classroom\ManageLesson;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourseLessonController extends Controller
{
    public function store(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'content' => ['nullable', 'string'],
                'embed_html' => ['nullable', 'string'],
                'video_url' => ['nullable', 'url', 'max:500'],
                'cta_label' => ['nullable', 'string', 'max:100'],
                'cta_url' => ['nullable', 'url', 'max:500'],
            ]);

            $action->store($module, $data);

            return back()->with('success', 'Lesson added!');
        } catch (\Throwable $e) {
            Log::error('CourseLessonController@store failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function update(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson, ManageLesson $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title' => ['sometimes', 'string', 'max:255'],
                'content' => ['nullable', 'string'],
                'embed_html' => ['nullable', 'string'],
                'video_url' => ['nullable', 'url', 'max:500'],
                'video_path' => ['nullable', 'string', 'max:1000'],
                'cta_label' => ['nullable', 'string', 'max:100'],
                'cta_url' => ['nullable', 'url', 'max:500'],
            ]);

            $action->update($lesson, $data);

            return back()->with('success', 'Lesson updated!');
        } catch (\Throwable $e) {
            Log::error('CourseLessonController@update failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroy(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $lesson->delete();

            return back()->with('success', 'Lesson deleted!');
        } catch (\Throwable $e) {
            Log::error('CourseLessonController@destroy failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function complete(Request $request, Community $community, Course $course, CourseLesson $lesson, CompleteLesson $action): RedirectResponse
    {
        try {
            $action->execute($request->user(), $lesson, $community->id);

            return back()->with('success', 'Lesson marked as complete!');
        } catch (\Throwable $e) {
            Log::error('CourseLessonController@complete failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function uploadImage(Request $request, Community $community, ManageLesson $action): JsonResponse
    {
        try {
            $this->authorize('manage', $community);

            $request->validate([
                'image' => ['required', 'image', 'max:10240'],
            ]);

            $url = $action->uploadImage($request->file('image'));

            return response()->json(['url' => $url]);
        } catch (\Throwable $e) {
            Log::error('CourseLessonController@uploadImage failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function reorder(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $request->validate([
                'lesson_ids' => ['required', 'array'],
                'lesson_ids.*' => ['required', 'integer', 'exists:course_lessons,id'],
            ]);

            $action->reorder($module, $request->lesson_ids);

            return back()->with('success', 'Lessons reordered!');
        } catch (\Throwable $e) {
            Log::error('CourseLessonController@reorder failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }
}
