<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailSend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function signPayload(string $secret, string $body, string $msgId = 'msg_123', ?string $timestamp = null): array
    {
        $timestamp = $timestamp ?? (string) time();
        $toSign    = "{$msgId}.{$timestamp}.{$body}";
        $decoded   = base64_decode(str_replace('whsec_', '', $secret));
        $sig       = base64_encode(hash_hmac('sha256', $toSign, $decoded, true));

        return [
            'svix-id'        => $msgId,
            'svix-timestamp' => $timestamp,
            'svix-signature' => "v1,{$sig}",
        ];
    }

    // ─── Signature verification ────────────────────────────────────────────────

    public function test_invalid_signature_returns_401(): void
    {
        config(['services.resend.webhook_secret' => 'whsec_dGVzdHNlY3JldA==']);

        $this->postJson('/webhooks/resend', ['type' => 'email.delivered', 'data' => []], [
            'svix-id'        => 'msg_1',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,invalidsignature',
        ])->assertUnauthorized();
    }

    public function test_valid_signature_returns_ok(): void
    {
        $secret = 'whsec_dGVzdHNlY3JldA==';
        config(['services.resend.webhook_secret' => $secret]);

        $payload = json_encode(['type' => 'email.delivered', 'data' => []]);
        $headers = $this->signPayload($secret, $payload);

        $this->call('POST', '/webhooks/resend', [], [], [], $this->transformHeaders($headers), $payload)
            ->assertOk();
    }

    // ─── Event handling ────────────────────────────────────────────────────────

    public function test_delivered_event_updates_status(): void
    {
        // Disable signature verification for simpler event tests
        config(['services.resend.webhook_secret' => null]);

        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create(['community_id' => $community->id]);

        $send = EmailSend::create([
            'community_id'        => $community->id,
            'community_member_id' => $member->id,
            'resend_email_id'     => 'resend_abc123',
            'status'              => 'sent',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.delivered',
            'data' => ['email_id' => 'resend_abc123'],
        ])->assertOk();

        $send->refresh();
        $this->assertEquals('delivered', $send->status);
    }

    public function test_opened_event_sets_opened_at(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create(['community_id' => $community->id]);

        $send = EmailSend::create([
            'community_id'        => $community->id,
            'community_member_id' => $member->id,
            'resend_email_id'     => 'resend_open1',
            'status'              => 'delivered',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.opened',
            'data' => ['email_id' => 'resend_open1'],
        ])->assertOk();

        $send->refresh();
        $this->assertNotNull($send->opened_at);
    }

    public function test_clicked_event_sets_clicked_at(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create(['community_id' => $community->id]);

        $send = EmailSend::create([
            'community_id'        => $community->id,
            'community_member_id' => $member->id,
            'resend_email_id'     => 'resend_click1',
            'status'              => 'delivered',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.clicked',
            'data' => ['email_id' => 'resend_click1'],
        ])->assertOk();

        $send->refresh();
        $this->assertNotNull($send->clicked_at);
    }

    public function test_unknown_event_type_returns_ok(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create(['community_id' => $community->id]);

        EmailSend::create([
            'community_id'        => $community->id,
            'community_member_id' => $member->id,
            'resend_email_id'     => 'resend_weird1',
            'status'              => 'sent',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.weird_event',
            'data' => ['email_id' => 'resend_weird1'],
        ])->assertOk();
    }

    public function test_bounced_event_unsubscribes_user(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $send = EmailSend::create([
            'community_id'        => $community->id,
            'community_member_id' => $member->id,
            'resend_email_id'     => 'resend_bounce1',
            'status'              => 'sent',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.bounced',
            'data' => ['email_id' => 'resend_bounce1'],
        ])->assertOk();

        $send->refresh();
        $this->assertEquals('bounced', $send->status);
        $this->assertNotNull($send->bounced_at);

        $this->assertDatabaseHas('email_unsubscribes', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'reason'       => 'bounced',
        ]);
    }

    public function test_complained_event_unsubscribes_user(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $send = EmailSend::create([
            'community_id'        => $community->id,
            'community_member_id' => $member->id,
            'resend_email_id'     => 'resend_complaint1',
            'status'              => 'sent',
        ]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.complained',
            'data' => ['email_id' => 'resend_complaint1'],
        ])->assertOk();

        $this->assertDatabaseHas('email_unsubscribes', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'reason'       => 'complained',
        ]);
    }

    public function test_unknown_email_id_returns_ok(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.delivered',
            'data' => ['email_id' => 'nonexistent_id'],
        ])->assertOk();
    }

    public function test_missing_email_id_returns_ok(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $this->postJson('/webhooks/resend', [
            'type' => 'email.delivered',
            'data' => [],
        ])->assertOk();
    }

    // ─── Helper ─────────────────────────────────────────────────────────────────

    private function transformHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result['HTTP_' . str_replace('-', '_', strtoupper($key))] = $value;
        }

        return $result;
    }
}
