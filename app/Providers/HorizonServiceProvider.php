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
        Horizon::auth(fn () => true);
    }
}
