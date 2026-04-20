<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('trial_mode', 16)->default('none')->after('billing_type'); // none | per_user | window
            $table->unsignedSmallInteger('trial_days')->nullable()->after('trial_mode');
            $table->timestamp('free_until')->nullable()->after('trial_days');
            $table->decimal('first_month_price', 10, 2)->nullable()->after('price'); // null = charge `price` on first cycle too
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['trial_mode', 'trial_days', 'free_until', 'first_month_price']);
        });
    }
};
