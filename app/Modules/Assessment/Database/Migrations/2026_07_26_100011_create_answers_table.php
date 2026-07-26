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
        Schema::create('answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignUlid('selected_choice_id')->nullable()->constrained('question_choices')->nullOnDelete();
            $table->text('text_response')->nullable();
            $table->string('audio_response_url')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score_earned', 8, 2)->default(0);
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
