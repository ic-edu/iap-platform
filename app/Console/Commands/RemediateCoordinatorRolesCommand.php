<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Services\ActivityLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('iap:remediate-coordinator-roles {--dry-run : Only show what would be remediated}')]
#[Description('Safely audit and remediate accidental student roles attached to privileged Organization Coordinators.')]
class RemediateCoordinatorRolesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== IAP — ORGANIZATION COORDINATOR IDENTITY REMEDIATION ===');
        $isDryRun = (bool) $this->option('dry-run');

        // Find users with both organization-coordinator and student roles
        $candidates = User::role('organization-coordinator')
            ->whereHas('roles', fn ($q) => $q->where('name', 'student'))
            ->with(['roles', 'organizationMemberships.organization'])
            ->get();

        $this->info("Found {$candidates->count()} user(s) holding both organization-coordinator and student roles.");

        if ($candidates->isEmpty()) {
            $this->info('No coordinator identity anomalies detected.');
            return self::SUCCESS;
        }

        foreach ($candidates as $user) {
            $this->line("Inspecting User ID: {$user->id} | Name: {$user->name} | Email: {$user->email}");
            $rolesBefore = $user->getRoleNames()->values()->all();
            $this->line("  Current Roles: " . json_encode($rolesBefore));

            // Verify if user is an active privileged coordinator/admin/owner
            $privilegedMemberships = $user->organizationMemberships()
                ->whereIn('role', [MembershipRole::Coordinator, MembershipRole::Admin, MembershipRole::Owner])
                ->where('status', 'active')
                ->get();

            $memberMemberships = $user->organizationMemberships()
                ->where('role', MembershipRole::Member)
                ->where('status', 'active')
                ->get();

            $this->line("  Privileged Memberships: {$privilegedMemberships->count()}, Candidate Member Memberships: {$memberMemberships->count()}");

            // If user only has privileged membership and no legitimate candidate member membership
            if ($privilegedMemberships->isNotEmpty() && $memberMemberships->isEmpty()) {
                if ($isDryRun) {
                    $this->warn("  [DRY RUN] Would detach 'student' role from {$user->email}.");
                    continue;
                }

                DB::transaction(function () use ($user, $rolesBefore) {
                    $user->removeRole('student');

                    ActivityLogger::log(
                        action: 'USER_ROLE_REMEDIATED',
                        description: "Remediated accidental student role from privileged Organization Coordinator {$user->name} ({$user->email})",
                        subject: $user,
                        properties: [
                            'remediation'    => 'IAP_ORGANIZATION_COORDINATOR_IDENTITY_REMEDIATION',
                            'previous_roles' => $rolesBefore,
                            'new_roles'      => $user->fresh()->getRoleNames()->values()->all(),
                        ],
                        userId: null
                    );
                });

                $rolesAfter = $user->fresh()->getRoleNames()->values()->all();
                $this->info("  [SUCCESS] Detached 'student' role from {$user->email}. New Roles: " . json_encode($rolesAfter));
            } else {
                $this->warn("  [SKIPPED] User {$user->email} has legitimate candidate membership context. Preserving dual role.");
            }
        }

        $this->info('Remediation process completed.');
        return self::SUCCESS;
    }
}

