<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('access_type', ['free', 'inclusive', 'paid_once'])->default('inclusive')->after('position');
            $table->decimal('price', 10, 2)->nullable()->after('access_type');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['access_type', 'price']);
        });
    }
};
