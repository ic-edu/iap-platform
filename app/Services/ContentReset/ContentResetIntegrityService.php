<?php

namespace App\Services\ContentReset;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ContentResetIntegrityService
{
    /**
     * Capture pre-reset counts of protected domains.
     *
     * @return array<string, int>
     */
    public function capturePreResetCounts(): array
    {
        return [
            'users_count'         => User::count(),
            'roles_count'         => Role::count(),
            'permissions_count'   => Permission::count(),
            'orders_count'        => Order::count(),
            'payments_count'      => Payment::count(),
            'activity_logs_count' => ActivityLog::count(),
        ];
    }

    /**
     * Verify database integrity and protected domain integrity after a reset.
     *
     * @param array<string, int> $preResetCounts
     * @return array<string, mixed>
     */
    public function verifyPostResetIntegrity(array $preResetCounts): array
    {
        $failures = [];

        // 1. Orphan Checks
        $orphanSections = TestSection::whereDoesntHave('test')->count();
        if ($orphanSections > 0) {
            $failures[] = "Integrity failure: Found {$orphanSections} orphaned test section(s).";
        }

        $orphanBindings = TestQuestion::whereDoesntHave('section')->count();
        if ($orphanBindings > 0) {
            $failures[] = "Integrity failure: Found {$orphanBindings} orphaned test-question binding(s).";
        }

        $orphanChoices = QuestionChoice::whereDoesntHave('question')->count();
        if ($orphanChoices > 0) {
            $failures[] = "Integrity failure: Found {$orphanChoices} orphaned question choice(s).";
        }

        // 2. Protected Domain Integrity Checks
        $currentUsersCount = User::count();
        if ($currentUsersCount !== $preResetCounts['users_count']) {
            $failures[] = "Protected domain violation: Users count mutated from {$preResetCounts['users_count']} to {$currentUsersCount}.";
        }

        $currentRolesCount = Role::count();
        if ($currentRolesCount !== $preResetCounts['roles_count']) {
            $failures[] = "Protected domain violation: Roles count mutated from {$preResetCounts['roles_count']} to {$currentRolesCount}.";
        }

        $currentPermsCount = Permission::count();
        if ($currentPermsCount !== $preResetCounts['permissions_count']) {
            $failures[] = "Protected domain violation: Permissions count mutated from {$preResetCounts['permissions_count']} to {$currentPermsCount}.";
        }

        $currentOrdersCount = Order::count();
        if ($currentOrdersCount !== $preResetCounts['orders_count']) {
            $failures[] = "Protected domain violation: Financial orders count mutated from {$preResetCounts['orders_count']} to {$currentOrdersCount}.";
        }

        $currentPaymentsCount = Payment::count();
        if ($currentPaymentsCount !== $preResetCounts['payments_count']) {
            $failures[] = "Protected domain violation: Financial payments count mutated from {$preResetCounts['payments_count']} to {$currentPaymentsCount}.";
        }

        $currentLogsCount = ActivityLog::count();
        if ($currentLogsCount < $preResetCounts['activity_logs_count']) {
            $failures[] = "Protected domain violation: Activity logs were deleted (count decreased from {$preResetCounts['activity_logs_count']} to {$currentLogsCount}).";
        }

        // 3. Routing & Engine Health Check
        $criticalRoutes = [
            'login',
            'candidate.portal',
            'admin.repository-manager.dashboard',
            'finance.dashboard',
            'admin.certificates.index',
        ];

        foreach ($criticalRoutes as $routeName) {
            if (!Route::has($routeName)) {
                $failures[] = "Engine route failure: Critical route '{$routeName}' is missing.";
            }
        }

        if (!empty($failures)) {
            throw new RuntimeException("Post-Reset Integrity Validation FAILED:\n" . implode("\n", $failures));
        }

        return [
            'passed'                => true,
            'orphan_sections'       => 0,
            'orphan_bindings'       => 0,
            'orphan_choices'        => 0,
            'protected_domain_ok'   => true,
            'routes_verified_count' => count($criticalRoutes),
            'verified_at'           => now()->toIso8601String(),
        ];
    }
}
