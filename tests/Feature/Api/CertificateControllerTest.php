<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_certificate_when_user_has_completed_all_lessons(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test Course',
            'description'  => 'Desc',
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);
        $lesson1 = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson 1',
            'position'  => 1,
        ]);
        $lesson2 = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson 2',
            'position'  => 2,
        ]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson1->id]);
        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson2->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/communities/{$community->slug}/courses/{$course->id}/certificate")
            ->assertStatus(201)
            ->assertJsonPath('message', 'Certificate issued.')
            ->assertJsonStructure(['certificate' => ['uuid']]);

        $this->assertDatabaseHas('certificates', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_get_certificate_by_uuid_returns_public_data(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test Course',
            'description'  => 'Desc',
        ]);

        $uuid = (string) Str::uuid();
        Certificate::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'uuid'      => $uuid,
            'issued_at' => now(),
        ]);

        $this->getJson("/api/certificates/{$uuid}")
            ->assertOk()
            ->assertJsonStructure([
                'certificate' => [
                    'uuid',
                    'issued_at',
                    'student_name',
                    'student_avatar',
                    'course_title',
                    'community_name',
                    'community_slug',
                ],
            ]);
    }
}
