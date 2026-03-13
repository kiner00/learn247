<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BadgeSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->call([
                DevSeeder::class,
                PayoutTestSeeder::class,
                AffiliatePayoutTestSeeder::class,
            ]);
        }
    }
}
