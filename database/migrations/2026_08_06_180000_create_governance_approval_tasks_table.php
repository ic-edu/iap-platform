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
        if (!Schema::hasTable('governance_approval_tasks')) {
            Schema::create('governance_approval_tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('question_bank_id');
                $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('assigned_repository_manager_id')->nullable()->constrained('users')->onDelete('set null');
                $table->enum('workflow', ['APPROVAL', 'REVISION', 'ARCHIVE', 'DELETION'])->default('APPROVAL');
                $table->enum('status', ['OPEN', 'IN_REVIEW', 'COMPLETED', 'REJECTED', 'CANCELLED'])->default('OPEN');
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('question_bank_id')->references('id')->on('question_banks')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('governance_approval_tasks');
    }
};
