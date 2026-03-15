<?php

namespace App\Http\Controllers\Api;

use App\Actions\Classroom\CreateLessonComment;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonCommentController extends Controller
{
    public function store(Request $request, Community $community, Course $course, CourseLesson $lesson, CreateLessonComment $action): JsonResponse
    {
        abort_unless(
            CommunityMember::where('community_id', $community->id)->where('user_id', $request->user()->id)->exists(),
            403, 'You must be a member to comment.'
        );

        $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $comment = $action->execute($request->user(), $lesson, $community->id, $request->content);

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
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        abort_unless($comment->user_id === $user->id || $user->is_super_admin, 403);
        $comment->delete();

        return response()->json(['deleted' => $comment->id]);
    }
}
