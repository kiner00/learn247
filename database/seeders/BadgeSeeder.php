<?php

namespace Database\Seeders;

use App\Services\BadgeService;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        BadgeService::seedDefaults();
    }
}
