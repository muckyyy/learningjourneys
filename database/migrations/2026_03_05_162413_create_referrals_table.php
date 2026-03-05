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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('has_paid')->default(false);
            $table->timestamp('first_payment_at')->nullable();
            $table->boolean('rewarded')->default(false);
            $table->timestamps();

            $table->unique('referred_id'); // a user can only be referred once
            $table->index(['referrer_id', 'has_paid', 'rewarded']);
        });

        // Add referred_by to users table if it doesn't already exist
        if (! Schema::hasColumn('users', 'referred_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('referred_by')->nullable()
                      ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'referred_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['referred_by']);
                $table->dropColumn('referred_by');
            });
        }
        Schema::dropIfExists('referrals');
    }
};
