<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Models\Certificate;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class CleanUatArtifactsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'iap:clean-uat-artifacts {--force : Force cleanup without confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Safely remove verified role-less UAT factory users and legacy Simulator certificates before Human UAT';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting IAP UAT Artifacts Audit & Clean...');

        // 1. Audit & Clean Role-less Users
        $targetEmails = [
            'homenick.grayson@example.com',
            'hansen.emilie@example.org',
        ];

        $usersToClean = User::whereIn('email', $targetEmails)->get();
        $this->info("Found {$usersToClean->count()} target role-less user candidates.");

        foreach ($usersToClean as $user) {
            $hasRoles = $user->roles()->count() > 0;
            $hasAttempts = $user->testAttempts ? $user->testAttempts()->count() > 0 : false;
            $hasOrgs = $user->organizationMemberships()->count() > 0;
            $hasCerts = Certificate::where('user_id', $user->id)->count() > 0;

            if ($hasRoles || $hasAttempts || $hasOrgs || $hasCerts) {
                $this->warn("Skipping User ID {$user->id} ({$user->name}) - Has active domain relationships.");
                continue;
            }

            $userName = $user->name;
            $userEmail = $user->email;
            $userId = $user->id;

            ActivityLogger::log(
                action: 'UAT_ARTIFACT_USER_CLEANED',
                description: "Cleaned unused role-less UAT artifact user {$userName} ({$userEmail})",
                subject: $user,
                properties: [
                    'user_id' => $userId,
                    'email'   => $userEmail,
                ]
            );

            $user->delete();
            $this->info("✓ Safely deleted role-less UAT user ID {$userId}: {$userName} ({$userEmail})");
        }

        // 2. Audit & Clean Legacy Simulator Certificate
        $legacyCert = Certificate::where('certificate_number', 'CERT-20260904-K40Z')->first();
        if ($legacyCert) {
            $this->info("Found legacy certificate {$legacyCert->certificate_number} (ID: {$legacyCert->id})");

            $attempt = Attempt::with('test')->find($legacyCert->attempt_id);
            $isSimulator = false;

            if ($attempt && $attempt->test) {
                $mode = $attempt->test->assessment_mode;
                $isSimulator = ($mode === AssessmentMode::Simulator || (is_string($mode) && $mode === 'simulator') || (is_object($mode) && isset($mode->value) && $mode->value === 'simulator'));
            }

            if ($isSimulator) {
                $certNumber = $legacyCert->certificate_number;
                $certId = $legacyCert->id;
                $attemptId = $legacyCert->attempt_id;

                ActivityLogger::log(
                    action: 'UAT_SIMULATOR_CERT_CLEANED',
                    description: "Removed erroneous Simulator certificate {$certNumber} for Attempt {$attemptId}",
                    subject: $legacyCert,
                    properties: [
                        'certificate_id'     => $certId,
                        'certificate_number' => $certNumber,
                        'attempt_id'         => $attemptId,
                    ]
                );

                $legacyCert->delete();
                $this->info("✓ Safely removed legacy Simulator certificate {$certNumber} (Attempt and Results preserved).");
            } else {
                $this->warn("Certificate {$legacyCert->certificate_number} does not belong to a Simulator test. Skipping deletion.");
            }
        } else {
            $this->info("No legacy Simulator certificate CERT-20260904-K40Z found in database.");
        }

        $this->info("=== UAT Cleanup Completed ===");
        $this->info("Current Total Non-Deleted Users: " . User::count());
        $this->info("Current Total Certificates: " . Certificate::count());

        return Command::SUCCESS;
    }
}
