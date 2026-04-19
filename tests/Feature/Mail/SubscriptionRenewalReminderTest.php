<?php

namespace Tests\Feature\Mail;

use App\Mail\SubscriptionRenewalReminder;
use App\Models\Community;
use App\Models\EmailTemplate;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscription(): Subscription
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $community = Community::factory()->create(['name' => 'Dev Community']);

        return Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'expires_at' => now()->addDays(5),
        ]);
    }

    public function test_envelope_subject_interpolates_community_name_from_seeded_template(): void
    {
        $subscription = $this->createSubscription();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew');
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Dev Community', $envelope->subject);
        $this->assertStringContainsString('expires in 5 days', $envelope->subject);
    }

    public function test_envelope_urgent_uses_seeded_template_subject(): void
    {
        $subscription = $this->createSubscription();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew', urgent: true);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Dev Community', $envelope->subject);
        $this->assertStringContainsString('expires tomorrow', $envelope->subject);
    }

    public function test_envelope_falls_back_to_default_non_urgent_subject(): void
    {
        $subscription = $this->createSubscription();
        EmailTemplate::where('key', 'subscription-renewal')->delete();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew');
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Dev Community', $envelope->subject);
        $this->assertStringContainsString('expiring in 5 days', $envelope->subject);
    }

    public function test_envelope_falls_back_to_default_urgent_subject(): void
    {
        $subscription = $this->createSubscription();
        EmailTemplate::where('key', 'subscription-renewal-urgent')->delete();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew', urgent: true);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Last chance', $envelope->subject);
        $this->assertStringContainsString('Dev Community', $envelope->subject);
    }

    public function test_envelope_uses_updated_template_subject(): void
    {
        $subscription = $this->createSubscription();

        EmailTemplate::where('key', 'subscription-renewal')->update([
            'subject' => 'Renew {{community_name}} soon!',
        ]);

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew');
        $envelope = $mailable->envelope();

        $this->assertEquals('Renew Dev Community soon!', $envelope->subject);
    }

    public function test_content_falls_back_to_blade_view_without_template(): void
    {
        $subscription = $this->createSubscription();
        EmailTemplate::where('key', 'subscription-renewal')->delete();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew');
        $content = $mailable->content();

        $this->assertEquals('emails.subscription-renewal', $content->view);
    }

    public function test_content_uses_html_string_from_seeded_template(): void
    {
        $subscription = $this->createSubscription();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew');
        $content = $mailable->content();

        $this->assertNotNull($content->htmlString);
        $this->assertStringContainsString('Alice', $content->htmlString);
        $this->assertStringContainsString('Dev Community', $content->htmlString);
    }

    public function test_content_interpolates_renewal_url(): void
    {
        $subscription = $this->createSubscription();

        EmailTemplate::where('key', 'subscription-renewal')->update([
            'html_body' => '<a href="{{renewal_url}}">Renew</a>',
        ]);

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew');
        $content = $mailable->content();

        $this->assertStringContainsString('https://example.com/renew', $content->htmlString);
    }

    public function test_mailable_holds_subscription_and_renewal_url(): void
    {
        $subscription = $this->createSubscription();
        $renewalUrl = 'https://example.com/renew';

        $mailable = new SubscriptionRenewalReminder($subscription, $renewalUrl);

        $this->assertSame($subscription->id, $mailable->subscription->id);
        $this->assertEquals($renewalUrl, $mailable->renewalUrl);
        $this->assertFalse($mailable->urgent);
    }

    public function test_urgent_flag_is_stored(): void
    {
        $subscription = $this->createSubscription();

        $mailable = new SubscriptionRenewalReminder($subscription, 'https://example.com/renew', urgent: true);

        $this->assertTrue($mailable->urgent);
    }
}
