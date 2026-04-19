<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Community extends Model
{
    use HasFactory, SoftDeletes;

    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_ONE_TIME = 'one_time';

    protected $fillable = [
        'name', 'slug', 'subdomain', 'custom_domain', 'owner_id', 'description', 'category',
        'avatar', 'cover_image', 'is_private', 'price', 'currency',
        'billing_type', 'affiliate_commission_rate',
        'facebook_pixel_id', 'tiktok_pixel_id', 'google_analytics_id',
        'telegram_bot_token', 'telegram_chat_id',
        'sms_provider', 'sms_api_key', 'sms_api_secret', 'sms_sender_name', 'sms_device_url',
        'resend_api_key', 'email_provider', 'resend_from_email', 'resend_from_name', 'resend_reply_to',
        'resend_domain_id', 'resend_domain_status',
        'deletion_requested_at', 'is_featured', 'landing_page', 'ai_chatbot_instructions',
        'brand_context',
        'curzzo_topup_packs',
    ];

    protected $appends = ['gallery_images'];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'is_featured' => 'boolean',
            'price' => 'decimal:2',
            'affiliate_commission_rate' => 'integer',
            'landing_page' => 'array',
            'brand_context' => 'array',
            'curzzo_topup_packs' => 'array',
            'deletion_requested_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ─── Encrypted secrets ────────────────────────────────────────────────────
    // Accessors decrypt lazily and return null if the stored value is plaintext
    // or was encrypted with a different APP_KEY, so a single bad row never 500s
    // a list view that hydrates many communities.

    protected function telegramBotToken(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => self::safeDecrypt($v),
            set: fn ($v) => $v === null || $v === '' ? null : Crypt::encryptString($v),
        );
    }

    protected function resendApiKey(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => self::safeDecrypt($v),
            set: fn ($v) => $v === null || $v === '' ? null : Crypt::encryptString($v),
        );
    }

    private static function safeDecrypt(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return Crypt::decryptString($v);
        } catch (DecryptException) {
            return null;
        }
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(CommunityGalleryItem::class)->orderBy('position');
    }

    public function getGalleryImagesAttribute(): array
    {
        if (! $this->relationLoaded('galleryItems')) {
            $this->load('galleryItems');
        }

        return $this->galleryItems->map(fn (CommunityGalleryItem $item) => [
            'id' => $item->id,
            'type' => $item->type,
            'url' => $item->url,
            'poster_url' => $item->poster_url,
            'hls_url' => $item->video_ready
                ? route('communities.gallery.hls', [
                    'community' => $this->slug,
                    'item' => $item->id,
                    'file' => 'video.m3u8',
                ])
                : null,
            'transcode_status' => $item->transcode_status,
            'transcode_percent' => $item->transcode_percent,
            'video_ready' => $item->video_ready,
            'position' => $item->position,
        ])->values()->all();
    }

    public function members(): HasMany
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

    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class)->orderBy('position');
    }

    public function curzzos(): HasMany
    {
        return $this->hasMany(Curzzo::class)->orderBy('position');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('start_at');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CommunityInvite::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(CourseCertification::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function emailCampaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class);
    }

    public function emailUnsubscribes(): HasMany
    {
        return $this->hasMany(EmailUnsubscribe::class);
    }

    public function emailSequences(): HasMany
    {
        return $this->hasMany(EmailSequence::class);
    }

    public function cartEvents(): HasMany
    {
        return $this->hasMany(CartEvent::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isFree(): bool
    {
        return $this->price <= 0;
    }

    public function hasAffiliateProgram(): bool
    {
        return $this->affiliate_commission_rate !== null && $this->affiliate_commission_rate > 0;
    }

    /** Returns the preferred public URL for this community. */
    public function url(): string
    {
        if ($this->custom_domain) {
            return 'https://'.$this->custom_domain;
        }

        $appUrl = rtrim(config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?? '';
        // Strip port for the subdomain host
        $bareHost = explode(':', $appHost)[0];

        if ($this->subdomain && $bareHost) {
            $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';

            return $scheme.'://'.$this->subdomain.'.'.$bareHost;
        }

        return $appUrl.'/communities/'.$this->slug;
    }

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    public function activeSubscribersCount(): int
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->count();
    }

    /** Flat payout fee in PHP deducted when a creator requests a payout. */
    const PAYOUT_FEE = 15.0;

    /** Platform fee rate per transaction: 9.8% free, 4.9% basic, 2.9% pro. */
    public function platformFeeRate(): float
    {
        return match ($this->owner?->creatorPlan()) {
            'pro' => 0.029,
            'basic' => 0.049,
            default => 0.098,
        };
    }
}
