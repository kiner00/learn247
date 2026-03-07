<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LessonCommentController extends Controller
{
    public function store(Request $request, Community $community, Course $course, CourseLesson $lesson): RedirectResponse
    {
        abort_unless(
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $request->user()->id)
                ->exists(),
            403,
            'You must be a member to comment.'
        );

        $request->validate(['content' => ['required', 'string', 'max:2000']]);

        Comment::create([
            'lesson_id'    => $lesson->id,
            'community_id' => $community->id,
            'user_id'      => $request->user()->id,
            'content'      => $request->content,
        ]);

        return back();
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(
            $comment->user_id === $user->id || $user->is_super_admin,
            403
        );

        $comment->delete();

        return back();
    }
}
