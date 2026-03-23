<?php

namespace App\Jobs;

use App\Services\PloiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RemoveCustomDomain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $domain) {}

    public function handle(PloiService $ploi): void
    {
        Log::info("Removing custom domain tenant: {$this->domain}");

        // Removing the tenant also removes the nginx block + cert via Ploi.
        $ploi->removeTenant($this->domain);

        Log::info("Custom domain removed: {$this->domain}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Failed to remove custom domain {$this->domain}: {$e->getMessage()}");
    }
}
