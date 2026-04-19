<?php

namespace Tests\Feature\Models;

use App\Models\CartEvent;
use App\Models\Community;
use App\Models\EmailBroadcast;
use App\Models\EmailSend;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // CartEvent
    // =====================================================================

    public function test_cart_event_community_relationship(): void
    {
        $model = new CartEvent;
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_cart_event_user_relationship(): void
    {
        $model = new CartEvent;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_cart_event_fillable(): void
    {
        $model = new CartEvent;
        $expected = [
            'community_id', 'user_id', 'email', 'event_type',
            'reference_type', 'reference_id', 'metadata', 'abandoned_email_sent',
        ];
        $this->assertSame($expected, $model->getFillable());
    }

    public function test_cart_event_casts_metadata_to_array(): void
    {
        $model = new CartEvent;
        $casts = $model->getCasts();
        $this->assertSame('array', $casts['metadata']);
    }

    public function test_cart_event_casts_abandoned_email_sent_to_boolean(): void
    {
        $model = new CartEvent;
        $casts = $model->getCasts();
        $this->assertSame('boolean', $casts['abandoned_email_sent']);
    }

    public function test_cart_event_constants(): void
    {
        $this->assertSame('checkout_started', CartEvent::TYPE_CHECKOUT_STARTED);
        $this->assertSame('payment_completed', CartEvent::TYPE_PAYMENT_COMPLETED);
        $this->assertSame('abandoned', CartEvent::TYPE_ABANDONED);
    }

    public function test_cart_event_create_and_retrieve_with_relationships(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $event = CartEvent::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'email' => 'test@example.com',
            'event_type' => CartEvent::TYPE_CHECKOUT_STARTED,
            'metadata' => ['plan' => 'basic'],
        ]);

        $this->assertSame($community->id, $event->community->id);
        $this->assertSame($user->id, $event->user->id);
        $this->assertSame(['plan' => 'basic'], $event->metadata);
    }

    // =====================================================================
    // EmailBroadcast
    // =====================================================================

    public function test_email_broadcast_community_relationship(): void
    {
        $model = new EmailBroadcast;
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_email_broadcast_campaign_relationship(): void
    {
        $model = new EmailBroadcast;
        $this->assertInstanceOf(BelongsTo::class, $model->campaign());
    }

    public function test_email_broadcast_sends_relationship(): void
    {
        $model = new EmailBroadcast;
        $this->assertInstanceOf(HasMany::class, $model->sends());
    }

    public function test_email_broadcast_fillable(): void
    {
        $model = new EmailBroadcast;
        $fillable = $model->getFillable();
        $this->assertContains('subject', $fillable);
        $this->assertContains('html_body', $fillable);
        $this->assertContains('community_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('filter_tags', $fillable);
        $this->assertContains('filter_exclude_tags', $fillable);
    }

    public function test_email_broadcast_casts_filter_tags_to_array(): void
    {
        $model = new EmailBroadcast;
        $casts = $model->getCasts();
        $this->assertSame('array', $casts['filter_tags']);
        $this->assertSame('array', $casts['filter_exclude_tags']);
    }

    public function test_email_broadcast_casts_dates(): void
    {
        $model = new EmailBroadcast;
        $casts = $model->getCasts();
        $this->assertSame('datetime', $casts['scheduled_at']);
        $this->assertSame('datetime', $casts['sent_at']);
    }

    public function test_email_broadcast_constants(): void
    {
        $this->assertSame('draft', EmailBroadcast::STATUS_DRAFT);
        $this->assertSame('scheduled', EmailBroadcast::STATUS_SCHEDULED);
        $this->assertSame('sending', EmailBroadcast::STATUS_SENDING);
        $this->assertSame('sent', EmailBroadcast::STATUS_SENT);
        $this->assertSame('cancelled', EmailBroadcast::STATUS_CANCELLED);
    }

    // =====================================================================
    // EmailSend
    // =====================================================================

    public function test_email_send_broadcast_relationship(): void
    {
        $model = new EmailSend;
        $this->assertInstanceOf(BelongsTo::class, $model->broadcast());
    }

    public function test_email_send_community_relationship(): void
    {
        $model = new EmailSend;
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_email_send_member_relationship(): void
    {
        $model = new EmailSend;
        $this->assertInstanceOf(BelongsTo::class, $model->member());
    }

    public function test_email_send_fillable(): void
    {
        $model = new EmailSend;
        $fillable = $model->getFillable();
        $this->assertContains('broadcast_id', $fillable);
        $this->assertContains('community_id', $fillable);
        $this->assertContains('community_member_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('opened_at', $fillable);
        $this->assertContains('clicked_at', $fillable);
        $this->assertContains('failed_reason', $fillable);
    }

    public function test_email_send_casts_dates(): void
    {
        $model = new EmailSend;
        $casts = $model->getCasts();
        $this->assertSame('datetime', $casts['opened_at']);
        $this->assertSame('datetime', $casts['clicked_at']);
        $this->assertSame('datetime', $casts['bounced_at']);
    }

    public function test_email_send_constants(): void
    {
        $this->assertSame('queued', EmailSend::STATUS_QUEUED);
        $this->assertSame('sent', EmailSend::STATUS_SENT);
        $this->assertSame('delivered', EmailSend::STATUS_DELIVERED);
        $this->assertSame('bounced', EmailSend::STATUS_BOUNCED);
        $this->assertSame('complained', EmailSend::STATUS_COMPLAINED);
        $this->assertSame('failed', EmailSend::STATUS_FAILED);
    }

    // =====================================================================
    // Ticket
    // =====================================================================

    public function test_ticket_user_relationship(): void
    {
        $model = new Ticket;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_ticket_attachments_relationship(): void
    {
        $model = new Ticket;
        $this->assertInstanceOf(HasMany::class, $model->attachments());
    }

    public function test_ticket_replies_relationship(): void
    {
        $model = new Ticket;
        $this->assertInstanceOf(HasMany::class, $model->replies());
    }

    public function test_ticket_fillable(): void
    {
        $model = new Ticket;
        $expected = ['user_id', 'subject', 'description', 'type', 'status', 'priority'];
        $this->assertSame($expected, $model->getFillable());
    }

    public function test_ticket_uses_soft_deletes(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Test Ticket',
            'description' => 'Testing soft delete',
            'type' => 'bug',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $ticket->delete();

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
        $this->assertNotNull(Ticket::withTrashed()->find($ticket->id));
    }

    public function test_ticket_create_with_relationships(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Help needed',
            'description' => 'Something is broken',
            'type' => 'bug',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $this->assertSame($user->id, $ticket->user->id);
        $this->assertCount(0, $ticket->replies);
        $this->assertCount(0, $ticket->attachments);
    }

    // =====================================================================
    // TicketReply
    // =====================================================================

    public function test_ticket_reply_ticket_relationship(): void
    {
        $model = new TicketReply;
        $this->assertInstanceOf(BelongsTo::class, $model->ticket());
    }

    public function test_ticket_reply_user_relationship(): void
    {
        $model = new TicketReply;
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_ticket_reply_fillable(): void
    {
        $model = new TicketReply;
        $expected = ['ticket_id', 'user_id', 'content', 'is_admin'];
        $this->assertSame($expected, $model->getFillable());
    }

    public function test_ticket_reply_casts_is_admin_to_boolean(): void
    {
        $model = new TicketReply;
        $casts = $model->getCasts();
        $this->assertSame('boolean', $casts['is_admin']);
    }

    public function test_ticket_reply_uses_soft_deletes(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Reply Test',
            'description' => 'For testing replies',
            'type' => 'bug',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'My reply',
            'is_admin' => false,
        ]);

        $reply->delete();

        $this->assertSoftDeleted('ticket_replies', ['id' => $reply->id]);
    }

    public function test_ticket_reply_create_and_retrieve(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Reply Test',
            'description' => 'For testing',
            'type' => 'bug',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'Thanks for helping!',
            'is_admin' => true,
        ]);

        $this->assertTrue($reply->is_admin);
        $this->assertSame($ticket->id, $reply->ticket->id);
        $this->assertSame($user->id, $reply->user->id);
        $this->assertCount(1, $ticket->replies);
    }

    // =====================================================================
    // Tag
    // =====================================================================

    public function test_tag_community_relationship(): void
    {
        $model = new Tag;
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_tag_members_relationship(): void
    {
        $model = new Tag;
        $this->assertInstanceOf(BelongsToMany::class, $model->members());
    }

    public function test_tag_fillable(): void
    {
        $model = new Tag;
        $expected = ['community_id', 'name', 'slug', 'color', 'type', 'auto_rule'];
        $this->assertSame($expected, $model->getFillable());
    }

    public function test_tag_casts_auto_rule_to_array(): void
    {
        $model = new Tag;
        $casts = $model->getCasts();
        $this->assertSame('array', $casts['auto_rule']);
    }

    public function test_tag_constants(): void
    {
        $this->assertSame('manual', Tag::TYPE_MANUAL);
        $this->assertSame('automatic', Tag::TYPE_AUTOMATIC);
    }

    public function test_tag_create_with_community(): void
    {
        $community = Community::factory()->create();
        $tag = Tag::create([
            'community_id' => $community->id,
            'name' => 'VIP',
            'slug' => 'vip',
            'color' => '#FF0000',
            'type' => Tag::TYPE_MANUAL,
        ]);

        $this->assertSame($community->id, $tag->community->id);
        $this->assertSame('VIP', $tag->name);
    }

    public function test_tag_auto_rule_with_array_data(): void
    {
        $community = Community::factory()->create();
        $tag = Tag::create([
            'community_id' => $community->id,
            'name' => 'New Members',
            'slug' => 'new-members',
            'type' => Tag::TYPE_AUTOMATIC,
            'auto_rule' => ['trigger' => 'join', 'days' => 30],
        ]);

        $tag->refresh();
        $this->assertSame(['trigger' => 'join', 'days' => 30], $tag->auto_rule);
    }
}
