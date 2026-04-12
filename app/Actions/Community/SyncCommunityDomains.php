<?php

namespace App\Actions\Community;

use App\Jobs\ProvisionCustomDomain;
use App\Jobs\RemoveCustomDomain;
use App\Models\Community;

class SyncCommunityDomains
{
    public function execute(Community $community, ?string $oldSubdomain, ?string $oldCustomDomain): void
    {
        $fresh       = $community->fresh();
        $appHost     = parse_url(config('app.url'), PHP_URL_HOST) ?? 'curzzo.com';
        $baseDomain  = explode(':', $appHost)[0];

        $newSubdomain = $fresh->subdomain;
        if ($oldSubdomain !== $newSubdomain) {
            if ($oldSubdomain) {
                RemoveCustomDomain::dispatch("{$oldSubdomain}.{$baseDomain}");
            }
            if ($newSubdomain) {
                ProvisionCustomDomain::dispatch("{$newSubdomain}.{$baseDomain}");
            }
        }

        $newCustomDomain = $fresh->custom_domain;
        if ($oldCustomDomain !== $newCustomDomain) {
            if ($oldCustomDomain) {
                RemoveCustomDomain::dispatch($oldCustomDomain);
            }
            if ($newCustomDomain) {
                // DNS propagation needs time before cert request succeeds.
                ProvisionCustomDomain::dispatch($newCustomDomain)->delay(now()->addMinutes(2));
            }
        }
    }
}
