<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Queries\Creator\GetCreatorDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreatorController extends Controller
{
    public function dashboard(Request $request, GetCreatorDashboard $query): JsonResponse
    {
        $user = $request->user();

        try {
            $data = $query->execute($user);

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Api\CreatorController@dashboard failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['message' => 'Failed to load dashboard data.'], 500);
        }
    }
}
