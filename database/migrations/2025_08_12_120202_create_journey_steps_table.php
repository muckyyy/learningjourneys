<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJourneyStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journey_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->longText('content');
            $table->enum('type', ['text', 'video', 'audio', 'image', 'quiz', 'interactive', 'assignment']);
            $table->integer('order');
            $table->integer('ratepass');
            $table->integer('maxattempts');
            $table->integer('time_limit')->nullable();
            $table->integer('maxfollowups')->default(1);
            $table->json('config')->nullable(); // Store step-specific configuration
            $table->text('expected_output')->nullable(); // Store step-specific expected output
            $table->text('expected_output_retry')->nullable(); // Store step-specific expected output for retries
            $table->text('expected_output_followup')->nullable(); // Store step-specific expected output for follow-ups
            $table->text('rating_prompt')->nullable(); // Store step-specific rating prompt
            $table->boolean('is_required')->default(true);
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
        Schema::dropIfExists('journey_steps');
    }
}
