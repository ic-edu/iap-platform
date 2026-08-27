<?php

namespace App\Modules\QuestionBank\Database\Seeders;

use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'teacher@icedu.org')->first();

        if (!$teacher) {
            $teacher = User::create([
                'name'     => 'Teacher Instructor',
                'email'    => 'teacher@icedu.org',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]);
            $teacher->assignRole('teacher');
        }

        if (!$teacher->hasRole('teacher')) {
            throw new \RuntimeException('QuestionBank creator must possess the teacher role.');
        }

        // Note: Standard institutional question libraries are seeded via AclStarterLibrarySeeder.
        // Legacy 'toeic-official-bank-vol-1' seed definition has been removed.
    }
}
