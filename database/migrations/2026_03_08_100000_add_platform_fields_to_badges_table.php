<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->enum('type', ['member', 'creator'])->nullable()->after('key');
            $table->text('how_to_earn')->nullable()->after('description');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('condition_value');
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['key', 'type', 'how_to_earn', 'sort_order']);
        });
    }
};
