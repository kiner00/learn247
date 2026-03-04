<?php

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommunityMemberController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

// Xendit webhook — no auth (token-verified internally)
Route::post('/xendit/webhook', XenditWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    // Posts
    Route::post('/posts', [PostController::class, 'store']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    // Comments
    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // Community members
    Route::get('/community-members', [CommunityMemberController::class, 'index']);
    Route::delete('/communities/{community}/members/{user}', [CommunityMemberController::class, 'destroy']);
    Route::patch('/communities/{community}/members/{user}/role', [CommunityMemberController::class, 'changeRole']);

    // Subscriptions
    Route::post('/communities/{community}/checkout', [SubscriptionController::class, 'checkout']);
});
