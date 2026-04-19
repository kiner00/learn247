<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

class CreateTicket
{
    public function __construct(private StorageService $storage) {}

    public function execute(User $user, array $data): Ticket
    {
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'medium',
        ]);

        if (! empty($data['attachments'])) {
            foreach ($data['attachments'] as $file) {
                if ($file instanceof UploadedFile) {
                    $url = $this->storage->upload($file, 'ticket-attachments');
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'file_url' => $url,
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }
        }

        return $ticket;
    }
}
