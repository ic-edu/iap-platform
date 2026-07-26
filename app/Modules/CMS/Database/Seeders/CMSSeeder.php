<?php

namespace App\Modules\CMS\Database\Seeders;

use App\Modules\CMS\Models\Announcement;
use App\Modules\CMS\Models\Page;
use Illuminate\Database\Seeder;

class CMSSeeder extends Seeder
{
    public function run(): void
    {
        Page::firstOrCreate([
            'slug' => 'about-us',
        ], [
            'title' => 'About iC.edu Assessment Platform',
            'content' => 'iC.edu Assessment Platform (IAP) is an enterprise CBT and e-learning solution.',
            'is_published' => true,
        ]);

        Announcement::firstOrCreate([
            'title' => 'Upcoming TOEIC Simulation Schedule',
        ], [
            'content' => 'The monthly TOEIC simulation test will be conducted on the first Saturday of next month.',
            'target_role' => 'student',
            'published_at' => now(),
        ]);
    }
}
