<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('question_generation_batches')) {
            Schema::create('question_generation_batches', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('question_bank_id')->nullable()->constrained('question_banks')->nullOnDelete();
                $table->string('assessment_family', 40)->index();
                $table->foreignUlid('assessment_standard_id')->constrained('assessment_standards')->restrictOnDelete();
                $table->string('standard_version', 30);
                $table->string('planner_type', 50);
                $table->string('planner_strategy_version', 30);
                $table->string('plan_fingerprint', 64)->index();
                $table->string('status', 30)->default('draft')->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('idempotency_key', 120)->unique();
                $table->string('prompt_contract_version', 30)->default('question_generation_v1');
                $table->integer('total_slots')->default(0);
                $table->integer('pending_slots')->default(0);
                $table->integer('processing_slots')->default(0);
                $table->integer('generated_slots')->default(0);
                $table->integer('validated_slots')->default(0);
                $table->integer('failed_slots')->default(0);
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->json('generation_config')->nullable();
                $table->json('plan_snapshot')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_generation_batches');
    }
};
