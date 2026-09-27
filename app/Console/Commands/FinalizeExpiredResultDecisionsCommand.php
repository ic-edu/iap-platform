<?php

namespace App\Console\Commands;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Services\ResultDecisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FinalizeExpiredResultDecisionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:finalize-expired-result-decisions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-finalize Attempt #1 Mock Test results where the 72-hour candidate decision window has expired';

    /**
     * Execute the console command.
     */
    public function handle(ResultDecisionService $decisionService): int
    {
        $this->info('Checking for expired Mock Test candidate decision windows (72h)...');

        $cutoffTime = now()->subHours(72);

        $expiredAttempts = Attempt::with(['assignment', 'test', 'user'])
            ->where('attempt_number', 1)
            ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::Expired])
            ->where('result_release_status', ResultReleaseStatus::Released)
            ->whereNotNull('result_released_at')
            ->where('result_released_at', '<=', $cutoffTime)
            ->where('is_final', false)
            ->where('decision_status', 'pending_decision')
            ->whereHas('test', fn ($q) => $q->where('assessment_mode', 'real_test'))
            ->whereHas('assignment', fn ($q) => $q->where('status', 'active'))
            ->whereDoesntHave('assignment.attempts', fn ($q) => $q->where('attempt_number', 2))
            ->get();

        $count = $expiredAttempts->count();
        $this->info("Found {$count} expired decision attempt(s) eligible for auto-finalization.");

        $finalizedCount = 0;
        $failedCount = 0;

        foreach ($expiredAttempts as $attempt) {
            try {
                $assignment = $attempt->assignment;
                if (!$assignment) {
                    continue;
                }

                $decisionService->finalizeFirstAttempt($assignment, $attempt, 'auto_expired');
                $finalizedCount++;
                $this->line("Auto-finalized Attempt #{$attempt->id} (Candidate: {$attempt->user?->email})");
            } catch (\Throwable $e) {
                $failedCount++;
                $this->error("Failed to auto-finalize Attempt #{$attempt->id}: {$e->getMessage()}");
                Log::error("Failed to auto-finalize Attempt #{$attempt->id}: {$e->getMessage()}", [
                    'attempt_id' => $attempt->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Auto-finalization complete: {$finalizedCount} finalized, {$failedCount} failed.");

        return Command::SUCCESS;
    }
}
