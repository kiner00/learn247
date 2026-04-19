<?php

namespace Tests\Feature\Web;

use App\Actions\Classroom\ManageQuiz;
use App\Actions\Classroom\SubmitQuiz;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class QuizControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCommunityWithLesson(User $owner): array
    {
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Test Course',
            'description' => 'A test course',
            'position' => 0,
        ]);

        $module = CourseModule::create([
            'course_id' => $course->id,
            'title' => 'Module 1',
            'position' => 0,
        ]);

        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'content' => 'Lesson content',
            'position' => 0,
        ]);

        return [$community, $course, $lesson];
    }

    private function quizPayload(): array
    {
        return [
            'title' => 'Chapter 1 Quiz',
            'pass_score' => 70,
            'questions' => [
                [
                    'question' => 'What is 2+2?',
                    'type' => 'multiple_choice',
                    'options' => [
                        ['label' => '3', 'is_correct' => false],
                        ['label' => '4', 'is_correct' => true],
                        ['label' => '5', 'is_correct' => false],
                    ],
                ],
                [
                    'question' => 'PHP is a programming language.',
                    'type' => 'true_false',
                    'options' => [
                        ['label' => 'True', 'is_correct' => true],
                        ['label' => 'False', 'is_correct' => false],
                    ],
                ],
            ],
        ];
    }

    // ─── store ──────────────────────────────────────────────────────────────────

    public function test_owner_can_create_quiz(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz", $this->quizPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quiz saved!');
        $this->assertDatabaseHas('quizzes', [
            'lesson_id' => $lesson->id,
            'title' => 'Chapter 1 Quiz',
            'pass_score' => 70,
        ]);
        $this->assertEquals(2, QuizQuestion::where('quiz_id', Quiz::first()->id)->count());
    }

    public function test_non_owner_cannot_create_quiz(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz", $this->quizPayload());

        $response->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz", []);

        $response->assertSessionHasErrors(['title', 'pass_score', 'questions']);
    }

    public function test_guest_cannot_create_quiz(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $response = $this->post(
            "/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz",
            $this->quizPayload()
        );

        $response->assertRedirect('/login');
    }

    // ─── submit ─────────────────────────────────────────────────────────────────

    public function test_member_can_submit_quiz(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Submit Quiz',
            'pass_score' => 50,
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question' => 'What is 1+1?',
            'type' => 'multiple_choice',
            'position' => 0,
        ]);

        $correctOption = QuizQuestionOption::create([
            'question_id' => $question->id,
            'label' => '2',
            'is_correct' => true,
        ]);

        QuizQuestionOption::create([
            'question_id' => $question->id,
            'label' => '3',
            'is_correct' => false,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}/submit", [
                'answers' => [$question->id => $correctOption->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('quiz_result');

        $quizResult = session('quiz_result');
        $this->assertEquals(100, $quizResult['score']);
        $this->assertTrue($quizResult['passed']);
        $this->assertEquals(1, $quizResult['total']);
        $this->assertEquals(1, $quizResult['correct']);

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $member->id,
            'passed' => true,
        ]);
    }

    public function test_member_can_fail_quiz(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Hard Quiz',
            'pass_score' => 100,
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question' => 'What is 1+1?',
            'type' => 'multiple_choice',
            'position' => 0,
        ]);

        QuizQuestionOption::create([
            'question_id' => $question->id,
            'label' => '2',
            'is_correct' => true,
        ]);

        $wrongOption = QuizQuestionOption::create([
            'question_id' => $question->id,
            'label' => '3',
            'is_correct' => false,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}/submit", [
                'answers' => [$question->id => $wrongOption->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('quiz_result');

        $quizResult = session('quiz_result');
        $this->assertEquals(0, $quizResult['score']);
        $this->assertFalse($quizResult['passed']);
    }

    public function test_submit_validates_answers_required(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Validate Quiz',
            'pass_score' => 50,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}/submit", []);

        $response->assertSessionHasErrors('answers');
    }

    // ─── destroy ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_quiz(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Deletable Quiz',
            'pass_score' => 50,
        ]);

        $response = $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quiz deleted!');
        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }

    public function test_non_owner_cannot_delete_quiz(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Protected Quiz',
            'pass_score' => 50,
        ]);

        $response = $this->actingAs($member)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_non_member_cannot_access_quiz_routes(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $response = $this->actingAs($outsider)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz", $this->quizPayload());

        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    // ─── error branch: store ─────────────────────────────────────────────────

    public function test_store_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $mock = Mockery::mock(ManageQuiz::class);
        $mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageQuiz::class, $mock);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz", $this->quizPayload());

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to save quiz.');
    }

    // ─── error branch: submit ────────────────────────────────────────────────

    public function test_submit_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Failing Quiz',
            'pass_score' => 50,
        ]);

        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question' => 'What is 1+1?',
            'type' => 'multiple_choice',
            'position' => 0,
        ]);

        $option = QuizQuestionOption::create([
            'question_id' => $question->id,
            'label' => '2',
            'is_correct' => true,
        ]);

        $mock = Mockery::mock(SubmitQuiz::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(SubmitQuiz::class, $mock);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}/submit", [
                'answers' => [$question->id => $option->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to submit quiz.');
    }

    // ─── error branch: destroy ───────────────────────────────────────────────

    public function test_destroy_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Failing Quiz',
            'pass_score' => 50,
        ]);

        $mock = Mockery::mock(ManageQuiz::class);
        $mock->shouldReceive('destroy')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageQuiz::class, $mock);

        $response = $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to delete quiz.');
    }

    // ─── guest cannot submit quiz ────────────────────────────────────────────

    public function test_guest_cannot_submit_quiz(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Protected Quiz',
            'pass_score' => 50,
        ]);

        $response = $this->post(
            "/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}/submit",
            ['answers' => [1 => 1]]
        );

        $response->assertRedirect('/login');
    }

    // ─── guest cannot delete quiz ────────────────────────────────────────────

    public function test_guest_cannot_delete_quiz(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Protected Quiz',
            'pass_score' => 50,
        ]);

        $response = $this->delete(
            "/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}"
        );

        $response->assertRedirect('/login');
    }

    // ─── submit validates answers must be integers ───────────────────────────

    public function test_submit_validates_answers_must_be_integers(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createCommunityWithLesson($owner);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Validate Quiz',
            'pass_score' => 50,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}/submit", [
                'answers' => ['not_an_integer'],
            ]);

        $response->assertSessionHasErrors('answers.0');
    }
}
