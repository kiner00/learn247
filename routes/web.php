<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AffiliateController;
use App\Http\Controllers\Web\BadgeController;
use App\Http\Controllers\Web\CreatorController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PayoutRequestController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CertificateController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\ClassroomController;
use App\Http\Controllers\Web\DirectMessageController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\CommunityController;
use App\Http\Controllers\Web\CommunityInviteController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\CommunityMemberController;
use App\Http\Controllers\Web\AccountSettingsController;
use App\Http\Controllers\Web\LeaderboardController;
use App\Http\Controllers\Web\LessonCommentController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\LikeController;
use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\AIAssistantController;
use App\Http\Controllers\Web\QuizController;
use App\Http\Controllers\Web\CertificationExamController;
use App\Http\Controllers\Web\CheckoutCallbackController;
use App\Http\Controllers\Web\CourseEnrollmentController;
use App\Http\Controllers\Web\GuestCheckoutController;
use App\Http\Controllers\Web\RefController;
use App\Http\Controllers\Web\ForgotPasswordController;
use App\Http\Controllers\Web\SetPasswordController;
use App\Http\Controllers\Web\FreeSubscribeController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Controllers\Web\TelegramWebhookController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/communities'))->name('home');

// ─── Certificates (public shareable link) ─────────────────────────────────────
Route::get('/certificates/{uuid}', [CertificateController::class, 'show'])->name('certificates.show');

// ─── Community invite accept (public — redirects to login if not authed) ──────
Route::get('/invite/{token}', [CommunityInviteController::class, 'accept'])->name('community.invite.accept');

// ─── Affiliate referral link (public) ─────────────────────────────────────────
Route::get('/ref/{code}', [RefController::class, 'redirect'])->name('ref.redirect');

// ─── Guest checkout via affiliate link (public, POST only) ───────────────────
Route::post('/ref-checkout/{code}', [GuestCheckoutController::class, 'process'])->name('ref.checkout.process');

// ─── Guest checkout without affiliate code (public, POST only) ───────────────
Route::post('/communities/{community}/guest-checkout', [GuestCheckoutController::class, 'processNoAffiliate'])->name('communities.guest.checkout');

// ─── Checkout callback: auto-login + processing screen (signed URL) ──────────
Route::get('/checkout-callback/{user}/{community}', CheckoutCallbackController::class)->name('checkout.callback');

// ─── Checkout status poll (auth required) ────────────────────────────────────
Route::get('/checkout-status/{community:slug}', [CheckoutCallbackController::class, 'status'])->middleware('auth')->name('checkout.status');

// ─── Set permanent password (auth required) ───────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/set-password', [SetPasswordController::class, 'show'])->name('password.setup');
    Route::post('/set-password', [SetPasswordController::class, 'store'])->name('password.setup.store');
});

// ─── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ─── Super Admin ──────────────────────────────────────────────────────────────

Route::middleware(['auth', EnsureSuperAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::patch('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    Route::patch('/creator-plan-pricing', [AdminController::class, 'updateCreatorPlanPricing'])->name('admin.creator-plan-pricing.update');
    Route::get('/payouts', [AdminController::class, 'payouts'])->name('admin.payouts');
    Route::post('/payouts/owner/{community:id}', [AdminController::class, 'payOwner'])->name('admin.payouts.owner');
    Route::post('/payouts/owners/batch', [AdminController::class, 'batchPayOwners'])->name('admin.payouts.owners.batch');
    Route::post('/payouts/owners/selected', [AdminController::class, 'paySelectedOwners'])->name('admin.payouts.owners.selected');
    Route::post('/payouts/affiliates/batch', [AdminController::class, 'batchPayAffiliates'])->name('admin.payouts.affiliates.batch');
    Route::post('/payouts/affiliates/selected', [AdminController::class, 'paySelectedAffiliates'])->name('admin.payouts.affiliates.selected');
    Route::post('/payout-requests/{payoutRequest}/approve', [AdminController::class, 'approvePayoutRequest'])->name('admin.payout-requests.approve');
    Route::post('/payout-requests/{payoutRequest}/reject', [AdminController::class, 'rejectPayoutRequest'])->name('admin.payout-requests.reject');
    Route::post('/payout-requests/{payoutRequest}/mark-paid', [AdminController::class, 'markPayoutRequestPaid'])->name('admin.payout-requests.mark-paid');
    Route::post('/onboarding/{user}/resend', [AdminController::class, 'resendOnboardingEmail'])->name('admin.onboarding.resend');
    // Featured communities
    Route::post('/communities/{community}/toggle-featured', [AdminController::class, 'toggleFeatured'])->name('admin.communities.toggle-featured');
    // Analytics
    Route::get('/creator-analytics', [AdminController::class, 'creatorAnalytics'])->name('admin.creator-analytics');
    Route::get('/affiliate-analytics', [AdminController::class, 'affiliateAnalytics'])->name('admin.affiliate-analytics');
    // User management
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::patch('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('admin.users.toggle');
    Route::patch('/users/{user}/toggle-kyc', [AdminController::class, 'toggleKyc'])->name('admin.users.toggle-kyc');
    Route::get('/kyc-reviews', [AdminController::class, 'kycReviews'])->name('admin.kyc-reviews');
    Route::patch('/kyc-reviews/{user}/approve', [AdminController::class, 'approveKyc'])->name('admin.kyc.approve');
    Route::patch('/kyc-reviews/{user}/reject', [AdminController::class, 'rejectKyc'])->name('admin.kyc.reject');
    // Soft delete recovery
    Route::get('/posts/trashed', [AdminController::class, 'trashedPosts'])->name('admin.posts.trashed');
    Route::post('/posts/{postId}/restore', [AdminController::class, 'restorePost'])->name('admin.posts.restore');
    Route::delete('/posts/{postId}/force-delete', [AdminController::class, 'forceDeletePost'])->name('admin.posts.force-delete');
    // Global announcement
    Route::get('/announcements', [AdminController::class, 'globalAnnouncement'])->name('admin.announcements');
    Route::post('/announcements', [AdminController::class, 'sendGlobalAnnouncement'])->name('admin.announcements.send');
    // Email templates
    Route::get('/email-templates', [AdminController::class, 'emailTemplates'])->name('admin.email-templates');
    Route::get('/email-templates/{key}/edit', [AdminController::class, 'editEmailTemplate'])->name('admin.email-templates.edit');
    Route::put('/email-templates/{key}', [AdminController::class, 'updateEmailTemplate'])->name('admin.email-templates.update');
    Route::post('/email-templates/{key}/preview', [AdminController::class, 'previewEmailTemplate'])->name('admin.email-templates.preview');
    // Coupons
    Route::get('/coupons', [AdminController::class, 'coupons'])->name('admin.coupons');
    Route::post('/coupons', [AdminController::class, 'storeCoupon'])->name('admin.coupons.store');
    Route::post('/coupons/{coupon}/toggle', [AdminController::class, 'toggleCoupon'])->name('admin.coupons.toggle');
    Route::delete('/coupons/{coupon}', [AdminController::class, 'deleteCoupon'])->name('admin.coupons.destroy');
});

// ─── User Profile shortlink ─────────────────────────────────────────────────
Route::get('/u/{username}', fn ($username) => redirect("/profile/{$username}"))->name('u.show');

// ─── Notifications ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
});

// ─── Profile ───────────────────────────────────────────────────────────────────

Route::get('/profile', [ProfileController::class, 'me'])->middleware('auth')->name('profile');
Route::get('/profile/{username}', [ProfileController::class, 'show'])->name('profile.show');

// ─── Badges ────────────────────────────────────────────────────────────────────

Route::get('/badges', [BadgeController::class, 'index'])->middleware('auth')->name('badges');

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
    Route::patch('/settings/payout', [AccountSettingsController::class, 'updatePayout'])->name('account.settings.payout');
    Route::patch('/settings/crypto', [AccountSettingsController::class, 'updateCrypto'])->name('account.settings.crypto');
    Route::post('/settings/kyc', [AccountSettingsController::class, 'submitKyc'])->name('account.settings.kyc');
});

// ─── Communities ───────────────────────────────────────────────────────────────

// Public tabs + listing
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');
Route::get('/communities/{community}/about', [CommunityController::class, 'about'])->name('communities.about');
Route::get('/communities/{community}/landing', [CommunityController::class, 'landing'])->name('communities.landing');
Route::get('/communities/{community}/calendar', [EventController::class, 'index'])->name('communities.calendar');

// ─── Classroom (public read) ───────────────────────────────────────────────
Route::get('/communities/{community}/classroom', [ClassroomController::class, 'index'])->name('communities.classroom');
Route::get('/communities/{community}/classroom/courses/{course}', [ClassroomController::class, 'showCourse'])->name('communities.classroom.courses.show');

Route::middleware('auth')->group(function () {
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::post('/communities/{community}/join', [CommunityController::class, 'join'])->name('communities.join');

    // Paid community checkout → redirects to Xendit invoice URL
    Route::post('/communities/{community}/checkout', [SubscriptionController::class, 'checkout'])->name('communities.checkout');

    // Free community subscription (for access to free courses)
    Route::post('/communities/{community}/free-subscribe', [FreeSubscribeController::class, 'store'])->name('communities.free-subscribe');

    // Course one-time purchase (no membership required)
    Route::post('/communities/{community}/classroom/courses/{course}/enroll', [CourseEnrollmentController::class, 'checkout'])->name('communities.classroom.courses.enroll');

    // Owner-only: invite members by email / CSV
    Route::get('/communities/{community}/invites', [CommunityInviteController::class, 'index'])->name('communities.invites.index');
    Route::post('/communities/{community}/invite', [CommunityInviteController::class, 'store'])->name('communities.invite');

    // Events (owner-only mutations; calendar view is public above)
    Route::post('/communities/{community}/events', [EventController::class, 'store'])->name('communities.events.store');
    Route::post('/communities/{community}/events/{event}', [EventController::class, 'update'])->name('communities.events.update');
    Route::delete('/communities/{community}/events/{event}', [EventController::class, 'destroy'])->name('communities.events.destroy');

    // Owner-only mutations
    Route::match(['patch', 'post'], '/communities/{community}', [CommunityController::class, 'update'])->name('communities.update');
    Route::patch('/communities/{community}/level-perks', [CommunityController::class, 'updateLevelPerks'])->name('communities.level-perks');
    Route::post('/communities/{community}/gallery', [CommunityController::class, 'addGalleryImage'])->name('communities.gallery.add');
    Route::delete('/communities/{community}/gallery/{index}', [CommunityController::class, 'removeGalleryImage'])->name('communities.gallery.remove');
    Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');
    Route::post('/communities/{community}/cancel-deletion', [CommunityController::class, 'cancelDeletion'])->name('communities.cancel-deletion');
    Route::post('/communities/{community}/announce', [CommunityController::class, 'announce'])->name('communities.announce');
    Route::post('/communities/{community}/sms-config', [CommunityController::class, 'updateSmsConfig'])->name('communities.sms-config');
    Route::post('/communities/{community}/sms-test', [CommunityController::class, 'testSms'])->name('communities.sms-test');
    Route::post('/communities/{community}/sms-blast', [CommunityController::class, 'sendSmsBlast'])->name('communities.sms-blast');
    Route::post('/communities/{community}/ai-landing', [CommunityController::class, 'generateLandingPage'])->name('communities.ai-landing');
    Route::post('/communities/{community}/ai-landing/section', [CommunityController::class, 'regenerateSection'])->name('communities.ai-landing.section');
    Route::post('/communities/{community}/landing-page/upload-image', [CommunityController::class, 'uploadSectionImage'])->name('communities.landing-page.upload-image');
    Route::post('/communities/{community}/landing-page/upload-video', [CommunityController::class, 'uploadSectionVideo'])->name('communities.landing-page.upload-video');
    Route::patch('/communities/{community}/landing-page', [CommunityController::class, 'updateLandingPage'])->name('communities.landing-page.update');

    // Member management (admin only — enforced by Action)
    Route::delete('/communities/{community}/members/{user}', [CommunityMemberController::class, 'destroy'])->name('communities.members.destroy');
    Route::patch('/communities/{community}/members/{user}/role', [CommunityMemberController::class, 'changeRole'])->name('communities.members.role');
    Route::patch('/communities/{community}/members/{user}/block', [CommunityMemberController::class, 'toggleBlock'])->name('communities.members.block');
    Route::patch('/communities/{community}/members/extend-access', [CommunityMemberController::class, 'extendAccess'])->name('communities.members.extend-access');

    // Gated: active membership required
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/communities/{community}/members', [CommunityController::class, 'members'])->name('communities.members');
        Route::get('/communities/{community}/settings', [CommunityController::class, 'settings'])->name('communities.settings');
        Route::get('/communities/{community}/analytics', [CommunityController::class, 'analytics'])->name('communities.analytics');

        // Posts (community-scoped)
        Route::post('/communities/{community}/posts', [PostController::class, 'store'])->name('posts.store');

        // ─── Classroom (mutations — membership required) ───────────────────────
        Route::post('/communities/{community}/classroom/courses/reorder', [ClassroomController::class, 'reorderCourses'])->name('communities.classroom.courses.reorder');
        Route::post('/communities/{community}/classroom/courses', [ClassroomController::class, 'storeCourse'])->name('communities.classroom.courses.store');
        Route::post('/communities/{community}/classroom/courses/{course}/update', [ClassroomController::class, 'updateCourse'])->name('communities.classroom.courses.update');
        Route::delete('/communities/{community}/classroom/courses/{course}', [ClassroomController::class, 'destroyCourse'])->name('communities.classroom.courses.destroy');
        Route::post('/communities/{community}/classroom/courses/{course}/toggle-publish', [ClassroomController::class, 'togglePublish'])->name('communities.classroom.courses.toggle-publish');
        Route::post('/communities/{community}/classroom/courses/{course}/modules', [ClassroomController::class, 'storeModule'])->name('communities.classroom.modules.store');
        Route::match(['patch', 'post'], '/communities/{community}/classroom/courses/{course}/modules/{module}', [ClassroomController::class, 'updateModule'])->name('communities.classroom.modules.update');
        Route::delete('/communities/{community}/classroom/courses/{course}/modules/{module}', [ClassroomController::class, 'destroyModule'])->name('communities.classroom.modules.destroy');
        Route::post('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons', [ClassroomController::class, 'storeLesson'])->name('communities.classroom.lessons.store');
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/complete', [ClassroomController::class, 'completeLesson'])->name('communities.classroom.lessons.complete');
        Route::post('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/reorder', [ClassroomController::class, 'reorderLessons'])->name('communities.classroom.lessons.reorder');
        Route::match(['patch', 'post'], '/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/{lesson}', [ClassroomController::class, 'updateLesson'])->name('communities.classroom.lessons.update');
        Route::delete('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/{lesson}', [ClassroomController::class, 'destroyLesson'])->name('communities.classroom.lessons.destroy');
        Route::post('/communities/{community}/classroom/lesson-images', [ClassroomController::class, 'uploadLessonImage'])->name('communities.classroom.lesson-images');
        Route::post('/communities/{community}/classroom/lesson-videos', [ClassroomController::class, 'uploadLessonVideo'])->name('communities.classroom.lesson-videos');
        Route::get('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/stream', [ClassroomController::class, 'streamLessonVideo'])->name('communities.classroom.lessons.stream');
        Route::get('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/transcode-status', [ClassroomController::class, 'transcodeStatus'])->name('communities.classroom.lessons.transcode-status');
        Route::get('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/hls/{file}', [ClassroomController::class, 'hlsFile'])->where('file', '.*')->name('communities.classroom.lessons.hls');

        // Lesson comments
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/comments', [LessonCommentController::class, 'store'])->name('lesson.comments.store');
        Route::delete('/lesson-comments/{comment}', [LessonCommentController::class, 'destroy'])->name('lesson.comments.destroy');

        // Quizzes
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'store'])->name('lesson.quiz.store');
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/quiz/{quiz}/submit', [QuizController::class, 'submit'])->name('lesson.quiz.submit');
        Route::delete('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/quiz/{quiz}', [QuizController::class, 'destroy'])->name('lesson.quiz.destroy');

        // Certifications (community-level)
        Route::get('/communities/{community}/certifications', [CertificationExamController::class, 'index'])->name('communities.certifications');
        Route::post('/communities/{community}/certifications', [CertificationExamController::class, 'store'])->name('certification.store');
        Route::post('/communities/{community}/certifications/{certification}', [CertificationExamController::class, 'update'])->name('certification.update');
        Route::post('/communities/{community}/certifications/{certification}/submit', [CertificationExamController::class, 'submit'])->name('certification.submit');
        Route::post('/communities/{community}/certifications/{certification}/checkout', [CertificationExamController::class, 'checkout'])->name('certification.checkout');
        Route::delete('/communities/{community}/certifications/{certification}', [CertificationExamController::class, 'destroy'])->name('certification.destroy');

        // ─── Chat ─────────────────────────────────────────────────────────────
        Route::get('/communities/{community}/chat', [ChatController::class, 'index'])->name('communities.chat');
        Route::post('/communities/{community}/chat', [ChatController::class, 'store'])->name('communities.chat.store');
        Route::get('/communities/{community}/chat/poll', [ChatController::class, 'poll'])->name('communities.chat.poll');
        Route::delete('/communities/{community}/chat/{message}', [ChatController::class, 'destroy'])->name('communities.chat.destroy');

        // ─── Leaderboard ──────────────────────────────────────────────────────
        Route::get('/communities/{community}/leaderboard', [LeaderboardController::class, 'show'])->name('communities.leaderboard');
    });

    // Posts & comments (not community-scoped — community check done in Action)
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/pin', [PostController::class, 'togglePin'])->name('posts.pin');
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
    Route::delete('/direct-messages/{directMessage}', [DirectMessageController::class, 'destroy'])->name('messages.destroy');

    // ─── Creator Dashboard ────────────────────────────────────────────────────
    Route::get('/creator/plan', [CreatorController::class, 'plan'])->name('creator.plan');
    Route::post('/creator/plan/checkout', [CreatorController::class, 'planCheckout'])->name('creator.plan.checkout');
    Route::post('/creator/plan/redeem-coupon', [CreatorController::class, 'redeemCoupon'])->name('creator.plan.redeem-coupon');
    Route::get('/creator/dashboard', [CreatorController::class, 'dashboard'])->name('creator.dashboard');
    Route::post('/creator/payout-request/{community:id}', [PayoutRequestController::class, 'storeOwner'])->name('creator.payout-request.store');

    // ─── Affiliates ───────────────────────────────────────────────────────────
    Route::get('/my-affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::get('/my-affiliates/analytics', [AffiliateController::class, 'analytics'])->name('affiliates.analytics');
    Route::post('/communities/{community}/affiliates', [AffiliateController::class, 'store'])->name('communities.affiliates.join');
    Route::get('/communities/{community}/affiliates', [AffiliateController::class, 'dashboard'])->name('communities.affiliates');
    Route::patch('/affiliate-conversions/{conversion}/paid', [AffiliateController::class, 'markPaid'])->name('affiliate-conversions.paid');
    Route::post('/affiliate-conversions/{conversion}/disburse', [AffiliateController::class, 'disburse'])->name('affiliate-conversions.disburse');
    Route::patch('/affiliates/{affiliate}/payout', [AffiliateController::class, 'updatePayout'])->name('affiliates.payout');
    Route::patch('/affiliates/{affiliate}/pixels', [AffiliateController::class, 'updatePixels'])->name('affiliates.pixels');
    Route::post('/affiliates/{affiliate}/payout-request', [PayoutRequestController::class, 'storeAffiliate'])->name('affiliates.payout-request.store');
    Route::post('/affiliates/payout-request/all', [PayoutRequestController::class, 'storeAffiliateAll'])->name('affiliates.payout-request.all');

    // ─── AI Assistant ─────────────────────────────────────────────────────────
    Route::post('/ai/chat', [AIAssistantController::class, 'chat'])->name('ai.chat');
    Route::post('/ai/greet', [AIAssistantController::class, 'greet'])->name('ai.greet');
});

// ─── Xendit Webhooks (no auth, no CSRF) ────────────────────────────────────
Route::post('/webhooks/xendit/payouts', [XenditWebhookController::class, 'payouts'])->name('webhooks.xendit.payouts');

// ─── Telegram Webhooks (no auth, no CSRF) ──────────────────────────────────
Route::post('/webhooks/telegram/{slug}', TelegramWebhookController::class)->name('webhooks.telegram');
