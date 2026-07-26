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
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'home',
                'permission' => null,
                'active_pattern' => 'dashboard',
                'badge' => null,
            ],
            [
                'label' => 'Academic',
                'route' => 'dashboard',
                'icon' => 'academic-cap',
                'permission' => 'academic.view',
                'active_pattern' => 'academic*',
                'badge' => null,
            ],
            [
                'label' => 'Question Bank',
                'route' => 'dashboard',
                'icon' => 'folder',
                'permission' => 'question-bank.view',
                'active_pattern' => 'question-bank*',
                'badge' => null,
            ],
            [
                'label' => 'Assessment',
                'route' => 'dashboard',
                'icon' => 'clipboard-check',
                'permission' => 'assessment.view',
                'active_pattern' => 'assessment*',
                'badge' => null,
            ],
            [
                'label' => 'Certificates',
                'route' => 'dashboard',
                'icon' => 'badge-check',
                'permission' => 'certificate.view',
                'active_pattern' => 'certificate*',
                'badge' => null,
            ],
            [
                'label' => 'Finance',
                'route' => 'dashboard',
                'icon' => 'credit-card',
                'permission' => 'finance.view',
                'active_pattern' => 'finance*',
                'badge' => null,
            ],
            [
                'label' => 'CMS Pages',
                'route' => 'dashboard',
                'icon' => 'document-text',
                'permission' => 'cms.view',
                'active_pattern' => 'cms*',
                'badge' => null,
            ],
            [
                'label' => 'Reporting',
                'route' => 'dashboard',
                'icon' => 'chart-bar',
                'permission' => 'reporting.view',
                'active_pattern' => 'reporting*',
                'badge' => null,
            ],
            [
                'label' => 'Settings',
                'route' => 'admin.settings.index',
                'icon' => 'cog',
                'permission' => 'settings.view',
                'active_pattern' => 'admin/settings*',
                'badge' => null,
            ],
        ];

        $user = Auth::user();

        return array_values(array_filter($allMenu, function ($item) use ($user) {
            if ($item['permission'] === null) {
                return true;
            }

            if (!$user) {
                return false;
            }

            // Super Admin has access to all
            if ($user->hasRole('super-admin')) {
                return true;
            }

            return $user->hasPermissionTo($item['permission']);
        }));
    }
}
