<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\Community;
use App\Models\CourseCertification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_certificate_by_uuid_returns_public_data(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Exam',
            'cert_title' => 'Test Certificate',
            'pass_score' => 70,
        ]);

        $uuid = (string) Str::uuid();
        Certificate::create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
            'uuid' => $uuid,
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
                    'cert_title',
                    'exam_title',
                    'community_name',
                    'community_slug',
                ],
            ]);
    }

    public function test_get_certificate_returns_404_for_invalid_uuid(): void
    {
        $this->getJson('/api/certificates/nonexistent-uuid')
            ->assertNotFound();
    }
}
