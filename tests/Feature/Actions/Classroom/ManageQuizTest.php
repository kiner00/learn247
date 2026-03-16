<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\ManageQuiz;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageQuizTest extends TestCase
{
    use RefreshDatabase;

    private ManageQuiz $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ManageQuiz();
    }

    private function createLesson(): CourseLesson
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test Course',
            'position'     => 0,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Test Module',
            'position'  => 0,
        ]);

        return CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Test Lesson',
            'content'   => 'Some content',
            'position'  => 0,
        ]);
    }

    private function quizData(): array
    {
        return [
            'title'      => 'Sample Quiz',
            'pass_score' => 70,
            'questions'  => [
                [
                    'question' => 'What is 2+2?',
                    'type'     => 'multiple_choice',
                    'options'  => [
                        ['label' => '3', 'is_correct' => false],
                        ['label' => '4', 'is_correct' => true],
                    ],
                ],
                [
                    'question' => 'What is the capital of France?',
                    'type'     => 'multiple_choice',
                    'options'  => [
                        ['label' => 'Paris', 'is_correct' => true],
                        ['label' => 'London', 'is_correct' => false],
                        ['label' => 'Berlin', 'is_correct' => false],
                    ],
                ],
            ],
        ];
    }

    public function test_store_creates_quiz_for_lesson(): void
    {
        $lesson = $this->createLesson();

        $quiz = $this->action->store($lesson, $this->quizData());

        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertDatabaseHas('quizzes', [
            'lesson_id'  => $lesson->id,
            'title'      => 'Sample Quiz',
            'pass_score' => 70,
        ]);
    }

    public function test_store_creates_questions_with_correct_positions(): void
    {
        $lesson = $this->createLesson();

        $quiz = $this->action->store($lesson, $this->quizData());

        $questions = QuizQuestion::where('quiz_id', $quiz->id)->orderBy('position')->get();
        $this->assertCount(2, $questions);
        $this->assertEquals('What is 2+2?', $questions[0]->question);
        $this->assertEquals(0, $questions[0]->position);
        $this->assertEquals('What is the capital of France?', $questions[1]->question);
        $this->assertEquals(1, $questions[1]->position);
    }

    public function test_store_creates_options_for_each_question(): void
    {
        $lesson = $this->createLesson();

        $quiz = $this->action->store($lesson, $this->quizData());

        $questions = QuizQuestion::where('quiz_id', $quiz->id)->orderBy('position')->get();

        $firstQuestionOptions = QuizQuestionOption::where('question_id', $questions[0]->id)->get();
        $this->assertCount(2, $firstQuestionOptions);
        $this->assertTrue($firstQuestionOptions->where('label', '4')->first()->is_correct);
        $this->assertFalse($firstQuestionOptions->where('label', '3')->first()->is_correct);

        $secondQuestionOptions = QuizQuestionOption::where('question_id', $questions[1]->id)->get();
        $this->assertCount(3, $secondQuestionOptions);
        $this->assertTrue($secondQuestionOptions->where('label', 'Paris')->first()->is_correct);
    }

    public function test_store_replaces_existing_quiz_on_lesson(): void
    {
        $lesson = $this->createLesson();

        $oldQuiz = $this->action->store($lesson, $this->quizData());
        $oldQuizId = $oldQuiz->id;

        $newData = [
            'title'      => 'Replacement Quiz',
            'pass_score' => 80,
            'questions'  => [
                [
                    'question' => 'New question?',
                    'type'     => 'multiple_choice',
                    'options'  => [
                        ['label' => 'Yes', 'is_correct' => true],
                        ['label' => 'No', 'is_correct' => false],
                    ],
                ],
            ],
        ];

        $newQuiz = $this->action->store($lesson, $newData);

        $this->assertDatabaseMissing('quizzes', ['id' => $oldQuizId]);
        $this->assertDatabaseHas('quizzes', [
            'id'         => $newQuiz->id,
            'lesson_id'  => $lesson->id,
            'title'      => 'Replacement Quiz',
            'pass_score' => 80,
        ]);
        $this->assertCount(1, QuizQuestion::where('quiz_id', $newQuiz->id)->get());
    }

    public function test_destroy_deletes_quiz(): void
    {
        $lesson = $this->createLesson();
        $quiz = $this->action->store($lesson, $this->quizData());

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);

        $this->action->destroy($quiz);

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }
}
