<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJourneyTypeToJourneyAttemptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journey_attempts', function (Blueprint $table) {
            $table->enum('journey_type', ['attempt', 'preview'])->default('attempt')->after('journey_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journey_attempts', function (Blueprint $table) {
            $table->dropColumn('journey_type');
        });
    }
}
