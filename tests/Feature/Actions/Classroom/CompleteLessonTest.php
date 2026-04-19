<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\CompleteLesson;
use App\Contracts\BadgeEvaluator;
use App\Models\CourseLesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CompleteLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_completion_is_created(): void
    {
        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')->once();

        $action = new CompleteLesson($badges);
        $user = User::factory()->create();
        $lesson = CourseLesson::factory()->create();

        $completion = $action->execute($user, $lesson);

        $this->assertInstanceOf(LessonCompletion::class, $completion);
        $this->assertDatabaseHas('lesson_completions', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_completing_same_lesson_twice_does_not_duplicate(): void
    {
        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')->twice();

        $action = new CompleteLesson($badges);
        $user = User::factory()->create();
        $lesson = CourseLesson::factory()->create();

        $first = $action->execute($user, $lesson);
        $second = $action->execute($user, $lesson);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LessonCompletion::where('user_id', $user->id)->where('lesson_id', $lesson->id)->count());
    }

    public function test_badge_evaluator_is_called_with_community_id(): void
    {
        $communityId = 42;

        $badges = Mockery::mock(BadgeEvaluator::class);
        $badges->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::type(User::class), $communityId);

        $action = new CompleteLesson($badges);
        $user = User::factory()->create();
        $lesson = CourseLesson::factory()->create();

        $action->execute($user, $lesson, $communityId);
    }
}
