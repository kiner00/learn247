<?php

namespace Tests\Feature\Mail;

use App\Mail\TicketStatusChangedMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStatusChangedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Help me',
            'description' => 'Details',
            'type'        => 'bug',
            'status'      => 'in_progress',
            'priority'    => 'medium',
        ]);

        $mail = new TicketStatusChangedMail($ticket, 'open', 'in_progress');
        $envelope = $mail->envelope();

        $this->assertStringContainsString("Ticket #{$ticket->id}", $envelope->subject);
        $this->assertStringContainsString('In progress', $envelope->subject);
    }

    public function test_content_uses_correct_view(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Test',
            'description' => 'Desc',
            'type'        => 'bug',
            'status'      => 'open',
            'priority'    => 'low',
        ]);

        $mail = new TicketStatusChangedMail($ticket, 'open', 'resolved');

        $this->assertEquals('emails.ticket-status-changed', $mail->content()->view);
    }
}
