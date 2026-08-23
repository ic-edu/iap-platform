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
        Schema::create('content_reset_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('request_number')->unique();
            $table->string('mode')->default('refresh'); // refresh | hard_reset
            $table->string('status')->default('draft'); // draft, audit_pending, audit_completed, awaiting_ceo_approval, awaiting_sa_approval, approved, executing, completed, blocked, rejected, cancelled
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->json('scope');
            $table->json('target_entities')->nullable();
            $table->json('excluded_domains')->nullable();
            $table->string('risk_classification')->default('medium'); // low, medium, high, critical
            $table->json('audit_report')->nullable();
            $table->json('backup_reference')->nullable();
            $table->foreignId('ceo_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ceo_approved_at')->nullable();
            $table->text('ceo_notes')->nullable();
            $table->foreignId('sa_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sa_approved_at')->nullable();
            $table->text('sa_notes')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_log')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('content_reset_audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->constrained('content_reset_requests')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_reset_audit_logs');
        Schema::dropIfExists('content_reset_requests');
    }
};
