<?php

namespace App\Http\Controllers\Api;

use App\Actions\Classroom\ManageQuiz;
use App\Actions\Classroom\SubmitQuiz;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function store(Request $request, Community $community, Course $course, CourseLesson $lesson, ManageQuiz $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'                             => ['required', 'string', 'max:255'],
            'pass_score'                        => ['required', 'integer', 'min:1', 'max:100'],
            'questions'                         => ['required', 'array', 'min:1'],
            'questions.*.question'              => ['required', 'string'],
            'questions.*.type'                  => ['required', 'in:multiple_choice,true_false'],
            'questions.*.options'               => ['required', 'array', 'min:2'],
            'questions.*.options.*.label'       => ['required', 'string'],
            'questions.*.options.*.is_correct'  => ['required', 'boolean'],
        ]);

        $quiz = $action->store($lesson, $data);

        return response()->json(['message' => 'Quiz saved.', 'quiz_id' => $quiz->id], 201);
    }

    public function destroy(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz, ManageQuiz $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $action->destroy($quiz);

        return response()->json(['message' => 'Quiz deleted.']);
    }
}
