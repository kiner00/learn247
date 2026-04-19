<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\CreateLessonComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonCommentRequest;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class LessonCommentController extends Controller
{
    public function store(StoreLessonCommentRequest $request, Community $community, Course $course, CourseLesson $lesson, CreateLessonComment $action): RedirectResponse
    {
        abort_unless(
            CommunityMember::where('community_id', $community->id)->where('user_id', $request->user()->id)->exists(),
            403, 'You must be a member to comment.'
        );

        try {
            $action->execute($request->user(), $lesson, $community->id, $request->validated('content'));

            return back();
        } catch (\Throwable $e) {
            Log::error('LessonCommentController@store failed', ['error' => $e->getMessage(), 'lesson_id' => $lesson->id]);

            return back()->with('error', 'Failed to post comment.');
        }
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($comment->user_id === $user->id || $user->is_super_admin, 403);

        try {
            $comment->delete();

            return back();
        } catch (\Throwable $e) {
            Log::error('LessonCommentController@destroy failed', ['error' => $e->getMessage(), 'comment_id' => $comment->id]);

            return back()->with('error', 'Failed to delete comment.');
        }
    }
}
