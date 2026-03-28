<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RemoveCustomDomain;
use App\Services\PloiService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RemoveCustomDomainTest extends TestCase
{
    public function test_job_calls_remove_tenant(): void
    {
        $ploi = Mockery::mock(PloiService::class);
        $ploi->shouldReceive('removeTenant')
            ->once()
            ->with('old.example.com');

        $job = new RemoveCustomDomain('old.example.com');
        $job->handle($ploi);
    }

    public function test_job_has_correct_retry_configuration(): void
    {
        $job = new RemoveCustomDomain('example.com');

        $this->assertEquals(3, $job->tries);
    }

    public function test_job_stores_domain(): void
    {
        $job = new RemoveCustomDomain('my-domain.com');

        $this->assertEquals('my-domain.com', $job->domain);
    }

    public function test_failed_method_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Failed to remove custom domain remove\.com/'));

        $job = new RemoveCustomDomain('remove.com');
        $job->failed(new \RuntimeException('API error'));
    }

    public function test_handle_logs_removal_messages(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Removing custom domain tenant: logs.example.com');
        Log::shouldReceive('info')
            ->once()
            ->with('Custom domain removed: logs.example.com');

        $ploi = Mockery::mock(PloiService::class);
        $ploi->shouldReceive('removeTenant')->once();

        $job = new RemoveCustomDomain('logs.example.com');
        $job->handle($ploi);
    }
}
