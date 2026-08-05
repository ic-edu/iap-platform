<?php

namespace App\Modules\Assessment\Database\Seeders;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Database\Seeder;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'teacher@icedu.org')->first() ?? User::role('teacher')->first() ?? User::first();

        if (!$teacher) {
            return;
        }

        $test = Test::updateOrCreate([
            'slug' => 'toeic-full-simulation-test-01',
        ], [
            'title' => 'TOEIC Full Simulation Test 01',
            'test_type' => TestType::Toeic,
            'duration_minutes' => 120,
            'pass_score' => 700,
            'is_published' => true,
            'created_by' => $teacher->id,
        ]);

        $sectionListening = TestSection::firstOrCreate([
            'test_id' => $test->id,
            'section_type' => SectionType::Listening,
        ], [
            'title' => 'Listening Comprehension',
            'duration_minutes' => 45,
            'order' => 1,
        ]);

        $questions = Question::all();

        foreach ($questions as $index => $question) {
            TestQuestion::firstOrCreate([
                'test_section_id' => $sectionListening->id,
                'question_id' => $question->id,
            ], [
                'order' => $index + 1,
                'points' => $question->points,
            ]);
        }

        // Create sample attempt
        $student = User::role('student')->first() ?? $teacher;

        $attempt = Attempt::firstOrCreate([
            'test_id' => $test->id,
            'user_id' => $student->id,
        ], [
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'total_score' => 850.00,
            'section_scores' => [
                'listening' => 430,
                'reading' => 420,
            ],
            'status' => AttemptStatus::Submitted,
        ]);

        foreach ($questions as $question) {
            $choice = $question->choices()->where('is_correct', true)->first();

            Answer::firstOrCreate([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
            ], [
                'selected_choice_id' => $choice?->id,
                'is_correct' => true,
                'score_earned' => $question->points,
            ]);
        }
    }
}
