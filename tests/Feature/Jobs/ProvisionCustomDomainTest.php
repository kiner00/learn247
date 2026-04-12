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

    public function test_http_unreachable_throws_and_aborts_before_requesting_ssl(): void
    {
        // Fake so that HTTP calls throw a connection exception (e.g. nginx not serving domain).
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $ploi = Mockery::mock(PloiService::class);
        $ploi->shouldReceive('addTenant')->once()->with('bad.example.com');
        // Should NOT reach requestTenantCertificate when HTTP is unreachable.
        $ploi->shouldNotReceive('requestTenantCertificate');

        $this->app->instance(PloiService::class, $ploi);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP not reachable for bad.example.com');

        $job = new ProvisionCustomDomain('bad.example.com');
        $job->handle($ploi);
    }

    public function test_ssl_verification_failure_is_warned_but_does_not_throw(): void
    {
        // HTTP reachable check succeeds, but https:// call throws.
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_starts_with($request->url(), 'https://')) {
                throw new \Illuminate\Http\Client\ConnectionException('SSL handshake failed');
            }
            return Http::response('ok', 200);
        });

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/SSL verification failed for ssl\.example\.com/'));

        $ploi = Mockery::mock(PloiService::class);
        $ploi->shouldReceive('addTenant')->once();
        $ploi->shouldReceive('requestTenantCertificate')->once();

        // Should complete without throwing — SSL failure is a soft warning.
        $job = new ProvisionCustomDomain('ssl.example.com');
        $job->handle($ploi);

        $this->assertTrue(true);
    }
}
