<?php

namespace Database\Seeders;

use App\Models\AclAuditTrail;
use App\Models\AclCategory;
use App\Models\AclVersion;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AclStarterLibrarySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Configurable Taxonomy Categories
        $categoriesData = [
            // TOEFL Libraries
            ['name' => 'TOEFL Listening Library', 'slug' => 'toefl-listening', 'test_type' => 'toefl', 'section_code' => 'listening', 'target_questions' => 50, 'icon' => '🎧', 'description' => 'Listening comprehension dialogues, conversations, and academic lectures.'],
            ['name' => 'TOEFL Structure Library', 'slug' => 'toefl-structure', 'test_type' => 'toefl', 'section_code' => 'structure', 'target_questions' => 40, 'icon' => '✍️', 'description' => 'Structure and written expression sentence completion and error identification.'],
            ['name' => 'TOEFL Reading Library', 'slug' => 'toefl-reading', 'test_type' => 'toefl', 'section_code' => 'reading', 'target_questions' => 60, 'icon' => '📖', 'description' => 'Academic reading passages with vocabulary and inference questions.'],

            // TOEIC Libraries
            ['name' => 'TOEIC Part 1: Photographs', 'slug' => 'toeic-part-1', 'test_type' => 'toeic', 'section_code' => 'part_1', 'target_questions' => 30, 'icon' => '🖼', 'description' => 'Photo description statements and image evaluation.'],
            ['name' => 'TOEIC Part 2: Question-Response', 'slug' => 'toeic-part-2', 'test_type' => 'toeic', 'section_code' => 'part_2', 'target_questions' => 30, 'icon' => '❓', 'description' => 'Direct spoken question and response options.'],
            ['name' => 'TOEIC Part 3: Conversations', 'slug' => 'toeic-part-3', 'test_type' => 'toeic', 'section_code' => 'part_3', 'target_questions' => 40, 'icon' => '💬', 'description' => 'Workplace dialogues and conversation comprehension.'],
            ['name' => 'TOEIC Part 4: Short Talks', 'slug' => 'toeic-part-4', 'test_type' => 'toeic', 'section_code' => 'part_4', 'target_questions' => 40, 'icon' => '📢', 'description' => 'Monologues, announcements, and office reports.'],
            ['name' => 'TOEIC Part 5: Incomplete Sentences', 'slug' => 'toeic-part-5', 'test_type' => 'toeic', 'section_code' => 'part_5', 'target_questions' => 50, 'icon' => '📝', 'description' => 'Grammar and vocabulary sentence fill-in.'],
            ['name' => 'TOEIC Part 6: Text Completion', 'slug' => 'toeic-part-6', 'test_type' => 'toeic', 'section_code' => 'part_6', 'target_questions' => 40, 'icon' => '📄', 'description' => 'Passage-level sentence and vocabulary completion.'],
            ['name' => 'TOEIC Part 7: Reading Passages', 'slug' => 'toeic-part-7', 'test_type' => 'toeic', 'section_code' => 'part_7', 'target_questions' => 60, 'icon' => '📰', 'description' => 'Single, double, and triple passage reading comprehension.'],

            // IELTS Libraries
            ['name' => 'IELTS Listening Library', 'slug' => 'ielts-listening', 'test_type' => 'ielts', 'section_code' => 'listening', 'target_questions' => 40, 'icon' => '🎧', 'description' => 'Social conversations and academic lecture listening.'],
            ['name' => 'IELTS Reading Library', 'slug' => 'ielts-reading', 'test_type' => 'ielts', 'section_code' => 'reading', 'target_questions' => 40, 'icon' => '📚', 'description' => 'General and academic reading text analysis.'],
            ['name' => 'IELTS Writing Tasks', 'slug' => 'ielts-writing', 'test_type' => 'ielts', 'section_code' => 'writing', 'target_questions' => 20, 'icon' => '✍️', 'description' => 'Task 1 data graph summaries and Task 2 essay prompts.'],
            ['name' => 'IELTS Speaking Prompts', 'slug' => 'ielts-speaking', 'test_type' => 'ielts', 'section_code' => 'speaking', 'target_questions' => 20, 'icon' => '🎙', 'description' => 'Part 1 intro questions, Part 2 cue cards, and Part 3 discussion prompts.'],

            // Institutional Foundational Libraries
            ['name' => 'Institutional Placement Test Bank', 'slug' => 'placement-test', 'test_type' => 'general', 'section_code' => 'placement', 'target_questions' => 50, 'icon' => '📊', 'description' => 'Diagnostic level evaluation questions for candidate streaming.'],
            ['name' => 'Core Grammar Mastery Library', 'slug' => 'core-grammar', 'test_type' => 'general', 'section_code' => 'grammar', 'target_questions' => 60, 'icon' => '🧩', 'description' => 'Tenses, conditionals, passives, and clause structure items.'],
            ['name' => 'Academic Vocabulary Repository', 'slug' => 'academic-vocabulary', 'test_type' => 'general', 'section_code' => 'vocabulary', 'target_questions' => 60, 'icon' => '🔤', 'description' => 'Academic Word List (AWL) collocations and synonyms.'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['slug']] = AclCategory::updateOrCreate(
                ['slug' => $c['slug']],
                $c
            );
        }

        // Get Teacher user for creator attribution
        $teacher = User::role('teacher')->first() ?? User::factory()->create();

        // 2. Starter Content: TOEFL Listening & Structure Banks
        $toeflBank = QuestionBank::updateOrCreate(
            ['slug' => 'toefl-listening-core-starter'],
            [
                'title'           => 'TOEFL iBT Listening & Structure Starter Pool',
                'slug'            => 'toefl-listening-core-starter',
                'test_type'       => 'toefl',
                'description'     => 'Representative starter collection for TOEFL listening comprehension and written expression.',
                'acl_category_id' => $categories['toefl-listening']->id,
                'created_by'      => $teacher->id,
                'status'          => 'published',
                'is_published'    => true,
                'current_version' => '1.0',
            ]
        );

        $q1 = Question::updateOrCreate(
            ['question_bank_id' => $toeflBank->id, 'prompt' => 'According to the conversation, why does the student visit the professor?'],
            [
                'question_type' => 'listening',
                'difficulty'    => 'medium',
                'points'        => 10,
                'passage_text'  => 'Professor: Hello Mark, what can I do for you today?\nStudent: I am concerned about my term paper outline for Environmental Science.',
                'audio_url'     => '/storage/audio/toefl_dialogue_01.mp3',
                'explanation'   => 'The student explicitly states he is concerned about his term paper outline.',
            ]
        );

        QuestionChoice::updateOrCreate(['question_id' => $q1->id, 'label' => 'A'], ['content' => 'To discuss his term paper outline', 'is_correct' => true]);
        QuestionChoice::updateOrCreate(['question_id' => $q1->id, 'label' => 'B'], ['content' => 'To ask for a deadline extension', 'is_correct' => false]);
        QuestionChoice::updateOrCreate(['question_id' => $q1->id, 'label' => 'C'], ['content' => 'To submit his final exam paper', 'is_correct' => false]);
        QuestionChoice::updateOrCreate(['question_id' => $q1->id, 'label' => 'D'], ['content' => 'To request a reference letter', 'is_correct' => false]);

        // 3. Starter Content: TOEIC Part 1 & Part 5 Banks
        $toeicBank = QuestionBank::updateOrCreate(
            ['slug' => 'toeic-workplace-starter-pool'],
            [
                'title'           => 'TOEIC Workplace Communication Starter Bank',
                'slug'            => 'toeic-workplace-starter-pool',
                'test_type'       => 'toeic',
                'description'     => 'Essential TOEIC listening and incomplete sentence workplace items.',
                'acl_category_id' => $categories['toeic-part-5']->id,
                'created_by'      => $teacher->id,
                'status'          => 'published',
                'is_published'    => true,
                'current_version' => '1.0',
            ]
        );

        $q2 = Question::updateOrCreate(
            ['question_bank_id' => $toeicBank->id, 'prompt' => 'All annual financial reports must be submitted _______ 5:00 PM on Friday.'],
            [
                'question_type' => 'multiple_choice',
                'difficulty'    => 'easy',
                'points'        => 5,
                'explanation'   => "'Before' correctly indicates the deadline requirement for Friday.",
            ]
        );

        QuestionChoice::updateOrCreate(['question_id' => $q2->id, 'label' => 'A'], ['content' => 'before', 'is_correct' => true]);
        QuestionChoice::updateOrCreate(['question_id' => $q2->id, 'label' => 'B'], ['content' => 'until', 'is_correct' => false]);
        QuestionChoice::updateOrCreate(['question_id' => $q2->id, 'label' => 'C'], ['content' => 'since', 'is_correct' => false]);
        QuestionChoice::updateOrCreate(['question_id' => $q2->id, 'label' => 'D'], ['content' => 'during', 'is_correct' => false]);

        // 4. Starter Content: IELTS Speaking & Writing Prompts
        $ieltsBank = QuestionBank::updateOrCreate(
            ['slug' => 'ielts-academic-writing-speaking-starter'],
            [
                'title'           => 'IELTS Academic Task 2 & Speaking Prompt Bank',
                'slug'            => 'ielts-academic-writing-speaking-starter',
                'test_type'       => 'ielts',
                'description'     => 'Representative Task 2 essay prompt and Part 2 speaking cue cards.',
                'acl_category_id' => $categories['ielts-writing']->id,
                'created_by'      => $teacher->id,
                'status'          => 'approved',
                'is_published'    => false,
                'current_version' => '1.0',
            ]
        );

        $q3 = Question::updateOrCreate(
            ['question_bank_id' => $ieltsBank->id, 'prompt' => 'Some people believe university education should be free for all students. To what extent do you agree or disagree?'],
            [
                'question_type' => 'essay',
                'difficulty'    => 'hard',
                'points'        => 20,
                'explanation'   => 'Provide a clear opinion, 2 body paragraphs with arguments, and a conclusion.',
            ]
        );

        // 5. Versioning & Audit Trail Records
        foreach ([$toeflBank, $toeicBank, $ieltsBank] as $bank) {
            AclVersion::updateOrCreate(
                ['resource_id' => $bank->id, 'version_number' => '1.0'],
                [
                    'resource_type'  => 'QuestionBank',
                    'title'          => $bank->title,
                    'snapshot_data'  => [
                        'id'          => $bank->id,
                        'title'       => $bank->title,
                        'test_type'   => is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type,
                        'description' => $bank->description,
                        'status'      => $bank->status,
                    ],
                    'created_by'     => $teacher->id,
                    'change_reason'  => 'Initial starter library deployment',
                    'is_current'     => true,
                ]
            );

            AclAuditTrail::updateOrCreate(
                ['resource_id' => $bank->id, 'action' => 'created'],
                [
                    'resource_type' => 'QuestionBank',
                    'actor_id'      => $teacher->id,
                    'created_by'    => $teacher->id,
                    'version'       => '1.0',
                    'reason'        => 'Seeded starter institutional content',
                ]
            );
        }
    }
}
