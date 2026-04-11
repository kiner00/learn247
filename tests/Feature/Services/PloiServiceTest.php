<?php

namespace Tests\Feature\Services;

use App\Services\PloiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PloiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ploi.token'     => 'test-ploi-token',
            'services.ploi.server_id' => '111',
            'services.ploi.site_id'   => '222',
        ]);
    }

    public function test_add_tenant_posts_domain(): void
    {
        Http::fake([
            'https://ploi.io/api/servers/111/sites/222/tenants' => Http::response(['data' => ['domain' => 'custom.com']], 200),
        ]);

        $service = new PloiService();
        $response = $service->addTenant('custom.com');

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/tenants')
                && $request['tenants'] === ['custom.com']
                && $request->hasHeader('Authorization', 'Bearer test-ploi-token');
        });
    }

    public function test_remove_tenant_sends_delete(): void
    {
        Http::fake([
            'https://ploi.io/api/servers/111/sites/222/tenants/custom.com' => Http::response([], 200),
        ]);

        $service = new PloiService();
        $response = $service->removeTenant('custom.com');

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/tenants/custom.com');
        });
    }

    public function test_request_tenant_certificate(): void
    {
        Http::fake([
            'https://ploi.io/api/servers/111/sites/222/tenants/custom.com/request-certificate' => Http::response(['data' => []], 200),
        ]);

        $service = new PloiService();
        $response = $service->requestTenantCertificate('custom.com');

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/request-certificate');
        });
    }

    public function test_throws_when_token_not_configured(): void
    {
        config(['services.ploi.token' => null]);

        $service = new PloiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PLOI_API_TOKEN is not configured.');

        $service->addTenant('example.com');
    }

    public function test_add_tenant_throws_on_http_error(): void
    {
        Http::fake([
            'https://ploi.io/api/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = new PloiService();

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $service->addTenant('bad-domain.com');
    }

    public function test_remove_tenant_throws_on_http_error(): void
    {
        Http::fake([
            'https://ploi.io/api/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $service = new PloiService();

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $service->removeTenant('gone-domain.com');
    }
}
