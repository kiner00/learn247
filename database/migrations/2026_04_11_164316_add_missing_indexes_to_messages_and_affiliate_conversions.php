<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['community_id', 'created_at']);
            $table->index('user_id');
        });

        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['community_id', 'created_at']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->dropIndex(['affiliate_id', 'status']);
            $table->dropIndex(['status', 'paid_at']);
        });
    }
};
