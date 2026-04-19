<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->whereNull('username')->get(['id', 'name']);

        foreach ($users as $user) {
            $parts = explode(' ', trim($user->name), 2);
            $first = $parts[0] ?? 'user';
            $last = $parts[1] ?? '';

            $slug = fn (string $s): string => trim(
                preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($s)))),
                '-'
            );

            $first = $slug($first) ?: 'user';
            $last = $slug($last);
            $base = $last ? "{$first}-{$last}" : $first;
            $username = "{$base}-{$user->id}";

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        // Not reversible — backfill only
    }
};
