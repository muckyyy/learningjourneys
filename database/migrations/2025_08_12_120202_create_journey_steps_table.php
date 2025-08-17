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
            $table->json('config')->nullable(); // Store step-specific configuration
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
