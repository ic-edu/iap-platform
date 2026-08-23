<?php

namespace App\Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;

class AssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Clean baseline: The assessment catalog is intentionally kept clean without legacy demo assessments.
     * Future institutional Mock Tests and Simulators will be authored through structured workflow governance.
     */
    public function run(): void
    {
        // Clean baseline: No legacy/polluted assessments seeded.
    }
}
