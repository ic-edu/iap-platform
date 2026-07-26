<?php

namespace App\Modules\Academic\Database\Seeders;

use App\Modules\Academic\Enums\CourseLevel;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'TOEIC Preparation' => 'Comprehensive TOEIC listening & reading preparation courses.',
            'TOEFL iBT Intensive' => 'Advanced TOEFL iBT strategy and practice courses.',
            'IELTS Academic Masterclass' => 'Complete IELTS preparation for academic test takers.',
        ];

        foreach ($categories as $name => $desc) {
            $cat = CourseCategory::firstOrCreate([
                'slug' => Str::slug($name),
            ], [
                'name' => $name,
                'description' => $desc,
                'is_active' => true,
            ]);

            Course::firstOrCreate([
                'slug' => Str::slug($name.' Master Course'),
            ], [
                'category_id' => $cat->id,
                'title' => $name.' Master Course',
                'code' => strtoupper(Str::slug($name, '-')),
                'description' => 'Official master course for '.$name,
                'level' => CourseLevel::Intermediate,
                'is_published' => true,
            ]);
        }
    }
}
