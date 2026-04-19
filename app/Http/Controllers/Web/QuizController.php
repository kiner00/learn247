<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\ManageQuiz;
use App\Actions\Classroom\SubmitQuiz;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function store(StoreQuizRequest $request, Community $community, Course $course, CourseLesson $lesson, ManageQuiz $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        try {
            $action->store($lesson, $request->validated());

            return back()->with('success', 'Quiz saved!');
        } catch (\Throwable $e) {
            Log::error('QuizController@store failed', ['error' => $e->getMessage(), 'lesson_id' => $lesson->id]);

            return back()->with('error', 'Failed to save quiz.');
        }
    }

    public function submit(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz, SubmitQuiz $action): RedirectResponse
    {
        $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        try {
            $result = $action->execute($request->user(), $quiz, $request->answers, $community->id);

            return back()->with('quiz_result', [
                'score' => $result['score'],
                'passed' => $result['passed'],
                'total' => $result['total'],
                'correct' => $result['correct'],
            ]);
        } catch (\Throwable $e) {
            Log::error('QuizController@submit failed', ['error' => $e->getMessage(), 'quiz_id' => $quiz->id]);

            return back()->with('error', 'Failed to submit quiz.');
        }
    }

    public function destroy(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz, ManageQuiz $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        try {
            $action->destroy($quiz);

            return back()->with('success', 'Quiz deleted!');
        } catch (\Throwable $e) {
            Log::error('QuizController@destroy failed', ['error' => $e->getMessage(), 'quiz_id' => $quiz->id]);

            return back()->with('error', 'Failed to delete quiz.');
        }
    }
}
