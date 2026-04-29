<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->upsert([
            ['key' => 'creator_plan_affiliate_commission_rate', 'value' => '20', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'creator_plan_affiliate_max_months',      'value' => '12', 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['value', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'creator_plan_affiliate_commission_rate',
            'creator_plan_affiliate_max_months',
        ])->delete();
    }
};
