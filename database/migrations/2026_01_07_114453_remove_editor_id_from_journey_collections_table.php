<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journey_collections', function (Blueprint $table) {
            if (Schema::hasColumn('journey_collections', 'editor_id')) {
                $table->dropForeign(['editor_id']);
                $table->dropColumn('editor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journey_collections', function (Blueprint $table) {
            $table->foreignId('editor_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
