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
        // 1. Repository Revision Requests Table (PART 1)
        Schema::create('repository_revision_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_bank_id');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('requested_by_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESUBMITTED', 'VERIFIED', 'COMPLETED', 'REJECTED'])->default('OPEN');
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('question_bank_id')->references('id')->on('question_banks')->onDelete('cascade');
        });

        // 2. Repository Revision Items Table (PART 1)
        Schema::create('repository_revision_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('repository_revision_request_id');
            $table->uuid('question_bank_id');
            $table->uuid('question_id')->nullable();
            $table->string('finding_type')->default('general');
            $table->string('field')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('feedback');
            $table->text('suggested_fix')->nullable();
            $table->enum('status', ['OPEN', 'FIXED', 'VERIFIED', 'CLOSED', 'DISMISSED'])->default('OPEN');
            $table->timestamps();

            $table->foreign('repository_revision_request_id')->references('id')->on('repository_revision_requests')->onDelete('cascade');
            $table->foreign('question_bank_id')->references('id')->on('question_banks')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
        });

        // 3. Repository Findings Table (PART 5)
        Schema::create('repository_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_bank_id');
            $table->uuid('question_id')->nullable();
            $table->string('finding_code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['OPEN', 'FIXED', 'VERIFIED', 'CLOSED', 'DISMISSED'])->default('OPEN');
            $table->timestamps();

            $table->foreign('question_bank_id')->references('id')->on('question_banks')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
        });

        // 4. Repository Finding Histories Table
        Schema::create('repository_finding_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('repository_finding_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('previous_status');
            $table->string('new_status');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('repository_finding_id')->references('id')->on('repository_findings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_finding_histories');
        Schema::dropIfExists('repository_findings');
        Schema::dropIfExists('repository_revision_items');
        Schema::dropIfExists('repository_revision_requests');
    }
};
