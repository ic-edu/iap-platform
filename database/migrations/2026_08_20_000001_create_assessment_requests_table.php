<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('test_type');
            $table->string('program_context')->nullable();
            $table->text('required_sections')->nullable();
            $table->text('notes')->nullable();
            $table->date('requested_deadline')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, draft_created, archived
            $table->string('test_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_requests');
    }
};
