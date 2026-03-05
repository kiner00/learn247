<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_members', function (Blueprint $table) {
            $table->json('notif_prefs')->nullable()->after('points');
            $table->boolean('chat_enabled')->default(true)->after('notif_prefs');
        });
    }

    public function down(): void
    {
        Schema::table('community_members', function (Blueprint $table) {
            $table->dropColumn(['notif_prefs', 'chat_enabled']);
        });
    }
};
