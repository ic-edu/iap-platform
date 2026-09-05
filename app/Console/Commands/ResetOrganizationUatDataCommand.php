<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationGroupMember;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Modules\Organization\Models\OrganizationMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetOrganizationUatDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:reset-org-uat-data {--force : Force deletion without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely audit and reset O1 UAT Organization data for clean Human UAT start.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== IAP — ORGANIZATION O1 UAT DATA AUDIT & RESET ===');
        $orgs = Organization::withCount(['memberships', 'groups'])->get();

        $this->info("Total Organizations found: {$orgs->count()}");

        if ($orgs->isEmpty()) {
            $this->info('No organization data exists in the database. Starting state is already clean (0 organizations).');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($orgs as $org) {
            $creator = $org->createdBy ? $org->createdBy->name . " ({$org->createdBy->email})" : 'N/A';
            $rows[] = [
                $org->id,
                $org->name,
                $org->slug,
                $org->created_at?->toDateTimeString(),
                $creator,
                $org->status?->value ?? (string) $org->status,
                $org->memberships_count,
                $org->groups_count,
            ];
        }

        $this->table(['ID', 'Name', 'Slug', 'Created At', 'Created By', 'Status', 'Members', 'Groups'], $rows);

        DB::transaction(function () use ($orgs) {
            foreach ($orgs as $org) {
                $this->warn("Safely deleting dependent records for Organization '{$org->name}' ({$org->id})...");

                // 1. Group Members
                $groupIds = OrganizationGroup::where('organization_id', $org->id)->pluck('id');
                OrganizationGroupMember::whereIn('organization_group_id', $groupIds)->delete();

                // 2. Groups
                OrganizationGroup::where('organization_id', $org->id)->delete();

                // 3. Invitations
                OrganizationInvitation::where('organization_id', $org->id)->delete();

                // 4. Memberships & normalize users
                $memberships = OrganizationMembership::where('organization_id', $org->id)->get();
                $userIds = $memberships->pluck('user_id')->unique();
                OrganizationMembership::where('organization_id', $org->id)->delete();

                // 5. Delete organization
                $org->delete();

                // 6. User normalization: check if user has any remaining active organization memberships
                foreach ($userIds as $userId) {
                    $user = User::find($userId);
                    if ($user && $user->hasRole('organization-coordinator')) {
                        $remainingMemberships = OrganizationMembership::where('user_id', $user->id)->count();
                        if ($remainingMemberships === 0) {
                            $user->removeRole('organization-coordinator');
                            $this->info("Normalized role for user {$user->email}: removed 'organization-coordinator' (0 active memberships).");
                        }
                    }
                }
            }
        });

        $finalCount = Organization::count();
        $this->info("Reset complete. Current Organization count: {$finalCount}");

        return self::SUCCESS;
    }
}
