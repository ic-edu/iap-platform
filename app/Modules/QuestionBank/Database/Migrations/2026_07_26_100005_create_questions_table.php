<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->text('passage_text')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('image_url')->nullable();
            $table->text('prompt');
            $table->string('section')->default('reading');
            $table->integer('part_number')->nullable();
            $table->string('question_type')->default('multiple_choice');
            $table->integer('points')->default(1);
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
