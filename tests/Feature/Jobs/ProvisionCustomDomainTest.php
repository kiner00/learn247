<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProvisionCustomDomain;
use App\Services\PloiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ProvisionCustomDomainTest extends TestCase
{
    public function test_job_calls_add_tenant_and_request_certificate(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $ploi = Mockery::mock(PloiService::class);
        $ploi->shouldReceive('addTenant')
            ->once()
            ->with('custom.example.com');
        $ploi->shouldReceive('requestTenantCertificate')
            ->once()
            ->with('custom.example.com');

        $this->app->instance(PloiService::class, $ploi);

        $job = new ProvisionCustomDomain('custom.example.com');
        $job->handle($ploi);
    }

    public function test_job_has_correct_retry_configuration(): void
    {
        $job = new ProvisionCustomDomain('example.com');

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([60, 300, 900, 1800, 3600], $job->backoff());
    }

    public function test_job_stores_domain(): void
    {
        $job = new ProvisionCustomDomain('my-domain.com');

        $this->assertEquals('my-domain.com', $job->domain);
    }

    public function test_failed_method_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Failed to provision custom domain test\.com/'));

        $job = new ProvisionCustomDomain('test.com');
        $job->failed(new \RuntimeException('DNS timeout'));
    }

    public function test_handle_logs_provisioning_messages(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        Log::shouldReceive('info')
            ->once()
            ->with('Provisioning custom domain: logs.example.com');
        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/HTTP reachable for logs\.example\.com/'));
        Log::shouldReceive('info')
            ->once()
            ->with('Custom domain provisioned and SSL active: logs.example.com');

        $ploi = Mockery::mock(PloiService::class);
        $ploi->shouldReceive('addTenant')->once();
        $ploi->shouldReceive('requestTenantCertificate')->once();

        $job = new ProvisionCustomDomain('logs.example.com');
        $job->handle($ploi);
    }
}
