<?php

namespace App\Actions\Billing;

use App\Actions\Billing\WebhookHandlers\HandleCertificationPurchasePaid;
use App\Actions\Billing\WebhookHandlers\HandleCourseEnrollmentPaid;
use App\Actions\Billing\WebhookHandlers\HandleCreatorPlanPaid;
use App\Actions\Billing\WebhookHandlers\HandleSubscriptionPaid;
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

    public function __construct(
        private readonly XenditService $xendit,
        HandleCourseEnrollmentPaid $courseEnrollment,
        HandleCertificationPurchasePaid $certificationPurchase,
        HandleCreatorPlanPaid $creatorPlan,
        HandleSubscriptionPaid $subscription,
    ) {
        $this->handlers = [
            $courseEnrollment,
            $certificationPurchase,
            $creatorPlan,
            $subscription,
        ];
    }

    /**
     * Verify, deduplicate, and process a Xendit invoice webhook.
     *
     * Idempotency key: "{invoice_id}_{STATUS}" ensures each invoice x status
     * transition is processed exactly once.
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
        $event   = $raw['event'] ?? null;
        $payload = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : $raw;

        // Skip non-invoice events (e.g. disbursement.completed, balance.updated)
        if ($event && ! str_starts_with($event, 'invoice.') && ! str_starts_with($event, 'payment.')) {
            Log::info('Xendit webhook: skipping non-invoice event', ['event' => $event]);
            return;
        }

        $status   = $payload['status']  ?? 'UNKNOWN';
        $xenditId = $payload['id']      ?? null;
        $eventId  = $xenditId ? "{$xenditId}_{$status}" : null;

        Log::info('Xendit webhook received', compact('xenditId', 'status', 'eventId', 'event'));

        // -- 2. Idempotency guard --
        if ($eventId && Payment::where('xendit_event_id', $eventId)->exists()) {
            Log::info('Xendit webhook: already processed', compact('eventId'));
            return;
        }

        // -- 3. Dispatch to the first matching handler --
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
}
