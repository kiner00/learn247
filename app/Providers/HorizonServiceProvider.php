<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        $this->authorization();
    }

    protected function authorization(): void
    {
        Horizon::auth(function ($request) {
            // Temporary: key-based access while debugging Octane session issue
            if ($request->query('key') === 'curzzo-horizon-2026') {
                return true;
            }

            // Normal: super admin check
            return (bool) $request->user()?->is_super_admin;
        });
    }
}
