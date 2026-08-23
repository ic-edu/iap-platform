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
        Schema::table('candidate_test_assignments', function (Blueprint $table) {
            $table->integer('max_attempts')->default(2)->after('status');
            $table->integer('attempts_count')->default(0)->after('max_attempts');
            $table->foreignUlid('final_attempt_id')->nullable()->after('completed_at')->constrained('attempts')->nullOnDelete();
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->foreignUlid('assignment_id')->nullable()->after('user_id')->constrained('candidate_test_assignments')->nullOnDelete();
            $table->integer('attempt_number')->default(1)->after('assignment_id');
            $table->boolean('is_final')->default(false)->after('attempt_number');
            $table->string('decision_status')->nullable()->after('is_final'); // pending_decision, finalized, retried
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropColumn(['assignment_id', 'attempt_number', 'is_final', 'decision_status']);
        });

        Schema::table('candidate_test_assignments', function (Blueprint $table) {
            $table->dropForeign(['final_attempt_id']);
            $table->dropColumn(['max_attempts', 'attempts_count', 'final_attempt_id']);
        });
    }
};
