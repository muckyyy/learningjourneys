<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateActionTypeEnumInJourneyPromptLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journey_prompt_logs', function (Blueprint $table) {
            // Drop and recreate the action_type column with expanded enum values
            $table->dropColumn('action_type');
        });
        
        Schema::table('journey_prompt_logs', function (Blueprint $table) {
            $table->enum('action_type', [
                'start_chat', 
                'submit_chat', 
                'submit_report',
                'evaluate_rating',
                'generate_response'
            ])->after('journey_step_response_id')->default('submit_chat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journey_prompt_logs', function (Blueprint $table) {
            $table->dropColumn('action_type');
        });
        
        Schema::table('journey_prompt_logs', function (Blueprint $table) {
            $table->enum('action_type', ['start_chat', 'submit_chat', 'submit_report'])
                  ->after('journey_step_response_id')
                  ->default('submit_chat');
        });
    }
}
