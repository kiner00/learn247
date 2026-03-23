<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE courses MODIFY COLUMN access_type ENUM('free','inclusive','paid_once','paid_monthly','member_once') NOT NULL DEFAULT 'inclusive'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE courses MODIFY COLUMN access_type ENUM('free','inclusive','paid_once','paid_monthly') NOT NULL DEFAULT 'inclusive'");
    }
};
