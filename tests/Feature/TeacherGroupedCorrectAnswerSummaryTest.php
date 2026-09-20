<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherGroupedCorrectAnswerSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected Test $test;
    protected TestSection $part3Section;
    protected TestSection $part4Section;
    protected TestSection $part6Section;
    protected TestSection $part7Section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'TOEIC Teacher',
            'email'  => 'toeic_grouped_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title'            => 'TOEIC Full Assessment Authoring Test',
            'slug'             => 'toeic-grouped-authoring-test-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 120,
            'pass_score'       => 750,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacher->id,
            'assigned_to'      => $this->teacher->id,
            'scoring_method'   => 'automatic',
        ]);

        $this->part3Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 3: Conversations',
            'section_type' => 'listening',
            'order'        => 3,
        ]);

        $this->part4Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 4: Short Talks',
            'section_type' => 'listening',
            'order'        => 4,
        ]);

        $this->part6Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 6: Text Completion',
            'section_type' => 'reading',
            'order'        => 6,
        ]);

        $this->part7Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 7: Reading Comprehension',
            'section_type' => 'reading',
            'order'        => 7,
        ]);
    }

    public function test_qa_group_answer_01_part3_audio_group_shows_correct_answers(): void
    {
        $ag = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Office Renovation Discussion',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'https://example.com/audio_p3.mp3',
            'order'       => 1,
        ]);

        $answers = ['A', 'C', 'D'];
        foreach ($answers as $idx => $correctAns) {
            $q = Question::create([
                'prompt'         => "Part 3 Child Question #" . ($idx + 1),
                'section'        => 'listening',
                'part_number'    => 3,
                'audio_group_id' => $ag->id,
                'difficulty'     => 'medium',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $lbl,
                    'content'     => "Choice {$lbl}",
                    'is_correct'  => ($lbl === $correctAns),
                    'order'       => $cIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $this->part3Section->id,
                'question_id'     => $q->id,
                'order'           => $idx + 1,
            ]);
        }

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString("id=\"audio-group-card-{$ag->id}\"", $html);
        $this->assertStringContainsString('Correct Answer: A', $html);
        $this->assertStringContainsString('Correct Answer: C', $html);
        $this->assertStringContainsString('Correct Answer: D', $html);
    }

    public function test_qa_group_answer_02_part4_audio_group_shows_correct_answers(): void
    {
        $ag = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Airport Flight Announcement',
            'group_type'  => 'talk',
            'part_number' => 4,
            'audio_url'   => 'https://example.com/audio_p4.mp3',
            'order'       => 1,
        ]);

        $answers = ['B', 'A', 'C'];
        foreach ($answers as $idx => $correctAns) {
            $q = Question::create([
                'prompt'         => "Part 4 Child Question #" . ($idx + 1),
                'section'        => 'listening',
                'part_number'    => 4,
                'audio_group_id' => $ag->id,
                'difficulty'     => 'easy',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $lbl,
                    'content'     => "Choice {$lbl}",
                    'is_correct'  => ($lbl === $correctAns),
                    'order'       => $cIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $this->part4Section->id,
                'question_id'     => $q->id,
                'order'           => $idx + 1,
            ]);
        }

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString("id=\"audio-group-card-{$ag->id}\"", $html);
        $this->assertStringContainsString('Correct Answer: B', $html);
        $this->assertStringContainsString('Correct Answer: A', $html);
        $this->assertStringContainsString('Correct Answer: C', $html);
    }

    public function test_qa_group_answer_03_part6_complete_passage_group(): void
    {
        $pg = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Email to Staff',
            'part_number'  => 6,
            'passage_type' => 'single',
            'order'        => 1,
        ]);

        Passage::create([
            'passage_group_id' => $pg->id,
            'title'            => 'Internal Memo',
            'content'          => 'Please review the updated policy in blanks [131], [132], [133], and [134].',
            'document_type'    => 'email',
            'order_in_group'   => 1,
        ]);

        $answers = ['A', 'B', 'C', 'D'];
        foreach ($answers as $idx => $correctAns) {
            $q = Question::create([
                'prompt'           => "Blank [13" . ($idx + 1) . "] context",
                'section'          => 'reading',
                'part_number'      => 6,
                'passage_group_id' => $pg->id,
                'difficulty'       => 'medium',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $lbl,
                    'content'     => "Option {$lbl}",
                    'is_correct'  => ($lbl === $correctAns),
                    'order'       => $cIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $this->part6Section->id,
                'question_id'     => $q->id,
                'order'           => $idx + 1,
            ]);
        }

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString("id=\"passage-group-card-{$pg->id}\"", $html);
        $this->assertStringContainsString('Correct Answer: A', $html);
        $this->assertStringContainsString('Correct Answer: B', $html);
        $this->assertStringContainsString('Correct Answer: C', $html);
        $this->assertStringContainsString('Correct Answer: D', $html);
    }

    public function test_qa_group_answer_04_part6_partial_slot_shows_neutral_fallback(): void
    {
        $pg = PassageGroup::create([
            'test_id'          => $this->test->id,
            'title'            => 'Newsletter Text Completion',
            'part_number'      => 6,
            'passage_type'     => 'single',
            'order'            => 1,
        ]);

        Passage::create([
            'passage_group_id' => $pg->id,
            'title'            => 'Newsletter Article',
            'content'          => 'Welcome to our company [131], please check [132], etc.',
            'document_type'    => 'article',
            'order_in_group'   => 1,
        ]);

        // Slot 1: Complete (A)
        $q1 = Question::create([
            'prompt'           => 'Blank [131]',
            'section'          => 'reading',
            'part_number'      => 6,
            'passage_group_id' => $pg->id,
            'difficulty'       => 'easy',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q1->id, 'label' => $lbl, 'content' => "Opt {$lbl}", 'is_correct' => ($lbl === 'A'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part6Section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Slot 3: Complete (C)
        $q3 = Question::create([
            'prompt'           => 'Blank [133]',
            'section'          => 'reading',
            'part_number'      => 6,
            'passage_group_id' => $pg->id,
            'difficulty'       => 'hard',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q3->id, 'label' => $lbl, 'content' => "Opt {$lbl}", 'is_correct' => ($lbl === 'C'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part6Section->id, 'question_id' => $q3->id, 'order' => 3]);

        // Slot 4: Complete (D)
        $q4 = Question::create([
            'prompt'           => 'Blank [134]',
            'section'          => 'reading',
            'part_number'      => 6,
            'passage_group_id' => $pg->id,
            'difficulty'       => 'medium',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q4->id, 'label' => $lbl, 'content' => "Opt {$lbl}", 'is_correct' => ($lbl === 'D'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part6Section->id, 'question_id' => $q4->id, 'order' => 4]);

        // Slot 2 is partial in draft_slots
        $pg->update([
            'context_metadata' => [
                'draft_slots' => [
                    0 => ['slot' => 0, 'state' => 'complete', 'question_id' => $q1->id],
                    1 => ['slot' => 1, 'state' => 'partial', 'prompt' => 'Draft context for 132', 'choices' => ['a', 'b', '', ''], 'correct_choice' => null],
                    2 => ['slot' => 2, 'state' => 'complete', 'question_id' => $q3->id],
                    3 => ['slot' => 3, 'state' => 'complete', 'question_id' => $q4->id],
                ],
            ],
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('Correct Answer: A', $html);
        $this->assertStringContainsString('Correct Answer: C', $html);
        $this->assertStringContainsString('Correct Answer: D', $html);
        $this->assertStringContainsString('Correct Answer: —', $html);
    }

    public function test_qa_group_answer_05_part6_zero_complete_group_shows_neutral_fallback(): void
    {
        $pg = PassageGroup::create([
            'test_id'          => $this->test->id,
            'title'            => 'Zero Complete Group',
            'part_number'      => 6,
            'passage_type'     => 'single',
            'order'            => 1,
            'context_metadata' => [
                'draft_slots' => [
                    0 => ['slot' => 0, 'state' => 'untouched'],
                    1 => ['slot' => 1, 'state' => 'partial', 'prompt' => 'Work in progress', 'choices' => ['', '', '', ''], 'correct_choice' => null],
                    2 => ['slot' => 2, 'state' => 'untouched'],
                    3 => ['slot' => 3, 'state' => 'untouched'],
                ],
            ],
        ]);

        Passage::create([
            'passage_group_id' => $pg->id,
            'title'            => 'Draft Passage',
            'content'          => 'Work in progress text...',
            'document_type'    => 'notice',
            'order_in_group'   => 1,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString("id=\"passage-group-card-{$pg->id}\"", $html);
        $this->assertStringContainsString('Correct Answer: —', $html);
        // Ensure no Question DB records were created
        $this->assertSame(0, Question::where('passage_group_id', $pg->id)->count());
    }

    public function test_qa_group_answer_06_part7_passage_group_shows_correct_answers(): void
    {
        $pg = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Double Passage Review',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
        ]);

        Passage::create([
            'passage_group_id' => $pg->id,
            'title'            => 'Document 1',
            'content'          => 'First document content.',
            'document_type'    => 'letter',
            'order_in_group'   => 1,
        ]);

        Passage::create([
            'passage_group_id' => $pg->id,
            'title'            => 'Document 2',
            'content'          => 'Second document content.',
            'document_type'    => 'form',
            'order_in_group'   => 2,
        ]);

        $answers = ['B', 'D'];
        foreach ($answers as $idx => $correctAns) {
            $q = Question::create([
                'prompt'           => "Part 7 Question " . ($idx + 1),
                'section'          => 'reading',
                'part_number'      => 7,
                'passage_group_id' => $pg->id,
                'difficulty'       => 'medium',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $lbl,
                    'content'     => "Choice {$lbl}",
                    'is_correct'  => ($lbl === $correctAns),
                    'order'       => $cIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $this->part7Section->id,
                'question_id'     => $q->id,
                'order'           => $idx + 1,
            ]);
        }

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString("id=\"passage-group-card-{$pg->id}\"", $html);
        $this->assertStringContainsString('Correct Answer: B', $html);
        $this->assertStringContainsString('Correct Answer: D', $html);
    }

    public function test_qa_group_answer_07_multiple_group_isolation(): void
    {
        // Group 1
        $ag1 = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Audio Group 1',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'https://example.com/ag1.mp3',
            'order'       => 1,
        ]);
        $q1 = Question::create(['prompt' => 'P3 Q1', 'section' => 'listening', 'part_number' => 3, 'audio_group_id' => $ag1->id, 'difficulty' => 'easy']);
        foreach (['A', 'B', 'C', 'D'] as $i => $lbl) {
            QuestionChoice::create(['question_id' => $q1->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'A'), 'order' => $i + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Group 2
        $ag2 = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Audio Group 2',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'https://example.com/ag2.mp3',
            'order'       => 2,
        ]);
        $q2 = Question::create(['prompt' => 'P3 Q2', 'section' => 'listening', 'part_number' => 3, 'audio_group_id' => $ag2->id, 'difficulty' => 'medium']);
        foreach (['A', 'B', 'C', 'D'] as $i => $lbl) {
            QuestionChoice::create(['question_id' => $q2->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'C'), 'order' => $i + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q2->id, 'order' => 2]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        // Check card 1 has A
        $c1Start = strpos($html, "<div id=\"audio-group-card-{$ag1->id}\"");
        $c2Start = strpos($html, "<div id=\"audio-group-card-{$ag2->id}\"");
        $this->assertNotFalse($c1Start);
        $this->assertNotFalse($c2Start);

        $c1Chunk = substr($html, $c1Start, $c2Start - $c1Start);
        $c2Chunk = substr($html, $c2Start, 10000);

        $this->assertStringContainsString('Correct Answer: A', $c1Chunk);
        $this->assertStringContainsString('Correct Answer: C', $c2Chunk);
    }

    public function test_qa_group_answer_08_updated_answer_via_edit_updates_summary(): void
    {
        $ag = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Initial Group',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'https://example.com/audio.mp3',
            'order'       => 1,
        ]);

        $q = Question::create(['prompt' => 'Child Q', 'section' => 'listening', 'part_number' => 3, 'audio_group_id' => $ag->id, 'difficulty' => 'easy']);
        foreach (['A', 'B', 'C', 'D'] as $i => $lbl) {
            QuestionChoice::create(['question_id' => $q->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'A'), 'order' => $i + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q->id, 'order' => 1]);

        $resInitial = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $resInitial->assertSee('Correct Answer: A', false);

        // Update answer to D
        foreach ($q->choices as $choice) {
            $choice->update(['is_correct' => ($choice->label === 'D')]);
        }

        $resUpdated = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $resUpdated->assertSee('Correct Answer: D', false);
    }

    public function test_qa_group_answer_09_no_icon_present(): void
    {
        $ag = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Clean Visual Group',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'https://example.com/audio.mp3',
            'order'       => 1,
        ]);

        $q = Question::create(['prompt' => 'Child Q', 'section' => 'listening', 'part_number' => 3, 'audio_group_id' => $ag->id, 'difficulty' => 'easy']);
        foreach (['A', 'B', 'C', 'D'] as $i => $lbl) {
            QuestionChoice::create(['question_id' => $q->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'B'), 'order' => $i + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q->id, 'order' => 1]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $cardStart = strpos($html, "<div id=\"audio-group-card-{$ag->id}\"");
        $cardChunk = substr($html, $cardStart, 10000);

        $this->assertStringContainsString('Correct Answer: B', $cardChunk);
        $this->assertStringNotContainsString('🎯 Correct Answer', $cardChunk);
    }

    public function test_qa_group_answer_10_green_and_neutral_styling_contract(): void
    {
        $ag = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Style Contract Group',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'https://example.com/audio.mp3',
            'order'       => 1,
        ]);

        // Complete question with correct answer A
        $q1 = Question::create(['prompt' => 'Child Q1', 'section' => 'listening', 'part_number' => 3, 'audio_group_id' => $ag->id, 'difficulty' => 'easy']);
        foreach (['A', 'B', 'C', 'D'] as $i => $lbl) {
            QuestionChoice::create(['question_id' => $q1->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'A'), 'order' => $i + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Incomplete question with NO correct choice
        $q2 = Question::create(['prompt' => 'Child Q2', 'section' => 'listening', 'part_number' => 3, 'audio_group_id' => $ag->id, 'difficulty' => 'medium']);
        foreach (['A', 'B', 'C', 'D'] as $i => $lbl) {
            QuestionChoice::create(['question_id' => $q2->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => false, 'order' => $i + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q2->id, 'order' => 2]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        // Green emerald style for Q1
        $res->assertSee('bg-emerald-50 text-emerald-700 border-emerald-200', false);
        $res->assertSee('Correct Answer: A', false);

        // Neutral slate style for Q2
        $res->assertSee('bg-slate-100 text-slate-500 border-slate-200', false);
        $res->assertSee('Correct Answer: —', false);
    }
}
