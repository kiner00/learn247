<?php

use App\Http\Controllers\Web\AccountSettingsController;
use App\Http\Controllers\Web\Admin\AiUsageController as AdminAiUsageController;
use App\Http\Controllers\Web\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Web\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\EmailTemplateController as AdminEmailTemplateController;
use App\Http\Controllers\Web\Admin\KycController as AdminKycController;
use App\Http\Controllers\Web\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Web\Admin\PostModerationController as AdminPostModerationController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\AffiliateController;
use App\Http\Controllers\Web\AIAssistantController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BadgeController;
use App\Http\Controllers\Web\CertificateController;
use App\Http\Controllers\Web\CertificationExamController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\CheckoutCallbackController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\CommunityChatbotController;
use App\Http\Controllers\Web\CommunityController;
use App\Http\Controllers\Web\CommunityDmController;
use App\Http\Controllers\Web\CommunityGalleryController;
use App\Http\Controllers\Web\CommunityInviteController;
use App\Http\Controllers\Web\CommunityMemberController;
use App\Http\Controllers\Web\CommunitySettingsController;
use App\Http\Controllers\Web\CourseController;
use App\Http\Controllers\Web\CourseEnrollmentController;
use App\Http\Controllers\Web\CourseLessonController;
use App\Http\Controllers\Web\CourseModuleController;
use App\Http\Controllers\Web\CreatorController;
use App\Http\Controllers\Web\CurzzoChatController;
use App\Http\Controllers\Web\CurzzoCheckoutController;
use App\Http\Controllers\Web\CurzzoController;
use App\Http\Controllers\Web\CurzzoTopupController;
use App\Http\Controllers\Web\DirectMessageController;
use App\Http\Controllers\Web\EmailAnalyticsController;
use App\Http\Controllers\Web\EmailCampaignController;
use App\Http\Controllers\Web\EmailHistoryController;
use App\Http\Controllers\Web\EmailProviderController;
use App\Http\Controllers\Web\EmailSequenceController;
use App\Http\Controllers\Web\EmailUnsubscribeController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\ForgotPasswordController;
use App\Http\Controllers\Web\FreeSubscribeController;
use App\Http\Controllers\Web\GuestCheckoutController;
use App\Http\Controllers\Web\LandingPageController;
use App\Http\Controllers\Web\LeaderboardController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\LessonCommentController;
use App\Http\Controllers\Web\LessonVideoController;
use App\Http\Controllers\Web\LikeController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PayoutRequestController;
use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\QuizController;
use App\Http\Controllers\Web\RecurringCancellationController;
use App\Http\Controllers\Web\RefController;
use App\Http\Controllers\Web\ResendWebhookController;
use App\Http\Controllers\Web\SetPasswordController;
use App\Http\Controllers\Web\SmsSettingsController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Controllers\Web\TagController;
use App\Http\Controllers\Web\TelegramWebhookController;
use App\Http\Controllers\Web\TicketController;
use App\Http\Controllers\Web\WorkflowController;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\Community;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/communities'))->name('home');

// ─── Certificates (public shareable link) ─────────────────────────────────────
Route::get('/certificates/{uuid}', [CertificateController::class, 'show'])->name('certificates.show');

// ─── Community invite accept (public — redirects to login if not authed) ──────
Route::get('/invite/{token}', [CommunityInviteController::class, 'accept'])->name('community.invite.accept');

// ─── Affiliate referral link (public) ─────────────────────────────────────────
Route::get('/ref/{code}', [RefController::class, 'redirect'])->name('ref.redirect');

// ─── Guest checkout via affiliate link (public, POST only) ───────────────────
Route::post('/ref-checkout/{code}', [GuestCheckoutController::class, 'process'])->name('ref.checkout.process')->middleware('throttle:10,1');

// ─── Guest checkout without affiliate code (public, POST only) ───────────────
Route::post('/communities/{community}/guest-checkout', [GuestCheckoutController::class, 'processNoAffiliate'])->name('communities.guest.checkout')->middleware('throttle:10,1');

// ─── Checkout callback: auto-login + processing screen (signed URL) ──────────
Route::get('/checkout-callback/{user}/{community}', CheckoutCallbackController::class)->name('checkout.callback');

// ─── Checkout status poll (auth required) ────────────────────────────────────
Route::get('/checkout-status/{community:slug}', [CheckoutCallbackController::class, 'status'])->middleware('auth')->name('checkout.status');

// ─── Set permanent password (auth required) ───────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/set-password', [SetPasswordController::class, 'show'])->name('password.setup');
    Route::post('/set-password', [SetPasswordController::class, 'store'])->name('password.setup.store');
});

// ─── Legal ────────────────────────────────────────────────────────────────────

Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');

// ─── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email')->middleware('throttle:3,1');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update')->middleware('throttle:3,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ─── Super Admin ──────────────────────────────────────────────────────────────

Route::middleware(['auth', EnsureSuperAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::patch('/settings', [AdminDashboardController::class, 'updateSettings'])->name('admin.settings.update');
    Route::patch('/creator-plan-pricing', [AdminDashboardController::class, 'updateCreatorPlanPricing'])->name('admin.creator-plan-pricing.update');
    Route::get('/payouts', [AdminPayoutController::class, 'index'])->name('admin.payouts');
    Route::post('/payouts/owner/{community:id}', [AdminPayoutController::class, 'payOwner'])->name('admin.payouts.owner')->middleware('throttle:10,1');
    Route::post('/payouts/owners/batch', [AdminPayoutController::class, 'batchPayOwners'])->name('admin.payouts.owners.batch')->middleware('throttle:5,1');
    Route::post('/payouts/owners/selected', [AdminPayoutController::class, 'paySelectedOwners'])->name('admin.payouts.owners.selected')->middleware('throttle:5,1');
    Route::post('/payouts/affiliates/batch', [AdminPayoutController::class, 'batchPayAffiliates'])->name('admin.payouts.affiliates.batch')->middleware('throttle:5,1');
    Route::post('/payouts/affiliates/selected', [AdminPayoutController::class, 'paySelectedAffiliates'])->name('admin.payouts.affiliates.selected')->middleware('throttle:5,1');
    Route::post('/payout-requests/{payoutRequest}/approve', [AdminPayoutController::class, 'approveRequest'])->name('admin.payout-requests.approve')->middleware('throttle:10,1');
    Route::post('/payout-requests/{payoutRequest}/reject', [AdminPayoutController::class, 'rejectRequest'])->name('admin.payout-requests.reject')->middleware('throttle:10,1');
    Route::post('/payout-requests/{payoutRequest}/mark-paid', [AdminPayoutController::class, 'markRequestPaid'])->name('admin.payout-requests.mark-paid')->middleware('throttle:10,1');
    Route::post('/onboarding/{user}/resend', [AdminUserController::class, 'resendOnboardingEmail'])->name('admin.onboarding.resend');
    // Featured communities
    Route::post('/communities/{community}/toggle-featured', [AdminDashboardController::class, 'toggleFeatured'])->name('admin.communities.toggle-featured');
    // Analytics
    Route::get('/creator-analytics', [AdminDashboardController::class, 'creatorAnalytics'])->name('admin.creator-analytics');
    Route::get('/affiliate-analytics', [AdminDashboardController::class, 'affiliateAnalytics'])->name('admin.affiliate-analytics');
    // User management
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle');
    Route::patch('/users/{user}/toggle-kyc', [AdminKycController::class, 'toggle'])->name('admin.users.toggle-kyc');
    Route::get('/kyc-reviews', [AdminKycController::class, 'reviews'])->name('admin.kyc-reviews');
    Route::patch('/kyc-reviews/{user}/approve', [AdminKycController::class, 'approve'])->name('admin.kyc.approve');
    Route::patch('/kyc-reviews/{user}/reject', [AdminKycController::class, 'reject'])->name('admin.kyc.reject');
    // Soft delete recovery
    Route::get('/posts/trashed', [AdminPostModerationController::class, 'trashed'])->name('admin.posts.trashed');
    Route::post('/posts/{postId}/restore', [AdminPostModerationController::class, 'restore'])->name('admin.posts.restore');
    Route::delete('/posts/{postId}/force-delete', [AdminPostModerationController::class, 'forceDelete'])->name('admin.posts.force-delete');
    // Global announcement
    Route::get('/announcements', [AdminAnnouncementController::class, 'show'])->name('admin.announcements');
    Route::post('/announcements', [AdminAnnouncementController::class, 'send'])->name('admin.announcements.send')->middleware('throttle:3,1');
    // Email templates
    Route::get('/email-templates', [AdminEmailTemplateController::class, 'index'])->name('admin.email-templates');
    Route::get('/email-templates/{key}/edit', [AdminEmailTemplateController::class, 'edit'])->name('admin.email-templates.edit');
    Route::put('/email-templates/{key}', [AdminEmailTemplateController::class, 'update'])->name('admin.email-templates.update');
    Route::post('/email-templates/{key}/preview', [AdminEmailTemplateController::class, 'preview'])->name('admin.email-templates.preview');
    // Coupons
    Route::get('/coupons', [AdminCouponController::class, 'index'])->name('admin.coupons');
    Route::post('/coupons', [AdminCouponController::class, 'store'])->name('admin.coupons.store');
    Route::post('/coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])->name('admin.coupons.toggle');
    Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('admin.coupons.destroy');
    // AI usage (observability)
    Route::get('/ai-usage', [AdminAiUsageController::class, 'index'])->name('admin.ai-usage');
    // Support Tickets
    Route::get('/tickets', [TicketController::class, 'adminIndex'])->name('admin.tickets');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('admin.tickets.show');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('admin.tickets.reply');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'adminUpdateStatus'])->name('admin.tickets.status');
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
    Route::patch('/settings/email', [AccountSettingsController::class, 'updateEmail'])->name('account.settings.email')->middleware('throttle:5,1');
    Route::patch('/settings/password', [AccountSettingsController::class, 'updatePassword'])->name('account.settings.password')->middleware('throttle:5,1');
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
    Route::post('/settings/kyc/manual-review', [AccountSettingsController::class, 'requestManualKycReview'])->name('account.settings.kyc.manual');
    Route::post('/settings/delete-account', [AccountSettingsController::class, 'deleteAccount'])->name('account.settings.delete');
});

// ─── Communities ───────────────────────────────────────────────────────────────

// Public tabs + listing
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');
Route::get('/communities/{community}/about', [CommunityController::class, 'about'])->name('communities.about');
Route::get('/communities/{community}/landing', [LandingPageController::class, 'show'])->name('communities.landing');
Route::get('/communities/{community}/calendar', [EventController::class, 'index'])->name('communities.calendar');

// Public HLS proxy for community gallery videos (anonymous landing-page playback)
Route::get('/communities/{community}/gallery/{item}/hls/{file}', [CommunityGalleryController::class, 'hlsFile'])
    ->where('file', '.+\.(m3u8|ts)')
    ->name('communities.gallery.hls');

// ─── Classroom (public read) ───────────────────────────────────────────────
Route::get('/communities/{community}/classroom', [CourseController::class, 'index'])->name('communities.classroom');
Route::get('/communities/{community}/classroom/courses/{course}', [CourseController::class, 'show'])->name('communities.classroom.courses.show');
Route::post('/communities/{community}/classroom/courses/{course}/preview-play', [CourseController::class, 'trackPreviewPlay'])->name('communities.classroom.courses.preview-play');

Route::middleware('auth')->group(function () {
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::post('/communities/{community}/join', [CommunityController::class, 'join'])->name('communities.join');
    Route::post('/communities/{community}/leave', [CommunityController::class, 'leave'])->name('communities.leave');

    // Paid community checkout → redirects to Xendit invoice URL
    Route::post('/communities/{community}/checkout', [SubscriptionController::class, 'checkout'])->name('communities.checkout')->middleware('throttle:10,1');
    Route::post('/subscriptions/{subscription}/cancel-recurring', [RecurringCancellationController::class, 'cancelSubscription'])->name('subscriptions.cancel-recurring');
    Route::post('/subscriptions/{subscription}/enable-auto-renew', [RecurringCancellationController::class, 'enableSubscriptionAutoRenew'])->name('subscriptions.enable-auto-renew');
    Route::post('/course-enrollments/{courseEnrollment}/cancel-recurring', [RecurringCancellationController::class, 'cancelCourseEnrollment'])->name('course-enrollments.cancel-recurring');
    Route::post('/curzzo-purchases/{curzzoPurchase}/cancel-recurring', [RecurringCancellationController::class, 'cancelCurzzoPurchase'])->name('curzzo-purchases.cancel-recurring');

    // Free community subscription (for access to free courses)
    Route::post('/communities/{community}/free-subscribe', [FreeSubscribeController::class, 'store'])->name('communities.free-subscribe');

    // Course one-time purchase (no membership required)
    Route::post('/communities/{community}/classroom/courses/{course}/enroll', [CourseEnrollmentController::class, 'checkout'])->name('communities.classroom.courses.enroll')->middleware('throttle:10,1');

    // Owner-only: invite members by email / CSV
    Route::get('/communities/{community}/invites', [CommunityInviteController::class, 'index'])->name('communities.invites.index');
    Route::post('/communities/{community}/invite', [CommunityInviteController::class, 'store'])->name('communities.invite');

    // Events (owner-only mutations; calendar view is public above)
    Route::post('/communities/{community}/events', [EventController::class, 'store'])->name('communities.events.store');
    Route::post('/communities/{community}/events/{event}', [EventController::class, 'update'])->name('communities.events.update');
    Route::delete('/communities/{community}/events/{event}', [EventController::class, 'destroy'])->name('communities.events.destroy');

    // Owner-only mutations
    Route::match(['patch', 'post'], '/communities/{community}', [CommunityController::class, 'update'])->name('communities.update');
    Route::patch('/communities/{community}/ai-instructions', [CommunityController::class, 'updateAiInstructions'])->name('communities.ai-instructions');
    Route::patch('/communities/{community}/level-perks', [CommunityController::class, 'updateLevelPerks'])->name('communities.level-perks');
    // Gallery — images (form-upload), videos (multipart), reorder, transcode status
    Route::post('/communities/{community}/gallery/images', [CommunityGalleryController::class, 'storeImage'])->name('communities.gallery.images.store');
    Route::post('/communities/{community}/gallery/videos/initiate', [CommunityGalleryController::class, 'initiateVideoUpload'])->name('communities.gallery.videos.initiate');
    Route::post('/communities/{community}/gallery/videos/part-url', [CommunityGalleryController::class, 'getVideoPartUrl'])->name('communities.gallery.videos.part-url');
    Route::post('/communities/{community}/gallery/videos/complete', [CommunityGalleryController::class, 'completeVideoUpload'])->name('communities.gallery.videos.complete');
    Route::post('/communities/{community}/gallery/videos/abort', [CommunityGalleryController::class, 'abortVideoUpload'])->name('communities.gallery.videos.abort');
    Route::get('/communities/{community}/gallery/{item}/status', [CommunityGalleryController::class, 'transcodeStatus'])->name('communities.gallery.status');
    Route::delete('/communities/{community}/gallery/{item}', [CommunityGalleryController::class, 'destroy'])->name('communities.gallery.destroy');
    Route::put('/communities/{community}/gallery/reorder', [CommunityGalleryController::class, 'reorder'])->name('communities.gallery.reorder');
    Route::post('/communities/{community}/gallery/ai-generate', [CommunityController::class, 'aiGenerateGallery'])->name('communities.gallery.ai-generate');
    Route::get('/communities/{community}/gallery/ai-status', [CommunityController::class, 'aiGalleryStatus'])->name('communities.gallery.ai-status');
    Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');
    Route::post('/communities/{community}/cancel-deletion', [CommunityController::class, 'cancelDeletion'])->name('communities.cancel-deletion');
    Route::post('/communities/{community}/announce', [CommunityController::class, 'announce'])->name('communities.announce')->middleware('throttle:5,1');
    Route::post('/communities/{community}/sms-config', [SmsSettingsController::class, 'update'])->name('communities.sms-config');
    Route::post('/communities/{community}/sms-test', [SmsSettingsController::class, 'test'])->name('communities.sms-test');
    Route::post('/communities/{community}/sms-blast', [SmsSettingsController::class, 'blast'])->name('communities.sms-blast')->middleware('throttle:3,1');

    // ─── Resend Email Config ─────────────────────────────────────────────────
    Route::post('/communities/{community}/resend-config', [EmailProviderController::class, 'updateConfig'])->name('communities.resend-config');
    Route::post('/communities/{community}/resend-add-domain', [EmailProviderController::class, 'addDomain'])->name('communities.resend-add-domain');
    Route::post('/communities/{community}/resend-verify-domain', [EmailProviderController::class, 'verifyDomain'])->name('communities.resend-verify-domain');
    Route::get('/communities/{community}/resend-domain-info', [EmailProviderController::class, 'getDomain'])->name('communities.resend-domain-info');
    Route::post('/communities/{community}/resend-test', [EmailProviderController::class, 'testEmail'])->name('communities.resend-test');

    // ─── Tags ────────────────────────────────────────────────────────────────
    Route::get('/communities/{community}/tags', [TagController::class, 'index'])->name('communities.tags.index');
    Route::post('/communities/{community}/tags', [TagController::class, 'store'])->name('communities.tags.store');
    Route::patch('/communities/{community}/tags/{tag}', [TagController::class, 'update'])->name('communities.tags.update');
    Route::delete('/communities/{community}/tags/{tag}', [TagController::class, 'destroy'])->name('communities.tags.destroy');
    Route::post('/communities/{community}/tags/assign', [TagController::class, 'assign'])->name('communities.tags.assign');

    Route::post('/communities/{community}/workflows', [WorkflowController::class, 'store'])->name('communities.workflows.store');
    Route::patch('/communities/{community}/workflows/{workflow}', [WorkflowController::class, 'update'])->name('communities.workflows.update');
    Route::post('/communities/{community}/workflows/{workflow}/toggle', [WorkflowController::class, 'toggle'])->name('communities.workflows.toggle');
    Route::delete('/communities/{community}/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('communities.workflows.destroy');
    Route::post('/communities/{community}/ai-landing', [LandingPageController::class, 'generate'])->name('communities.ai-landing');
    Route::post('/communities/{community}/ai-landing/section', [LandingPageController::class, 'regenerateSection'])->name('communities.ai-landing.section');
    Route::post('/communities/{community}/landing-page/upload-image', [LandingPageController::class, 'uploadImage'])->name('communities.landing-page.upload-image');
    Route::post('/communities/{community}/landing-page/upload-video', [LandingPageController::class, 'uploadVideo'])->name('communities.landing-page.upload-video');
    Route::patch('/communities/{community}/landing-page', [LandingPageController::class, 'update'])->name('communities.landing-page.update');

    // Member management (admin only — enforced by Action)
    Route::delete('/communities/{community}/members/{user}', [CommunityMemberController::class, 'destroy'])->name('communities.members.destroy');
    Route::patch('/communities/{community}/members/{user}/role', [CommunityMemberController::class, 'changeRole'])->name('communities.members.role');
    Route::patch('/communities/{community}/members/{user}/block', [CommunityMemberController::class, 'toggleBlock'])->name('communities.members.block');
    Route::patch('/communities/{community}/members/extend-access', [CommunityMemberController::class, 'extendAccess'])->name('communities.members.extend-access');
    Route::patch('/communities/{community}/members/set-expiry', [CommunityMemberController::class, 'setExpiry'])->name('communities.members.set-expiry');

    // Gated: active membership required
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/communities/{community}/members', [CommunityController::class, 'members'])->name('communities.members');
        Route::get('/communities/{community}/settings', fn (Community $community) => redirect()->route('communities.settings.general', $community))->name('communities.settings');
        Route::get('/communities/{community}/settings/general', [CommunitySettingsController::class, 'general'])->name('communities.settings.general');
        Route::get('/communities/{community}/settings/affiliate', [CommunitySettingsController::class, 'affiliate'])->name('communities.settings.affiliate');
        Route::get('/communities/{community}/settings/ai-tools', [CommunitySettingsController::class, 'aiTools'])->name('communities.settings.ai-tools');
        Route::get('/communities/{community}/settings/curzzos', [CurzzoController::class, 'index'])->name('communities.settings.curzzos');
        Route::get('/communities/{community}/settings/announcements', [CommunitySettingsController::class, 'announcements'])->name('communities.settings.announcements');
        Route::get('/communities/{community}/settings/level-perks', [CommunitySettingsController::class, 'levelPerks'])->name('communities.settings.level-perks');
        Route::get('/communities/{community}/settings/invite-members', [CommunitySettingsController::class, 'inviteMembers'])->name('communities.settings.invite-members');
        Route::get('/communities/{community}/settings/integrations', [CommunitySettingsController::class, 'integrations'])->name('communities.settings.integrations');
        Route::get('/communities/{community}/settings/domain', [CommunitySettingsController::class, 'domain'])->name('communities.settings.domain');
        Route::get('/communities/{community}/settings/sms', [CommunitySettingsController::class, 'sms'])->name('communities.settings.sms');
        Route::get('/communities/{community}/settings/email', [CommunitySettingsController::class, 'email'])->name('communities.settings.email');
        Route::get('/communities/{community}/settings/tags', [CommunitySettingsController::class, 'tags'])->name('communities.settings.tags');
        Route::get('/communities/{community}/settings/workflows', [CommunitySettingsController::class, 'workflows'])->name('communities.settings.workflows');

        // ─── Email Campaigns ─────────────────────────────────────────────────
        Route::get('/communities/{community}/email-campaigns', [EmailCampaignController::class, 'index'])->name('communities.email-campaigns.index');
        Route::get('/communities/{community}/email-campaigns/create', [EmailCampaignController::class, 'create'])->name('communities.email-campaigns.create');
        Route::post('/communities/{community}/email-campaigns', [EmailCampaignController::class, 'store'])->name('communities.email-campaigns.store');
        Route::get('/communities/{community}/email-campaigns/{campaign}', [EmailCampaignController::class, 'show'])->name('communities.email-campaigns.show');
        Route::patch('/communities/{community}/email-campaigns/{campaign}', [EmailCampaignController::class, 'update'])->name('communities.email-campaigns.update');
        Route::post('/communities/{community}/email-campaigns/{campaign}/send', [EmailCampaignController::class, 'send'])->name('communities.email-campaigns.send')->middleware('throttle:5,1');
        Route::delete('/communities/{community}/email-campaigns/{campaign}', [EmailCampaignController::class, 'destroy'])->name('communities.email-campaigns.destroy');
        Route::post('/communities/{community}/email-campaigns/upload-image', [EmailCampaignController::class, 'uploadImage'])->name('communities.email-campaigns.upload-image');

        // ─── Email Sequences ─────────────────────────────────────────────────
        Route::get('/communities/{community}/email-sequences', [EmailSequenceController::class, 'index'])->name('communities.email-sequences.index');
        Route::get('/communities/{community}/email-sequences/create', [EmailSequenceController::class, 'create'])->name('communities.email-sequences.create');
        Route::post('/communities/{community}/email-sequences', [EmailSequenceController::class, 'store'])->name('communities.email-sequences.store');
        Route::get('/communities/{community}/email-sequences/{sequence}', [EmailSequenceController::class, 'show'])->name('communities.email-sequences.show');
        Route::post('/communities/{community}/email-sequences/{sequence}/activate', [EmailSequenceController::class, 'activate'])->name('communities.email-sequences.activate');
        Route::post('/communities/{community}/email-sequences/{sequence}/pause', [EmailSequenceController::class, 'pause'])->name('communities.email-sequences.pause');
        Route::delete('/communities/{community}/email-sequences/{sequence}', [EmailSequenceController::class, 'destroy'])->name('communities.email-sequences.destroy');

        // ─── Email Analytics & History ────────────────────────────────────────
        Route::get('/communities/{community}/email-analytics', [EmailAnalyticsController::class, 'index'])->name('communities.email-analytics');
        Route::get('/communities/{community}/email-history', [EmailHistoryController::class, 'index'])->name('communities.email-history');
        Route::get('/communities/{community}/settings/danger-zone', [CommunitySettingsController::class, 'dangerZone'])->name('communities.settings.danger-zone');
        Route::get('/communities/{community}/settings/chat-history', [CommunitySettingsController::class, 'chatHistory'])->name('communities.settings.chat-history');
        Route::get('/communities/{community}/settings/chat-history/{userId}', [CommunitySettingsController::class, 'chatHistoryUser'])->name('communities.settings.chat-history.user');
        Route::get('/communities/{community}/analytics', [CommunityController::class, 'analytics'])->name('communities.analytics');

        // Posts (community-scoped)
        Route::post('/communities/{community}/posts', [PostController::class, 'store'])->name('posts.store');

        // ─── Classroom (mutations — membership required) ───────────────────────
        Route::post('/communities/{community}/classroom/courses/reorder', [CourseController::class, 'reorder'])->name('communities.classroom.courses.reorder');
        Route::post('/communities/{community}/classroom/courses', [CourseController::class, 'store'])->name('communities.classroom.courses.store');
        Route::post('/communities/{community}/classroom/courses/{course}/update', [CourseController::class, 'update'])->name('communities.classroom.courses.update');
        Route::delete('/communities/{community}/classroom/courses/{course}', [CourseController::class, 'destroy'])->name('communities.classroom.courses.destroy');
        Route::post('/communities/{community}/classroom/courses/{course}/toggle-publish', [CourseController::class, 'togglePublish'])->name('communities.classroom.courses.toggle-publish');
        Route::post('/communities/{community}/classroom/courses/{course}/modules', [CourseModuleController::class, 'store'])->name('communities.classroom.modules.store');
        Route::match(['patch', 'post'], '/communities/{community}/classroom/courses/{course}/modules/{module}', [CourseModuleController::class, 'update'])->name('communities.classroom.modules.update');
        Route::delete('/communities/{community}/classroom/courses/{course}/modules/{module}', [CourseModuleController::class, 'destroy'])->name('communities.classroom.modules.destroy');
        Route::post('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons', [CourseLessonController::class, 'store'])->name('communities.classroom.lessons.store');
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/complete', [CourseLessonController::class, 'complete'])->name('communities.classroom.lessons.complete');
        Route::post('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/reorder', [CourseLessonController::class, 'reorder'])->name('communities.classroom.lessons.reorder');
        Route::match(['patch', 'post'], '/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/{lesson}', [CourseLessonController::class, 'update'])->name('communities.classroom.lessons.update');
        Route::delete('/communities/{community}/classroom/courses/{course}/modules/{module}/lessons/{lesson}', [CourseLessonController::class, 'destroy'])->name('communities.classroom.lessons.destroy');
        Route::post('/communities/{community}/classroom/lesson-images', [CourseLessonController::class, 'uploadImage'])->name('communities.classroom.lesson-images');
        Route::post('/communities/{community}/classroom/lesson-videos', [LessonVideoController::class, 'uploadLessonVideo'])->name('communities.classroom.lesson-videos');
        Route::post('/communities/{community}/classroom/preview-videos', [LessonVideoController::class, 'uploadPreviewVideo'])->name('communities.classroom.preview-videos');
        Route::post('/communities/{community}/classroom/multipart/initiate', [LessonVideoController::class, 'initiateMultipartUpload'])->name('communities.classroom.multipart.initiate');
        Route::post('/communities/{community}/classroom/multipart/part-url', [LessonVideoController::class, 'getPartUploadUrl'])->name('communities.classroom.multipart.part-url');
        Route::post('/communities/{community}/classroom/multipart/complete', [LessonVideoController::class, 'completeMultipartUpload'])->name('communities.classroom.multipart.complete');
        Route::post('/communities/{community}/classroom/multipart/abort', [LessonVideoController::class, 'abortMultipartUpload'])->name('communities.classroom.multipart.abort');
        Route::get('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/stream', [LessonVideoController::class, 'stream'])->name('communities.classroom.lessons.stream');
        Route::get('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/transcode-status', [LessonVideoController::class, 'transcodeStatus'])->name('communities.classroom.lessons.transcode-status');
        Route::get('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/hls/{file}', [LessonVideoController::class, 'hlsFile'])->where('file', '.*')->name('communities.classroom.lessons.hls');
        Route::post('/communities/{community}/classroom/courses/{course}/lessons/{lesson}/video-play', [LessonVideoController::class, 'trackPlay'])->name('communities.classroom.lessons.video-play');

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
        Route::post('/communities/{community}/certifications/{certification}/checkout', [CertificationExamController::class, 'checkout'])->name('certification.checkout')->middleware('throttle:10,1');
        Route::delete('/communities/{community}/certifications/{certification}', [CertificationExamController::class, 'destroy'])->name('certification.destroy');

        // ─── Chat ─────────────────────────────────────────────────────────────
        Route::get('/communities/{community}/chat', [ChatController::class, 'index'])->name('communities.chat');
        Route::post('/communities/{community}/chat', [ChatController::class, 'store'])->name('communities.chat.store');
        Route::get('/communities/{community}/chat/poll', [ChatController::class, 'poll'])->name('communities.chat.poll');
        Route::get('/communities/{community}/chat/history', [ChatController::class, 'history'])->name('communities.chat.history');
        Route::delete('/communities/{community}/chat/{message}', [ChatController::class, 'destroy'])->name('communities.chat.destroy');
        Route::post('/communities/{community}/chatbot', [CommunityChatbotController::class, 'chat'])->name('communities.chatbot.chat');
        Route::post('/communities/{community}/chatbot/reply', [CommunityChatbotController::class, 'reply'])->name('communities.chatbot.reply');
        Route::get('/communities/{community}/chatbot/poll', [CommunityChatbotController::class, 'poll'])->name('communities.chatbot.poll');
        Route::get('/communities/{community}/chatbot/history', [CommunityChatbotController::class, 'history'])->name('communities.chatbot.history');

        // ─── Curzzos (Custom AI Bots) ───────────────────────────────────────
        Route::post('/communities/{community}/curzzos', [CurzzoController::class, 'store'])->name('communities.curzzos.store');
        Route::patch('/communities/{community}/curzzos/{curzzo}', [CurzzoController::class, 'update'])->name('communities.curzzos.update');
        Route::delete('/communities/{community}/curzzos/{curzzo}', [CurzzoController::class, 'destroy'])->name('communities.curzzos.destroy');
        Route::post('/communities/{community}/curzzos/reorder', [CurzzoController::class, 'reorder'])->name('communities.curzzos.reorder');
        Route::post('/communities/{community}/curzzos/{curzzo}/toggle-active', [CurzzoController::class, 'toggleActive'])->name('communities.curzzos.toggle-active');
        Route::post('/communities/{community}/curzzos/preview-videos', [CurzzoController::class, 'uploadPreviewVideo'])->name('communities.curzzos.upload-preview-video');
        Route::get('/communities/{community}/curzzos', [CommunityController::class, 'curzzos'])->name('communities.curzzos');
        Route::post('/communities/{community}/curzzos/{curzzo}/checkout', [CurzzoCheckoutController::class, 'checkout'])->name('communities.curzzos.checkout')->middleware('throttle:10,1');
        Route::get('/communities/{community}/curzzos/topup-packs', [CurzzoTopupController::class, 'packs'])->name('communities.curzzos.topup-packs');
        Route::post('/communities/{community}/curzzos/topup/checkout', [CurzzoTopupController::class, 'checkout'])->name('communities.curzzos.topup-checkout')->middleware('throttle:10,1');
        Route::post('/communities/{community}/curzzos/{curzzo}/chat', [CurzzoChatController::class, 'chat'])->name('communities.curzzos.chat');
        Route::get('/communities/{community}/curzzos/{curzzo}/history', [CurzzoChatController::class, 'history'])->name('communities.curzzos.history');
        Route::delete('/communities/{community}/curzzos/{curzzo}/history', [CurzzoChatController::class, 'resetHistory'])->name('communities.curzzos.reset');

        // ─── Community DMs ───────────────────────────────────────────────────
        Route::get('/communities/{community}/dm/conversations', [CommunityDmController::class, 'conversations'])->name('communities.dm.conversations');
        Route::get('/communities/{community}/dm/{userId}/messages', [CommunityDmController::class, 'messages'])->name('communities.dm.messages');
        Route::get('/communities/{community}/dm/{userId}/poll', [CommunityDmController::class, 'poll'])->name('communities.dm.poll');
        Route::post('/communities/{community}/dm/send', [CommunityDmController::class, 'send'])->name('communities.dm.send');

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
    Route::post('/creator/plan/checkout', [CreatorController::class, 'planCheckout'])->name('creator.plan.checkout')->middleware('throttle:10,1');
    Route::post('/creator/plan/validate-coupon', [CreatorController::class, 'validateCoupon'])->name('creator.plan.validate-coupon')->middleware('throttle:20,1');
    Route::post('/creator/plan/switch-cycle', [CreatorController::class, 'switchCycle'])->name('creator.plan.switch-cycle')->middleware('throttle:5,1');
    Route::post('/creator/plan/cancel-recurring', [RecurringCancellationController::class, 'cancelCreatorPlan'])->name('creator.plan.cancel-recurring');
    Route::post('/creator/plan/enable-auto-renew', [RecurringCancellationController::class, 'enableCreatorPlanAutoRenew'])->name('creator.plan.enable-auto-renew');
    Route::post('/creator/plan/redeem-coupon', [CreatorController::class, 'redeemCoupon'])->name('creator.plan.redeem-coupon');
    Route::get('/creator/dashboard', [CreatorController::class, 'dashboard'])->name('creator.dashboard');
    Route::post('/creator/payout-request/{community:id}', [PayoutRequestController::class, 'storeOwner'])->name('creator.payout-request.store')->middleware('throttle:5,1');

    // ─── Affiliates ───────────────────────────────────────────────────────────
    Route::get('/my-affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::get('/my-affiliates/analytics', [AffiliateController::class, 'analytics'])->name('affiliates.analytics');
    Route::post('/communities/{community}/affiliates', [AffiliateController::class, 'store'])->name('communities.affiliates.join');
    Route::get('/communities/{community}/affiliates', [AffiliateController::class, 'dashboard'])->name('communities.affiliates');
    Route::patch('/affiliate-conversions/{conversion}/paid', [AffiliateController::class, 'markPaid'])->name('affiliate-conversions.paid');
    Route::post('/affiliate-conversions/{conversion}/disburse', [AffiliateController::class, 'disburse'])->name('affiliate-conversions.disburse');
    Route::patch('/affiliates/{affiliate}/payout', [AffiliateController::class, 'updatePayout'])->name('affiliates.payout');
    Route::patch('/affiliates/{affiliate}/pixels', [AffiliateController::class, 'updatePixels'])->name('affiliates.pixels');
    Route::post('/affiliates/{affiliate}/payout-request', [PayoutRequestController::class, 'storeAffiliate'])->name('affiliates.payout-request.store')->middleware('throttle:5,1');
    Route::post('/affiliates/payout-request/all', [PayoutRequestController::class, 'storeAffiliateAll'])->name('affiliates.payout-request.all')->middleware('throttle:5,1');

    // ─── AI Assistant ─────────────────────────────────────────────────────────
    Route::post('/ai/chat', [AIAssistantController::class, 'chat'])->name('ai.chat');
    Route::post('/ai/greet', [AIAssistantController::class, 'greet'])->name('ai.greet');

    // ─── Support Tickets ─────────────────────────────────────────────────────
    Route::get('/support', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/support', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/support/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/support/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('/support/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::patch('/support/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
});

// ─── Xendit Webhooks (no auth, no CSRF) ────────────────────────────────────
Route::post('/webhooks/xendit/payouts', [XenditWebhookController::class, 'payouts'])->name('webhooks.xendit.payouts')->middleware('throttle:60,1');

// ─── Telegram Webhooks (no auth, no CSRF) ──────────────────────────────────
Route::post('/webhooks/telegram/{slug}', TelegramWebhookController::class)->name('webhooks.telegram')->middleware('throttle:60,1');

// ─── Resend Webhooks (no auth, no CSRF — covered by webhooks/* exclusion) ──
Route::post('/webhooks/resend', ResendWebhookController::class)->name('webhooks.resend')->middleware('throttle:60,1');

// ─── Email Unsubscribe (signed URL, no auth) ──────────────────────────────
Route::get('/email/unsubscribe/{community}/{member}', [EmailUnsubscribeController::class, 'unsubscribe'])
    ->name('email.unsubscribe')
    ->middleware('signed');
