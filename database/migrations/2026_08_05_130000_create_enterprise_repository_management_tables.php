<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_review_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('resource_type', 50); // MediaAsset, QuestionBank, Question, Passage
            $table->string('resource_id', 36);
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending_review'); // pending_review, approved, revision_requested, rejected
            $table->text('review_notes')->nullable();
            $table->json('changes_data')->nullable(); // Before vs After diff data
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'resource_type']);
            $table->index('submitted_by');
        });

        Schema::create('repository_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('resource_type', 50);
            $table->string('resource_id', 36);
            $table->string('version_number', 20)->default('v1.0');
            $table->string('title')->nullable();
            $table->json('snapshot_data')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('change_reason')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index(['resource_type', 'resource_id', 'is_current']);
        });

        Schema::create('repository_activity_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('resource_type', 50);
            $table->string('resource_id', 36);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // submitted_revision, approved, revision_requested, rejected, version_created, metadata_updated
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('repository_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_request_id')->constrained('repository_review_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('repository_approvals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_request_id')->constrained('repository_review_requests')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 30); // approved, revision_requested, rejected
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_approvals');
        Schema::dropIfExists('repository_comments');
        Schema::dropIfExists('repository_activity_logs');
        Schema::dropIfExists('repository_versions');
        Schema::dropIfExists('repository_review_requests');
    }
};
