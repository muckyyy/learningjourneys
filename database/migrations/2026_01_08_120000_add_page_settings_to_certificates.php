<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('page_size', 32)->default('A4')->after('validity_days');
            $table->string('orientation', 16)->default('portrait')->after('page_size');
            $table->unsignedInteger('page_width_mm')->default(210)->after('orientation');
            $table->unsignedInteger('page_height_mm')->default(297)->after('page_width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['page_size', 'orientation', 'page_width_mm', 'page_height_mm']);
        });
    }
};
