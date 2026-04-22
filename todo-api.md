# Mobile API Readiness — TODO

## The Gist (read first, applies to every task below)

> **One origin of logic. Both Inertia (web) and the API call the same thing.**

Every task on this list is an instance of the same pattern: a piece of behavior must exist in **exactly one place** in the codebase, and both the Web (Inertia) controller and the API controller call into it. There is no "build the API version" — there is only "extract the logic so one Action/Service serves both surfaces."

This means:
- **Logic** lives in `app/Actions/{Domain}/` (single-purpose use case) or `app/Services/` (stateful coordinator). Never in a controller.
- **Validation** lives in `app/Http/Requests/` Form Requests. Both Web and API controllers type-hint the same Form Request.
- **Response shape** for JSON lives in `app/Http/Resources/`. Locks the contract for mobile.
- **Controllers** (Web and API) are thin: receive Form Request, call Action, format response (Inertia/redirect for Web, JsonResource/JSON for API). That's it.

If a Web controller currently has the logic inline, the work is:
1. Write characterization tests against current Web behavior first (safety net)
2. Extract logic to Action(s) + Form Request(s) + Resource(s)
3. Refactor Web controller to delegate (tests stay green)
4. Add API controller wired to the same Action + Form Request
5. Add API feature tests asserting identical behavior

If the endpoint is brand new (no Web equivalent), the same rule applies — write the Action first, then both controllers if both surfaces need it. Do not put logic in the controller "just for now."

**Why:** Two callers will inevitably drift if they have two implementations. Mobile and web showing different behavior for the same feature is the bug we're preventing. One place to fix bugs. One place to change rules. One place to test deeply.

80% test coverage required on new code (project rule).

This is a running checklist — check items off as they ship. Pick any single item and execute; each is scoped to be self-contained.

---

## Tier 1 — must do before mobile starts

### [x] 1.1 Curzzo / AI bot API surface
- [x] 9 Actions in `app/Actions/Curzzo/` + `ChatResult` DTO
- [x] 5 Form Requests
- [x] `CurzzoResource`, `CurzzoChatMessageResource`
- [x] 4 API controllers, 13 routes under `/api/communities/{community}/curzzos*`
- [x] Web controllers refactored to use same Actions
- [x] 33 Web characterization tests + 31 API feature tests + 2 Action unit tests (all green)
- [x] `Curzzo` model gained `HasFactory` + `CurzzoFactory`

### [x] 1.2 Payment status polling + cancel  (~1.5–2 hr)
- [x] `POST /api/subscriptions/{subscription}/check-status`
- [x] `POST /api/subscriptions/{subscription}/cancel-recurring`
- [x] `POST /api/curzzo-purchases/{curzzoPurchase}/check-status`
- [x] `POST /api/curzzo-purchases/{curzzoPurchase}/cancel-recurring`
- [x] Reuse existing `CancelRecurringPlan` action (same one `RecurringCancellationController` uses)
- [x] New `CheckSubscriptionStatus` + `CheckCurzzoPurchaseStatus` actions (read-only; webhook remains source of truth for writes)
- [x] `SubscriptionStatusResource` + `CurzzoPurchaseStatusResource` lock the JSON contract
- [x] Web characterization tests for cancel-recurring already existed (19 tests, all green); 18 new API feature tests added
- Why: mobile starts checkout but cannot confirm completion without webhook visibility.

### [x] 1.3 Password reset / forgot password API  (~1–1.5 hr)
- [x] `POST /api/auth/forgot-password` (request reset email)
- [x] `POST /api/auth/verify-reset-token` (validate token before showing form)
- [x] `POST /api/auth/reset-password` (submit new password)
- [x] Extracted `SendPasswordResetLink`, `VerifyResetToken`, `ResetPassword` actions in `app/Actions/Auth/`
- [x] `ForgotPasswordRequest`, `VerifyResetTokenRequest`, `ResetPasswordRequest` form requests
- [x] Web `ForgotPasswordController` refactored to delegate to same Actions; 8 existing Web characterization tests still green
- [x] 11 new API feature tests in `tests/Feature/Api/AuthApiTest.php`

### [x] 1.4 KYC submit + status API
- [x] `POST /api/kyc/submit` (multipart: `id_document`, `selfie`)
- [x] `GET /api/kyc/status` (current verification state; no document URLs exposed)
- [x] `POST /api/kyc/manual-review` (parity with web manual-review endpoint)
- [x] `KycResource` — status, verified, verified_at, submitted_at, rejected_reason, ai_rejections, `can_request_manual_review` computed flag
- [x] `UserResource` now surfaces `kyc_status` + `kyc_verified` (so `/auth/me` exposes it)
- [x] Extracted `SubmitKyc` + `RequestManualKycReview` actions in `app/Actions/Account/`; both throw `ValidationException::withMessages(['kyc' => ...])` so web redirects-back and API returns 422 from the same guards
- [x] `SubmitKycRequest` form request (shape validation; same messages as before)
- [x] Web `AccountSettingsController@submitKyc` and `@requestManualKycReview` refactored to delegate to Actions
- [x] 2 new Web characterization tests added (job-dispatch assertion + oversized-files guard); all 10 Web KYC tests green
- [x] 17 new API feature tests in `tests/Feature/Api/KycApiTest.php`

### [x] 1.5 Email verification API
- [x] `POST /api/auth/email/send-verification` (auth:sanctum, throttle:3,1) — resends verification email, rejects already-verified users
- [x] `POST /api/auth/email/verify` (public, throttle:10,1) — accepts HMAC-signed token from deep link
- [x] `UserResource` now surfaces `email_verified` (bool) + `email_verified_at` (ISO)
- [x] `User` now implements `MustVerifyEmail`; overrides `sendEmailVerificationNotification` to dispatch our `VerifyEmailMail`
- [x] `app/Mail/VerifyEmailMail.php` + `resources/views/emails/verify-email.blade.php` (matches existing mail style)
- [x] `app/Support/EmailVerificationToken.php` — HMAC-SHA256 signed token (`base64url(payload).sig`), 60-min TTL, binds to user id + current email so email-change invalidates
- [x] `SendEmailVerification` + `VerifyEmail` actions in `app/Actions/Auth/` — guards throw `ValidationException` so both surfaces get consistent 422/redirect behavior; verification is idempotent (does not re-fire `Verified` event for already-verified users)
- [x] `VerifyEmailRequest` form request
- [x] 14 new API feature tests in `AuthApiTest.php` (send happy path, 401 unauth, already-verified guard, verify happy path, idempotency, tampered signature, malformed token, expired, deleted user, email-changed-after-issue, missing token, `/auth/me` exposes the fields)
- Deliberately NOT in scope (flag as follow-ups): wiring registration to auto-send; wiring email-change to re-verify; a web verification route.

### [x] 1.6 Account deletion API  **(Apple App Store requirement since 2022)**
- [x] Policy: **soft-delete + 30-day grace period, then hard-delete**. User on soft-delete cannot authenticate; communities they own stop accepting new members; existing members retain access.
- [x] `POST /api/account/delete` (auth:sanctum) — soft-deletes user, revokes all Sanctum tokens, auto-cancels any `recurring_status=ACTIVE` plans via `CancelRecurringPlan` (Subscription / CreatorSubscription / CurzzoPurchase / CourseEnrollment)
- [x] `POST /api/account/delete/cancel` (public, throttle:5,1) — accepts `{email, password}`; restores if within grace window. Generic error message for all failure modes to avoid leaking account state.
- [x] `GET /api/account/deletion-status` (auth:sanctum) — returns `{requested, requested_at, will_be_deleted_at, days_remaining, can_cancel}`
- [x] Web equivalent: `POST /account/settings/delete-account`
- [x] Actions: `RequestAccountDeletion`, `CancelAccountDeletion`, `HardDeleteUserAccount` (app/Actions/Account/)
- [x] Console command: `php artisan users:prune-deleted` — runs `HardDeleteUserAccount` for anyone past grace (wipes S3 avatar + KYC docs, then forceDelete → cascade)
- [x] `User` uses `SoftDeletes` (migration `2026_04_21_120000_add_soft_deletes_to_users_table`) + `DELETION_GRACE_DAYS = 30` constant
- [x] `Community::isAcceptingNewMembers()` helper + guards in `JoinCommunity` (both free + trial paths) and `StartSubscriptionCheckout`
- [x] `CancelAccountDeletionRequest` form request + `AccountDeletionStatusResource`
- [x] Tests: 17 API (happy paths, auth guards, expired grace, wrong credentials, Xendit failure tolerance, soft-delete-blocks-sanctum-auth, community-join guarding) + 2 Web characterization + 4 command tests. All green; 1547/1547 across the full API/Web/Middleware sweep.
- Deliberately NOT in scope (flag as follow-ups): scheduling `users:prune-deleted` in `app/Console/Kernel.php` (ops decision on when to run); Inertia UI page for the delete-account button; login endpoint could surface a "pending_deletion" status when credentials match a soft-deleted user (currently just returns generic invalid credentials — users discover the cancel flow via the dedicated endpoint).

### [ ] 1.7 OpenAPI/Scribe spec
Split into 6 subtasks so the work fits in separate sessions. Each subtask ships independently: Scribe re-generates the full spec every run, so annotating one batch of controllers immediately improves the docs without blocking future batches.

Global rule for every 1.7.x commit: Xendit webhook (`/api/xendit/webhook`) is excluded from generated docs — it's an external-service integration, not a mobile surface. Add to Scribe's `routes.exclude` during 1.7.1 and never revisit.

Why: Mobile team will reverse-engineer from `routes/api.php` otherwise. **Do this BEFORE mobile starts integration**, not after.

#### [ ] 1.7.1 Install Scribe + baseline generation  (~45 min)
- [ ] `composer require --dev knuckleswtf/scribe`
- [ ] `php artisan vendor:publish --tag=scribe-config`
- [ ] Configure `config/scribe.php`: title, description, base URL, auth (Sanctum bearer), route include pattern `api/v1/*`, exclude `api/xendit/webhook`
- [ ] First `php artisan scribe:generate` — verify output builds without errors even without a single annotation
- [ ] Smoke-check generated docs locally; confirm Form Requests + API Resources are being picked up (Scribe reads `rules()` and `toArray()` automatically)
- [ ] Decide where generated files live (`public/docs/` vs `storage/`) and add to `.gitignore` if generated, or commit if static
- Scope note: NO controller annotations yet. This subtask only proves the pipeline works end-to-end.

#### [ ] 1.7.2 Annotate auth + account surface  (~1 hr)
Target controllers (mobile's first integration point):
- [ ] `AuthController` — login, register, logout, me, forgot/reset/verify password, email verification
- [ ] `AccountSettingsController` — profile/email/password/notifications/theme/payout/crypto/timezone, delete account, deletion status, cancel deletion
- [ ] `KycController` — submit, status, manual-review
- For each: add `@group`, `@authenticated` where applicable, and spot-check generated examples. Fix anything where Scribe's auto-detection produces wrong output (usually by adding `@bodyParam` for non-obvious fields or `@response` for complex/branching shapes).
- [ ] Re-run `scribe:generate`; sanity-check that all three domains render.

#### [ ] 1.7.3 Annotate content surface  (~1–1.5 hr)
- [ ] `CommunityController` + `CommunityMemberController` + `EventController`
- [ ] `FeedController` + `PostController` + `CommentController` + `LikeController`
- [ ] `ClassroomController` + `LessonCommentController` + `QuizController`
- Same pattern as 1.7.2 — `@group`, `@authenticated`, fix auto-gen misses.

#### [ ] 1.7.4 Annotate commerce surface  (~1 hr)
- [ ] `SubscriptionController` + `CurzzoPurchaseController` + `CurzzoCheckoutController` + `CurzzoTopupController`
- [ ] `CouponController` + `PayoutRequestController` + `AffiliateController` + `CreatorController`
- These have the subtlest payloads — pay extra attention to request/response examples; mobile will integrate checkout deeply.

#### [ ] 1.7.5 Annotate chat/AI + remainder  (~1 hr)
- [ ] `CurzzoController` + `CurzzoChatController` + `AIAssistantController`
- [ ] `ChatController` (community chat) + `DirectMessageController`
- [ ] `NotificationController` + `ProfileController` + `BadgeController` + `CertificateController`
- Catch-all for anything left.

#### [ ] 1.7.6 Serve at /api/docs + CI drift check  (~45 min)
- [ ] Route `/api/docs` (or `/docs`) publicly serving the generated docs — pick Scribe's static-HTML mode and commit, OR use Scribe's Laravel route integration
- [ ] Add a CI step: run `scribe:generate --force` and fail if `git diff --exit-code` shows changes → forces annotation updates to land with the code change that caused them
- [ ] README snippet pointing mobile devs at the docs URL
- Why this is the LAST subtask: running CI drift check before all controllers are annotated means every 1.7.x PR has to regenerate the universe. Keep CI quiet until docs stabilize.

### [x] 1.8 API versioning
- [x] All mobile-facing routes wrapped in `Route::prefix('v1')->group(...)` in `routes/api.php` → surface is now `/api/v1/*`
- [x] **Xendit webhook kept UNVERSIONED** at `/api/xendit/webhook` (configured in Xendit dashboard; moving it breaks prod integration). Comment in routes file calls this out so future edits don't nest it by accident.
- [x] 31 test files in `tests/Feature/Api/` rewritten to hit `/api/v1/...`; webhook test path preserved. Full API suite green: 397/397.
- Deliberately NOT in scope (flag as follow-ups): OpenAPI spec update (covered by 1.7 when it lands); removing the old unversioned `/api/*` paths isn't needed since nothing external consumed them yet (no web frontend callers; mobile hasn't shipped) — mobile simply pins to `/v1` from day one.

---

## Tier 2 — broader API surface for Inertia-only domains

Each of these is a Curzzo-style extraction: characterization tests → extract Actions → refactor Web → add API. Estimate per domain depends on controller fatness; budget 4–8 Claude-hours each for the bigger ones.

### [ ] 2.1 `CommunityController` audit + extraction
- [ ] Audit which methods have logic vs. delegate to existing services/actions
- [ ] Extract fat methods into Actions
- [ ] Confirm API parity (most exists per audit; verify each)
- [ ] Tests

### [ ] 2.2 `ClassroomController` / Courses / Modules / Lessons
- [ ] Audit + extract Web `CourseController`, `CourseModuleController`, `CourseLessonController`
- [ ] Confirm API `ClassroomController` exposes: course/module/lesson create+update+delete, reorder, complete, quiz submit, lesson images, multipart video upload
- [ ] Lesson HLS streaming over API — mirror `LessonVideoController@hlsFile`
- [ ] Tests

### [ ] 2.3 Gallery
- [ ] Audit current Web gallery controller(s)
- [ ] API endpoints: list, view, add (creator), delete (creator)
- [ ] Video HLS endpoint over API
- [ ] Tests

### [ ] 2.4 Tickets / Support
- [ ] Audit `TicketController` (Web)
- [ ] API: list user's tickets, create, view thread, reply, close
- [ ] `TicketResource`
- [ ] Tests

### [ ] 2.5 Events
- [ ] Confirm parity (audit said exists for index/store/update/destroy)
- [ ] Add: RSVP, calendar export if needed for mobile
- [ ] `EventResource`
- [ ] Tests

### [ ] 2.6 Affiliates dashboard
- [ ] Member-facing affiliate stats: clicks, conversions, earnings
- [ ] `AffiliateResource`, `AffiliateDetailsResource`
- [ ] Tests

### [ ] 2.7 Email sequences (if creators get on mobile)
- [ ] Decide scope: full creator-on-mobile or web-only
- [ ] If in scope: API for list/create/update/pause/delete sequences
- [ ] Tests

### [ ] 2.8 Workflows (if creators get on mobile)
- [ ] Same scoping decision as email sequences
- [ ] Tests

### [ ] 2.9 Analytics
- [ ] API for community analytics dashboard data
- [ ] Member engagement, course completion, revenue (if exposed to creators on mobile)
- [ ] Tests

### [x] 2.10 Notifications — `POST /api/notifications/{id}/read` (single-mark)
- [x] Currently only `read-all` exists. Add per-notification mark-read.
- [x] Tests

### [x] 2.11 `PATCH /api/posts/{post}` — missing API endpoint  (~0.5 hr)
- [x] Reuse `UpdatePost` action
- [x] Tests

### [x] 2.12 `POST /api/communities/{community}/leave`  (~0.5 hr)
- [x] Missing on both Web and API; create `LeaveCommunity` action
- [x] Tests

---

## Tier 3 — auth, sessions, push

### [ ] 3.1 Sanctum device naming + session management  (~1.5–2 hr)
- [ ] Accept `device_name` on `POST /api/auth/login`
- [ ] `GET /api/devices` — list active sessions
- [ ] `DELETE /api/devices/{id}` — logout specific device
- [ ] Tests

### [ ] 3.2 Sanctum token refresh / expiration policy  (decision + ~1–2 hr)
- [ ] **Decide policy**: do mobile tokens expire? If yes, what TTL?
- [ ] If expiring: add `POST /api/auth/refresh` endpoint
- [ ] Update token storage / clean-up
- [ ] Tests

### [ ] 3.3 Sign in with Apple  (~3–4 hr if needed)
- [ ] **Decide first**: are we offering ANY social login (Google, Facebook)? If yes, Apple requires Sign in with Apple too.
- [ ] If yes: Socialite Apple driver, `POST /api/auth/social/apple`
- [ ] Tests

### [ ] 3.4 Sign in with Google  (~2–3 hr if needed)
- [ ] Decide scope (web has none today; flag if mobile expects it)
- [ ] If yes: Socialite, ID token verification flow for native
- [ ] Tests

### [ ] 3.5 2FA / TOTP  (decision)
- [ ] **Decide scope**: in for v1, deferred to v1.1, or out
- [ ] If in: enroll, verify, recovery codes, mobile-friendly endpoints

### [ ] 3.6 FCM (Android) + APNs (iOS) push registration  (~2–2.5 hr)
- [ ] `POST /api/devices/{deviceId}/push-tokens` — register FCM/APNs token
- [ ] Server-side dispatcher — laravel-notification-channels/fcm + apn
- [ ] Map existing DB notifications to push payloads
- [ ] Tests

### [x] 3.7 Coupon redeem API  (~0.5 hr)
- [x] `POST /api/coupons/{code}/redeem` — currently web-only for creator plan
- [x] Reuse `RedeemCoupon` action
- [x] Tests

---

## Tier 4 — mobile-platform & app-store concerns

### [ ] 4.1 In-app purchases (IAP) decision  (decision + ~4–8 hr if implementing)
- [ ] **Policy decision**: digital goods sold via Apple typically must use IAP. Browser-based Xendit checkout may be allowed depending on category.
- [ ] If IAP required: receipt verification endpoint, mapping IAP products to Curzzo/subscription, server-side validation
- [ ] If browser-only: ensure UX doesn't violate Apple guidelines (no in-app links to web checkout for digital goods)

### [ ] 4.2 Force-update mechanism  (~1–1.5 hr)
- [ ] `GET /api/app/version-status?platform=ios&version=1.2.3`
- [ ] Returns `{ status: "ok|update_recommended|update_required" }`
- [ ] Config-driven minimum versions
- [ ] Tests

### [ ] 4.3 Maintenance mode JSON response  (~0.5 hr)
- [ ] Current Laravel maintenance redirects HTML — native clients break
- [ ] Add JSON response handling so mobile shows "we're down for maintenance" gracefully
- [ ] Test

### [ ] 4.4 Privacy / data export  (Apple + GDPR)  (~2–3 hr)
- [ ] `POST /api/account/export` — request data export
- [ ] `GET /api/account/export/{requestId}/status`
- [ ] Email or download link with user's data
- [ ] Tests

---

## Tier 5 — operational

### [ ] 5.1 CORS configuration review  (~0.5 hr)
- [ ] Review `config/cors.php` for mobile-origin compatibility
- [ ] Native apps don't trigger CORS (no Origin header), but webview-based mobile may
- [ ] Document expected behavior

### [ ] 5.2 Rate limiting strategy for mobile  (decision + ~1 hr)
- [ ] Current rate limits are mostly per-IP — bad for mobile users on shared NAT
- [ ] Switch hot endpoints to per-user/per-token where the user is authenticated
- [ ] Tests

### [ ] 5.3 Realtime auth (Pusher/Reverb) for native clients  (~2–3 hr)
- [ ] Native Pusher/Reverb SDKs need an auth endpoint for private channels
- [ ] Confirm `/broadcasting/auth` works with Sanctum tokens (or add `/api/broadcasting/auth`)
- [ ] Test from a native SDK or document the endpoint contract

### [ ] 5.4 API Resources backfill (~10 missing domains)  (~2–3 hr)
- [ ] Audit which API endpoints return raw Eloquent vs. Resources
- [ ] Add: `ProfileResource`, `AffiliateResource`, `LessonResource`, `KycStatusResource`, `BadgeDetailsResource`, `TicketResource`, `CouponResource`, `EventResource`, etc.
- [ ] Update controllers to use them — locks the JSON contract for mobile

### [ ] 5.5 Localization / i18n decision  (decision)
- [ ] **Decide**: does mobile app support multiple languages?
- [ ] If yes: `GET /api/translations?locale=xx` or use mobile-side translation files
- [ ] Confirm error messages translate based on `Accept-Language` header

### [ ] 5.6 Settings / feature flags API  (decision + ~1 hr if implementing)
- [ ] **Decide**: do we want server-side feature flags for mobile rollouts?
- [ ] If yes: `GET /api/app/config` returning flags + values
- [ ] Tests

---

## Cross-cutting

- [ ] Pre-existing test failure to fix: `tests/Feature/Queries/CalculateEligibilityTest::test_for_owner_can_submit_new_request_after_rejection` (NOT caused by Curzzo work — failed on `main` before)

---

## How to run

```bash
# Single test class
vendor/bin/phpunit tests/Feature/Api/CurzzoControllerTest.php

# Full suite (needs 2GB memory)
php -d memory_limit=2G vendor/bin/phpunit

# Specific filter
vendor/bin/phpunit --filter test_chat_persists_messages

# Routes
php artisan route:list --path=curzzo
```

## Conventions to copy when adding the next batch

- **Actions**: one class per use case in `app/Actions/{Domain}/`. Constructor-inject services. `execute()` method. Throw `ValidationException` for business-rule failures, `HttpResponseException` when you need a specific JSON shape.
- **Form Requests**: flat in `app/Http/Requests/`, named `{Verb}{Noun}Request.php`. `authorize()` does policy check via `$this->user()?->can('update', $community)`. `rules()` returns the array.
- **API Resources**: flat in `app/Http/Resources/`. Use `$this->when(...)` to gate sensitive fields by viewer permission.
- **API tests**: `tests/Feature/Api/{Controller}Test.php`. Use `actingAs($user, 'sanctum')` and `postJson/getJson/patchJson/deleteJson`.
- **Web characterization tests**: `tests/Feature/Web/{Controller}Test.php`. Write BEFORE extracting logic.
- **POST/store** endpoints returning a `JsonResource` of a freshly-created model auto-respond with **201**, not 200.
- **Result DTOs** for multi-status endpoints (like Curzzo's `ChatResult` with status + body) — let both Web and API wrap response identically.
