<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AffiliateController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CertificateController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\ClassroomController;
use App\Http\Controllers\Web\DirectMessageController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\CommunityController;
use App\Http\Controllers\Web\CommunityMemberController;
use App\Http\Controllers\Web\AccountSettingsController;
use App\Http\Controllers\Web\LeaderboardController;
use App\Http\Controllers\Web\LessonCommentController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\LikeController;
use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\QuizController;
use App\Http\Controllers\Web\RefController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/communities'))->name('home');

// ─── Certificates (public shareable link) ─────────────────────────────────────
Route::get('/certificates/{uuid}', [CertificateController::class, 'show'])->name('certificates.show');

// ─── Affiliate referral link (public) ─────────────────────────────────────────
Route::get('/ref/{code}', [RefController::class, 'redirect'])->name('ref.redirect');

// ─── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ─── Super Admin ──────────────────────────────────────────────────────────────

Route::middleware(['auth', EnsureSuperAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::patch('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
});

// ─── Profile ───────────────────────────────────────────────────────────────────

Route::get('/profile', [ProfileController::class, 'me'])->middleware('auth')->name('profile');
Route::get('/profile/{username}', [ProfileController::class, 'show'])->name('profile.show');

// ─── Account Settings ──────────────────────────────────────────────────────────

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/settings', [AccountSettingsController::class, 'show'])->name('account.settings');
    Route::match(['patch', 'post'], '/settings/profile', [AccountSettingsController::class, 'updateProfile'])->name('account.settings.profile');
    Route::patch('/settings/profile/visibility/{communityId}', [AccountSettingsController::class, 'updateMembershipVisibility'])->name('account.settings.profile.visibility');
    Route::patch('/settings/email', [AccountSettingsController::class, 'updateEmail'])->name('account.settings.email');
    Route::patch('/settings/password', [AccountSettingsController::class, 'updatePassword'])->name('account.settings.password');
    Route::patch('/settings/timezone', [AccountSettingsController::class, 'updateTimezone'])->name('account.settings.timezone');
    Route::post('/settings/logout-everywhere', [AccountSettingsController::class, 'logoutEverywhere'])->name('account.settings.logout-everywhere');
    Route::patch('/settings/notifications', [AccountSettingsController::class, 'updateNotifications'])->name('account.settings.notifications');
    Route::patch('/settings/notifications/{communityId}', [AccountSettingsController::class, 'updateCommunityNotifications'])->name('account.settings.notifications.community');
    Route::patch('/settings/chat', [AccountSettingsController::class, 'updateChat'])->name('account.settings.chat');
    Route::patch('/settings/chat/{communityId}', [AccountSettingsController::class, 'updateCommunityChat'])->name('account.settings.chat.community');
    Route::patch('/settings/theme', [AccountSettingsController::class, 'updateTheme'])->name('account.settings.theme');
});

// ─── Communities ───────────────────────────────────────────────────────────────

// Public tabs + listing
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');
Route::get('/communities/{community}/about', [CommunityController::class, 'about'])->name('communities.about');

Route::middleware('auth')->group(function () {
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::post('/communities/{community}/join', [CommunityController::class, 'join'])->name('communities.join');

    // Paid community checkout → redirects to Xendit invoice URL
    Route::post('/communities/{community}/checkout', [SubscriptionController::class, 'checkout'])->name('communities.checkout');

    // Owner-only mutations
    Route::match(['patch', 'post'], '/communities/{community}', [CommunityController::class, 'update'])->name('communities.update');
    Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');

    // Member management (admin only — enforced by Action)
    Route::delete('/communities/{community}/members/{user}', [CommunityMemberController::class, 'destroy'])->name('communities.members.destroy');
    Route::patch('/communities/{community}/members/{user}/role', [CommunityMemberController::class, 'changeRole'])->name('communities.members.role');

    // Gated: active membership required
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/communities/{community}/members', [CommunityController::class, 'members'])->name('communities.members');
        Route::get('/communities/{community}/settings', [CommunityController::class, 'settings'])->name('communities.settings');
        Route::get('/communities/{community}/analytics', [CommunityController::class, 'analytics'])->name('communities.analytics');

        // Posts (community-scoped)
        Route::post('/communities/{community}/posts', [PostController::class, 'store'])->name('posts.store');

        // ─── Classroom ────────────────────────────────────────────────────────
        Route::get('/communities/{community}/classroom', [ClassroomController::class, 'index'])->name('communities.classroom');
        Route::post('/communities/{community}/classroom/courses', [ClassroomController::class, 'storeCourse'])->name('communities.classroom.courses.store');
        Route::get('/communities/{community}/classroom/courses/{course}', [ClassroomController::class, 'showCourse'])->name('communities.classroom.courses.show');
        Route::post('/communities/{community}/classroom/courses/{course}/modules', [ClassroomController::class, 'storeModule'])->name('communities.classroom.modules.store');
        Route::post('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons', [ClassroomController::class, 'storeLesson'])->name('communities.classroom.lessons.store');
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/complete', [ClassroomController::class, 'completeLesson'])->name('communities.classroom.lessons.complete');
        Route::match(['patch', 'post'], '/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/{lesson}', [ClassroomController::class, 'updateLesson'])->name('communities.classroom.lessons.update');

        // Lesson comments
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/comments', [LessonCommentController::class, 'store'])->name('lesson.comments.store');
        Route::delete('/lesson-comments/{comment}', [LessonCommentController::class, 'destroy'])->name('lesson.comments.destroy');

        // Quizzes
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'store'])->name('lesson.quiz.store');
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/quiz/{quiz}/submit', [QuizController::class, 'submit'])->name('lesson.quiz.submit');
        Route::delete('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/quiz/{quiz}', [QuizController::class, 'destroy'])->name('lesson.quiz.destroy');

        // Certificates
        Route::post('/communities/{community}/classroom/courses/{course}/certificate', [CertificateController::class, 'issue'])->name('communities.classroom.courses.certificate');

        // ─── Chat ─────────────────────────────────────────────────────────────
        Route::get('/communities/{community}/chat', [ChatController::class, 'index'])->name('communities.chat');
        Route::post('/communities/{community}/chat', [ChatController::class, 'store'])->name('communities.chat.store');
        Route::get('/communities/{community}/chat/poll', [ChatController::class, 'poll'])->name('communities.chat.poll');

        // ─── Leaderboard ──────────────────────────────────────────────────────
        Route::get('/communities/{community}/leaderboard', [LeaderboardController::class, 'show'])->name('communities.leaderboard');
    });

    // Posts & comments (not community-scoped — community check done in Action)
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/like', [LikeController::class, 'togglePost'])->name('posts.like');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/like', [LikeController::class, 'toggleComment'])->name('comments.like');

    // ─── Direct Messages ──────────────────────────────────────────────────────
    Route::get('/messages', [DirectMessageController::class, 'index'])->name('messages.index');
    Route::get('/users/search', [DirectMessageController::class, 'search'])->name('users.search');
    Route::get('/messages/{user:username}', [DirectMessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{user:username}', [DirectMessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{user:username}/poll', [DirectMessageController::class, 'poll'])->name('messages.poll');

    // ─── Affiliates ───────────────────────────────────────────────────────────
    Route::get('/my-affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::post('/communities/{community}/affiliates', [AffiliateController::class, 'store'])->name('communities.affiliates.join');
    Route::get('/communities/{community}/affiliates', [AffiliateController::class, 'dashboard'])->name('communities.affiliates');
    Route::patch('/affiliate-conversions/{conversion}/paid', [AffiliateController::class, 'markPaid'])->name('affiliate-conversions.paid');
});
