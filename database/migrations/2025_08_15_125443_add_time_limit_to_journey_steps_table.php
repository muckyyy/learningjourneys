<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeLimitToJourneyStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journey_steps', function (Blueprint $table) {
            $table->integer('time_limit')->nullable()->after('maxattempts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journey_steps', function (Blueprint $table) {
            $table->dropColumn('time_limit');
        });
    }
}
