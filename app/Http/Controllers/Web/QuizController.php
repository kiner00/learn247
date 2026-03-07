<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Services\BadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /** Owner creates or replaces a quiz on a lesson. */
    public function store(Request $request, Community $community, Course $course, CourseLesson $lesson): RedirectResponse
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

        // Delete existing quiz if present
        $lesson->quiz()->delete();

        $quiz = Quiz::create([
            'lesson_id'  => $lesson->id,
            'title'      => $data['title'],
            'pass_score' => $data['pass_score'],
        ]);

        foreach ($data['questions'] as $i => $q) {
            $question = QuizQuestion::create([
                'quiz_id'  => $quiz->id,
                'question' => $q['question'],
                'type'     => $q['type'],
                'position' => $i,
            ]);

            foreach ($q['options'] as $opt) {
                QuizQuestionOption::create([
                    'question_id' => $question->id,
                    'label'       => $opt['label'],
                    'is_correct'  => $opt['is_correct'],
                ]);
            }
        }

        return back()->with('success', 'Quiz saved!');
    }

    /** Student submits a quiz attempt. */
    public function submit(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz): RedirectResponse
    {
        $request->validate([
            'answers'   => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        $quiz->load('questions.options');

        $total   = $quiz->questions->count();
        $correct = 0;

        foreach ($quiz->questions as $question) {
            $selectedId = $request->answers[$question->id] ?? null;
            $correctOpt = $question->options->firstWhere('is_correct', true);

            if ($selectedId && $correctOpt && (int) $selectedId === $correctOpt->id) {
                $correct++;
            }
        }

        $score  = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $passed = $score >= $quiz->pass_score;

        QuizAttempt::create([
            'quiz_id'      => $quiz->id,
            'user_id'      => $request->user()->id,
            'answers'      => $request->answers,
            'score'        => $score,
            'passed'       => $passed,
            'completed_at' => now(),
        ]);

        if ($passed) {
            app(BadgeService::class)->evaluate($request->user(), $community->id);
        }

        return back()->with('quiz_result', [
            'score'  => $score,
            'passed' => $passed,
            'total'  => $total,
            'correct'=> $correct,
        ]);
    }

    public function destroy(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $quiz->delete();
        return back()->with('success', 'Quiz deleted!');
    }
}
