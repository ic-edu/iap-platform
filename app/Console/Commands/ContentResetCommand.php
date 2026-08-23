<?php

namespace App\Console\Commands;

use App\Models\ContentResetRequest;
use App\Models\User;
use App\Services\ContentReset\ContentResetAuditService;
use App\Services\ContentReset\ContentResetDomains;
use App\Services\ContentReset\ContentResetExecutor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ContentResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:content-reset
                            {--request-id= : Existing Content Reset Request ULID or Request Number}
                            {--mode= : Reset mode (refresh or hard_reset)}
                            {--scope=* : Content domains to include in scope}
                            {--target-assessments=* : Specific assessment IDs to target}
                            {--reason= : Operational reason for the content reset}
                            {--dry-run : Perform forensic audit and plan without mutating database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely perform forensic audit, dry run, or execute controlled content refresh / hard reset.';

    /**
     * Execute the console command.
     */
    public function handle(
        ContentResetAuditService $auditService,
        ContentResetExecutor $executor
    ): int {
        $requestId = $this->option('request-id');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('===============================================================');
        $this->info('  iC.edu Assessment Platform (IAP) — Content Reset Architecture');
        $this->info('===============================================================');

        $request = null;

        if ($requestId) {
            $request = ContentResetRequest::where('id', $requestId)
                ->orWhere('request_number', $requestId)
                ->first();

            if (!$request) {
                $this->error("Content Reset Request '{$requestId}' not found.");
                return self::FAILURE;
            }
        } else {
            // Build on-the-fly request for dry-run or audit
            $mode = $this->option('mode') ?? ContentResetDomains::MODE_REFRESH;
            $scope = $this->option('scope');
            if (empty($scope)) {
                $scope = [ContentResetDomains::DRAFT_ASSESSMENTS];
            }
            $targetAssessments = $this->option('target-assessments');
            $reason = $this->option('reason') ?? 'CLI Content Reset Operation';

            $adminUser = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))->first()
                ?? User::first();

            if (!$adminUser) {
                $this->error('No administrative user found to associate with reset request.');
                return self::FAILURE;
            }

            $dateStr = now()->format('Ymd');
            $randomCode = strtoupper(Str::random(4));
            $requestNumber = "RST-{$dateStr}-{$randomCode}";

            $request = ContentResetRequest::create([
                'request_number'   => $requestNumber,
                'mode'             => $mode,
                'status'           => 'draft',
                'requested_by'     => $adminUser->id,
                'reason'           => $reason,
                'scope'            => $scope,
                'target_entities'  => !empty($targetAssessments) ? ['assessment_ids' => $targetAssessments] : null,
                'excluded_domains' => ContentResetDomains::allProtectedDomains(),
            ]);
        }

        $this->line("Request Number: <comment>{$request->request_number}</comment>");
        $this->line("Mode:           <comment>{$request->mode}</comment>");
        $this->line("Scope:          <comment>" . implode(', ', $request->scope ?? []) . "</comment>");
        $this->line("Dry Run:        <comment>" . ($dryRun ? 'YES (No data will be modified)' : 'NO (Live Execution)') . "</comment>");
        $this->newLine();

        $this->info('1. Running Forensic Audit & Dependency Graph Analysis...');
        $auditReport = $auditService->performForensicAudit($request);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Is Blocked', $auditReport['is_blocked'] ? '<error>YES</error>' : '<info>NO</info>'],
                ['Risk Classification', $auditReport['risk_classification']],
                ['Target Assessments', $auditReport['target_assessments_count']],
                ['Target Test Sections', $auditReport['target_sections_count']],
                ['Test Question Bindings', $auditReport['test_question_bindings_count']],
                ['Bound Questions Total', $auditReport['bound_questions_total']],
                ['Shared Questions (Preserved)', "<info>{$auditReport['shared_questions_preserved']}</info>"],
                ['Deletable Questions', $auditReport['deletable_questions_count']],
                ['Transient Attempts', $auditReport['transient_attempts_count']],
            ]
        );

        if ($auditReport['is_blocked']) {
            $this->error('FORENSIC BLOCKERS DETECTED:');
            foreach ($auditReport['blockers'] as $blocker) {
                $this->error(" - {$blocker}");
            }
            return self::FAILURE;
        }

        if ($dryRun) {
            $executorUser = $request->requester ?? User::first();
            $result = $executor->execute($request, $executorUser, true);
            $this->info('Dry-run completed successfully. Zero database records or files were modified.');
            return self::SUCCESS;
        }

        // Live Execution
        $executorUser = $request->requester ?? User::first();
        try {
            $result = $executor->execute($request, $executorUser, false);
            $this->info("Content reset completed successfully for request {$request->request_number}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Execution failed and rolled back: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
