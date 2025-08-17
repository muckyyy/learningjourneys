<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSeparateTokensToJourneyPromptLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journey_prompt_logs', function (Blueprint $table) {
            // Add separate token columns
            $table->integer('request_tokens')->nullable()->after('tokens_used');
            $table->integer('response_tokens')->nullable()->after('request_tokens');
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
            $table->dropColumn(['request_tokens', 'response_tokens']);
        });
    }
}
