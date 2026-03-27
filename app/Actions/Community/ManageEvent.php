<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

class ManageEvent
{
    public function __construct(private StorageService $storage) {}

    public function store(Community $community, User $creator, array $data, ?UploadedFile $coverImage = null): Event
    {
        $url = $coverImage ? $this->storage->upload($coverImage, "events/{$community->id}") : null;

        return $community->events()->create([
            'created_by'  => $creator->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at'    => $data['start_at'],
            'end_at'      => $data['end_at'] ?? null,
            'timezone'    => $data['timezone'],
            'url'         => $data['url'] ?? null,
            'cover_image' => $url,
            'visibility'  => $data['visibility'] ?? 'public',
        ]);
    }

    public function update(Event $event, array $data, ?UploadedFile $coverImage = null): Event
    {
        if ($coverImage) {
            $this->storage->delete($event->cover_image);
            $data['cover_image'] = $this->storage->upload($coverImage, "events/{$event->community_id}");
        }

        $event->update($data);

        return $event;
    }

    public function destroy(Event $event): void
    {
        $this->storage->delete($event->cover_image);
        $event->delete();
    }
}
