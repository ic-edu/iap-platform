<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Academic & Question Bank
            'manage courses',
            'manage question banks',
            'manage questions',

            // Assessment & CBT
            'manage tests',
            'take tests',
            'view results',
            'grade tests',

            // System & Finance
            'manage settings',
            'manage payments',
            'issue certificates',

            // Granular Module View Permissions
            'academic.view',
            'question-bank.view',
            'question-bank.create',
            'question-bank.update',
            'question-bank.delete',
            'assessment.view',
            'test.view',
            'test.create',
            'test.update',
            'test.delete',
            'test.publish',
            'assessment.attempt',
            'assessment.review',
            'assessment.result.view',
            'assessment.result.export',
            'certificate.view',
            'certificate.issue',
            'certificate.reissue',
            'certificate.revoke',
            'certificate.download',
            'result.view',
            'result.export',
            'analytics.view',
            'verification.manage',
            'finance.view',
            'cms.view',
            'reporting.view',
            'settings.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Reset cache again to ensure new permissions are loaded in memory
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and assign permissions
        $roleStudent = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $roleStudent->syncPermissions([
            'take tests',
            'view results',
            'certificate.download',
        ]);

        $roleTeacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $roleTeacher->syncPermissions([
            'manage courses',
            'manage question banks',
            'manage questions',
            'manage tests',
            'grade tests',
            'view results',
            'issue certificates',
            'certificate.view',
            'certificate.issue',
            'certificate.download',
            'result.view',
            'analytics.view',
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->syncPermissions(Permission::all());

        $roleSuperAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $roleSuperAdmin->syncPermissions(Permission::all());
    }
}
