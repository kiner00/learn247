<?php

namespace App\Http\Controllers\Web;

use App\Actions\Notification\MarkAllAsRead;
use App\Http\Controllers\Controller;
use App\Queries\Notification\GetNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markAllRead(Request $request, MarkAllAsRead $action): RedirectResponse
    {
        $action->execute($request->user());

        return back();
    }

    public function recent(Request $request, GetNotifications $query): JsonResponse
    {
        return response()->json($query->recent($request->user()));
    }
}
