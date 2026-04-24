<?php

namespace App\Actions\DirectMessage;

use App\Events\DirectMessageSent;
use App\Models\DirectMessage;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

class SendDirectMessage
{
    public function __construct(private StorageService $storage) {}

    public function execute(
        User $sender,
        User $receiver,
        ?string $content,
        ?UploadedFile $image = null,
    ): DirectMessage {
        $content = $content !== null ? trim($content) : null;
        if ($content === '' || $content === null) {
            $content = null;
        }

        if ($content === null && ! $image) {
            throw new \InvalidArgumentException('A message must include content or an image.');
        }

        $imageUrl = $image
            ? $this->storage->upload($image, 'direct-message-attachments/'.$sender->id)
            : null;

        $message = DirectMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => $content,
            'image_url' => $imageUrl,
        ]);

        DirectMessageSent::dispatch($sender->id, $receiver->id, [
            'id' => $message->id,
            'content' => $message->content,
            'image_url' => $message->image_url,
            'is_mine' => false,
            'created_at' => $message->created_at,
        ]);

        return $message;
    }
}
