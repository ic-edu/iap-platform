<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class NavigationService
{
    /**
     * Get authorized modular navigation menu items grouped by section for each role according to Baseline v1.1.
     * ADMIN-OPS-001: Admin sidebar replaced with operational menus.
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
        if (! $user) {
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
                    'section' => 'Candidate Management',
                    'label' => 'Candidates',
                    'route' => 'admin.candidates.index',
                    'icon' => 'users',
                    'permission' => null,
                    'active_pattern' => 'admin/candidates*',
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
                    'section' => 'User Management',
                    'label' => 'Staff & Access Control',
                    'route' => 'admin.users.index',
                    'icon' => 'shield-check',
                    'permission' => null,
                    'active_pattern' => 'admin/users*',
                    'badge' => null,
                ],
                [
                    'section' => 'Governance',
                    'label' => 'Approval Center',
                    'route' => 'admin.approvals.index',
                    'icon' => 'shield-check',
                    'permission' => null,
                    'active_pattern' => 'admin/approvals*',
                    'badge' => 'NEW',
                ],
                [
                    'section' => 'Governance',
                    'label' => 'Archived Repositories',
                    'route' => 'admin.archived-repositories.index',
                    'icon' => 'archive',
                    'permission' => null,
                    'active_pattern' => 'admin/archived-repositories*',
                    'badge' => null,
                ],
                [
                    'section' => 'Governance',
                    'label' => 'Recycle Bin',
                    'route' => 'admin.recycle-bin.index',
                    'icon' => 'trash',
                    'permission' => null,
                    'active_pattern' => 'admin/recycle-bin*',
                    'badge' => null,
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
                    'label' => 'System Audit & Activity Logs',
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

        // Regular Admin Operational Navigation Sidebar
        if ($user->hasRole('admin')) {
            return [
                [
                    'section' => 'Operations',
                    'label' => 'Operational Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'home',
                    'permission' => null,
                    'active_pattern' => 'admin/dashboard*',
                    'badge' => null,
                ],
                [
                    'section' => 'Candidate Operations',
                    'label' => 'Candidates',
                    'route' => 'admin.candidates.index',
                    'icon' => 'users',
                    'permission' => null,
                    'active_pattern' => 'admin/candidates*',
                    'badge' => null,
                ],
                [
                    'section' => 'Candidate Operations',
                    'label' => 'Certificate Registry',
                    'route' => 'admin.certificates.index',
                    'icon' => 'badge-check',
                    'permission' => null,
                    'active_pattern' => 'admin/certificates*',
                    'badge' => null,
                ],
                [
                    'section' => 'Administrative Management',
                    'label' => 'Staff & Access Control',
                    'route' => 'admin.users.index',
                    'icon' => 'shield-check',
                    'permission' => null,
                    'active_pattern' => 'admin/users*',
                    'badge' => null,
                ],
                [
                    'section' => 'Assessment Operations',
                    'label' => 'Assessments & Assignments',
                    'route' => 'admin.tests.index',
                    'icon' => 'clipboard-check',
                    'permission' => null,
                    'active_pattern' => 'admin/tests*',
                    'badge' => null,
                ],
                [
                    'section' => 'Academic Operations',
                    'label' => 'Student Applications',
                    'route' => 'admin.academic-operations.applications',
                    'icon' => 'document-text',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-operations/applications*',
                    'badge' => null,
                ],
                [
                    'section' => 'Academic Operations',
                    'label' => 'Student Enrollments',
                    'route' => 'admin.academic-operations.enrollments',
                    'icon' => 'user-group',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-operations/enrollments*',
                    'badge' => null,
                ],
                [
                    'section' => 'Academic Operations',
                    'label' => 'Teacher Assignments',
                    'route' => 'admin.academic-operations.teacher-assignments',
                    'icon' => 'user-check',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-operations/teacher-assignments*',
                    'badge' => null,
                ],
                [
                    'section' => 'Commerce & Finance',
                    'label' => 'Commerce & Billing',
                    'route' => 'admin.commerce.index',
                    'icon' => 'shopping-bag',
                    'permission' => null,
                    'active_pattern' => 'admin/commerce*',
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
                    'label' => 'Academic Library',
                    'route' => 'admin.academic-library.index',
                    'icon' => 'academic-cap',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-library*',
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
                    'section' => 'Authoring',
                    'label' => 'Archived Repositories',
                    'route' => 'teacher.archived-repositories.index',
                    'icon' => 'archive',
                    'permission' => null,
                    'active_pattern' => 'teacher/archived-repositories*',
                    'badge' => null,
                ],

                [
                    'section' => 'Media',
                    'label' => 'My Media',
                    'route' => 'teacher.media.index',
                    'icon' => 'folder',
                    'permission' => null,
                    'active_pattern' => 'teacher/media*',
                    'badge' => null,
                ],
                [
                    'section' => 'Media',
                    'label' => 'Institutional Media',
                    'route' => 'admin.media.index',
                    'icon' => 'library',
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
                    'section' => 'Store & Billing',
                    'label' => 'Store',
                    'route' => 'candidate.store',
                    'icon' => 'shopping-bag',
                    'permission' => null,
                    'active_pattern' => 'candidate/store*|candidate/checkout*',
                    'badge' => null,
                ],
                [
                    'section' => 'Store & Billing',
                    'label' => 'My Orders',
                    'route' => 'candidate.orders.index',
                    'icon' => 'document-text',
                    'permission' => null,
                    'active_pattern' => 'candidate/orders*',
                    'badge' => null,
                ],
                [
                    'section' => 'Store & Billing',
                    'label' => 'My Invoices & Payments',
                    'route' => 'candidate.invoices.index',
                    'icon' => 'credit-card',
                    'permission' => null,
                    'active_pattern' => 'candidate/invoices*|candidate/payments*',
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
