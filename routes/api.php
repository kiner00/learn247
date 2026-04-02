<?php

use App\Http\Controllers\Api\AccountSettingsController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\AIAssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\CommunityMemberController;
use App\Http\Controllers\Api\CreatorController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LessonCommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PayoutRequestController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

// ─── Webhooks (no auth) ────────────────────────────────────────────────────
Route::post('/xendit/webhook', XenditWebhookController::class)->middleware('throttle:60,1');

// ─── Auth (public) ─────────────────────────────────────────────────────────
Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

// ─── Communities (public read) ─────────────────────────────────────────────
Route::get('/communities',                       [CommunityController::class, 'index']);
Route::get('/communities/{community}',           [CommunityController::class, 'show']);
Route::get('/communities/{community}/about',     [CommunityController::class, 'about']);
Route::get('/communities/{community}/events',    [EventController::class, 'index']);

// ─── Certificates (public) ─────────────────────────────────────────────────
Route::get('/certificates/{uuid}', [CertificateController::class, 'show']);

// ─── Authenticated routes ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // ─── Communities ───────────────────────────────────────────────────────
    Route::post('/communities',                                    [CommunityController::class, 'store']);
    Route::match(['patch', 'post'], '/communities/{community}',   [CommunityController::class, 'update']);
    Route::delete('/communities/{community}',                      [CommunityController::class, 'destroy']);
    Route::post('/communities/{community}/join',                   [CommunityController::class, 'join']);
    Route::get('/communities/{community}/members',                 [CommunityController::class, 'members']);
    Route::get('/communities/{community}/settings',                [CommunityController::class, 'settings']);
    Route::get('/communities/{community}/analytics',               [CommunityController::class, 'analytics']);
    Route::get('/communities/{community}/leaderboard',             [CommunityController::class, 'leaderboard']);
    Route::post('/communities/{community}/announce',               [CommunityController::class, 'announce']);
    Route::post('/communities/{community}/gallery',                [CommunityController::class, 'addGalleryImage']);
    Route::delete('/communities/{community}/gallery/{index}',      [CommunityController::class, 'removeGalleryImage']);
    Route::patch('/communities/{community}/level-perks',           [CommunityController::class, 'updateLevelPerks']);

    // ─── Events ────────────────────────────────────────────────────────────
    Route::post('/communities/{community}/events',                 [EventController::class, 'store']);
    Route::post('/communities/{community}/events/{event}',         [EventController::class, 'update']);
    Route::delete('/communities/{community}/events/{event}',       [EventController::class, 'destroy']);

    // ─── Feed ──────────────────────────────────────────────────────────────
    Route::get('/communities/{community}/posts', [FeedController::class, 'index']);
    Route::get('/posts/{post}',                  [FeedController::class, 'show']);

    // ─── Posts ─────────────────────────────────────────────────────────────
    Route::post('/posts',          [PostController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    // ─── Likes & reactions ─────────────────────────────────────────────────
    Route::post('/posts/{post}/like',       [LikeController::class, 'togglePost']);
    Route::post('/posts/{post}/pin',        [LikeController::class, 'togglePin']);
    Route::post('/comments/{comment}/like', [LikeController::class, 'toggleComment']);

    // ─── Comments ──────────────────────────────────────────────────────────
    Route::post('/posts/{post}/comments',    [CommentController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('/comments/{comment}',     [CommentController::class, 'destroy']);

    // ─── Classroom ─────────────────────────────────────────────────────────
    Route::get('/communities/{community}/courses',                                                       [ClassroomController::class, 'courses']);
    Route::get('/communities/{community}/courses/{course}',                                              [ClassroomController::class, 'course']);
    Route::post('/communities/{community}/courses',                                                      [ClassroomController::class, 'storeCourse']);
    Route::post('/communities/{community}/courses/{course}/update',                                      [ClassroomController::class, 'updateCourse']);
    Route::delete('/communities/{community}/courses/{course}',                                            [ClassroomController::class, 'destroyCourse']);
    Route::post('/communities/{community}/courses/{course}/modules',                                     [ClassroomController::class, 'storeModule']);
    Route::match(['patch', 'post'], '/communities/{community}/courses/{course}/modules/{module}',        [ClassroomController::class, 'updateModule']);
    Route::post('/communities/{community}/courses/{course}/modules/{module}/lessons',                    [ClassroomController::class, 'storeLesson']);
    Route::post('/communities/{community}/courses/{course}/modules/{module}/lessons/reorder',            [ClassroomController::class, 'reorderLessons']);
    Route::match(['patch', 'post'], '/communities/{community}/courses/{course}/modules/{module}/lessons/{lesson}', [ClassroomController::class, 'updateLesson']);
    Route::post('/communities/{community}/lesson-images',                                                [ClassroomController::class, 'uploadLessonImage']);
    Route::post('/communities/{community}/courses/{course}/lessons/{lesson}/complete',                   [ClassroomController::class, 'completeLesson']);
    Route::post('/communities/{community}/courses/{course}/lessons/{lesson}/quizzes/{quiz}/submit',      [ClassroomController::class, 'submitQuiz']);

    // ─── Lesson comments ───────────────────────────────────────────────────
    Route::post('/communities/{community}/courses/{course}/lessons/{lesson}/comments', [LessonCommentController::class, 'store']);
    Route::delete('/lesson-comments/{comment}',                                        [LessonCommentController::class, 'destroy']);

    // ─── Quizzes ───────────────────────────────────────────────────────────
    Route::post('/communities/{community}/courses/{course}/lessons/{lesson}/quiz',           [QuizController::class, 'store']);
    Route::delete('/communities/{community}/courses/{course}/lessons/{lesson}/quiz/{quiz}',  [QuizController::class, 'destroy']);

    // ─── Community members ─────────────────────────────────────────────────
    Route::get('/community-members',                                     [CommunityMemberController::class, 'index']);
    Route::delete('/communities/{community}/members/{user}',             [CommunityMemberController::class, 'destroy']);
    Route::patch('/communities/{community}/members/{user}/role',         [CommunityMemberController::class, 'changeRole']);

    // ─── Subscriptions ─────────────────────────────────────────────────────
    Route::post('/communities/{community}/checkout', [SubscriptionController::class, 'checkout']);

    // ─── Notifications ─────────────────────────────────────────────────────
    Route::get('/notifications',           [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

    // ─── Profile ───────────────────────────────────────────────────────────
    Route::get('/profile',          [ProfileController::class, 'me']);
    Route::patch('/profile',        [ProfileController::class, 'update']);
    Route::get('/users/{username}', [ProfileController::class, 'show']);

    // ─── Badges ────────────────────────────────────────────────────────────
    Route::get('/badges', [BadgeController::class, 'index']);

    // ─── Account Settings ──────────────────────────────────────────────────
    Route::prefix('account/settings')->group(function () {
        Route::get('/',                           [AccountSettingsController::class, 'show']);
        Route::match(['patch', 'post'], '/profile', [AccountSettingsController::class, 'updateProfile']);
        Route::patch('/profile/visibility/{communityId}', [AccountSettingsController::class, 'updateMembershipVisibility']);
        Route::patch('/email',                    [AccountSettingsController::class, 'updateEmail']);
        Route::patch('/password',                 [AccountSettingsController::class, 'updatePassword']);
        Route::patch('/timezone',                 [AccountSettingsController::class, 'updateTimezone']);
        Route::post('/logout-everywhere',         [AccountSettingsController::class, 'logoutEverywhere']);
        Route::patch('/notifications',            [AccountSettingsController::class, 'updateNotifications']);
        Route::patch('/notifications/{communityId}', [AccountSettingsController::class, 'updateCommunityNotifications']);
        Route::patch('/chat',                     [AccountSettingsController::class, 'updateChat']);
        Route::patch('/chat/{communityId}',       [AccountSettingsController::class, 'updateCommunityChat']);
        Route::patch('/theme',                    [AccountSettingsController::class, 'updateTheme']);
        Route::patch('/payout',                   [AccountSettingsController::class, 'updatePayout']);
        Route::patch('/crypto',                   [AccountSettingsController::class, 'updateCrypto']);
    });

    // ─── Community chat ────────────────────────────────────────────────────
    Route::get('/communities/{community}/chat',              [ChatController::class, 'index']);
    Route::post('/communities/{community}/chat',             [ChatController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/communities/{community}/chat/poll',         [ChatController::class, 'poll']);
    Route::delete('/communities/{community}/chat/{message}', [ChatController::class, 'destroy']);
    Route::post('/communities/{community}/chatbot',          [\App\Http\Controllers\Web\CommunityChatbotController::class, 'chat'])->middleware('throttle:20,1');

    // ─── Direct messages ───────────────────────────────────────────────────
    Route::get('/messages',                      [DirectMessageController::class, 'index']);
    Route::get('/messages/search',               [DirectMessageController::class, 'search']);
    Route::get('/messages/{user}',               [DirectMessageController::class, 'show']);
    Route::post('/messages/{user}',              [DirectMessageController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/messages/{user}/poll',           [DirectMessageController::class, 'poll']);
    Route::delete('/direct-messages/{directMessage}', [DirectMessageController::class, 'destroy']);

    // ─── Affiliates ────────────────────────────────────────────────────────
    Route::get('/affiliates',                           [AffiliateController::class, 'index']);
    Route::post('/communities/{community}/affiliates',  [AffiliateController::class, 'store']);
    Route::patch('/affiliates/{affiliate}/payout',      [AffiliateController::class, 'updatePayout']);

    // ─── Creator Dashboard ─────────────────────────────────────────────────
    Route::get('/creator/dashboard', [CreatorController::class, 'dashboard']);

    // ─── Payout Requests ───────────────────────────────────────────────────
    Route::post('/creator/payout-request/{community:id}',      [PayoutRequestController::class, 'storeOwner']);
    Route::post('/affiliates/{affiliate}/payout-request',      [PayoutRequestController::class, 'storeAffiliate']);
    Route::post('/affiliates/payout-request/all',              [PayoutRequestController::class, 'storeAffiliateAll']);

    // ─── AI Assistant ──────────────────────────────────────────────────────
    Route::post('/ai/chat',  [AIAssistantController::class, 'chat'])->middleware('throttle:15,1');
    Route::post('/ai/greet', [AIAssistantController::class, 'greet'])->middleware('throttle:15,1');
});
