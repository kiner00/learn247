<?php

use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\CommunityMemberController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

// ─── Webhooks (no auth) ────────────────────────────────────────────────────
Route::post('/xendit/webhook', XenditWebhookController::class);

// ─── Auth (public) ─────────────────────────────────────────────────────────
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// ─── Communities (public read) ─────────────────────────────────────────────
Route::get('/communities',            [CommunityController::class, 'index']);
Route::get('/communities/{community}', [CommunityController::class, 'show']);

// ─── Authenticated routes ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Communities
    Route::post('/communities/{community}/join',        [CommunityController::class, 'join']);
    Route::get('/communities/{community}/leaderboard',  [CommunityController::class, 'leaderboard']);

    // Feed
    Route::get('/communities/{community}/posts', [FeedController::class, 'index']);
    Route::get('/posts/{post}',                  [FeedController::class, 'show']);

    // Posts (create/delete — existing)
    Route::post('/posts',         [PostController::class, 'store']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    // Likes & reactions
    Route::post('/posts/{post}/like',        [LikeController::class, 'togglePost']);
    Route::post('/posts/{post}/pin',         [LikeController::class, 'togglePin']);
    Route::post('/comments/{comment}/like',  [LikeController::class, 'toggleComment']);

    // Comments (existing)
    Route::post('/posts/{post}/comments',    [CommentController::class, 'store']);
    Route::delete('/comments/{comment}',     [CommentController::class, 'destroy']);

    // Classroom
    Route::get('/communities/{community}/courses',                                                      [ClassroomController::class, 'courses']);
    Route::get('/communities/{community}/courses/{course}',                                             [ClassroomController::class, 'course']);
    Route::post('/communities/{community}/courses/{course}/lessons/{lesson}/complete',                  [ClassroomController::class, 'completeLesson']);
    Route::post('/communities/{community}/courses/{course}/lessons/{lesson}/quizzes/{quiz}/submit',     [ClassroomController::class, 'submitQuiz']);

    // Community members (existing)
    Route::get('/community-members',                                        [CommunityMemberController::class, 'index']);
    Route::delete('/communities/{community}/members/{user}',                [CommunityMemberController::class, 'destroy']);
    Route::patch('/communities/{community}/members/{user}/role',            [CommunityMemberController::class, 'changeRole']);

    // Subscriptions (existing)
    Route::post('/communities/{community}/checkout', [SubscriptionController::class, 'checkout']);

    // Notifications
    Route::get('/notifications',           [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

    // Profile
    Route::get('/profile',              [ProfileController::class, 'me']);
    Route::patch('/profile',            [ProfileController::class, 'update']);
    Route::get('/users/{username}',     [ProfileController::class, 'show']);

    // Community chat
    Route::get('/communities/{community}/chat',  [ChatController::class, 'index']);
    Route::post('/communities/{community}/chat', [ChatController::class, 'store']);

    // Direct messages
    Route::get('/messages',             [DirectMessageController::class, 'index']);
    Route::get('/messages/search',      [DirectMessageController::class, 'search']);
    Route::get('/messages/{user}',      [DirectMessageController::class, 'show']);
    Route::post('/messages/{user}',     [DirectMessageController::class, 'store']);

    // Affiliates
    Route::get('/affiliates', [AffiliateController::class, 'index']);
});
