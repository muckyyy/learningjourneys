<?php

use App\Enums\UserRole;
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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'institution_id')) {
                $table->dropForeign(['institution_id']);
            }

            $table->dropColumn(['role', 'institution_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::REGULAR)->after('password');
            $table->foreignId('institution_id')->nullable()->after('role')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->after('institution_id');
        });
    }
};
