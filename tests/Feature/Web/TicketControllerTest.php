<?php

namespace Tests\Feature\Web;

use App\Mail\TicketResolvedReminderMail;
use App\Mail\TicketStatusChangedMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['is_super_admin' => false]);
    }

    private function createTicket(array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'user_id'     => User::factory()->create()->id,
            'subject'     => 'Test ticket subject',
            'description' => 'Test ticket description',
            'type'        => 'bug',
            'status'      => 'open',
            'priority'    => 'medium',
        ], $attributes));
    }

    // ─── Admin: Index with status filters ────────────────────────────────────

    public function test_admin_can_view_all_tickets(): void
    {
        $admin = $this->superAdmin();

        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'in_progress']);
        $this->createTicket(['status' => 'resolved']);

        $response = $this->actingAs($admin)->get(route('admin.tickets'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tickets')
            ->has('tickets.data', 3)
            ->has('counts')
        );
    }

    public function test_admin_can_filter_tickets_by_status(): void
    {
        $admin = $this->superAdmin();

        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'in_progress']);
        $this->createTicket(['status' => 'resolved']);

        $response = $this->actingAs($admin)->get(route('admin.tickets', ['status' => 'open']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tickets')
            ->has('tickets.data', 2)
            ->where('currentStatus', 'open')
        );
    }

    public function test_admin_index_returns_counts(): void
    {
        $admin = $this->superAdmin();

        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'open']);
        $this->createTicket(['status' => 'in_progress']);
        $this->createTicket(['status' => 'resolved']);
        $this->createTicket(['status' => 'closed']);

        $response = $this->actingAs($admin)->get(route('admin.tickets'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tickets')
            ->where('counts.all', 5)
            ->where('counts.open', 2)
            ->where('counts.in_progress', 1)
            ->where('counts.resolved', 1)
            ->where('counts.closed', 1)
        );
    }

    public function test_non_admin_cannot_access_admin_tickets(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->get(route('admin.tickets'));

        $response->assertForbidden();
    }

    // ─── Admin: Status update with email ─────────────────────────────────────

    public function test_admin_can_update_ticket_status(): void
    {
        $admin  = $this->superAdmin();
        $ticket = $this->createTicket(['status' => 'open']);

        $response = $this->actingAs($admin)
            ->patch(route('admin.tickets.status', $ticket), ['status' => 'in_progress']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_admin_status_update_sends_email_to_user(): void
    {
        Mail::fake();

        $admin  = $this->superAdmin();
        $owner  = User::factory()->create(['email' => 'owner@example.com']);
        $ticket = $this->createTicket(['user_id' => $owner->id, 'status' => 'open']);

        $this->actingAs($admin)
            ->patch(route('admin.tickets.status', $ticket), ['status' => 'resolved']);

        Mail::assertQueued(TicketStatusChangedMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });
    }

    public function test_admin_status_update_does_not_email_when_status_unchanged(): void
    {
        Mail::fake();

        $admin  = $this->superAdmin();
        $ticket = $this->createTicket(['status' => 'open']);

        $this->actingAs($admin)
            ->patch(route('admin.tickets.status', $ticket), ['status' => 'open']);

        Mail::assertNothingQueued();
    }

    // ─── User: Reopen / Close ────────────────────────────────────────────────

    public function test_user_can_reopen_resolved_ticket(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id, 'status' => 'resolved']);

        $response = $this->actingAs($user)
            ->patch(route('tickets.reopen', $ticket));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'open',
        ]);
    }

    public function test_user_cannot_reopen_non_resolved_ticket(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id, 'status' => 'open']);

        $response = $this->actingAs($user)
            ->patch(route('tickets.reopen', $ticket));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'open',
        ]);
    }

    public function test_user_can_close_resolved_ticket(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id, 'status' => 'resolved']);

        $response = $this->actingAs($user)
            ->patch(route('tickets.close', $ticket));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_user_cannot_close_non_resolved_ticket(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)
            ->patch(route('tickets.close', $ticket));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_user_cannot_close_other_users_ticket(): void
    {
        $user      = $this->regularUser();
        $otherUser = $this->regularUser();
        $ticket    = $this->createTicket(['user_id' => $otherUser->id, 'status' => 'resolved']);

        $response = $this->actingAs($user)
            ->patch(route('tickets.close', $ticket));

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'resolved',
        ]);
    }

    public function test_store_ticket_handles_action_exception(): void
    {
        $user = $this->regularUser();

        // Force the CreateTicket action to throw
        $this->mock(\App\Actions\Tickets\CreateTicket::class, function ($mock) {
            $mock->shouldReceive('execute')->andThrow(new \RuntimeException('boom'));
        });

        $response = $this->actingAs($user)->post(route('tickets.store'), [
            'subject'     => 'Something',
            'description' => 'Something broke',
            'type'        => 'bug',
            'priority'    => 'medium',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_reply_handles_action_exception(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id]);

        $this->mock(\App\Actions\Tickets\ReplyToTicket::class, function ($mock) {
            $mock->shouldReceive('execute')->andThrow(new \RuntimeException('boom'));
        });

        $response = $this->actingAs($user)->post(route('tickets.reply', $ticket), [
            'content' => 'Hi there',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_cannot_reopen_other_users_ticket(): void
    {
        $user        = $this->regularUser();
        $otherUser   = $this->regularUser();
        $ticket      = $this->createTicket(['user_id' => $otherUser->id, 'status' => 'resolved']);

        $response = $this->actingAs($user)
            ->patch(route('tickets.reopen', $ticket));

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => 'resolved',
        ]);
    }

    // ─── Mails ───────────────────────────────────────────────────────────────

    public function test_ticket_status_changed_mail_has_correct_subject(): void
    {
        $ticket = $this->createTicket(['status' => 'resolved']);

        $mail = new TicketStatusChangedMail($ticket, 'open', 'resolved');

        $this->assertEquals(
            "Ticket #{$ticket->id} — Status updated to Resolved",
            $mail->envelope()->subject,
        );
    }

    public function test_ticket_resolved_reminder_mail_has_correct_subject(): void
    {
        $ticket = $this->createTicket(['status' => 'resolved']);

        $mail = new TicketResolvedReminderMail($ticket);

        $this->assertEquals(
            "Reminder: Ticket #{$ticket->id} is resolved — please verify and close",
            $mail->envelope()->subject,
        );
    }

    // ─── User: Index / Store / Show / Reply ──────────────────────────────────

    public function test_user_can_view_own_tickets(): void
    {
        $user = $this->regularUser();
        $other = $this->regularUser();

        $this->createTicket(['user_id' => $user->id]);
        $this->createTicket(['user_id' => $user->id]);
        $this->createTicket(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Support/Index')
            ->has('tickets.data', 2)
        );
    }

    public function test_user_can_create_ticket(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->post(route('tickets.store'), [
            'subject'     => 'Something is broken',
            'description' => 'It crashed when I clicked the button',
            'type'        => 'bug',
            'priority'    => 'medium',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'user_id'     => $user->id,
            'subject'     => 'Something is broken',
            'type'        => 'bug',
        ]);
    }

    public function test_store_ticket_validates_required_fields(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->post(route('tickets.store'), []);

        $response->assertSessionHasErrors(['subject', 'description', 'type']);
    }

    public function test_user_can_view_own_ticket(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Support/Show')
            ->where('ticket.id', $ticket->id)
            ->where('isAdmin', false)
        );
    }

    public function test_user_cannot_view_other_users_ticket(): void
    {
        $user   = $this->regularUser();
        $other  = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_admin_can_view_any_ticket(): void
    {
        $admin  = $this->superAdmin();
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Support/Show')
            ->where('isAdmin', true)
        );
    }

    public function test_user_can_reply_to_own_ticket(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('tickets.reply', $ticket), [
            'content' => 'Thanks for your help!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'content'   => 'Thanks for your help!',
            'is_admin'  => false,
        ]);
    }

    public function test_admin_reply_is_marked_as_admin(): void
    {
        $admin  = $this->superAdmin();
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id]);

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), [
            'content' => 'We are looking into it.',
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id'   => $admin->id,
            'is_admin'  => true,
        ]);
    }

    public function test_user_cannot_reply_to_other_users_ticket(): void
    {
        $user   = $this->regularUser();
        $other  = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $other->id]);

        $response = $this->actingAs($user)->post(route('tickets.reply', $ticket), [
            'content' => 'Not my ticket',
        ]);

        $response->assertForbidden();
    }

    public function test_reply_validates_content(): void
    {
        $user   = $this->regularUser();
        $ticket = $this->createTicket(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('tickets.reply', $ticket), []);

        $response->assertSessionHasErrors(['content']);
    }
}
