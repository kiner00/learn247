<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManageEvent
{
    public function store(Community $community, User $creator, array $data, ?UploadedFile $coverImage = null): Event
    {
        $path = $coverImage ? $coverImage->store("events/{$community->id}", 'public') : null;

        return $community->events()->create([
            'created_by'      => $creator->id,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'start_at'        => $data['start_at'],
            'end_at'          => $data['end_at'] ?? null,
            'timezone'        => $data['timezone'],
            'url'             => $data['url'] ?? null,
            'cover_image'     => $path,
            'is_members_only' => $data['is_members_only'] ?? false,
        ]);
    }

    public function update(Event $event, array $data, ?UploadedFile $coverImage = null): Event
    {
        if ($coverImage) {
            if ($event->cover_image) {
                Storage::disk('public')->delete($event->cover_image);
            }
            $data['cover_image'] = $coverImage->store("events/{$event->community_id}", 'public');
        }

        $event->update($data);

        return $event;
    }

    public function destroy(Event $event): void
    {
        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }
        $event->delete();
    }
}
