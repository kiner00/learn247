<?php

namespace Tests\Feature\Console;

use App\Mail\TicketResolvedReminderMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindResolvedTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_for_tickets_resolved_2_to_3_days_ago(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Old resolved ticket',
            'description' => 'Test',
            'type'        => 'bug',
            'status'      => 'resolved',
            'priority'    => 'medium',
        ]);
        // Manually set updated_at to 2.5 days ago
        Ticket::query()->update(['updated_at' => now()->subDays(2)->subHours(12)]);

        $this->artisan('tickets:remind-resolved')
            ->expectsOutputToContain('1 resolved ticket reminder')
            ->assertSuccessful();

        Mail::assertQueued(TicketResolvedReminderMail::class, 1);
    }

    public function test_does_not_send_reminder_for_recently_resolved_tickets(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Recent resolved ticket',
            'description' => 'Test',
            'type'        => 'bug',
            'status'      => 'resolved',
            'priority'    => 'medium',
        ]);
        // Updated just now — too recent for reminder

        $this->artisan('tickets:remind-resolved')
            ->expectsOutputToContain('0 resolved ticket reminder')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_does_not_send_reminder_for_tickets_resolved_more_than_3_days_ago(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Very old resolved ticket',
            'description' => 'Test',
            'type'        => 'bug',
            'status'      => 'resolved',
            'priority'    => 'medium',
        ]);
        Ticket::query()->update(['updated_at' => now()->subDays(5)]);

        $this->artisan('tickets:remind-resolved')
            ->expectsOutputToContain('0 resolved ticket reminder')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_skips_ticket_when_user_is_missing(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Resolved ticket for missing user',
            'description' => 'Test',
            'type'        => 'bug',
            'status'      => 'resolved',
            'priority'    => 'medium',
        ]);
        Ticket::query()->update(['updated_at' => now()->subDays(2)->subHours(12)]);

        // Delete the user so $ticket->user is null → ->user?->email is null → skipped
        $user->forceDelete();

        $this->artisan('tickets:remind-resolved')
            ->expectsOutputToContain('0 resolved ticket reminder')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_does_not_send_reminder_for_non_resolved_tickets(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Ticket::create([
            'user_id'     => $user->id,
            'subject'     => 'Open ticket',
            'description' => 'Test',
            'type'        => 'bug',
            'status'      => 'open',
            'priority'    => 'medium',
        ]);
        Ticket::query()->update(['updated_at' => now()->subDays(2)->subHours(12)]);

        $this->artisan('tickets:remind-resolved')
            ->expectsOutputToContain('0 resolved ticket reminder')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
