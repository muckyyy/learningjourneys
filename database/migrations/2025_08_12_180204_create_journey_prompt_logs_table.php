<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJourneyPromptLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journey_prompt_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('journey_step_response_id')->nullable()->constrained()->onDelete('cascade');
            $table->longText('prompt'); // The prompt sent to AI
            $table->longText('response'); // The response received from AI
            $table->json('metadata')->nullable(); // AI model info, tokens used, processing time, etc.
            $table->string('ai_model')->nullable(); // e.g., 'gpt-4', 'claude-3', etc.
            $table->integer('tokens_used')->nullable();
            $table->decimal('processing_time_ms', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journey_prompt_logs');
    }
}
