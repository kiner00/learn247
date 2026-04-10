<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'subscriptions',
        'creator_subscriptions',
        'course_enrollments',
        'curzzo_purchases',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('xendit_plan_id')->nullable()->index();
                $blueprint->string('xendit_customer_id')->nullable();
                $blueprint->string('recurring_status')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['xendit_plan_id']);
                $blueprint->dropColumn(['xendit_plan_id', 'xendit_customer_id', 'recurring_status']);
            });
        }
    }
};
