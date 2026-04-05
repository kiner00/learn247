<?php

namespace App\Contracts;

use App\Models\Community;

interface EmailProvider
{
    /**
     * Validate the API key is correct.
     */
    public function validateApiKey(Community $community): bool;

    /**
     * Send a single email.
     *
     * @param  array{from: string, to: array, subject: string, html: string, reply_to?: array}  $params
     * @return array{id: string|null}
     */
    public function sendEmail(Community $community, array $params): array;

    /**
     * Send a batch of emails (up to 100).
     *
     * @param  array<array{from: string, to: array, subject: string, html: string}>  $emails
     * @return array<array{id: string|null}>
     */
    public function sendBatch(Community $community, array $emails): array;

    /**
     * Add a domain for verification.
     *
     * @return array{id: string, status: string, records: array}
     */
    public function addDomain(Community $community, string $domain): array;

    /**
     * Get domain details including DNS records.
     *
     * @return array{id: string, name: string, status: string, records: array}
     */
    public function getDomain(Community $community, string $domainId): array;

    /**
     * Trigger domain verification.
     *
     * @return array{id: string, status: string}
     */
    public function verifyDomain(Community $community, string $domainId): array;

    /**
     * Return the provider name identifier.
     */
    public static function id(): string;

    /**
     * Return the human-readable provider name.
     */
    public static function label(): string;
}
