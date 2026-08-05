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
        Schema::create('test_question_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('test_id')->index();
            $table->string('test_section_id')->nullable()->index();
            $table->string('question_id')->index();
            $table->string('status')->default('not_reviewed'); // not_reviewed, reviewed_ok, needs_revision
            $table->string('field')->nullable(); // stem, choices, correct_answer, explanation, media, general
            $table->text('comment')->nullable();
            $table->string('severity')->default('warning'); // warning, critical
            $table->string('reviewer_id')->nullable();
            $table->timestamps();

            $table->unique(['test_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_question_reviews');
    }
};
