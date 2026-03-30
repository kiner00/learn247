<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return request()->query('key') === 'curzzo-horizon-2026';
        });
    }
}
