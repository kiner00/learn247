<?php

namespace App\Actions\Billing;

use App\Models\CertificationPurchase;
use App\Models\Community;
use App\Models\CourseCertification;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Validation\ValidationException;

class CheckoutCertification
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{purchase: CertificationPurchase, checkout_url: string}
     */
    public function execute(User $user, Community $community, CourseCertification $certification, string $successRedirectUrl): array
    {
        if ($certification->isFree()) {
            throw ValidationException::withMessages(['certification' => 'This certification does not require payment.']);
        }

        // Check for existing paid purchase
        $existing = CertificationPurchase::where('user_id', $user->id)
            ->where('certification_id', $certification->id)
            ->where('status', CertificationPurchase::STATUS_PAID)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['certification' => 'You already purchased this certification.']);
        }

        $externalId = "cert_{$certification->id}_{$user->id}_" . time();

        $invoice = $this->xendit->createInvoice([
            'external_id' => $externalId,
            'amount'      => (float) $certification->price,
            'currency'    => $community->currency ?? 'PHP',
            'description' => "Certification: {$certification->title}",
            'customer'    => ['given_names' => $user->name, 'email' => $user->email],
            'customer_notification_preference' => [
                'invoice_created' => ['email'],
                'invoice_paid'    => ['email'],
            ],
            'success_redirect_url' => $successRedirectUrl,
            'failure_redirect_url' => $successRedirectUrl,
            'items' => [[
                'name'     => $certification->title,
                'quantity' => 1,
                'price'    => (float) $certification->price,
                'category' => 'Certification',
            ]],
        ]);

        // Resolve affiliate from user's active subscription in this community
        $affiliateId = Subscription::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->whereNotNull('affiliate_id')
            ->value('affiliate_id');

        $purchase = CertificationPurchase::updateOrCreate(
            ['user_id' => $user->id, 'certification_id' => $certification->id],
            ['affiliate_id' => $affiliateId, 'xendit_id' => $invoice['id'], 'status' => CertificationPurchase::STATUS_PENDING, 'paid_at' => null],
        );

        return ['purchase' => $purchase, 'checkout_url' => $invoice['invoice_url']];
    }
}
