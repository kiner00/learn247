<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->upsert([
            ['key' => 'creator_plan_regular_price',    'value' => '3000', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'creator_plan_discounted_price', 'value' => '1999', 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['value', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'creator_plan_regular_price',
            'creator_plan_discounted_price',
        ])->delete();
    }
};
