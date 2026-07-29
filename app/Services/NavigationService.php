<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class NavigationService
{
    /**
     * Get authorized modular navigation menu items grouped by section for each role according to Baseline v1.1.
     *
     * @return array<int, array{
     *     section: string,
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
        $user = Auth::user();
        if (!$user) {
            return [];
        }

        if ($user->hasRole('super-admin')) {
            return [
                [
                    'section' => 'Dashboard',
                    'label' => 'Platform Overview',
                    'route' => 'super-admin.dashboard',
                    'icon' => 'home',
                    'permission' => null,
                    'active_pattern' => 'super-admin/dashboard*',
                    'badge' => null,
                ],
                [
                    'section' => 'User Management',
                    'label' => 'Users & Access Control',
                    'route' => 'admin.users.index',
                    'icon' => 'users',
                    'permission' => null,
                    'active_pattern' => 'admin/users*',
                    'badge' => null,
                ],
                [
                    'section' => 'Assessment Management',
                    'label' => 'Approval Center',
                    'route' => 'admin.approvals.index',
                    'icon' => 'shield-check',
                    'permission' => null,
                    'active_pattern' => 'admin/approvals*',
                    'badge' => 'NEW',
                ],
                [
                    'section' => 'Platform Observability',
                    'label' => 'System Observability',
                    'route' => 'admin.monitoring.index',
                    'icon' => 'activity',
                    'permission' => null,
                    'active_pattern' => 'admin/monitoring*',
                    'badge' => 'LIVE',
                ],
                [
                    'section' => 'Security',
                    'label' => 'Audit & Activity Logs',
                    'route' => 'admin.audit-logs.index',
                    'icon' => 'document-text',
                    'permission' => null,
                    'active_pattern' => 'admin/audit-logs*',
                    'badge' => null,
                ],
                [
                    'section' => 'Platform Settings',
                    'label' => 'Platform Settings',
                    'route' => 'admin.settings.index',
                    'icon' => 'cog',
                    'permission' => null,
                    'active_pattern' => 'admin/settings*',
                    'badge' => null,
                ],
                [
                    'section' => 'Candidate Management',
                    'label' => 'Certificate Registry',
                    'route' => 'admin.certificates.index',
                    'icon' => 'badge-check',
                    'permission' => null,
                    'active_pattern' => 'admin/certificates*',
                    'badge' => null,
                ],
                [
                    'section' => 'Reporting & Analytics',
                    'label' => 'Reports & Analytics',
                    'route' => 'admin.reporting.index',
                    'icon' => 'chart-bar',
                    'permission' => null,
                    'active_pattern' => 'admin/reporting*',
                    'badge' => null,
                ],
                [
                    'section' => 'Commerce',
                    'label' => 'Commerce & Billing',
                    'route' => 'admin.commerce.index',
                    'icon' => 'shopping-bag',
                    'permission' => null,
                    'active_pattern' => 'admin/commerce*',
                    'badge' => null,
                ],
            ];
        }

        if ($user->hasRole('admin')) {
            return [
                [
                    'section' => 'Dashboard',
                    'label' => 'Operational Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'home',
                    'permission' => null,
                    'active_pattern' => 'admin/dashboard*',
                    'badge' => null,
                ],
                [
                    'section' => 'User Management',
                    'label' => 'Users & Access Control',
                    'route' => 'admin.users.index',
                    'icon' => 'users',
                    'permission' => null,
                    'active_pattern' => 'admin/users*',
                    'badge' => null,
                ],
                [
                    'section' => 'Assessment Management',
                    'label' => 'Question Banks',
                    'route' => 'admin.question-banks.index',
                    'icon' => 'folder',
                    'permission' => null,
                    'active_pattern' => 'admin/question-banks*',
                    'badge' => null,
                ],
                [
                    'section' => 'Assessment Management',
                    'label' => 'Test Builder',
                    'route' => 'admin.tests.index',
                    'icon' => 'clipboard-check',
                    'permission' => null,
                    'active_pattern' => 'admin/tests*',
                    'badge' => null,
                ],
                [
                    'section' => 'Reporting & Analytics',
                    'label' => 'Reports & Analytics',
                    'route' => 'admin.reporting.index',
                    'icon' => 'chart-bar',
                    'permission' => null,
                    'active_pattern' => 'admin/reporting*',
                    'badge' => null,
                ],
                [
                    'section' => 'Commerce',
                    'label' => 'Commerce & Billing',
                    'route' => 'admin.commerce.index',
                    'icon' => 'shopping-bag',
                    'permission' => null,
                    'active_pattern' => 'admin/commerce*',
                    'badge' => null,
                ],
            ];
        }

        if ($user->hasRole('teacher')) {
            return [
                [
                    'section' => 'Dashboard',
                    'label' => 'Teacher Workspace',
                    'route' => 'teacher.dashboard',
                    'icon' => 'home',
                    'permission' => null,
                    'active_pattern' => 'teacher/dashboard*',
                    'badge' => null,
                ],
                [
                    'section' => 'Authoring',
                    'label' => 'Question Banks',
                    'route' => 'admin.question-banks.index',
                    'icon' => 'folder',
                    'permission' => null,
                    'active_pattern' => 'admin/question-banks*',
                    'badge' => null,
                ],
                [
                    'section' => 'Authoring',
                    'label' => 'Test Builder',
                    'route' => 'admin.tests.index',
                    'icon' => 'clipboard-check',
                    'permission' => null,
                    'active_pattern' => 'admin/tests*',
                    'badge' => null,
                ],
                [
                    'section' => 'Curriculum',
                    'label' => 'Academic Curriculum',
                    'route' => 'admin.academic.index',
                    'icon' => 'academic-cap',
                    'permission' => null,
                    'active_pattern' => 'admin/academic*',
                    'badge' => null,
                ],
                [
                    'section' => 'Media',
                    'label' => 'Media Library',
                    'route' => 'admin.media.index',
                    'icon' => 'folder',
                    'permission' => null,
                    'active_pattern' => 'admin/media*',
                    'badge' => null,
                ],
            ];
        }

        if ($user->hasRole('finance')) {
            return [
                [
                    'section' => 'Finance Core',
                    'label' => 'Dashboard',
                    'route' => 'finance.dashboard',
                    'icon' => 'home',
                    'permission' => null,
                    'active_pattern' => 'finance/dashboard*',
                    'badge' => null,
                ],
                [
                    'section' => 'Finance Core',
                    'label' => 'Commerce & Billing',
                    'route' => 'admin.commerce.index',
                    'icon' => 'shopping-bag',
                    'permission' => null,
                    'active_pattern' => 'admin/commerce*',
                    'badge' => null,
                ],
            ];
        }

        if ($user->hasRole('student')) {
            return [
                [
                    'section' => 'Candidate Portal',
                    'label' => 'Candidate Portal',
                    'route' => 'candidate.portal',
                    'icon' => 'home',
                    'permission' => null,
                    'active_pattern' => 'candidate/portal*',
                    'badge' => null,
                ],
                [
                    'section' => 'Assessments',
                    'label' => 'Available Tests',
                    'route' => 'candidate.available-tests',
                    'icon' => 'clipboard-check',
                    'permission' => null,
                    'active_pattern' => 'candidate/available-tests*',
                    'badge' => null,
                ],
                [
                    'section' => 'History',
                    'label' => 'My Attempts',
                    'route' => 'candidate.my-attempts',
                    'icon' => 'clock',
                    'permission' => null,
                    'active_pattern' => 'candidate/my-attempts*',
                    'badge' => null,
                ],
                [
                    'section' => 'Certificates',
                    'label' => 'My Certificates',
                    'route' => 'candidate.my-certificates',
                    'icon' => 'badge-check',
                    'permission' => null,
                    'active_pattern' => 'candidate/my-certificates*',
                    'badge' => null,
                ],
            ];
        }

        return [];
    }
}
