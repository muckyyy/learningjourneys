<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificate_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->text('text_content')->nullable();
            $table->string('variable_key', 64)->nullable();
            $table->string('asset_path')->nullable();
            $table->unsignedInteger('sorting')->default(0);
            $table->decimal('position_x', 8, 2)->default(0);
            $table->decimal('position_y', 8, 2)->default(0);
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->json('fpdf_settings')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['certificate_id', 'sorting']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_elements');
    }
};
