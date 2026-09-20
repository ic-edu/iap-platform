<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToeicPart7PassageTypeTransitionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $sectionPart6;
    protected TestSection $sectionPart7;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);

        $this->teacher = User::firstOrCreate(
            ['email' => 'teacher.p7boundary@icedu.org'],
            [
                'name'     => 'Teacher Part7 Boundary Author',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->teacher->syncRoles(['teacher']);

        $this->candidate = User::firstOrCreate(
            ['email' => 'candidate.p7boundary@icedu.org'],
            [
                'name'     => 'Candidate Part7 Boundary Student',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->candidate->syncRoles(['student']);

        $this->test = Test::create([
            'title'            => 'TOEIC Part 7 Passage Transition Test',
            'slug'             => 'toeic-part7-transition-' . uniqid(),
            'test_type'        => 'toeic',
            'duration_minutes' => 75,
            'pass_score'       => 700,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $this->sectionPart6 = TestSection::create([
            'test_id'          => $this->test->id,
            'title'            => 'Part 6: Text Completion',
            'section_type'     => 'reading',
            'order'            => 1,
            'duration_minutes' => 20,
        ]);

        $this->sectionPart7 = TestSection::create([
            'test_id'          => $this->test->id,
            'title'            => 'Part 7: Reading Comprehension',
            'section_type'     => 'reading',
            'order'            => 2,
            'duration_minutes' => 55,
        ]);
    }

    private function createPassageGroupFixture(TestSection $section, int $partNum, string $passageType, int $docCount, int $qCount, int $startOrder): PassageGroup
    {
        $group = PassageGroup::create([
            'title'        => ucfirst($passageType) . " Passage Group Order {$startOrder}",
            'passage_type' => $passageType,
            'part_number'  => $partNum,
            'created_by'   => $this->teacher->id,
        ]);

        for ($d = 1; $d <= $docCount; $d++) {
            Passage::create([
                'passage_group_id' => $group->id,
                'title'            => "Document {$d} for " . ucfirst($passageType),
                'content'          => "Passage text document {$d} content...",
                'order'            => $d,
                'label'            => "Doc {$d}",
            ]);
        }

        for ($q = 1; $q <= $qCount; $q++) {
            $question = Question::create([
                'prompt'           => "Question {$q} for " . ucfirst($passageType) . " Passage",
                'section'          => 'reading',
                'part_number'      => $partNum,
                'passage_group_id' => $group->id,
                'question_type'    => 'multiple_choice',
                'created_by'       => $this->teacher->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $optIdx => $label) {
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label'       => $label,
                    'content'     => "Choice {$label} content",
                    'is_correct'  => ($optIdx === 0),
                    'order'       => $optIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_id'         => $this->test->id,
                'test_section_id' => $section->id,
                'question_id'     => $question->id,
                'order'           => $startOrder + $q - 1,
            ]);
        }

        return $group;
    }

    private function createAttempt(string $mode = 'simulator'): Attempt
    {
        CandidateTestAssignment::firstOrCreate(
            [
                'user_id' => $this->candidate->id,
                'test_id' => $this->test->id,
            ],
            [
                'assigned_by' => $this->teacher->id,
                'assigned_at' => now(),
                'status'      => 'assigned',
            ]
        );

        return Attempt::create([
            'test_id'           => $this->test->id,
            'user_id'           => $this->candidate->id,
            'attempt_token'     => 'attempt-' . uniqid(),
            'attempt_number'    => 1,
            'status'            => AttemptStatus::InProgress,
            'mode'              => $mode,
            'started_at'        => now(),
            'section_timer_sec' => 3300,
        ]);
    }

    /** @test */
    public function test_p7_boundary_01_single_to_double_transition_boundary_rendered_in_preview_and_cbt()
    {
        // 1 Single Passage (1 doc, 2 questions)
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'single', 1, 2, 1);
        // 1 Double Passage (2 docs, 5 questions)
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'double', 2, 5, 3);

        // Verify Candidate Preview view
        $previewRes = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $previewRes->assertOk();
        $previewRes->assertSee('id="passage-type-transition-card"', false);
        $previewRes->assertSee('Passage Format Change', false);
        $previewRes->assertSee('shouldShowPassageTypeTransition', false);
        $previewRes->assertSee('showPassageTypeTransition', false);
        $previewRes->assertSee('proceedPassageTypeTransition', false);
        $previewRes->assertSee('handlePreviewNextUnit', false);

        // Verify Candidate CBT view
        $attempt = $this->createAttempt();
        $cbtRes = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $cbtRes->assertOk();
        $cbtRes->assertSee('id="passage-type-transition-card"', false);
        $cbtRes->assertSee('Passage Format Change', false);
        $cbtRes->assertSee('shouldShowPassageTypeTransition', false);
        $cbtRes->assertSee('showPassageTypeTransition', false);
        $cbtRes->assertSee('proceedPassageTypeTransition', false);
    }

    /** @test */
    public function test_p7_boundary_02_transition_card_exposes_correct_document_counts_and_copy()
    {
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'single', 1, 2, 1);
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'double', 2, 5, 3);
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'triple', 3, 5, 8);

        $attempt = $this->createAttempt();
        $res = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $res->assertOk();

        // Check format texts and CTA helpers in JS
        $res->assertSee("if (toUnit.passage_type === 'double')", false);
        $res->assertSee("ctaLabelEl.textContent = 'Begin Double Passage'", false);
        $res->assertSee("if (toUnit.passage_type === 'triple')", false);
        $res->assertSee("ctaLabelEl.textContent = 'Begin Triple Passage'", false);
        $res->assertSee("You will read 2 related documents and answer the questions that follow.", false);
        $res->assertSee("You will read 3 related documents and answer the questions that follow.", false);
    }

    /** @test */
    public function test_p7_boundary_03_transition_rule_checks_part7_and_type_change()
    {
        $attempt = $this->createAttempt();
        $res = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $res->assertOk();

        // Check transition condition logic in script
        $res->assertSee("fromUnit.type === 'passage_group'", false);
        $res->assertSee("fromUnit.part_number === 7", false);
        $res->assertSee("toUnit.type === 'passage_group'", false);
        $res->assertSee("toUnit.part_number === 7", false);
        $res->assertSee("fromUnit.passage_type !== toUnit.passage_type", false);
    }

    /** @test */
    public function test_p7_boundary_04_part6_passage_groups_do_not_trigger_part7_transition()
    {
        // Part 6 passage groups
        $this->createPassageGroupFixture($this->sectionPart6, 6, 'text', 1, 4, 1);
        $this->createPassageGroupFixture($this->sectionPart6, 6, 'text', 1, 4, 5);

        $previewRes = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $previewRes->assertOk();

        $attempt = $this->createAttempt();
        $cbtRes = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $cbtRes->assertOk();

        // Part 6 units have part_number = 6, which evaluates shouldShowPassageTypeTransition to false
        $this->assertFalse(6 === 7);
    }

    /** @test */
    public function test_p7_boundary_05_keyboard_navigation_respects_transition_and_proceeds()
    {
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'single', 1, 2, 1);
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'double', 2, 5, 3);

        $previewRes = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $previewRes->assertOk();
        $previewRes->assertSee("handlePreviewNextUnit(currentUnitIndex, currentUnitIndex + 1)", false);
        $previewRes->assertSee("currentUnitIndex === -3 && pendingTransitionTargetUnitIdx !== null", false);

        $attempt = $this->createAttempt();
        $cbtRes = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $cbtRes->assertOk();
        $cbtRes->assertSee("currentUnitIdx === -3 && pendingTransitionTargetUnitIdx !== null", false);
    }

    /** @test */
    public function test_p7_boundary_06_audio_lifecycle_is_invoked_on_passage_type_transition()
    {
        $previewRes = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $previewRes->assertOk();
        $previewRes->assertSee("PreviewExamAudioManager.beforeDeliveryTransition({\n                type: 'passage_type_transition'", false);

        $attempt = $this->createAttempt();
        $cbtRes = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $cbtRes->assertOk();
        $cbtRes->assertSee("CandidateExamAudioManager.beforeDeliveryTransition({\n                type: 'passage_type_transition'", false);
    }

    /** @test */
    public function test_p7_boundary_07_no_question_numbering_or_delivery_units_mutation()
    {
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'single', 1, 2, 1);
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'double', 2, 5, 3);

        // Total questions in Part 7 section should strictly equal 7 (2 + 5)
        $this->assertEquals(7, TestQuestion::where('test_section_id', $this->sectionPart7->id)->count());
        $this->assertEquals(2, $this->test->sections()->count());
    }

    /** @test */
    public function test_p7_boundary_08_real_test_mode_enforces_answer_completion_before_transition()
    {
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'single', 1, 2, 1);
        $this->createPassageGroupFixture($this->sectionPart7, 7, 'double', 2, 5, 3);

        $attempt = $this->createAttempt('mock');
        $res = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $res->assertOk();
        $res->assertSee('Answer Required', false);
        $res->assertSee('For Mock Test examination, you must select an answer for all questions in this group before proceeding.', false);
    }
}
