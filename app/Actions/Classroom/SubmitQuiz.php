<?php

namespace App\Actions\Classroom;

use App\Contracts\BadgeEvaluator;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

class SubmitQuiz
{
    public function __construct(private BadgeEvaluator $badges) {}

    /**
     * @return array{score: int, passed: bool, total: int, correct: int, attempt: QuizAttempt}
     */
    public function execute(User $user, Quiz $quiz, array $answers, ?int $communityId = null): array
    {
        $quiz->load('questions.options');

        $total   = $quiz->questions->count();
        $correct = 0;

        foreach ($quiz->questions as $question) {
            $selectedId = $answers[$question->id] ?? null;
            $correctOpt = $question->options->firstWhere('is_correct', true);

            if ($selectedId && $correctOpt && (int) $selectedId === $correctOpt->id) {
                $correct++;
            }
        }

        $score  = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $passed = $score >= $quiz->pass_score;

        $attempt = QuizAttempt::create([
            'quiz_id'      => $quiz->id,
            'user_id'      => $user->id,
            'answers'      => $answers,
            'score'        => $score,
            'passed'       => $passed,
            'completed_at' => now(),
        ]);

        if ($passed) {
            $this->badges->evaluate($user, $communityId);
        }

        return [
            'score'   => $score,
            'passed'  => $passed,
            'total'   => $total,
            'correct' => $correct,
            'attempt' => $attempt,
        ];
    }
}
