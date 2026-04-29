<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropUnique(['community_id', 'user_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('scope', 20)->default('community')->after('user_id')->index();
            $table->foreignId('community_id')->nullable()->change();
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();
            $table->unique(['community_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropUnique(['community_id', 'user_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
            $table->foreignId('community_id')->nullable(false)->change();
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();
            $table->unique(['community_id', 'user_id']);
        });
    }
};
