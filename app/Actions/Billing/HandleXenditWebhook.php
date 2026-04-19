<?php

namespace App\Actions\Billing;

use App\Actions\Billing\WebhookHandlers\AbstractRecurringCycleHandler;
use App\Actions\Billing\WebhookHandlers\HandleCertificationPurchasePaid;
use App\Actions\Billing\WebhookHandlers\HandleCourseEnrollmentPaid;
use App\Actions\Billing\WebhookHandlers\HandleCreatorPlanPaid;
use App\Actions\Billing\WebhookHandlers\HandleCurzzoPurchasePaid;
use App\Actions\Billing\WebhookHandlers\HandleCurzzoTopupPaid;
use App\Actions\Billing\WebhookHandlers\HandleSubscriptionPaid;
use App\Actions\Billing\WebhookHandlers\RecurringCourseEnrollmentHandler;
use App\Actions\Billing\WebhookHandlers\RecurringCreatorPlanHandler;
use App\Actions\Billing\WebhookHandlers\RecurringCurzzoPurchaseHandler;
use App\Actions\Billing\WebhookHandlers\RecurringSubscriptionHandler;
use App\Contracts\WebhookHandler;
use App\Models\Payment;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HandleXenditWebhook
{
    /** @var WebhookHandler[] */
    private array $handlers;

    /** @var AbstractRecurringCycleHandler[] */
    private array $recurringHandlers;

    public function __construct(
        private readonly XenditService $xendit,
        HandleCourseEnrollmentPaid $courseEnrollment,
        HandleCertificationPurchasePaid $certificationPurchase,
        HandleCurzzoPurchasePaid $curzzoPurchase,
        HandleCurzzoTopupPaid $curzzoTopup,
        HandleCreatorPlanPaid $creatorPlan,
        HandleSubscriptionPaid $subscription,
        RecurringSubscriptionHandler $recurringSubscription,
        RecurringCreatorPlanHandler $recurringCreatorPlan,
        RecurringCourseEnrollmentHandler $recurringCourseEnrollment,
        RecurringCurzzoPurchaseHandler $recurringCurzzoPurchase,
    ) {
        $this->handlers = [
            $courseEnrollment,
            $certificationPurchase,
            $curzzoPurchase,
            $curzzoTopup,
            $creatorPlan,
            $subscription,
        ];

        $this->recurringHandlers = [
            $recurringCourseEnrollment,
            $recurringCurzzoPurchase,
            $recurringCreatorPlan,
            $recurringSubscription,
        ];
    }

    /**
     * Verify, deduplicate, and process a Xendit webhook.
     *
     * Routes invoice events to existing handlers and recurring events
     * to the Template Method–based recurring handlers.
     *
     * @throws HttpException on invalid callback token
     */
    public function execute(Request $request): void
    {
        // -- 1. Verify callback token --
        if (! $this->xendit->verifyCallbackToken($request->header('x-callback-token'))) {
            Log::warning('Xendit webhook: invalid callback token');
            throw new HttpException(401, 'Invalid Xendit callback token.');
        }

        $raw = $request->all();

        // Xendit v2 event-based format: { event: "invoice.paid", data: { id, status, ... } }
        // Fall back to v1 flat format: { id, status, ... }
        $event = $raw['event'] ?? null;
        $payload = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : $raw;

        // -- 2. Route recurring events to recurring handlers --
        if ($event && str_starts_with($event, 'recurring.')) {
            $this->handleRecurringEvent($event, $payload);

            return;
        }

        // Skip non-invoice events (e.g. disbursement.completed, balance.updated)
        if ($event && ! str_starts_with($event, 'invoice.') && ! str_starts_with($event, 'payment.')) {
            Log::info('Xendit webhook: skipping non-invoice event', ['event' => $event]);

            return;
        }

        $status = $payload['status'] ?? 'UNKNOWN';
        $xenditId = $payload['id'] ?? null;
        $eventId = $xenditId ? "{$xenditId}_{$status}" : null;

        Log::info('Xendit webhook received', compact('xenditId', 'status', 'eventId', 'event'));

        // -- 3. Idempotency guard --
        if ($eventId && Payment::where('xendit_event_id', $eventId)->exists()) {
            Log::info('Xendit webhook: already processed', compact('eventId'));

            return;
        }

        // -- 4. Dispatch to the first matching handler --
        if ($xenditId) {
            foreach ($this->handlers as $handler) {
                if ($handler->matches($xenditId)) {
                    $handler->handle($payload, $eventId, $status);

                    return;
                }
            }
        }

        Log::warning('Xendit webhook: no matching handler', compact('xenditId'));
    }

    /**
     * Route recurring.* events to the appropriate Template Method handler.
     */
    private function handleRecurringEvent(string $event, array $payload): void
    {
        $planId = $payload['plan_id'] ?? $payload['id'] ?? null;

        if (! $planId) {
            Log::warning('Xendit recurring webhook: no plan_id in payload', ['event' => $event]);

            return;
        }

        Log::info('Xendit recurring webhook received', compact('event', 'planId'));

        foreach ($this->recurringHandlers as $handler) {
            if (! $handler->matchesPlan($planId)) {
                continue;
            }

            match ($event) {
                'recurring.cycle.succeeded' => $handler->handleCycleSucceeded($payload),
                'recurring.plan.activated' => $handler->handlePlanActivated($payload),
                'recurring.plan.inactivated' => $handler->handlePlanInactivated($payload),
                default => Log::info('Xendit recurring webhook: ignoring event', ['event' => $event]),
            };

            return;
        }

        Log::warning('Xendit recurring webhook: no matching handler', compact('planId', 'event'));
    }
}
