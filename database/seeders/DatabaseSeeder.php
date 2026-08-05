<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academic\Database\Seeders\AcademicSeeder;
use App\Modules\Assessment\Database\Seeders\AssessmentSeeder;
use App\Modules\CMS\Database\Seeders\CMSSeeder;
use App\Modules\Finance\Database\Seeders\FinanceSeeder;
use App\Modules\QuestionBank\Database\Seeders\QuestionBankSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Helper to find or create user even if soft-deleted
        $findOrCreate = function (string $email, string $name, string $role) {
            $user = User::withTrashed()->where('email', $email)->first();
            if (!$user) {
                $user = User::create([
                    'email' => $email,
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ]);
            } else {
                if ($user->trashed()) {
                    $user->restore();
                }
                $user->update(['status' => 'active']);
            }
            $user->syncRoles([$role]);

            return $user;
        };

        // 1. Super Admin Demo Account
        $findOrCreate('admin@icedu.org', 'Super Admin', 'super-admin');

        // 2. Operational Admin Demo Account
        $findOrCreate('opadmin@icedu.org', 'Operational Admin', 'admin');

        // 3. Teacher Demo Account
        $findOrCreate('teacher@icedu.org', 'Teacher Instructor', 'teacher');

        // 4. Student Demo Account
        $findOrCreate('student@icedu.org', 'Candidate Student', 'student');

        // 5. Finance Demo Account
        $findOrCreate('finance@icedu.org', 'Finance Manager', 'finance');

        // 6. General Test User
        $findOrCreate('test@example.com', 'Test User', 'super-admin');

        $this->call([
            AcademicSeeder::class,
            MediaInstitutionalRepositorySeeder::class,
            AclStarterLibrarySeeder::class,
            QuestionBankSeeder::class,
            AssessmentSeeder::class,
            FinanceSeeder::class,
            CMSSeeder::class,
        ]);
    }
}
