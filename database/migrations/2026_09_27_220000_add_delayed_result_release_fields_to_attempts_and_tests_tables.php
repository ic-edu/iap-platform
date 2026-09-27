<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->integer('result_release_delay_hours')->default(24)->after('pass_score');
            $table->string('result_release_mode')->default('ra_controlled')->after('result_release_delay_hours');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->string('result_release_status')->nullable()->after('decision_status');
            $table->timestamp('result_release_at')->nullable()->after('result_release_status');
            $table->timestamp('result_released_at')->nullable()->after('result_release_at');
            $table->foreignId('result_released_by')->nullable()->after('result_released_at')->constrained('users')->nullOnDelete();
        });

        // Conservative historical backfill:
        // Existing attempts that are already completed/submitted/expired, or is_final = true,
        // or have an issued certificate, or belong to a completed assignment are backfilled as 'released'.
        DB::table('attempts')
            ->where(function ($q) {
                $q->whereIn('status', ['submitted', 'completed', 'expired'])
                    ->orWhere('is_final', true)
                    ->orWhereNotNull('submitted_at')
                    ->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('certificates')
                            ->whereColumn('certificates.attempt_id', 'attempts.id');
                    })
                    ->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('candidate_test_assignments')
                            ->whereColumn('candidate_test_assignments.id', 'attempts.assignment_id')
                            ->where('candidate_test_assignments.status', 'completed');
                    });
            })
            ->update([
                'result_release_status' => 'released',
                'result_released_at' => DB::raw('COALESCE(submitted_at, updated_at, created_at)'),
                'result_release_at' => DB::raw('COALESCE(submitted_at, updated_at, created_at)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_released_by');
            $table->dropColumn([
                'result_release_status',
                'result_release_at',
                'result_released_at',
            ]);
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn([
                'result_release_delay_hours',
                'result_release_mode',
            ]);
        });
    }
};
