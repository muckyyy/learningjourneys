<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiFieldsToJourneyStepResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journey_step_responses', function (Blueprint $table) {
            $table->longText('user_input')->nullable()->after('journey_step_id');
            $table->longText('ai_response')->nullable()->after('user_input');
            $table->string('interaction_type')->default('text')->after('ai_response'); // text, voice, rating
            $table->json('ai_metadata')->nullable()->after('interaction_type'); // Store AI model info, processing time, etc.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journey_step_responses', function (Blueprint $table) {
            $table->dropColumn(['user_input', 'ai_response', 'interaction_type', 'ai_metadata']);
        });
    }
}
