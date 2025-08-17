<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJourneyStepResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journey_step_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('journey_step_id')->constrained()->onDelete('cascade');
            $table->text('step_action')->nullable(); 
            $table->integer('step_rate')->nullable(); 
            $table->json('response_data');
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('submitted_at');
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
        Schema::dropIfExists('journey_step_responses');
    }
}
