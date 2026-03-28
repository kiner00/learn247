<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetUserProgressTool;
use App\Models\Badge;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetUserProgressToolTest extends TestCase
{
    use RefreshDatabase;

    private function createCommunityWithLessons(User $owner, string $name = 'Test Community'): array
    {
        $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => $name]);
        $course    = Course::factory()->create(['community_id' => $community->id, 'title' => 'Course 1']);
        $module    = CourseModule::factory()->create(['course_id' => $course->id, 'title' => 'Module 1']);
        $lesson1   = CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'Lesson 1']);
        $lesson2   = CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'Lesson 2']);
        $lesson3   = CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'Lesson 3']);

        return compact('community', 'course', 'module', 'lesson1', 'lesson2', 'lesson3');
    }

    public function test_returns_not_member_message_when_user_is_not_in_community(): void
    {
        $user = User::factory()->create();
        $tool = new GetUserProgressTool($user->id);

        $result = $tool->handle(new Request(['community' => 'Nonexistent']));

        $this->assertStringContainsString('not a member', $result);
    }

    public function test_returns_progress_for_valid_member(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();
        $data  = $this->createCommunityWithLessons($owner, 'PHP Masters');

        CommunityMember::factory()->create([
            'community_id' => $data['community']->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
            'points'       => 50,
        ]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $data['lesson1']->id]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'PHP Masters']));
        $json   = json_decode($result, true);

        $this->assertSame('PHP Masters', $json['community']);
        $this->assertSame('member', $json['role']);
        // 50 base + 20 from LessonCompletion observer = 70
        $this->assertSame(70, $json['points']);
        $this->assertSame(1, $json['lessons_done']);
        $this->assertSame(3, $json['lessons_total']);
        $this->assertCount(2, $json['pending_lessons']);
    }

    public function test_includes_quiz_data(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();
        $data  = $this->createCommunityWithLessons($owner, 'Quiz Community');

        CommunityMember::factory()->create([
            'community_id' => $data['community']->id,
            'user_id'      => $user->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id'  => $data['lesson1']->id,
            'title'      => 'Quiz 1',
            'pass_score' => 70,
        ]);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'answers' => [],
            'score'   => 85,
            'passed'  => true,
        ]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'Quiz Community']));
        $json   = json_decode($result, true);

        $this->assertCount(1, $json['quizzes']);
        $this->assertSame('Quiz 1', $json['quizzes'][0]['quiz']);
        $this->assertTrue($json['quizzes'][0]['attempted']);
        $this->assertTrue($json['quizzes'][0]['passed']);
        $this->assertSame(85, $json['quizzes'][0]['score']);
    }

    public function test_includes_quiz_not_attempted(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();
        $data  = $this->createCommunityWithLessons($owner, 'Quiz Community');

        CommunityMember::factory()->create([
            'community_id' => $data['community']->id,
            'user_id'      => $user->id,
        ]);

        Quiz::create([
            'lesson_id'  => $data['lesson1']->id,
            'title'      => 'Unattempted Quiz',
            'pass_score' => 70,
        ]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'Quiz Community']));
        $json   = json_decode($result, true);

        $this->assertFalse($json['quizzes'][0]['attempted']);
        $this->assertFalse($json['quizzes'][0]['passed']);
        $this->assertSame(0, $json['quizzes'][0]['score']);
    }

    public function test_includes_badges(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();
        $data  = $this->createCommunityWithLessons($owner, 'Badge Community');

        CommunityMember::factory()->create([
            'community_id' => $data['community']->id,
            'user_id'      => $user->id,
        ]);

        $badge = Badge::create([
            'key'             => 'first_lesson',
            'type'            => 'member',
            'community_id'    => $data['community']->id,
            'name'            => 'First Lesson Badge',
            'description'     => 'Complete your first lesson',
            'icon'            => '🏆',
            'condition_type'  => 'lessons_completed',
            'condition_value' => 1,
        ]);

        UserBadge::create([
            'user_id'      => $user->id,
            'badge_id'     => $badge->id,
            'community_id' => $data['community']->id,
            'earned_at'    => now(),
        ]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'Badge Community']));
        $json   = json_decode($result, true);

        $this->assertContains('First Lesson Badge', $json['badges']);
    }

    public function test_matches_community_name_case_insensitively(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();
        $data  = $this->createCommunityWithLessons($owner, 'PHP Masters');

        CommunityMember::factory()->create([
            'community_id' => $data['community']->id,
            'user_id'      => $user->id,
        ]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'php masters']));
        $json   = json_decode($result, true);

        $this->assertSame('PHP Masters', $json['community']);
    }

    public function test_pending_lessons_limited_to_five(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();

        $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Big Community']);
        $course    = Course::factory()->create(['community_id' => $community->id]);
        $module    = CourseModule::factory()->create(['course_id' => $course->id]);

        for ($i = 1; $i <= 8; $i++) {
            CourseLesson::factory()->create(['module_id' => $module->id, 'title' => "Lesson {$i}"]);
        }

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'Big Community']));
        $json   = json_decode($result, true);

        $this->assertCount(5, $json['pending_lessons']);
        $this->assertSame(8, $json['lessons_total']);
    }

    public function test_picks_highest_quiz_score_from_multiple_attempts(): void
    {
        $user  = User::factory()->create();
        $owner = User::factory()->create();
        $data  = $this->createCommunityWithLessons($owner, 'Multi Attempt');

        CommunityMember::factory()->create([
            'community_id' => $data['community']->id,
            'user_id'      => $user->id,
        ]);

        $quiz = Quiz::create([
            'lesson_id'  => $data['lesson1']->id,
            'title'      => 'Retry Quiz',
            'pass_score' => 70,
        ]);

        QuizAttempt::create(['quiz_id' => $quiz->id, 'user_id' => $user->id, 'answers' => [], 'score' => 40, 'passed' => false]);
        QuizAttempt::create(['quiz_id' => $quiz->id, 'user_id' => $user->id, 'answers' => [], 'score' => 95, 'passed' => true]);

        $tool   = new GetUserProgressTool($user->id);
        $result = $tool->handle(new Request(['community' => 'Multi Attempt']));
        $json   = json_decode($result, true);

        $this->assertSame(95, $json['quizzes'][0]['score']);
    }

    public function test_description_returns_string(): void
    {
        $tool = new GetUserProgressTool(1);
        $this->assertIsString($tool->description());
        $this->assertNotEmpty($tool->description());
    }

    public function test_schema_returns_community_key(): void
    {
        $tool   = new GetUserProgressTool(1);
        $schema = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);

        $builder = new class {
            public function description($d) { return $this; }
            public function required() { return $this; }
        };

        $schema->method('string')->willReturn($builder);

        $result = $tool->schema($schema);
        $this->assertArrayHasKey('community', $result);
    }
}
