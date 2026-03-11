<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function recent(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->with(['actor:id,name,avatar', 'community:id,name,slug'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($n) => [
                'id'             => $n->id,
                'type'           => $n->type,
                'data'           => $n->data,
                'read_at'        => $n->read_at,
                'created_at'     => $n->created_at,
                'actor_name'     => $n->actor?->name,
                'actor_avatar'   => $n->actor?->avatar,
                'community_name' => $n->community?->name,
                'community_slug' => $n->community?->slug,
            ]);

        return response()->json($notifications);
    }
}
