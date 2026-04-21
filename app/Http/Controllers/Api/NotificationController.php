<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\MarkAllAsRead;
use App\Actions\Notification\MarkAsRead;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Queries\Notification\GetNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request, GetNotifications $query): AnonymousResourceCollection
    {
        return NotificationResource::collection($query->paginated($request->user()));
    }

    public function read(Request $request, Notification $notification, MarkAsRead $action): JsonResponse
    {
        $action->execute($request->user(), $notification);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request, MarkAllAsRead $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
