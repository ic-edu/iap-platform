<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class NavigationService
{
    /**
     * Get authorized modular navigation menu items.
     *
     * @return array<int, array{
     *     label: string,
     *     route: string,
     *     icon: string,
     *     permission: string|null,
     *     active_pattern: string,
     *     badge: string|null
     * }>
     */
    public static function getMenuItems(): array
    {
        $allMenu = [
            [
                'label' => 'System Observability',
                'route' => 'admin.monitoring.index',
                'icon' => 'activity',
                'permission' => null,
                'active_pattern' => 'admin/monitoring*',
                'badge' => 'LIVE',
            ],
            [
                'label' => 'Approval Center',
                'route' => 'admin.approvals.index',
                'icon' => 'shield-check',
                'permission' => null,
                'active_pattern' => 'admin/approvals*',
                'badge' => 'NEW',
            ],
            [
                'label' => 'Question Banks',
                'route' => 'admin.question-banks.index',
                'icon' => 'folder',
                'permission' => null,
                'active_pattern' => 'admin/question-banks*',
                'badge' => null,
            ],
            [
                'label' => 'Question Media Library',
                'route' => 'admin.media.index',
                'icon' => 'film',
                'permission' => null,
                'active_pattern' => 'admin/media*',
                'badge' => null,
            ],
            [
                'label' => 'Test Builder',
                'route' => 'admin.tests.index',
                'icon' => 'clipboard-check',
                'permission' => null,
                'active_pattern' => 'admin/tests*',
                'badge' => null,
            ],
            [
                'label' => 'User & Role Access',
                'route' => 'admin.users.index',
                'icon' => 'users',
                'permission' => null,
                'active_pattern' => 'admin/users*',
                'badge' => null,
            ],
            [
                'label' => 'Academic Curriculum',
                'route' => 'admin.academic.index',
                'icon' => 'academic-cap',
                'permission' => null,
                'active_pattern' => 'admin/academic*',
                'badge' => null,
            ],
            [
                'label' => 'Certificate Registry',
                'route' => 'admin.certificates.index',
                'icon' => 'badge-check',
                'permission' => null,
                'active_pattern' => 'admin/certificates*',
                'badge' => null,
            ],
            [
                'label' => 'Commerce & Billing',
                'route' => 'admin.commerce.index',
                'icon' => 'shopping-bag',
                'permission' => null,
                'active_pattern' => 'admin/commerce*',
                'badge' => null,
            ],
            [
                'label' => 'Reports & Analytics',
                'route' => 'admin.reporting.index',
                'icon' => 'chart-bar',
                'permission' => null,
                'active_pattern' => 'admin/reporting*',
                'badge' => null,
            ],
            [
                'label' => 'Audit & Activity Logs',
                'route' => 'admin.audit-logs.index',
                'icon' => 'document-text',
                'permission' => null,
                'active_pattern' => 'admin/audit-logs*',
                'badge' => null,
            ],
            [
                'label' => 'System Settings',
                'route' => 'admin.settings.index',
                'icon' => 'cog',
                'permission' => null,
                'active_pattern' => 'admin/settings*',
                'badge' => null,
            ],
        ];

        $user = Auth::user();

        return array_values(array_filter($allMenu, function ($item) use ($user) {
            if (!$user) {
                return false;
            }

            if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
                return true;
            }

            if ($user->hasRole('teacher')) {
                // Media Library is opened inside Question Authoring Editor modal, not in sidebar
                return in_array($item['route'], ['admin.question-banks.index', 'admin.tests.index', 'admin.academic.index', 'admin.reporting.index']);
            }

            if ($user->hasRole('finance')) {
                return in_array($item['route'], ['admin.commerce.index', 'admin.reporting.index']);
            }

            return false;
        }));
    }
}
