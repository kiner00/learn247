<?php

namespace App\Jobs;

use App\Services\PloiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionCustomDomain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry up to 5 times — DNS propagation can take a few minutes,
     * so we back off before attempting the certificate request.
     */
    public int $tries = 5;

    /**
     * Exponential backoff: 1 min, 5 min, 15 min, 30 min, 60 min.
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function __construct(public readonly string $domain) {}

    public function handle(PloiService $ploi): void
    {
        Log::info("Provisioning custom domain: {$this->domain}");

        // 1. Register the domain as a tenant on the Ploi site.
        //    This updates the nginx server_name block automatically.
        $ploi->addTenant($this->domain);

        // 2. Request Let's Encrypt certificate.
        //    DNS must be pointing at the server before this succeeds —
        //    that's why we have retries with backoff.
        $ploi->requestTenantCertificate($this->domain);

        Log::info("Custom domain provisioned: {$this->domain}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Failed to provision custom domain {$this->domain}: {$e->getMessage()}");
    }
}
