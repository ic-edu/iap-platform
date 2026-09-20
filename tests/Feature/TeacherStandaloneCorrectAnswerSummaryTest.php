<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherStandaloneCorrectAnswerSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected Test $test;
    protected TestSection $part1Section;
    protected TestSection $part2Section;
    protected TestSection $part5Section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'TOEIC Teacher',
            'email'  => 'toeic_author_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title'            => 'TOEIC Full Assessment Authoring Test',
            'slug'             => 'toeic-full-assessment-authoring-test-' . \Illuminate\Support\Str::random(6),
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

        $this->part1Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $this->part2Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 2: Question-Response',
            'section_type' => 'listening',
            'order'        => 2,
        ]);

        $this->part5Section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 5: Incomplete Sentences',
            'section_type' => 'reading',
            'order'        => 3,
        ]);
    }

    public function test_qa_answer_01_part1_question_shows_correct_answer_a(): void
    {
        $q = Question::create([
            'prompt'      => 'Look at the photograph and listen to the audio.',
            'section'     => 'listening',
            'part_number' => 1,
            'image_url'   => 'https://example.com/photo.jpg',
            'audio_url'   => 'https://example.com/audio.mp3',
            'difficulty'  => 'easy',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $lbl,
                'content'     => "Option {$lbl}",
                'is_correct'  => ($lbl === 'A'),
                'order'       => $idx + 1,
            ]);
        }

        TestQuestion::create([
            'test_section_id' => $this->part1Section->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);
        $res->assertSee('Correct Answer: A', false);
    }

    public function test_qa_answer_02_part1_alternate_answer_d(): void
    {
        $q = Question::create([
            'prompt'      => 'Look at the photograph for item 2.',
            'section'     => 'listening',
            'part_number' => 1,
            'image_url'   => 'https://example.com/photo2.jpg',
            'audio_url'   => 'https://example.com/audio2.mp3',
            'difficulty'  => 'medium',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $lbl,
                'content'     => "Option {$lbl}",
                'is_correct'  => ($lbl === 'D'),
                'order'       => $idx + 1,
            ]);
        }

        TestQuestion::create([
            'test_section_id' => $this->part1Section->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);
        $res->assertSee('Correct Answer: D', false);
    }

    public function test_qa_answer_03_part2_question_shows_correct_answer_b_and_no_d(): void
    {
        $q = Question::create([
            'prompt'      => 'Where did you leave the meeting notes?',
            'section'     => 'listening',
            'part_number' => 2,
            'audio_url'   => 'https://example.com/part2_audio.mp3',
            'difficulty'  => 'easy',
        ]);

        foreach (['A', 'B', 'C'] as $idx => $lbl) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $lbl,
                'content'     => "Response {$lbl}",
                'is_correct'  => ($lbl === 'B'),
                'order'       => $idx + 1,
            ]);
        }

        TestQuestion::create([
            'test_section_id' => $this->part2Section->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);
        $res->assertSee('Correct Answer: B', false);
        $res->assertDontSee('Correct Answer: D', false);
    }

    public function test_qa_answer_04_part5_question_shows_correct_answer_c(): void
    {
        $q = Question::create([
            'prompt'      => 'The quarterly sales report will be _______ next Tuesday.',
            'section'     => 'reading',
            'part_number' => 5,
            'difficulty'  => 'medium',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $lbl,
                'content'     => "Choice {$lbl}",
                'is_correct'  => ($lbl === 'C'),
                'order'       => $idx + 1,
            ]);
        }

        TestQuestion::create([
            'test_section_id' => $this->part5Section->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);
        $res->assertSee('Correct Answer: C', false);
    }

    public function test_qa_answer_05_missing_correct_answer_shows_neutral_fallback(): void
    {
        $q = Question::create([
            'prompt'      => 'A question without any correct choice marked.',
            'section'     => 'reading',
            'part_number' => 5,
            'difficulty'  => 'hard',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $lbl,
                'content'     => "Choice {$lbl}",
                'is_correct'  => false,
                'order'       => $idx + 1,
            ]);
        }

        TestQuestion::create([
            'test_section_id' => $this->part5Section->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);
        $res->assertSee('Correct Answer: —', false);
    }

    public function test_qa_answer_06_multiple_cards_display_their_own_distinct_answers(): void
    {
        $q1 = Question::create([
            'prompt'      => 'Question One Prompt',
            'section'     => 'reading',
            'part_number' => 5,
            'difficulty'  => 'easy',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q1->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'A'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q1->id, 'order' => 1]);

        $q2 = Question::create([
            'prompt'      => 'Question Two Prompt',
            'section'     => 'reading',
            'part_number' => 5,
            'difficulty'  => 'medium',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q2->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'C'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q2->id, 'order' => 2]);

        $q3 = Question::create([
            'prompt'      => 'Question Three Prompt',
            'section'     => 'reading',
            'part_number' => 5,
            'difficulty'  => 'hard',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q3->id, 'label' => $lbl, 'content' => "C {$lbl}", 'is_correct' => ($lbl === 'D'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q3->id, 'order' => 3]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $content = $res->getContent();
        // Check that card 1 contains Correct Answer: A
        $this->assertStringContainsString("id=\"question-card-{$q1->id}\"", $content);
        $this->assertStringContainsString("id=\"question-card-{$q2->id}\"", $content);
        $this->assertStringContainsString("id=\"question-card-{$q3->id}\"", $content);
        $res->assertSee('Correct Answer: A', false);
        $res->assertSee('Correct Answer: C', false);
        $res->assertSee('Correct Answer: D', false);
    }

    public function test_qa_answer_07_updated_answer_via_question_edit_updates_summary_card(): void
    {
        $q = Question::create([
            'prompt'      => 'Sentence needing word completion.',
            'section'     => 'reading',
            'part_number' => 5,
            'difficulty'  => 'easy',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
            QuestionChoice::create(['question_id' => $q->id, 'label' => $lbl, 'content' => "Option {$lbl}", 'is_correct' => ($lbl === 'A'), 'order' => $idx + 1]);
        }
        TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q->id, 'order' => 1]);

        $resInitial = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $resInitial->assertSee('Correct Answer: A', false);

        // Update correct choice to B
        $choices = $q->choices()->get();
        foreach ($choices as $choice) {
            $choice->update(['is_correct' => ($choice->label === 'B')]);
        }

        $resUpdated = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $resUpdated->assertSee('Correct Answer: B', false);
        $resUpdated->assertDontSee('Correct Answer: A', false);
    }
}
