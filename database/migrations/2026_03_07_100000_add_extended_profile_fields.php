<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('bio');
            $table->string('location', 255)->nullable()->after('avatar');
            $table->json('social_links')->nullable()->after('location');
            $table->boolean('hide_from_search')->default(false)->after('social_links');
        });

        Schema::table('community_members', function (Blueprint $table) {
            $table->boolean('show_on_profile')->default(true)->after('chat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'location', 'social_links', 'hide_from_search']);
        });

        Schema::table('community_members', function (Blueprint $table) {
            $table->dropColumn('show_on_profile');
        });
    }
};
