<?php

namespace Tests\Feature;

use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryReviewCollapsibleSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Jane',
            'email'  => 'teacher_collapsible@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Collapsible',
            'email'  => 'repomanager_collapsible@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'      => 'Collapsible Section Review Bank',
            'slug'       => 'collapsible-section-review-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacher->id,
        ]);
    }

    /**
     * TEST 1: Section headers are data-driven, and the first section is expanded by default while subsequent sections are collapsed.
     */
    public function test_section_headers_are_data_driven_and_first_section_is_expanded_by_default(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'Multi-Section Assessment Review',
            'slug'             => 'multi-section-assessment-review',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 75,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);

        $sec1 = TestSection::create(['test_id' => $test->id, 'title' => 'Listening Part 1', 'order' => 1]);
        $sec2 = TestSection::create(['test_id' => $test->id, 'title' => 'Reading Part 5', 'order' => 2]);
        $sec3 = TestSection::create(['test_id' => $test->id, 'title' => 'Reading Part 7', 'order' => 3]);

        $q1 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Q1 Stem in Listening']);
        $q2 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Q2 Stem in Listening']);
        $q3 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Q3 Stem in Reading Part 5']);
        $q4 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Q4 Stem in Reading Part 7']);

        TestQuestion::create(['test_section_id' => $sec1->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $sec1->id, 'question_id' => $q2->id, 'order' => 2]);
        TestQuestion::create(['test_section_id' => $sec2->id, 'question_id' => $q3->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $sec3->id, 'question_id' => $q4->id, 'order' => 1]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(200);

        $html = $response->getContent();

        // 1. Check data-driven titles & counts
        $response->assertSee('Section 1: Listening Part 1');
        $response->assertSee('2 Questions');
        $response->assertSee('Section 2: Reading Part 5');
        $response->assertSee('1 Questions');
        $response->assertSee('Section 3: Reading Part 7');

        // 2. Check First Section is Open by default
        $this->assertStringContainsString('id="section-toggle-' . $sec1->id . '"', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertMatchesRegularExpression('/id="section-content-' . preg_quote($sec1->id, '/') . '"\s+class="section-content\s+mt-3"/', $html);

        // 3. Check Subsequent Sections are Collapsed (aria-expanded="false" and class="section-content hidden mt-3")
        $this->assertMatchesRegularExpression('/id="section-content-' . preg_quote($sec2->id, '/') . '"\s+class="section-content\s+hidden\s+mt-3"/', $html);
        $this->assertMatchesRegularExpression('/id="section-content-' . preg_quote($sec3->id, '/') . '"\s+class="section-content\s+hidden\s+mt-3"/', $html);
    }

    /**
     * TEST 2: Section header shows a flagged count badge only when greater than zero.
     */
    public function test_section_header_displays_flagged_count_only_when_greater_than_zero(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'Flagged Section Assessment Review',
            'slug'             => 'flagged-section-assessment-review',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacher->id,
        ]);

        $sec1 = TestSection::create(['test_id' => $test->id, 'title' => 'Section Clean', 'order' => 1]);
        $sec2 = TestSection::create(['test_id' => $test->id, 'title' => 'Section With Issues', 'order' => 2]);

        $q1 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Clean Question 1']);
        $q2 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Issue Question 2']);
        $q3 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Issue Question 3']);

        TestQuestion::create(['test_section_id' => $sec1->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $sec2->id, 'question_id' => $q2->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $sec2->id, 'question_id' => $q3->id, 'order' => 2]);

        // Sec 1: 0 flagged
        // Sec 2: 2 flagged (1 needs_revision, 1 critical_issue)
        TestQuestionReview::create([
            'test_id'     => (string) $test->id,
            'question_id' => (string) $q2->id,
            'status'      => 'needs_revision',
            'field'       => 'choices',
            'comment'     => 'Choice typos',
            'reviewer_id' => $this->repoManager->id,
        ]);
        TestQuestionReview::create([
            'test_id'     => (string) $test->id,
            'question_id' => (string) $q3->id,
            'status'      => 'critical_issue',
            'field'       => 'correct_answer',
            'comment'     => 'No correct answer marked',
            'severity'    => 'critical',
            'reviewer_id' => $this->repoManager->id,
        ]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Sec 1 badge is hidden
        $this->assertMatchesRegularExpression('/id="section-flagged-badge-' . preg_quote($sec1->id, '/') . '"[^>]*class="[^"]*hidden[^"]*"/', $html);

        // Sec 2 badge is visible and shows count 2
        $this->assertMatchesRegularExpression('/id="section-flagged-badge-' . preg_quote($sec2->id, '/') . '"[^>]*class="[^"]*inline-flex items-center gap-1[^"]*"/', $html);
        $this->assertStringContainsString('id="section-flagged-count-' . $sec2->id . '">2</span>', $html);
    }

    /**
     * TEST 3: All question cards, choices, and annotation panels are retained in DOM across collapsed and expanded sections.
     */
    public function test_all_question_cards_and_choices_retained_in_dom(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'Retained DOM Assessment Review',
            'slug'             => 'retained-dom-assessment-review',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);

        $sec1 = TestSection::create(['test_id' => $test->id, 'title' => 'Open Section', 'order' => 1]);
        $sec2 = TestSection::create(['test_id' => $test->id, 'title' => 'Collapsed Section', 'order' => 2]);

        $q1 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Prompt Alpha in Open Sec', 'explanation' => 'Rationale Alpha']);
        $q2 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Prompt Beta in Collapsed Sec', 'explanation' => 'Rationale Beta']);

        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Option A1', 'is_correct' => true, 'order' => 1]);
        QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'Option B1', 'is_correct' => true, 'order' => 1]);

        TestQuestion::create(['test_section_id' => $sec1->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $sec2->id, 'question_id' => $q2->id, 'order' => 1]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(200);

        // Both cards exist in DOM
        $response->assertSee('id="question-card-' . $q1->id . '"', false);
        $response->assertSee('id="question-card-' . $q2->id . '"', false);
        $response->assertSee('Prompt Alpha in Open Sec');
        $response->assertSee('Prompt Beta in Collapsed Sec');
        $response->assertSee('Option A1');
        $response->assertSee('Option B1');
        $response->assertSee('Rationale Alpha');
        $response->assertSee('Rationale Beta');

        // Check dataset attributes for JavaScript section linkage
        $response->assertSee('data-section-id="' . $sec1->id . '"', false);
        $response->assertSee('data-section-id="' . $sec2->id . '"', false);
        $response->assertSee('data-status="default_ok"', false);
    }

    /**
     * TEST 4: Question Navigator correctly renders navigateToQuestion calls with section IDs and maintains target href anchors.
     */
    public function test_question_navigator_renders_navigatetoquestion_links(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'Navigator Link Assessment Review',
            'slug'             => 'navigator-link-assessment-review',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);

        $sec1 = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);
        $sec2 = TestSection::create(['test_id' => $test->id, 'title' => 'Part 2', 'order' => 2]);

        $q1 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Q1 Prompt']);
        $q2 = Question::create(['question_bank_id' => $this->bank->id, 'prompt' => 'Q2 Prompt']);

        TestQuestion::create(['test_section_id' => $sec1->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $sec2->id, 'question_id' => $q2->id, 'order' => 1]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Check navigator links
        $this->assertStringContainsString('id="nav-pill-' . $q1->id . '"', $html);
        $this->assertStringContainsString('href="#question-card-' . $q1->id . '"', $html);
        $this->assertStringContainsString("onclick=\"navigateToQuestion('{$q1->id}', '{$sec1->id}');return false;\"", $html);

        $this->assertStringContainsString('id="nav-pill-' . $q2->id . '"', $html);
        $this->assertStringContainsString('href="#question-card-' . $q2->id . '"', $html);
        $this->assertStringContainsString("onclick=\"navigateToQuestion('{$q2->id}', '{$sec2->id}');return false;\"", $html);
    }

    /**
     * TEST 5: Accessible toggle buttons have correct aria attributes.
     */
    public function test_accessible_toggle_buttons_have_correct_aria_attributes(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'Accessibility Assessment Review',
            'slug'             => 'accessibility-assessment-review',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);

        $sec1 = TestSection::create(['test_id' => $test->id, 'title' => 'Section A', 'order' => 1]);
        $sec2 = TestSection::create(['test_id' => $test->id, 'title' => 'Section B', 'order' => 2]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Check aria-controls and aria-expanded
        $this->assertStringContainsString('aria-controls="section-content-' . $sec1->id . '"', $html);
        $this->assertStringContainsString('aria-controls="section-content-' . $sec2->id . '"', $html);
        $this->assertStringContainsString('onclick="toggleSection(\'' . $sec1->id . '\')"', $html);
        $this->assertStringContainsString('onclick="toggleSection(\'' . $sec2->id . '\')"', $html);
    }
}
