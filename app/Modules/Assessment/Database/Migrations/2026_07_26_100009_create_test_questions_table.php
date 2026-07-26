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
        Schema::create('test_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('test_section_id')->constrained('test_sections')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->integer('points')->default(1);
            $table->timestamps();

            $table->unique(['test_section_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_questions');
    }
};
