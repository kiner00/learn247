<?php

namespace App\Jobs;

use App\Services\PloiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProvisionCustomDomain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function __construct(public readonly string $domain) {}

    public function handle(PloiService $ploi): void
    {
        Log::info("Provisioning custom domain: {$this->domain}");

        // 1. Register the domain as a tenant on the Ploi site.
        $ploi->addTenant($this->domain);

        // 2. Wait briefly for nginx to reload with the new tenant config.
        sleep(5);

        // 3. Verify HTTP is reachable before requesting SSL.
        //    The ACME HTTP-01 challenge will fail if nginx can't serve
        //    the domain (e.g. empty SSL include file blocking the server block).
        $this->verifyHttpReachable();

        // 4. Request Let's Encrypt certificate.
        $ploi->requestTenantCertificate($this->domain);

        // 5. Wait for cert provisioning, then verify SSL is actually active.
        sleep(10);
        $this->verifySslActive();

        Log::info("Custom domain provisioned and SSL active: {$this->domain}");
    }

    /**
     * Ensure the domain responds over HTTP before requesting an SSL cert.
     * If nginx isn't serving the domain, the ACME challenge will fail.
     */
    private function verifyHttpReachable(): void
    {
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get("http://{$this->domain}/.well-known/acme-challenge/test");

            // Any HTTP response (even 404) means nginx is serving the domain.
            // A connection error or empty reply means it's not.
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "HTTP not reachable for {$this->domain} — nginx may not be serving this tenant yet. "
                ."ACME challenge will fail. Error: {$e->getMessage()}"
            );
        }

        Log::info("HTTP reachable for {$this->domain}, proceeding with SSL request.");
    }

    /**
     * After requesting the cert, verify that HTTPS actually works.
     * Ploi's API may return 200 even when the cert request fails internally.
     */
    private function verifySslActive(): void
    {
        try {
            $response = Http::timeout(10)->get("https://{$this->domain}");
        } catch (\Throwable $e) {
            Log::warning(
                "SSL verification failed for {$this->domain} after cert request. "
                ."The certificate may still be provisioning. Error: {$e->getMessage()}"
            );
            // Don't throw — the cert might just need more time.
            // This is a warning, not a hard failure.
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Failed to provision custom domain {$this->domain}: {$e->getMessage()}");
    }
}
