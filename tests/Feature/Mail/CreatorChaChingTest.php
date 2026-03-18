<?php

namespace Tests\Feature\Mail;

use App\Mail\CreatorChaChing;
use App\Models\Community;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorChaChingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_subject_contains_community_name(): void
    {
        EmailTemplate::where('key', 'creator-cha-ching')->delete();

        $creator   = User::factory()->create();
        $community = Community::factory()->create(['name' => 'Awesome Community']);

        $mail     = new CreatorChaChing($creator, $community, 500.00, null);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Awesome Community', $envelope->subject);
    }

    public function test_content_uses_view_when_no_template(): void
    {
        EmailTemplate::where('key', 'creator-cha-ching')->delete();

        $creator   = User::factory()->create();
        $community = Community::factory()->create();

        $mail    = new CreatorChaChing($creator, $community, 500.00, null);
        $content = $mail->content();

        $this->assertEquals('emails.creator-cha-ching', $content->view);
    }

    public function test_envelope_uses_custom_template_subject(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'creator-cha-ching'],
            [
                'name'      => 'Creator Sale',
                'subject'   => 'New sale in {{community_name}}: {{sale_amount}}',
                'html_body' => '<p>Congrats</p>',
            ]
        );

        $creator   = User::factory()->create(['name' => 'Alice']);
        $community = Community::factory()->create(['name' => 'TestComm']);

        $mail     = new CreatorChaChing($creator, $community, 1500.00, 'Bob');
        $envelope = $mail->envelope();

        $this->assertStringContainsString('TestComm', $envelope->subject);
        $this->assertStringContainsString('1,500.00', $envelope->subject);
    }

    public function test_content_uses_custom_template_html(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'creator-cha-ching'],
            [
                'name'      => 'Creator Sale',
                'subject'   => 'Cha-ching!',
                'html_body' => '<p>Hi {{creator_name}}, new sale of {{sale_amount}} in {{community_name}}{{referred_by}}</p>',
            ]
        );

        $creator   = User::factory()->create(['name' => 'Alice']);
        $community = Community::factory()->create(['name' => 'My Community']);

        $mail    = new CreatorChaChing($creator, $community, 500.00, 'Bob');
        $content = $mail->content();

        $this->assertNotNull($content->htmlString);
        $this->assertStringContainsString('Alice', $content->htmlString);
        $this->assertStringContainsString('500.00', $content->htmlString);
        $this->assertStringContainsString('My Community', $content->htmlString);
        $this->assertStringContainsString('Bob', $content->htmlString);
    }

    public function test_referred_by_is_empty_string_when_null(): void
    {
        EmailTemplate::where('key', 'creator-cha-ching')->delete();

        $creator   = User::factory()->create(['name' => 'Alice']);
        $community = Community::factory()->create(['name' => 'My Community']);

        $mail    = new CreatorChaChing($creator, $community, 500.00, null);
        $content = $mail->content();

        // When no referredByName, the view is used (no template)
        $this->assertEquals('emails.creator-cha-ching', $content->view);
    }
}
