<?php

namespace App\Actions\Classroom;

use App\Models\CourseLesson;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;

class ManageQuiz
{
    public function store(CourseLesson $lesson, array $data): Quiz
    {
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

        return $quiz;
    }

    public function destroy(Quiz $quiz): void
    {
        $quiz->delete();
    }
}
