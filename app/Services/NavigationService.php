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
                    'section' => 'User Management',
                    'label' => 'Users & Access Control',
                    'route' => 'admin.users.index',
                    'icon' => 'users',
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

        // ADMIN-OPS-001 Section 2: Admin Operational Sidebar
        // Replaced Question Banks + Test Builder with Question Publications + Assessment Publications
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
                    'section' => 'User Management',
                    'label' => 'Users & Access Control',
                    'route' => 'admin.users.index',
                    'icon' => 'users',
                    'permission' => null,
                    'active_pattern' => 'admin/users*',
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
                    'label' => 'Course Management',
                    'route' => 'admin.academic-operations.courses',
                    'icon' => 'academic-cap',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-operations/courses*',
                    'badge' => null,
                ],
                [
                    'section' => 'Academic Operations',
                    'label' => 'Student Enrolments',
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
                    'section' => 'Academic Operations',
                    'label' => 'Academic Libraries',
                    'route' => 'admin.academic-operations.libraries',
                    'icon' => 'book-open',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-operations/libraries*',
                    'badge' => null,
                ],
                [
                    'section' => 'Academic Operations',
                    'label' => 'Course Monitoring',
                    'route' => 'admin.academic-operations.monitoring',
                    'icon' => 'chart-bar',
                    'permission' => null,
                    'active_pattern' => 'admin/academic-operations/monitoring*',
                    'badge' => null,
                ],
                [
                    'section' => 'Publication Queues',
                    'label' => 'Question Publications',
                    'route' => 'admin.publications.question-banks',
                    'icon' => 'folder',
                    'permission' => null,
                    'active_pattern' => 'admin/publications/question-banks*',
                    'badge' => null,
                ],
                [
                    'section' => 'Publication Queues',
                    'label' => 'Assessment Publications',
                    'route' => 'admin.publications.assessments',
                    'icon' => 'clipboard-check',
                    'permission' => null,
                    'active_pattern' => 'admin/publications/assessments*',
                    'badge' => null,
                ],
                [
                    'section' => 'Publication Queues',
                    'label' => 'Published Contents',
                    'route' => 'admin.publications.published',
                    'icon' => 'check-circle',
                    'permission' => null,
                    'active_pattern' => 'admin/publications/published*',
                    'badge' => null,
                ],
                [
                    'section' => 'Publication Queues',
                    'label' => 'Archive Requests',
                    'route' => 'admin.publications.archive-requests',
                    'icon' => 'archive',
                    'permission' => null,
                    'active_pattern' => 'admin/publications/archive-requests*',
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
                    'section' => 'Media Management',
                    'label' => 'Media Library',
                    'route' => 'admin.media.index',
                    'icon' => 'folder',
                    'permission' => null,
                    'active_pattern' => 'admin/media',
                    'badge' => null,
                ],
                [
                    'section' => 'Media Management',
                    'label' => 'Media Archive',
                    'route' => 'admin.media.archive-index',
                    'icon' => 'archive',
                    'permission' => null,
                    'active_pattern' => 'admin/media/archive*',
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
