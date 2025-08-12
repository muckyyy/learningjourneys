<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfileFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('profile_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Display name for the field
            $table->string('short_name')->unique(); // Short name/key for the field
            $table->enum('input_type', ['text', 'number', 'textarea', 'select', 'select_multiple']);
            $table->json('options')->nullable(); // For select and select_multiple, store options as JSON
            $table->boolean('required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
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
        Schema::dropIfExists('profile_fields');
    }
}
