<?php

namespace Tests\Feature\Api;

use App\Actions\Classroom\ManageQuiz;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class QuizControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createClassroomStructure(User $owner): array
    {
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['module_id' => $module->id]);

        return [$community, $course, $lesson];
    }

    private function quizPayload(): array
    {
        return [
            'title' => 'Chapter 1 Quiz',
            'pass_score' => 70,
            'questions' => [
                [
                    'question' => 'What is Laravel?',
                    'type' => 'multiple_choice',
                    'options' => [
                        ['label' => 'A PHP framework', 'is_correct' => true],
                        ['label' => 'A JavaScript library', 'is_correct' => false],
                    ],
                ],
                [
                    'question' => 'Laravel uses MVC pattern.',
                    'type' => 'true_false',
                    'options' => [
                        ['label' => 'True', 'is_correct' => true],
                        ['label' => 'False', 'is_correct' => false],
                    ],
                ],
            ],
        ];
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $this->postJson(
            "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
            $this->quizPayload()
        )->assertUnauthorized();
    }

    public function test_store_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
                $this->quizPayload()
            )
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'pass_score', 'questions']);
    }

    public function test_store_validates_question_structure(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
                [
                    'title' => 'Quiz',
                    'pass_score' => 70,
                    'questions' => [
                        ['question' => '', 'type' => 'invalid', 'options' => []],
                    ],
                ]
            )
            ->assertUnprocessable();
    }

    public function test_store_creates_quiz_with_questions_and_options(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
                $this->quizPayload()
            );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Quiz saved.')
            ->assertJsonStructure(['quiz_id']);

        $this->assertDatabaseHas('quizzes', [
            'lesson_id' => $lesson->id,
            'title' => 'Chapter 1 Quiz',
            'pass_score' => 70,
        ]);

        $this->assertDatabaseHas('quiz_questions', [
            'quiz_id' => $response->json('quiz_id'),
            'question' => 'What is Laravel?',
            'type' => 'multiple_choice',
        ]);
    }

    public function test_store_replaces_existing_quiz_on_same_lesson(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Old Quiz',
            'pass_score' => 50,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
                $this->quizPayload()
            )
            ->assertStatus(201);

        $this->assertDatabaseMissing('quizzes', ['title' => 'Old Quiz']);
        $this->assertDatabaseHas('quizzes', ['title' => 'Chapter 1 Quiz', 'lesson_id' => $lesson->id]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        $quiz = Quiz::create(['lesson_id' => $lesson->id, 'title' => 'Test', 'pass_score' => 50]);

        $this->deleteJson(
            "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}"
        )->assertUnauthorized();
    }

    public function test_destroy_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        $quiz = Quiz::create(['lesson_id' => $lesson->id, 'title' => 'Test', 'pass_score' => 50]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}"
            )
            ->assertForbidden();
    }

    public function test_destroy_deletes_quiz(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        $quiz = Quiz::create(['lesson_id' => $lesson->id, 'title' => 'To Delete', 'pass_score' => 50]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}"
            )
            ->assertOk()
            ->assertJsonPath('message', 'Quiz deleted.');

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }

    // ─── error branch: store ─────────────────────────────────────────────────

    public function test_store_returns_500_when_action_throws(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $mock = Mockery::mock(ManageQuiz::class);
        $mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageQuiz::class, $mock);

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz",
                $this->quizPayload()
            )
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to save quiz.');
    }

    // ─── error branch: destroy ───────────────────────────────────────────────

    public function test_destroy_returns_500_when_action_throws(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        $quiz = Quiz::create(['lesson_id' => $lesson->id, 'title' => 'Failing', 'pass_score' => 50]);

        $mock = Mockery::mock(ManageQuiz::class);
        $mock->shouldReceive('destroy')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageQuiz::class, $mock);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson(
                "/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/quiz/{$quiz->id}"
            )
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to delete quiz.');
    }
}
