<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('token_bundle_id')->constrained('token_bundles')->onDelete('cascade');
            $table->string('payment_provider')->default('virtual_vendor');
            $table->string('provider_reference')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('tokens');
            $table->json('metadata')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_purchases');
    }
};
