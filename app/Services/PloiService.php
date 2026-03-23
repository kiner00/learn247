<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PloiService
{
    private string $serverId;
    private string $siteId;

    public function __construct()
    {
        $this->serverId = (string) config('services.ploi.server_id');
        $this->siteId   = (string) config('services.ploi.site_id');
    }

    // ─── Tenant (custom domain) management ────────────────────────────────────

    /**
     * Add a custom domain as a tenant on the Ploi site.
     * Ploi's tenant system handles nginx server_name + directory setup.
     */
    public function addTenant(string $domain): Response
    {
        return $this->client()
            ->post($this->siteUrl('tenants'), ['domain' => $domain])
            ->throw();
    }

    /**
     * Remove a previously-added tenant domain.
     */
    public function removeTenant(string $domain): Response
    {
        return $this->client()
            ->delete($this->siteUrl("tenants/{$domain}"))
            ->throw();
    }

    /**
     * Request a Let's Encrypt certificate for a tenant domain.
     * Call this after addTenant — Ploi provisions the cert via ACME.
     */
    public function requestTenantCertificate(string $domain): Response
    {
        return $this->client()
            ->post($this->siteUrl("tenants/{$domain}/request-certificate"))
            ->throw();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function client(): PendingRequest
    {
        $token = config('services.ploi.token');

        if (! $token) {
            throw new RuntimeException('PLOI_API_TOKEN is not configured.');
        }

        return Http::baseUrl('https://ploi.io/api')
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }

    private function siteUrl(string $path): string
    {
        return "/servers/{$this->serverId}/sites/{$this->siteId}/{$path}";
    }
}
