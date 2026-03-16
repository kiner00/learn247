<?php

namespace Tests\Feature\Mail;

use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityInviteMailTest extends TestCase
{
    use RefreshDatabase;

    private function createInvite(array $communityAttrs = []): CommunityInvite
    {
        $community = Community::factory()->create(array_merge(
            ['name' => 'Laravel Masters'],
            $communityAttrs,
        ));

        return CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'invitee@example.com',
            'token'        => 'test-token-abc123',
            'expires_at'   => now()->addDays(7),
        ]);
    }

    public function test_envelope_subject_interpolates_community_name_from_seeded_template(): void
    {
        $invite = $this->createInvite();

        $mailable = new CommunityInviteMail($invite);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Laravel Masters', $envelope->subject);
    }

    public function test_envelope_uses_updated_template_subject(): void
    {
        $invite = $this->createInvite();

        EmailTemplate::where('key', 'community-invite')->update([
            'subject' => 'Join {{community_name}} today!',
        ]);

        $mailable = new CommunityInviteMail($invite);
        $envelope = $mailable->envelope();

        $this->assertEquals('Join Laravel Masters today!', $envelope->subject);
    }

    public function test_envelope_falls_back_to_default_subject_without_template(): void
    {
        $invite = $this->createInvite();
        EmailTemplate::where('key', 'community-invite')->delete();

        $mailable = new CommunityInviteMail($invite);
        $envelope = $mailable->envelope();

        $this->assertEquals("You're invited to join Laravel Masters", $envelope->subject);
    }

    public function test_content_falls_back_to_blade_view_without_template(): void
    {
        $invite = $this->createInvite();
        EmailTemplate::where('key', 'community-invite')->delete();

        $mailable = new CommunityInviteMail($invite);
        $content = $mailable->content();

        $this->assertEquals('emails.community-invite', $content->view);
    }

    public function test_content_uses_html_string_from_seeded_template(): void
    {
        $invite = $this->createInvite();

        $mailable = new CommunityInviteMail($invite);
        $content = $mailable->content();

        $this->assertNotNull($content->htmlString);
        $this->assertStringContainsString('Laravel Masters', $content->htmlString);
    }

    public function test_content_interpolates_invite_url(): void
    {
        $invite = $this->createInvite();

        EmailTemplate::where('key', 'community-invite')->update([
            'html_body' => '<a href="{{invite_url}}">Accept</a>',
        ]);

        $mailable = new CommunityInviteMail($invite);
        $content = $mailable->content();

        $expectedUrl = config('app.url') . '/invite/test-token-abc123';
        $this->assertStringContainsString($expectedUrl, $content->htmlString);
    }

    public function test_mailable_contains_invite_instance(): void
    {
        $invite = $this->createInvite();

        $mailable = new CommunityInviteMail($invite);

        $this->assertSame($invite->id, $mailable->invite->id);
        $this->assertEquals('invitee@example.com', $mailable->invite->email);
    }
}
