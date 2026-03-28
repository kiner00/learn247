<?php

namespace App\Http\Controllers\Api;

use App\Actions\Classroom\CreateLessonComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonCommentRequest;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LessonCommentController extends Controller
{
    public function store(StoreLessonCommentRequest $request, Community $community, Course $course, CourseLesson $lesson, CreateLessonComment $action): JsonResponse
    {
        abort_unless(
            CommunityMember::where('community_id', $community->id)->where('user_id', $request->user()->id)->exists(),
            403, 'You must be a member to comment.'
        );

        try {
            $comment = $action->execute($request->user(), $lesson, $community->id, $request->validated('content'));

            return response()->json([
                'message' => 'Comment posted.',
                'comment' => [
                    'id'         => $comment->id,
                    'content'    => $comment->content,
                    'created_at' => $comment->created_at,
                    'user'       => [
                        'id'       => $request->user()->id,
                        'name'     => $request->user()->name,
                        'username' => $request->user()->username,
                    ],
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Api\LessonCommentController@store failed', ['error' => $e->getMessage(), 'lesson_id' => $lesson->id]);
            return response()->json(['message' => 'Failed to post comment.'], 500);
        }
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        abort_unless($comment->user_id === $user->id || $user->is_super_admin, 403);

        try {
            $comment->delete();

            return response()->json(['deleted' => $comment->id]);
        } catch (\Throwable $e) {
            Log::error('Api\LessonCommentController@destroy failed', ['error' => $e->getMessage(), 'comment_id' => $comment->id]);
            return response()->json(['message' => 'Failed to delete comment.'], 500);
        }
    }
}
