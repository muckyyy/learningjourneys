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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('validity_days')->nullable()->comment('Number of days the certificate remains valid after issuance');
            $table->string('page_size', 32)->default('A4');
            $table->string('orientation', 16)->default('portrait');
            $table->unsignedInteger('page_width_mm')->default(210);
            $table->unsignedInteger('page_height_mm')->default(297);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
