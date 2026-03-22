<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('visibility')->default('public')->after('is_members_only'); // public | free | paid
        });

        // Migrate existing data: members-only → paid, public → public
        DB::table('events')->where('is_members_only', true)->update(['visibility' => 'paid']);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_members_only');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_members_only')->default(false)->after('cover_image');
        });

        DB::table('events')->where('visibility', '!=', 'public')->update(['is_members_only' => true]);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
