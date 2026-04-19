<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Services\Sms\SemaphoreProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemaphoreProviderTest extends TestCase
{
    use RefreshDatabase;

    private SemaphoreProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new SemaphoreProvider;
    }

    public function test_send_success(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create([
            'sms_api_key' => 'sem-key',
            'sms_sender_name' => 'MySender',
        ]);

        $result = $this->provider->send($community, ['639170000001', '639170000002'], 'Hello');

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

    public function test_send_without_sender_name(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create([
            'sms_api_key' => 'sem-key',
            'sms_sender_name' => null,
        ]);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(1, $result['sent']);

        Http::assertSent(function ($request) {
            return ! isset($request->data()['sendername']);
        });
    }

    public function test_send_handles_failure_response(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response('Unauthorized', 401),
        ]);

        $community = Community::factory()->create(['sms_api_key' => 'bad-key']);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('Semaphore error', $result['errors'][0]);
    }

    public function test_send_chunks_large_batches(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create(['sms_api_key' => 'sem-key']);

        $numbers = array_map(fn ($i) => '63917'.str_pad($i, 7, '0', STR_PAD_LEFT), range(1, 250));

        $result = $this->provider->send($community, $numbers, 'Batch test');

        $this->assertEquals(250, $result['sent']);
        Http::assertSentCount(3); // 100 + 100 + 50
    }

    public function test_send_catches_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $community = Community::factory()->create(['sms_api_key' => 'sem-key']);

        $result = $this->provider->send($community, ['639170000001'], 'Hello');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('Connection refused', $result['errors'][0]);
    }
}
