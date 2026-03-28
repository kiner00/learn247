<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Services\Sms\PhilSmsProvider;
use App\Services\Sms\SemaphoreProvider;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\XtremeSmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function makeDispatcher(): SmsDispatcher
    {
        return new SmsDispatcher(
            new SemaphoreProvider(),
            new PhilSmsProvider(),
            new XtremeSmsProvider(),
        );
    }

    public function test_blast_returns_zero_counts_for_empty_numbers(): void
    {
        $community = Community::factory()->create(['sms_provider' => SmsDispatcher::PROVIDER_SEMAPHORE]);

        $result = $this->makeDispatcher()->blast($community, [], 'Hello');

        $this->assertEquals(['sent' => 0, 'failed' => 0, 'errors' => []], $result);
    }

    public function test_blast_throws_for_unknown_provider(): void
    {
        $community = Community::factory()->create(['sms_provider' => 'unknown']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No SMS provider configured.');

        $this->makeDispatcher()->blast($community, ['639170000001'], 'Hello');
    }

    public function test_blast_delegates_to_semaphore_provider(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsDispatcher::PROVIDER_SEMAPHORE,
            'sms_api_key'  => 'sem-key',
        ]);

        $result = $this->makeDispatcher()->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }

    public function test_blast_delegates_to_philsms_provider(): void
    {
        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider' => SmsDispatcher::PROVIDER_PHILSMS,
            'sms_api_key'  => 'phil-key',
        ]);

        $result = $this->makeDispatcher()->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(1, $result['sent']);
    }

    public function test_blast_delegates_to_xtreme_provider(): void
    {
        Http::fake([
            'https://xtreme.example.com/services/send.php' => Http::response(['success' => true], 200),
        ]);

        $community = Community::factory()->create([
            'sms_provider'   => SmsDispatcher::PROVIDER_XTREME,
            'sms_api_key'    => 'xt-key',
            'sms_device_url' => 'https://xtreme.example.com',
        ]);

        $result = $this->makeDispatcher()->blast($community, ['639170000001'], 'Test');

        $this->assertEquals(1, $result['sent']);
    }
}
