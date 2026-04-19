<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SmsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SmsService;
    }

    // ─── blast() routing ─────────────────────────────────────────────────────

    public function test_blast_returns_zero_counts_for_empty_numbers(): void
    {
        $community = Community::factory()->create(['sms_provider' => SmsService::PROVIDER_SEMAPHORE]);

        $result = $this->service->blast($community, [], 'Hello');

        $this->assertEquals(['sent' => 0, 'failed' => 0, 'errors' => []], $result);
    }

    public function test_blast_throws_for_unknown_provider(): void
    {
        $community = Community::factory()->create(['sms_provider' => 'unknown']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No SMS provider configured.');

        $this->service->blast($community, ['639170000001'], 'Hello');
    }

    // ─── Semaphore ───────────────────────────────────────────────────────────

    public function test_semaphore_success(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_SEMAPHORE,
            'sms_api_key' => 'sem-key',
            'sms_sender_name' => 'MySender',
        ]);

        $result = $this->service->blast($community, ['639170000001', '639170000002'], 'Test msg');

        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && $request['apikey'] === 'sem-key'
                && $request['sendername'] === 'MySender'
                && str_contains($request['number'], '639170000001');
        });
    }

    public function test_semaphore_without_sender_name(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_SEMAPHORE,
            'sms_api_key' => 'sem-key',
            'sms_sender_name' => null,
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test msg');

        $this->assertEquals(1, $result['sent']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && ! isset($request->data()['sendername']);
        });
    }

    public function test_semaphore_failure_response(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response('Unauthorized', 401),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_SEMAPHORE,
            'sms_api_key' => 'bad-key',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Semaphore error', $result['errors'][0]);
    }

    public function test_semaphore_chunks_large_batches(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_SEMAPHORE,
            'sms_api_key' => 'sem-key',
        ]);

        // 150 numbers => 2 chunks (100 + 50)
        $numbers = array_map(fn ($i) => '63917'.str_pad($i, 7, '0', STR_PAD_LEFT), range(1, 150));

        $result = $this->service->blast($community, $numbers, 'Test');

        $this->assertEquals(150, $result['sent']);
        Http::assertSentCount(2);
    }

    // ─── PhilSMS ─────────────────────────────────────────────────────────────

    public function test_philsms_success(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_PHILSMS,
            'sms_api_key' => 'phil-token',
            'sms_sender_name' => 'PhilSender',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.philsms.com/api/v3/sms/send'
                && $request->hasHeader('Authorization', 'Bearer phil-token')
                && $request['sender_id'] === 'PhilSender';
        });
    }

    public function test_philsms_failure_response(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(
                ['status' => 'error', 'message' => 'Insufficient balance'],
                200
            ),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_PHILSMS,
            'sms_api_key' => 'phil-token',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('PhilSMS error', $result['errors'][0]);
    }

    public function test_philsms_http_failure(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response('Server Error', 500),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_PHILSMS,
            'sms_api_key' => 'phil-token',
        ]);

        $result = $this->service->blast($community, ['639170000001', '639170000002'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(2, $result['failed']);
    }

    // ─── Xtreme SMS ──────────────────────────────────────────────────────────

    public function test_xtreme_success(): void
    {
        Http::fake([
            'https://my-xtreme.com/services/send.php' => Http::response(['success' => true], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_XTREME,
            'sms_api_key' => 'xt-key',
            'sms_device_url' => 'https://my-xtreme.com',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }

    public function test_xtreme_missing_device_url(): void
    {
        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_XTREME,
            'sms_api_key' => 'xt-key',
            'sms_device_url' => null,
        ]);

        $result = $this->service->blast($community, ['639170000001', '639170000002'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(2, $result['failed']);
        $this->assertStringContainsString('URL not set', $result['errors'][0]);
    }

    public function test_xtreme_api_error_response(): void
    {
        Http::fake([
            'https://my-xtreme.com/services/send.php' => Http::response(
                ['success' => false, 'error' => ['message' => 'Invalid key']],
                200
            ),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_XTREME,
            'sms_api_key' => 'bad-key',
            'sms_device_url' => 'https://my-xtreme.com',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('Invalid key', $result['errors'][0]);
    }

    public function test_xtreme_http_error(): void
    {
        Http::fake([
            'https://my-xtreme.com/services/send.php' => Http::response('Error', 503),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_XTREME,
            'sms_api_key' => 'xt-key',
            'sms_device_url' => 'https://my-xtreme.com',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('HTTP error', $result['errors'][0]);
    }

    // ─── Exception paths ─────────────────────────────────────────────────────

    public function test_semaphore_exception_is_caught(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_SEMAPHORE,
            'sms_api_key' => 'sem-key',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('Connection refused', $result['errors'][0]);
    }

    public function test_philsms_exception_is_caught(): void
    {
        Http::fake(function () {
            throw new \Exception('DNS lookup failed');
        });

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_PHILSMS,
            'sms_api_key' => 'phil-token',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('DNS lookup failed', $result['errors'][0]);
    }

    public function test_philsms_without_sender_name(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_PHILSMS,
            'sms_api_key' => 'phil-token',
            'sms_sender_name' => null,
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(1, $result['sent']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.philsms.com/api/v3/sms/send'
                && ! isset($request->data()['sender_id']);
        });
    }

    public function test_xtreme_exception_is_caught(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout exceeded');
        });

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_XTREME,
            'sms_api_key' => 'xt-key',
            'sms_device_url' => 'https://my-xtreme.com',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('Timeout exceeded', $result['errors'][0]);
    }

    public function test_xtreme_api_error_without_error_message_key(): void
    {
        Http::fake([
            'https://my-xtreme.com/services/send.php' => Http::response(
                ['success' => false],
                200
            ),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsService::PROVIDER_XTREME,
            'sms_api_key' => 'xt-key',
            'sms_device_url' => 'https://my-xtreme.com',
        ]);

        $result = $this->service->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('Xtreme SMS error', $result['errors'][0]);
    }
}
