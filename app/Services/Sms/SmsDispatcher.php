<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Models\Community;

class SmsDispatcher implements SmsProvider
{
    public const PROVIDER_SEMAPHORE = 'semaphore';

    public const PROVIDER_XTREME = 'xtreme_sms';

    public const PROVIDER_PHILSMS = 'philsms';

    public const PROVIDERS = [
        self::PROVIDER_SEMAPHORE => 'Semaphore',
        self::PROVIDER_PHILSMS => 'PhilSMS',
        self::PROVIDER_XTREME => 'Xtreme SMS',
    ];

    private array $providerMap;

    public function __construct(
        SemaphoreProvider $semaphore,
        PhilSmsProvider $philSms,
        XtremeSmsProvider $xtremeSms,
    ) {
        $this->providerMap = [
            self::PROVIDER_SEMAPHORE => $semaphore,
            self::PROVIDER_PHILSMS => $philSms,
            self::PROVIDER_XTREME => $xtremeSms,
        ];
    }

    public function blast(Community $community, array $numbers, string $message): array
    {
        if (empty($numbers)) {
            return ['sent' => 0, 'failed' => 0, 'errors' => []];
        }

        $provider = $this->providerMap[$community->sms_provider] ?? null;

        if (! $provider) {
            throw new \RuntimeException('No SMS provider configured.');
        }

        return $provider->send($community, $numbers, $message);
    }
}
