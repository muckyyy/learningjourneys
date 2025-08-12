<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJourneyDebugTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journey_debug', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_step_response_id')->constrained()->onDelete('cascade');
            $table->foreignId('journey_step_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('debug_type')->default('ai_interaction'); // ai_interaction, prompt_processing, token_usage, etc.
            $table->text('prompt_sent')->nullable(); // The prompt that was sent to AI
            $table->longText('ai_response_received')->nullable(); // The full AI response received
            $table->json('request_data')->nullable(); // Full request payload to AI service
            $table->json('response_data')->nullable(); // Full response payload from AI service
            $table->integer('request_tokens')->nullable(); // Tokens used in the request
            $table->integer('response_tokens')->nullable(); // Tokens used in the response
            $table->integer('total_tokens')->nullable(); // Total tokens consumed
            $table->decimal('cost', 10, 6)->nullable(); // Cost of the API call
            $table->string('ai_model')->nullable(); // Which AI model was used (gpt-4, gpt-3.5-turbo, etc.)
            $table->float('processing_time')->nullable(); // Time taken to process in seconds
            $table->string('status')->default('success'); // success, error, timeout, etc.
            $table->text('error_message')->nullable(); // Any error messages
            $table->json('metadata')->nullable(); // Additional debug information
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
        Schema::dropIfExists('journey_debug');
    }
}
