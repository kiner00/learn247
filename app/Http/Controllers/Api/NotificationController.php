<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\MarkAllAsRead;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
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

    public function readAll(Request $request, MarkAllAsRead $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
