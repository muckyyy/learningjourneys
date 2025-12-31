<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_token_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('token_bundle_id')->nullable()->constrained('token_bundles')->nullOnDelete();
            $table->foreignId('token_purchase_id')->nullable()->constrained('token_purchases')->nullOnDelete();
            $table->string('source')->default('purchase');
            $table->unsignedInteger('tokens_total');
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('tokens_remaining');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('granted_at')->useCurrent();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_token_grants');
    }
};
