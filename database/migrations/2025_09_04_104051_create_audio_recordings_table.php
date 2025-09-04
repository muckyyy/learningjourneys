<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAudioRecordingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audio_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('journey_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('journey_step_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable();
            $table->string('file_path')->nullable(); // Store path to audio file
            $table->text('transcription')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->decimal('processing_cost', 10, 6)->default(0);
            $table->integer('duration_seconds')->nullable();
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->json('metadata')->nullable(); // Store additional data like chunk info, etc.
            $table->timestamps();
            
            $table->index(['user_id', 'journey_attempt_id']);
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audio_recordings');
    }
}
