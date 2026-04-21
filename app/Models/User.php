<?php

namespace App\Models;

use App\Services\XenditService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const KYC_NONE = 'none';

    public const KYC_SUBMITTED = 'submitted';

    public const KYC_APPROVED = 'approved';

    public const KYC_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'username', 'bio', 'email', 'password', 'needs_password_setup', 'phone', 'is_super_admin', 'is_active',
        'timezone', 'theme', 'notification_prefs', 'chat_prefs',
        'avatar', 'location', 'social_links', 'hide_from_search',
        'payout_method', 'payout_details', 'bank_name',
        'crypto_wallet', 'crz_token_balance',
        'kyc_verified_at', 'kyc_status', 'kyc_id_document', 'kyc_selfie',
        'kyc_submitted_at', 'kyc_rejected_reason', 'kyc_ai_result', 'kyc_ai_rejections',
        'xendit_customer_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'needs_password_setup' => 'boolean',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'notification_prefs' => 'array',
            'chat_prefs' => 'array',
            'social_links' => 'array',
            'hide_from_search' => 'boolean',
            'crz_token_balance' => 'decimal:8',
            'kyc_verified_at' => 'datetime',
            'kyc_submitted_at' => 'datetime',
            'kyc_ai_result' => 'array',
            'kyc_ai_rejections' => 'integer',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_verified_at !== null || $this->kyc_status === self::KYC_APPROVED;
    }

    public function sendEmailVerificationNotification(): void
    {
        \Illuminate\Support\Facades\Mail::to($this->email)
            ->send(new \App\Mail\VerifyEmailMail($this));
    }

    public function ownedCommunities(): HasMany
    {
        return $this->hasMany(Community::class, 'owner_id');
    }

    public function communityMemberships(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creatorSubscriptions(): HasMany
    {
        return $this->hasMany(CreatorSubscription::class);
    }

    /** Returns 'free', 'basic', or 'pro'. */
    public function creatorPlan(): string
    {
        if ($this->is_super_admin) {
            return CreatorSubscription::PLAN_PRO;
        }

        $sub = $this->creatorSubscriptions()
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->first();

        return $sub?->plan ?? 'free';
    }

    public function hasActiveCreatorPlan(): bool
    {
        return $this->creatorPlan() !== 'free';
    }

    /**
     * Ensure this user has a Xendit customer record. Creates one if needed.
     */
    public function ensureXenditCustomer(XenditService $xendit): string
    {
        if ($this->xendit_customer_id) {
            return $this->xendit_customer_id;
        }

        $customer = $xendit->createCustomer([
            'reference_id' => "user_{$this->id}",
            'email' => $this->email,
            'type' => 'INDIVIDUAL',
            'individual_detail' => [
                'given_names' => $this->name,
            ],
        ]);

        $this->update(['xendit_customer_id' => $customer['id']]);

        return $customer['id'];
    }
}
