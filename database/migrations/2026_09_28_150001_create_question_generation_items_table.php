<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('question_generation_items')) {
            Schema::create('question_generation_items', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('generation_batch_id')->constrained('question_generation_batches')->cascadeOnDelete();
                $table->integer('slot_sequence');
                $table->string('slot_fingerprint', 64)->nullable()->index();
                $table->string('assessment_family', 40)->index();
                $table->foreignUlid('assessment_standard_id')->constrained('assessment_standards')->restrictOnDelete();
                $table->string('standard_version', 30)->nullable();
                $table->string('section', 40)->nullable()->index();
                $table->integer('part_number')->nullable()->index();
                $table->string('task_type', 60)->nullable()->index();
                $table->string('claim', 60)->nullable();
                $table->string('skill', 60)->nullable();
                $table->string('construct', 60)->nullable();
                $table->string('proficiency_target', 30)->nullable();
                $table->string('difficulty', 30)->nullable();
                $table->string('domain', 60)->nullable();
                $table->string('context', 60)->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->integer('attempt_count')->default(0);
                $table->string('last_error_code', 60)->nullable();
                $table->text('last_error_message')->nullable();
                $table->json('prompt_payload')->nullable();
                $table->json('provider_request_metadata')->nullable();
                $table->json('raw_output')->nullable();
                $table->json('normalized_output')->nullable();
                $table->json('validation_result')->nullable();
                $table->foreignUlid('question_id')->nullable()->constrained('questions')->nullOnDelete();
                $table->timestamps();

                $table->unique(['generation_batch_id', 'slot_sequence']);
                $table->index(['generation_batch_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_generation_items');
    }
};
