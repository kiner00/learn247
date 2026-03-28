<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Services\Sms\XtremeSmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XtremeSmsProviderTest extends TestCase
{
    use RefreshDatabase;

    private XtremeSmsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new XtremeSmsProvider();
    }

    public function test_send_success(): void
    {
        Http::fake([
            'https://xtreme.example.com/services/send.php' => Http::response(['success' => true], 200),
        ]);

        $community = Community::factory()->create([
            'sms_api_key'    => 'xt-key',
            'sms_device_url' => 'https://xtreme.example.com',
        ]);

        $result = $this->provider->send($community, ['639170000001', '639170000002'], 'Hello');

        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/services/send.php')
                && $request['key'] === 'xt-key';
        });
    }

    public function test_send_fails_when_device_url_not_set(): void
    {
        $community = Community::factory()->create([
            'sms_api_key'    => 'xt-key',
            'sms_device_url' => null,
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('URL not set', $result['errors'][0]);
    }

    public function test_send_handles_api_error(): void
    {
        Http::fake([
            'https://xtreme.example.com/services/send.php' => Http::response(
                ['success' => false, 'error' => ['message' => 'Invalid API key']],
                200
            ),
        ]);

        $community = Community::factory()->create([
            'sms_api_key'    => 'bad-key',
            'sms_device_url' => 'https://xtreme.example.com',
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('Invalid API key', $result['errors'][0]);
    }

    public function test_send_handles_http_error(): void
    {
        Http::fake([
            'https://xtreme.example.com/services/send.php' => Http::response('Server Down', 503),
        ]);

        $community = Community::factory()->create([
            'sms_api_key'    => 'xt-key',
            'sms_device_url' => 'https://xtreme.example.com',
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('HTTP error', $result['errors'][0]);
    }

    public function test_send_chunks_large_batches(): void
    {
        Http::fake([
            'https://xtreme.example.com/services/send.php' => Http::response(['success' => true], 200),
        ]);

        $community = Community::factory()->create([
            'sms_api_key'    => 'xt-key',
            'sms_device_url' => 'https://xtreme.example.com',
        ]);

        $numbers = array_map(fn ($i) => '63917' . str_pad($i, 7, '0', STR_PAD_LEFT), range(1, 150));

        $result = $this->provider->send($community, $numbers, 'Test');

        $this->assertEquals(150, $result['sent']);
        Http::assertSentCount(2);
    }

    public function test_send_catches_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout exceeded');
        });

        $community = Community::factory()->create([
            'sms_api_key'    => 'xt-key',
            'sms_device_url' => 'https://xtreme.example.com',
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('Timeout exceeded', $result['errors'][0]);
    }
}
