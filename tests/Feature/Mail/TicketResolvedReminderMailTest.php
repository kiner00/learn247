<?php

namespace Tests\Feature\Mail;

use App\Mail\TicketResolvedReminderMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketResolvedReminderMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Issue',
            'description' => 'Details',
            'type' => 'bug',
            'status' => 'resolved',
            'priority' => 'high',
        ]);

        $mail = new TicketResolvedReminderMail($ticket);
        $envelope = $mail->envelope();

        $this->assertStringContainsString("Ticket #{$ticket->id}", $envelope->subject);
        $this->assertStringContainsString('resolved', $envelope->subject);
        $this->assertStringContainsString('verify and close', $envelope->subject);
    }

    public function test_content_uses_correct_view(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Test',
            'description' => 'Desc',
            'type' => 'question',
            'status' => 'resolved',
            'priority' => 'low',
        ]);

        $mail = new TicketResolvedReminderMail($ticket);

        $this->assertEquals('emails.ticket-resolved-reminder', $mail->content()->view);
    }
}
