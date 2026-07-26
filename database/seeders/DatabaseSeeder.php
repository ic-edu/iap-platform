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

        // 1. Super Admin Demo Account
        $admin = User::firstOrCreate([
            'email' => 'admin@icedu.org',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('super-admin');

        // 2. Teacher Demo Account
        $teacher = User::firstOrCreate([
            'email' => 'teacher@icedu.org',
        ], [
            'name' => 'Teacher Instructor',
            'password' => Hash::make('password'),
        ]);
        $teacher->assignRole('teacher');

        // 3. Student Demo Account
        $student = User::firstOrCreate([
            'email' => 'student@icedu.org',
        ], [
            'name' => 'Candidate Student',
            'password' => Hash::make('password'),
        ]);
        $student->assignRole('student');

        // 4. Finance Demo Account
        $finance = User::firstOrCreate([
            'email' => 'finance@icedu.org',
        ], [
            'name' => 'Finance Manager',
            'password' => Hash::make('password'),
        ]);
        $finance->assignRole('admin');

        // General Test User
        $testUser = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
        ]);
        $testUser->assignRole('super-admin');

        $this->call([
            AcademicSeeder::class,
            QuestionBankSeeder::class,
            AssessmentSeeder::class,
            FinanceSeeder::class,
            CMSSeeder::class,
        ]);
    }
}
