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

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->assignRole('super-admin');

        $this->call([
            AcademicSeeder::class,
            QuestionBankSeeder::class,
            AssessmentSeeder::class,
            FinanceSeeder::class,
            CMSSeeder::class,
        ]);
    }
}
