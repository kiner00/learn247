<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_invites', function (Blueprint $table) {
            $table->unsignedTinyInteger('free_access_months')->nullable()->after('expires_at'); // null = forever
        });
    }

    public function down(): void
    {
        Schema::table('community_invites', function (Blueprint $table) {
            $table->dropColumn('free_access_months');
        });
    }
};
