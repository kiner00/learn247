<?php

namespace Tests\Feature\Mail;

use App\Mail\AffiliateChaChing;
use App\Models\Community;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateChaChingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_subject_contains_community_name(): void
    {
        $affiliate = User::factory()->create();
        $community = Community::factory()->create(['name' => 'Awesome Community']);

        $mail     = new AffiliateChaChing($affiliate, $community, 500.00, 50.00);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Awesome Community', $envelope->subject);
    }

    public function test_content_uses_view_when_no_template(): void
    {
        EmailTemplate::where('key', 'affiliate-cha-ching')->delete();

        $affiliate = User::factory()->create();
        $community = Community::factory()->create();

        $mail    = new AffiliateChaChing($affiliate, $community, 500.00, 50.00);
        $content = $mail->content();

        $this->assertEquals('emails.affiliate-cha-ching', $content->view);
    }

    public function test_envelope_uses_custom_template_subject(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'affiliate-cha-ching'],
            [
                'name'      => 'Affiliate Sale',
                'subject'   => 'You earned {{commission_amount}} from {{community_name}}',
                'html_body' => '<p>Congrats</p>',
            ]
        );

        $affiliate = User::factory()->create(['name' => 'John']);
        $community = Community::factory()->create(['name' => 'TestComm']);

        $mail     = new AffiliateChaChing($affiliate, $community, 500.00, 50.00);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('TestComm', $envelope->subject);
        $this->assertStringContainsString('50.00', $envelope->subject);
    }

    public function test_content_uses_custom_template_html(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'affiliate-cha-ching'],
            [
                'name'      => 'Affiliate Sale',
                'subject'   => 'Cha-ching!',
                'html_body' => '<p>Hi {{affiliate_name}}, you earned {{commission_amount}} from {{community_name}}</p>',
            ]
        );

        $affiliate = User::factory()->create(['name' => 'Jane']);
        $community = Community::factory()->create(['name' => 'My Community']);

        $mail    = new AffiliateChaChing($affiliate, $community, 1000.00, 100.00);
        $content = $mail->content();

        $this->assertNotNull($content->htmlString);
        $this->assertStringContainsString('Jane', $content->htmlString);
        $this->assertStringContainsString('100.00', $content->htmlString);
        $this->assertStringContainsString('My Community', $content->htmlString);
    }
}
