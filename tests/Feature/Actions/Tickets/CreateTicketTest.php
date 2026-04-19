<?php

namespace Tests\Feature\Actions\Tickets;

use App\Actions\Tickets\CreateTicket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class CreateTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_ticket_without_attachments(): void
    {
        $user = User::factory()->create();

        $storage = Mockery::mock(StorageService::class);
        $storage->shouldNotReceive('upload');

        $action = new CreateTicket($storage);
        $ticket = $action->execute($user, [
            'subject' => 'Broken feature',
            'description' => 'It does not work',
            'type' => 'bug',
        ]);

        $this->assertEquals($user->id, $ticket->user_id);
        $this->assertEquals('Broken feature', $ticket->subject);
        $this->assertEquals('medium', $ticket->priority); // default applied
        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_respects_custom_priority(): void
    {
        $user = User::factory()->create();
        $storage = Mockery::mock(StorageService::class);

        $action = new CreateTicket($storage);
        $ticket = $action->execute($user, [
            'subject' => 'Urgent',
            'description' => 'Critical issue',
            'type' => 'bug',
            'priority' => 'high',
        ]);

        $this->assertEquals('high', $ticket->priority);
    }

    public function test_creates_attachments_when_provided(): void
    {
        $user = User::factory()->create();

        $storage = Mockery::mock(StorageService::class);
        $storage->shouldReceive('upload')
            ->twice()
            ->andReturn('https://cdn.example.com/a.png', 'https://cdn.example.com/b.pdf');

        $file1 = UploadedFile::fake()->image('screenshot.png');
        $file2 = UploadedFile::fake()->create('log.pdf', 10, 'application/pdf');

        $action = new CreateTicket($storage);
        $ticket = $action->execute($user, [
            'subject' => 'With attachments',
            'description' => 'See attached',
            'type' => 'bug',
            'attachments' => [$file1, $file2],
        ]);

        $this->assertEquals(2, TicketAttachment::where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'file_url' => 'https://cdn.example.com/a.png',
            'file_name' => 'screenshot.png',
        ]);
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'file_url' => 'https://cdn.example.com/b.pdf',
            'file_name' => 'log.pdf',
        ]);
    }

    public function test_ignores_non_uploaded_file_entries_in_attachments(): void
    {
        $user = User::factory()->create();
        $storage = Mockery::mock(StorageService::class);
        $storage->shouldNotReceive('upload');

        $action = new CreateTicket($storage);
        $ticket = $action->execute($user, [
            'subject' => 'Bad input',
            'description' => 'x',
            'type' => 'bug',
            'attachments' => ['not-a-file', null, 123],
        ]);

        $this->assertEquals(0, TicketAttachment::where('ticket_id', $ticket->id)->count());
    }
}
