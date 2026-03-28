<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function createInvoice(array $data): array;

    public function getInvoice(string $invoiceId): array;

    public function createPayout(array $data): array;

    public function getPayout(string $payoutId): array;

    public function getBalance(string $accountType = 'CASH'): float;

    public static function collectionFee(string $channel, float $gross): float;

    public function verifyCallbackToken(?string $token): bool;
}
