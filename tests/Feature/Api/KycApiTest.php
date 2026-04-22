<?php

namespace Tests\Feature\Api;

use App\Jobs\VerifyKycDocuments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KycApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── submit ────────────────────────────────────────────────────────────

    public function test_submit_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/kyc/submit', []);

        $response->assertStatus(401);
    }

    public function test_submit_happy_path_returns_kyc_resource_and_dispatches_job(): void
    {
        Storage::fake(config('filesystems.default'));
        Queue::fake();

        $user = User::factory()->create(['kyc_status' => User::KYC_NONE]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/submit', [
            'id_document' => UploadedFile::fake()->image('id.jpg', 400, 300),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 300),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', User::KYC_SUBMITTED)
            ->assertJsonPath('data.verified', false)
            ->assertJsonStructure([
                'data' => [
                    'status', 'verified', 'verified_at', 'submitted_at',
                    'rejected_reason', 'ai_rejections', 'can_request_manual_review',
                ],
            ]);

        $user->refresh();
        $this->assertEquals(User::KYC_SUBMITTED, $user->kyc_status);
        $this->assertNotNull($user->kyc_id_document);
        $this->assertNotNull($user->kyc_selfie);
        $this->assertNotNull($user->kyc_submitted_at);

        Queue::assertPushed(VerifyKycDocuments::class, function ($job) use ($user) {
            return $job->user->is($user);
        });
    }

    public function test_submit_rejects_when_already_submitted(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_SUBMITTED]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/submit', [
            'id_document' => UploadedFile::fake()->image('id.jpg', 400, 300),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 300),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kyc']);
    }

    public function test_submit_rejects_when_already_approved(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_APPROVED]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/submit', [
            'id_document' => UploadedFile::fake()->image('id.jpg', 400, 300),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 400, 300),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kyc']);
    }

    public function test_submit_validates_required_images(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_REJECTED]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/submit', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_document', 'selfie']);
    }

    public function test_submit_validates_files_are_images(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_REJECTED]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/submit', [
            'id_document' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            'selfie' => UploadedFile::fake()->create('text.txt', 100, 'text/plain'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_document', 'selfie']);
    }

    public function test_submit_rejects_oversized_files(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_REJECTED]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/submit', [
            'id_document' => UploadedFile::fake()->image('big.jpg')->size(10241),
            'selfie' => UploadedFile::fake()->image('big2.jpg')->size(10241),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_document', 'selfie']);
    }

    // ─── status ────────────────────────────────────────────────────────────

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/v1/kyc/status')->assertStatus(401);
    }

    public function test_status_returns_defaults_for_user_who_never_submitted(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_NONE]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/kyc/status');

        $response->assertOk()
            ->assertJsonPath('data.status', User::KYC_NONE)
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.ai_rejections', 0)
            ->assertJsonPath('data.can_request_manual_review', false);
    }

    public function test_status_reports_verified_for_approved_user(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_APPROVED,
            'kyc_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/kyc/status');

        $response->assertOk()
            ->assertJsonPath('data.status', User::KYC_APPROVED)
            ->assertJsonPath('data.verified', true);
    }

    public function test_status_flags_can_request_manual_review_after_three_rejections(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_REJECTED,
            'kyc_ai_rejections' => 3,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/kyc/status');

        $response->assertOk()
            ->assertJsonPath('data.can_request_manual_review', true);
    }

    public function test_status_does_not_expose_document_urls(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_id_document' => 'https://example.com/id.jpg',
            'kyc_selfie' => 'https://example.com/selfie.jpg',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/kyc/status');

        $response->assertOk();
        $response->assertJsonMissing(['kyc_id_document' => 'https://example.com/id.jpg']);
        $response->assertJsonMissing(['kyc_selfie' => 'https://example.com/selfie.jpg']);
    }

    // ─── manual-review ─────────────────────────────────────────────────────

    public function test_manual_review_requires_authentication(): void
    {
        $this->postJson('/api/v1/kyc/manual-review')->assertStatus(401);
    }

    public function test_manual_review_happy_path_after_three_rejections(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_REJECTED,
            'kyc_ai_rejections' => 3,
            'kyc_rejected_reason' => 'Blurry image',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/manual-review');

        $response->assertOk()
            ->assertJsonPath('data.status', User::KYC_SUBMITTED);

        $user->refresh();
        $this->assertEquals(User::KYC_SUBMITTED, $user->kyc_status);
        $this->assertNull($user->kyc_rejected_reason);
    }

    public function test_manual_review_denied_under_three_rejections(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_REJECTED,
            'kyc_ai_rejections' => 2,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/manual-review');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kyc']);
    }

    public function test_manual_review_denied_when_already_submitted(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_ai_rejections' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/kyc/manual-review');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kyc']);
    }

    // ─── UserResource kyc_status surfacing ─────────────────────────────────

    public function test_auth_me_exposes_kyc_status(): void
    {
        $user = User::factory()->create([
            'kyc_status' => User::KYC_APPROVED,
            'kyc_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.kyc_status', User::KYC_APPROVED)
            ->assertJsonPath('data.kyc_verified', true);
    }
}
