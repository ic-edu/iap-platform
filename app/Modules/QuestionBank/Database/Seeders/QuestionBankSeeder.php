<?php

namespace App\Modules\QuestionBank\Database\Seeders;

use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Academic\Models\CourseCategory;
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
        $teacher = User::where('email', 'teacher@icedu.org')->first()
            ?? User::role('teacher')->first();

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

        $category = CourseCategory::first();

        $bank = QuestionBank::firstOrCreate([
            'slug' => 'toeic-official-bank-vol-1',
        ], [
            'title' => 'TOEIC Official Question Bank Vol. 1',
            'category_id' => $category?->id,
            'created_by' => $teacher->id,
            'test_type' => TestType::Toeic,
            'description' => 'Official TOEIC listening and reading question pool.',
        ]);

        // Remediation: Ensure created_by belongs to Teacher
        if ($bank->created_by !== $teacher->id) {
            $bank->created_by = $teacher->id;
            $bank->save();
        }

        // Remediation: Reconcile active RepositoryRevisionRequest teacher_id to match author
        RepositoryRevisionRequest::where('question_bank_id', $bank->id)
            ->where('teacher_id', '!=', $teacher->id)
            ->update(['teacher_id' => $teacher->id]);

        // Create sample listening question
        $q1 = Question::firstOrCreate([
            'question_bank_id' => $bank->id,
            'prompt' => 'Listen to the audio and select the statement that best describes the picture.',
        ], [
            'section' => SectionType::Listening,
            'part_number' => 1,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
            'explanation' => 'Option (A) accurately depicts the person operating the equipment.',
        ]);

        $choices1 = [
            ['label' => 'A', 'content' => 'He is operating heavy machinery in a warehouse.', 'is_correct' => true],
            ['label' => 'B', 'content' => 'He is writing notes on a chalkboard.', 'is_correct' => false],
            ['label' => 'C', 'content' => 'He is boarding an airplane.', 'is_correct' => false],
            ['label' => 'D', 'content' => 'He is serving coffee to customers.', 'is_correct' => false],
        ];

        foreach ($choices1 as $choice) {
            QuestionChoice::firstOrCreate([
                'question_id' => $q1->id,
                'label' => $choice['label'],
            ], [
                'content' => $choice['content'],
                'is_correct' => $choice['is_correct'],
            ]);
        }

        // Create sample reading question
        $q2 = Question::firstOrCreate([
            'question_bank_id' => $bank->id,
            'prompt' => 'Choose the word that best completes the sentence: "The quarterly financial report must be ______ by Friday."',
        ], [
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
            'explanation' => '"Submitted" is the grammatically correct past participle adjective needed.',
        ]);

        $choices2 = [
            ['label' => 'A', 'content' => 'submitting', 'is_correct' => false],
            ['label' => 'B', 'content' => 'submitted', 'is_correct' => true],
            ['label' => 'C', 'content' => 'submits', 'is_correct' => false],
            ['label' => 'D', 'content' => 'submission', 'is_correct' => false],
        ];

        foreach ($choices2 as $choice) {
            QuestionChoice::firstOrCreate([
                'question_id' => $q2->id,
                'label' => $choice['label'],
            ], [
                'content' => $choice['content'],
                'is_correct' => $choice['is_correct'],
            ]);
        }
    }
}
