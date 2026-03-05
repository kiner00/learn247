<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->default('Asia/Manila')->after('bio');
            $table->string('theme')->default('light')->after('timezone');
            $table->json('notification_prefs')->nullable()->after('theme');
            $table->json('chat_prefs')->nullable()->after('notification_prefs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'theme', 'notification_prefs', 'chat_prefs']);
        });
    }
};
