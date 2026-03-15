<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Queries\Badge\GetBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index(Request $request, GetBadges $query): JsonResponse
    {
        $badges = $query->execute($request->user()?->id);

        return response()->json([
            'member_badges'  => $badges['member'],
            'creator_badges' => $badges['creator'],
        ]);
    }
}
