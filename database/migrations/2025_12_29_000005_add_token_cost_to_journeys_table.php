<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            if (!Schema::hasColumn('journeys', 'token_cost')) {
                $table->unsignedInteger('token_cost')->default(0)->after('recordtime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            if (Schema::hasColumn('journeys', 'token_cost')) {
                $table->dropColumn('token_cost');
            }
        });
    }
};
