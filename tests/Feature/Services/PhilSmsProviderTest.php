<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Services\Sms\PhilSmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhilSmsProviderTest extends TestCase
{
    use RefreshDatabase;

    private PhilSmsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new PhilSmsProvider();
    }

    public function test_send_success(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $community = Community::factory()->create([
            'sms_api_key'     => 'phil-token',
            'sms_sender_name' => 'TestSender',
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.philsms.com/api/v3/sms/send'
                && $request->hasHeader('Authorization', 'Bearer phil-token')
                && $request['recipient'] === '639170000001'
                && $request['sender_id'] === 'TestSender'
                && $request['type'] === 'plain';
        });
    }

    public function test_send_without_sender_name(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $community = Community::factory()->create([
            'sms_api_key'     => 'phil-token',
            'sms_sender_name' => null,
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(1, $result['sent']);

        Http::assertSent(function ($request) {
            return ! isset($request->data()['sender_id']);
        });
    }

    public function test_send_handles_api_error_status(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(
                ['status' => 'error', 'message' => 'Insufficient credits'],
                200
            ),
        ]);

        $community = Community::factory()->create(['sms_api_key' => 'phil-token']);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('Insufficient credits', $result['errors'][0]);
    }

    public function test_send_handles_http_failure(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response('Internal Error', 500),
        ]);

        $community = Community::factory()->create(['sms_api_key' => 'phil-token']);

        $result = $this->provider->send($community, ['639170000001', '639170000002'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(2, $result['failed']);
    }

    public function test_send_multiple_numbers(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $community = Community::factory()->create(['sms_api_key' => 'phil-token']);
        $numbers = ['639170000001', '639170000002', '639170000003'];

        $result = $this->provider->send($community, $numbers, 'Hello');

        $this->assertEquals(3, $result['sent']);
        Http::assertSentCount(3);
    }

    public function test_send_catches_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('DNS lookup failed');
        });

        $community = Community::factory()->create(['sms_api_key' => 'phil-token']);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('DNS lookup failed', $result['errors'][0]);
    }
}
