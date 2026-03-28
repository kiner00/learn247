<?php

namespace Tests\Feature\Web;

use App\Models\Certificate;
use App\Models\Community;
use App\Models\CourseCertification;
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
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title'        => 'PHP Fundamentals',
            'cert_title'   => 'PHP Certified Developer',
            'pass_score'   => 70,
        ]);
        $cert = Certificate::create([
            'user_id'          => $user->id,
            'certification_id' => $certification->id,
            'issued_at'        => now(),
        ]);

        $this->get(route('certificates.show', $cert->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Certificate/Show')
                ->has('certificate')
                ->where('certificate.uuid', $cert->uuid)
                ->where('certificate.student_name', $user->name)
                ->where('certificate.exam_title', 'PHP Fundamentals')
                ->where('certificate.community_name', $community->name)
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
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title'        => 'Test Exam',
            'cert_title'   => 'Test Certificate',
            'pass_score'   => 70,
        ]);
        $cert = Certificate::create([
            'user_id'          => $user->id,
            'certification_id' => $certification->id,
            'issued_at'        => now(),
        ]);

        $this->assertGuest();
        $this->get(route('certificates.show', $cert->uuid))->assertOk();
    }
}
