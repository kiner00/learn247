<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->unsignedInteger('bounced')->default(0);
            $table->unsignedInteger('complained')->default(0);
            $table->unsignedInteger('unsubscribed')->default(0);

            $table->unique(['community_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_daily_stats');
    }
};
