<?php

namespace Tests\Feature\Web;

use App\Models\Certificate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── show (public) ─────────────────────────────────────────────────────────

    public function test_show_displays_certificate_for_valid_uuid(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);
        $cert = Certificate::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        $this->get(route('certificates.show', $cert->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Certificate/Show')
                ->has('certificate')
                ->where('certificate.uuid', $cert->uuid)
                ->where('certificate.student_name', $user->name)
                ->where('certificate.course_title', $course->title)
            );
    }

    public function test_show_returns_404_for_nonexistent_uuid(): void
    {
        $this->get(route('certificates.show', 'nonexistent-uuid'))
            ->assertNotFound();
    }

    public function test_show_is_accessible_without_auth(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);
        $cert = Certificate::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        $this->assertGuest();
        $this->get(route('certificates.show', $cert->uuid))->assertOk();
    }

    // ── issue ─────────────────────────────────────────────────────────────────

    public function test_issue_creates_certificate_for_completed_course(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['module_id' => $module->id]);

        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        LessonCompletion::create([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $this->actingAs($user)
            ->post(route('communities.classroom.courses.certificate', [$community, $course]))
            ->assertRedirect();

        $this->assertDatabaseHas('certificates', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_issue_denied_when_lessons_not_completed(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['module_id' => $module->id]);

        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('communities.classroom.courses.certificate', [$community, $course]))
            ->assertStatus(422);
    }

    public function test_issue_requires_auth(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $this->post(route('communities.classroom.courses.certificate', [$community, $course]))
            ->assertRedirect('/login');
    }

    public function test_issue_returns_existing_certificate_on_duplicate(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['module_id' => $module->id]);

        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        LessonCompletion::create([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $existing = Certificate::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('communities.classroom.courses.certificate', [$community, $course]))
            ->assertRedirect(route('certificates.show', $existing->uuid));

        $this->assertDatabaseCount('certificates', 1);
    }
}
