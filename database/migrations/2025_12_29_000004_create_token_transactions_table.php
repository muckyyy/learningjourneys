<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_token_grant_id')->nullable()->constrained('user_token_grants')->nullOnDelete();
            $table->foreignId('token_purchase_id')->nullable()->constrained('token_purchases')->nullOnDelete();
            $table->foreignId('journey_id')->nullable()->constrained('journeys')->nullOnDelete();
            $table->foreignId('journey_attempt_id')->nullable()->constrained('journey_attempts')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('balance_after')->default(0);
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_transactions');
    }
};
