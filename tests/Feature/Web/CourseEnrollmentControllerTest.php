<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourseEnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function paidCourse(Community $community, string $accessType = Course::ACCESS_PAID_ONCE): Course
    {
        return Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => $accessType,
            'price'        => 500,
            'position'     => 1,
        ]);
    }

    // ─── checkout ───────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_initiate_enrollment_checkout(): void
    {
        Http::fake([
            '*' => Http::response([
                'id'          => 'inv_enroll_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_enroll_123',
            ]),
        ]);

        $user      = User::factory()->create();
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = $this->paidCourse($community);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/enroll");

        $response->assertRedirect('https://checkout.xendit.co/inv_enroll_123');

        $this->assertDatabaseHas('course_enrollments', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);
    }

    public function test_authenticated_user_can_enroll_in_paid_monthly_course(): void
    {
        Http::fake([
            '*' => Http::response([
                'id'          => 'inv_monthly_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_monthly_123',
            ]),
        ]);

        $user      = User::factory()->create();
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = $this->paidCourse($community, Course::ACCESS_PAID_MONTHLY);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/enroll");

        $response->assertRedirect('https://checkout.xendit.co/inv_monthly_123');
    }

    public function test_guest_cannot_access_enrollment_endpoint(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = $this->paidCourse($community);

        $response = $this->post("/communities/{$community->slug}/classroom/courses/{$course->id}/enroll");

        $response->assertRedirect('/login');
    }

    public function test_enrolling_in_free_course_returns_validation_error(): void
    {
        $user      = User::factory()->create();
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Free Course',
            'access_type'  => Course::ACCESS_FREE,
            'position'     => 1,
        ]);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/enroll");

        $response->assertSessionHasErrors('course');
    }

    public function test_enrolling_when_already_enrolled_returns_validation_error(): void
    {
        $user      = User::factory()->create();
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = $this->paidCourse($community);

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/enroll");

        $response->assertSessionHasErrors('course');
    }
}
