<?php

namespace Tests\Feature\Queries\AI;

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
use App\Queries\AI\BuildAIContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildAIContextTest extends TestCase
{
    use RefreshDatabase;

    private BuildAIContext $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new BuildAIContext;
    }

    public function test_returns_basic_info_when_user_has_no_memberships(): void
    {
        $user = User::factory()->create(['name' => 'Solo User', 'email' => 'solo@example.com']);

        $result = $this->query->execute($user);

        $this->assertEquals('Solo User', $result['name']);
        $this->assertEquals('solo@example.com', $result['email']);
        $this->assertEmpty($result['communities']);
    }

    public function test_returns_community_with_role_and_points(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['name' => 'Test Community']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'points' => 100,
        ]);

        $result = $this->query->execute($user);

        $this->assertCount(1, $result['communities']);
        $communityData = $result['communities'][0];
        $this->assertEquals('Test Community', $communityData['name']);
        $this->assertEquals('admin', $communityData['role']);
        $this->assertEquals(100, $communityData['points']);
        $this->assertArrayHasKey('level', $communityData);
    }

    public function test_tracks_lesson_completion_progress(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course 1',
            'position' => 0,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title' => 'Module 1',
            'position' => 0,
        ]);
        $lesson1 = CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson A',
            'position' => 0,
        ]);
        $lesson2 = CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson B',
            'position' => 1,
        ]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson1->id]);

        $result = $this->query->execute($user);

        $communityData = $result['communities'][0];
        $this->assertEquals(1, $communityData['lessons_done']);
        $this->assertEquals(2, $communityData['lessons_total']);
        $this->assertContains('Lesson B', $communityData['lessons_pending_names']);
    }

    public function test_includes_quiz_data_with_attempts(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'position' => 0,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title' => 'Module',
            'position' => 0,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Quiz Lesson',
            'position' => 0,
        ]);

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Final Quiz',
            'pass_score' => 70,
        ]);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'answers' => [],
            'score' => 85,
            'passed' => true,
            'completed_at' => now(),
        ]);

        $result = $this->query->execute($user);

        $quizzes = $result['communities'][0]['quizzes'];
        $this->assertCount(1, $quizzes);
        $this->assertEquals('Final Quiz', $quizzes[0]['title']);
        $this->assertTrue($quizzes[0]['attempted']);
        $this->assertTrue($quizzes[0]['passed']);
        $this->assertEquals(85, $quizzes[0]['score']);
    }

    public function test_quiz_shows_unattempted_when_no_attempt_exists(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'position' => 0,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title' => 'Module',
            'position' => 0,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson',
            'position' => 0,
        ]);
        Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Unattempted Quiz',
            'pass_score' => 50,
        ]);

        $result = $this->query->execute($user);

        $quizzes = $result['communities'][0]['quizzes'];
        $this->assertCount(1, $quizzes);
        $this->assertFalse($quizzes[0]['attempted']);
        $this->assertFalse($quizzes[0]['passed']);
        $this->assertEquals(0, $quizzes[0]['score']);
    }

    public function test_includes_earned_badges(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $badge = Badge::create([
            'key' => 'first_post',
            'type' => 'member',
            'community_id' => $community->id,
            'name' => 'First Post',
            'icon' => '📝',
            'description' => 'Made your first post',
            'condition_type' => 'first_post',
            'condition_value' => 1,
        ]);

        UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'community_id' => $community->id,
            'earned_at' => now(),
        ]);

        $result = $this->query->execute($user);

        $this->assertContains('First Post', $result['communities'][0]['badges']);
    }

    public function test_handles_multiple_communities(): void
    {
        $user = User::factory()->create();

        $community1 = Community::factory()->create(['name' => 'Community A']);
        $community2 = Community::factory()->create(['name' => 'Community B']);

        CommunityMember::factory()->create([
            'community_id' => $community1->id,
            'user_id' => $user->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community2->id,
            'user_id' => $user->id,
        ]);

        $result = $this->query->execute($user);

        $this->assertCount(2, $result['communities']);
        $names = array_column($result['communities'], 'name');
        $this->assertContains('Community A', $names);
        $this->assertContains('Community B', $names);
    }

    public function test_uses_best_quiz_score_from_multiple_attempts(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'position' => 0,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title' => 'Module',
            'position' => 0,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson',
            'position' => 0,
        ]);
        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Retry Quiz',
            'pass_score' => 70,
        ]);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'answers' => [],
            'score' => 40,
            'passed' => false,
            'completed_at' => now()->subHour(),
        ]);
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'answers' => [],
            'score' => 90,
            'passed' => true,
            'completed_at' => now(),
        ]);

        $result = $this->query->execute($user);

        $quizzes = $result['communities'][0]['quizzes'];
        $this->assertEquals(90, $quizzes[0]['score']);
        $this->assertTrue($quizzes[0]['passed']);
    }
}
