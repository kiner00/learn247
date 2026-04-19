<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    private const SUPER_ADMINS = [
        'kinermercurio@gmail.com',
        'clivellora05@gmail.com',
    ];

    public function run(): void
    {
        User::whereIn('email', self::SUPER_ADMINS)
            ->update(['is_super_admin' => true]);

        $found = User::whereIn('email', self::SUPER_ADMINS)->pluck('email');
        $missing = array_diff(self::SUPER_ADMINS, $found->toArray());

        foreach ($missing as $email) {
            $this->command->warn("Super admin not set — user not found: {$email}");
        }

        $this->command->info('Super admins updated: '.$found->implode(', '));
    }
}
