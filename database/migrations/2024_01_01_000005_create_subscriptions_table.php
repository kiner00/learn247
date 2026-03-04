<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | active | expired | cancelled
            $table->string('xendit_id')->nullable()->unique();
            $table->string('xendit_invoice_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('community_id');
            $table->index('user_id');
            $table->index('xendit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
