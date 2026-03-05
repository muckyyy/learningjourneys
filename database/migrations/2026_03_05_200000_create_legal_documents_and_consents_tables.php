<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type');           // terms_of_service, privacy_policy, cookie_policy
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');         // HTML content
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_required')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('legal_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepted')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'legal_document_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_consents');
        Schema::dropIfExists('legal_documents');
    }
};
