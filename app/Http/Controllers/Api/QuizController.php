<?php

namespace App\Http\Controllers\Api;

use App\Actions\Classroom\ManageQuiz;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function store(StoreQuizRequest $request, Community $community, Course $course, CourseLesson $lesson, ManageQuiz $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        try {
            $quiz = $action->store($lesson, $request->validated());

            return response()->json(['message' => 'Quiz saved.', 'quiz_id' => $quiz->id], 201);
        } catch (\Throwable $e) {
            Log::error('Api\QuizController@store failed', ['error' => $e->getMessage(), 'lesson_id' => $lesson->id]);

            return response()->json(['message' => 'Failed to save quiz.'], 500);
        }
    }

    public function destroy(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz, ManageQuiz $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        try {
            $action->destroy($quiz);

            return response()->json(['message' => 'Quiz deleted.']);
        } catch (\Throwable $e) {
            Log::error('Api\QuizController@destroy failed', ['error' => $e->getMessage(), 'quiz_id' => $quiz->id]);

            return response()->json(['message' => 'Failed to delete quiz.'], 500);
        }
    }
}
