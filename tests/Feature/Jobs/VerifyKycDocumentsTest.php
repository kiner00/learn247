<?php

namespace Tests\Feature\Jobs;

use App\Jobs\VerifyKycDocuments;
use App\Models\User;
use App\Services\KycVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class VerifyKycDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_user_when_status_is_submitted(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_SUBMITTED]);

        $service = Mockery::mock(KycVerificationService::class);
        $service->shouldReceive('verifyAndUpdate')
            ->once()
            ->with(Mockery::on(fn ($u) => $u->id === $user->id))
            ->andReturn(['approved' => true, 'reason' => 'Documents match']);

        $this->app->instance(KycVerificationService::class, $service);

        $job = new VerifyKycDocuments($user);
        $job->handle(app(KycVerificationService::class));
    }

    public function test_skips_verification_when_status_is_not_submitted(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_APPROVED]);

        $service = Mockery::mock(KycVerificationService::class);
        $service->shouldNotReceive('verifyAndUpdate');

        $this->app->instance(KycVerificationService::class, $service);

        $job = new VerifyKycDocuments($user);
        $job->handle(app(KycVerificationService::class));
    }

    public function test_skips_when_status_is_none(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_NONE]);

        $service = Mockery::mock(KycVerificationService::class);
        $service->shouldNotReceive('verifyAndUpdate');

        $this->app->instance(KycVerificationService::class, $service);

        $job = new VerifyKycDocuments($user);
        $job->handle(app(KycVerificationService::class));
    }

    public function test_skips_when_status_is_rejected(): void
    {
        $user = User::factory()->create(['kyc_status' => User::KYC_REJECTED]);

        $service = Mockery::mock(KycVerificationService::class);
        $service->shouldNotReceive('verifyAndUpdate');

        $this->app->instance(KycVerificationService::class, $service);

        $job = new VerifyKycDocuments($user);
        $job->handle(app(KycVerificationService::class));
    }

    public function test_logs_when_not_auto_approved(): void
    {
        Log::spy();

        $user = User::factory()->create(['kyc_status' => User::KYC_SUBMITTED]);

        $service = Mockery::mock(KycVerificationService::class);
        $service->shouldReceive('verifyAndUpdate')
            ->once()
            ->andReturn(['approved' => false, 'reason' => 'Face mismatch']);

        $this->app->instance(KycVerificationService::class, $service);

        $job = new VerifyKycDocuments($user);
        $job->handle(app(KycVerificationService::class));

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'not auto-approved')
                && $ctx['user_id'] === $user->id
                && $ctx['reason'] === 'Face mismatch')
            ->once();
    }

    public function test_does_not_log_when_approved(): void
    {
        Log::spy();

        $user = User::factory()->create(['kyc_status' => User::KYC_SUBMITTED]);

        $service = Mockery::mock(KycVerificationService::class);
        $service->shouldReceive('verifyAndUpdate')
            ->once()
            ->andReturn(['approved' => true, 'reason' => 'All good']);

        $this->app->instance(KycVerificationService::class, $service);

        $job = new VerifyKycDocuments($user);
        $job->handle(app(KycVerificationService::class));

        Log::shouldNotHaveReceived('info');
    }

    public function test_job_has_correct_retry_and_timeout(): void
    {
        $user = User::factory()->create();
        $job = new VerifyKycDocuments($user);

        $this->assertEquals(2, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }
}
