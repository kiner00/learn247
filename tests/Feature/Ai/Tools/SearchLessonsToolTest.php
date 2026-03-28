<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\SearchLessonsTool;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class SearchLessonsToolTest extends TestCase
{
    use RefreshDatabase;

    private function seedLessons(User $user): array
    {
        $community = Community::factory()->create(['name' => 'Dev Community']);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $course = Course::factory()->create(['community_id' => $community->id, 'title' => 'PHP Course']);
        $module = CourseModule::factory()->create(['course_id' => $course->id, 'title' => 'Basics']);

        $lesson1 = CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'Introduction to PHP']);
        $lesson2 = CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'Variables and Types']);
        $lesson3 = CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'PHP Functions']);

        return compact('community', 'course', 'module', 'lesson1', 'lesson2', 'lesson3');
    }

    public function test_returns_error_when_query_is_empty(): void
    {
        $user = User::factory()->create();
        $tool = new SearchLessonsTool($user->id);

        $result = $tool->handle(new Request(['query' => '']));

        $this->assertStringContainsString('provide a search query', $result);
    }

    public function test_returns_error_when_query_is_missing(): void
    {
        $user = User::factory()->create();
        $tool = new SearchLessonsTool($user->id);

        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('provide a search query', $result);
    }

    public function test_finds_lessons_matching_keyword(): void
    {
        $user = User::factory()->create();
        $this->seedLessons($user);

        $tool   = new SearchLessonsTool($user->id);
        $result = $tool->handle(new Request(['query' => 'PHP']));
        $json   = json_decode($result, true);

        $this->assertCount(2, $json);

        $titles = array_column($json, 'lesson');
        $this->assertContains('Introduction to PHP', $titles);
        $this->assertContains('PHP Functions', $titles);
    }

    public function test_returns_module_course_community_info(): void
    {
        $user = User::factory()->create();
        $this->seedLessons($user);

        $tool   = new SearchLessonsTool($user->id);
        $result = $tool->handle(new Request(['query' => 'Introduction']));
        $json   = json_decode($result, true);

        $this->assertSame('Basics', $json[0]['module']);
        $this->assertSame('PHP Course', $json[0]['course']);
        $this->assertSame('Dev Community', $json[0]['community']);
    }

    public function test_returns_no_lessons_message_when_none_match(): void
    {
        $user = User::factory()->create();
        $this->seedLessons($user);

        $tool   = new SearchLessonsTool($user->id);
        $result = $tool->handle(new Request(['query' => 'Quantum']));

        $this->assertStringContainsString('No lessons found', $result);
        $this->assertStringContainsString('Quantum', $result);
    }

    public function test_only_searches_user_communities(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        // User's community
        $this->seedLessons($user);

        // Other user's community with a lesson
        $otherCommunity = Community::factory()->create(['name' => 'Other']);
        CommunityMember::factory()->create(['community_id' => $otherCommunity->id, 'user_id' => $other->id]);
        $course  = Course::factory()->create(['community_id' => $otherCommunity->id]);
        $module  = CourseModule::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['module_id' => $module->id, 'title' => 'Secret PHP Lesson']);

        $tool   = new SearchLessonsTool($user->id);
        $result = $tool->handle(new Request(['query' => 'Secret']));

        $this->assertStringContainsString('No lessons found', $result);
    }

    public function test_limits_results_to_ten(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);

        for ($i = 1; $i <= 15; $i++) {
            CourseLesson::factory()->create(['module_id' => $module->id, 'title' => "Topic Lesson {$i}"]);
        }

        $tool   = new SearchLessonsTool($user->id);
        $result = $tool->handle(new Request(['query' => 'Topic']));
        $json   = json_decode($result, true);

        $this->assertCount(10, $json);
    }

    public function test_description_returns_string(): void
    {
        $tool = new SearchLessonsTool(1);
        $this->assertIsString($tool->description());
    }

    public function test_schema_has_query_key(): void
    {
        $tool   = new SearchLessonsTool(1);
        $schema = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);

        $builder = new class {
            public function description($d) { return $this; }
            public function required() { return $this; }
        };

        $schema->method('string')->willReturn($builder);

        $result = $tool->schema($schema);
        $this->assertArrayHasKey('query', $result);
    }
}
