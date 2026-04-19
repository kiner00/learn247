<?php

namespace Tests\Feature\Models;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscribers_count_only_counts_active_non_expired(): void
    {
        $community = Community::factory()->create();

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'expires_at' => now()->addMonth(),
        ]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'expires_at' => now()->addMonth(),
        ]);
        // Expired — should not count
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'expires_at' => now()->subDay(),
        ]);
        // Cancelled — should not count
        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => Subscription::STATUS_CANCELLED,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertSame(2, $community->activeSubscribersCount());
    }

    public function test_platform_fee_rate_defaults_to_free_tier(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.098, $community->platformFeeRate());
    }

    public function test_resend_api_key_is_encrypted_at_rest_and_decrypted_on_read(): void
    {
        $community = Community::factory()->create(['resend_api_key' => 'super-secret-key']);

        $this->assertSame('super-secret-key', $community->fresh()->resend_api_key);

        // Raw DB value should be encrypted, not the plaintext
        $raw = \DB::table('communities')->where('id', $community->id)->value('resend_api_key');
        $this->assertNotSame('super-secret-key', $raw);
        $this->assertSame('super-secret-key', Crypt::decryptString($raw));
    }

    public function test_resend_api_key_returns_null_for_corrupt_ciphertext(): void
    {
        $community = Community::factory()->create();
        // Write raw plaintext (not encrypted) to simulate legacy / cross-key row
        \DB::table('communities')->where('id', $community->id)->update([
            'resend_api_key' => 'not-a-valid-cipher',
        ]);

        $this->assertNull($community->fresh()->resend_api_key);
    }

    public function test_telegram_bot_token_round_trip(): void
    {
        $community = Community::factory()->create(['telegram_bot_token' => 'tg-token-123']);

        $this->assertSame('tg-token-123', $community->fresh()->telegram_bot_token);
    }

    public function test_resend_api_key_empty_string_stored_as_null(): void
    {
        $community = Community::factory()->create(['resend_api_key' => '']);

        $raw = \DB::table('communities')->where('id', $community->id)->value('resend_api_key');
        $this->assertNull($raw);
    }
}
