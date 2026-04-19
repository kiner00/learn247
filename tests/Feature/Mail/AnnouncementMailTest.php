<?php

namespace Tests\Feature\Mail;

use App\Mail\AnnouncementMail;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_mail_has_correct_subject(): void
    {
        $community = Community::factory()->create(['name' => 'Test Community']);
        $sender = User::factory()->create();

        $mail = new AnnouncementMail($community, $sender, 'Big News', 'Hello everyone!');

        $this->assertEquals('[Test Community] Big News', $mail->envelope()->subject);
    }

    public function test_announcement_mail_uses_correct_view(): void
    {
        $community = Community::factory()->create();
        $sender = User::factory()->create();

        $mail = new AnnouncementMail($community, $sender, 'Update', 'Content here');
        $content = $mail->content();

        $this->assertEquals('emails.announcement', $content->view);
    }
}
