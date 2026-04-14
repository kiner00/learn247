<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->mediumText('instructions')->change();
        });
    }

    public function down(): void
    {
        Schema::table('curzzos', function (Blueprint $table) {
            $table->text('instructions')->change();
        });
    }
};
