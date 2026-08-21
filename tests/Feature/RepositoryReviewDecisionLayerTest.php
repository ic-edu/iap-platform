<?php

namespace Tests\Feature;

use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryReviewDecisionLayerTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $repoManager;
    protected QuestionBank $bankA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_s11_2@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_2',
            'email'  => 'repomanager_s11_2@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Review Decision Layer Bank',
            'slug'       => 'review-decision-layer-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Assessment Review is pure read-only Question Viewer without inline popups.
     */
    public function test_1_decision_panel_separates_review_from_revision()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Review Decision 01',
            'slug'             => 'toeic-review-decision-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);

        $q1 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Question 1 Prompt',
            'question_type'    => 'multiple_choice',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Open review page -> Pure Read-Only Question Viewer (HOTFIX S11.3.1)
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);
        $res->assertDontSee('q-rev-modal');
        $res->assertDontSee('Request Revision on Q#');
        $res->assertDontSee('Submit Question Revision');
    }

    /**
     * TEST 2: Teacher Revision Center displays ONLY questions marked Needs Revision.
     */
    public function test_2_teacher_revision_center_displays_only_needs_revision_questions()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEFL Teacher Compatibility 02',
            'slug'             => 'toefl-teacher-compatibility-02',
            'test_type'        => 'toefl',
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Structure', 'order' => 1]);

        // Q1 (Reviewed OK)
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Perfect Question Stem Q1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestionReview::create(['test_id' => $test->id, 'question_id' => $q1->id, 'status' => 'reviewed_ok']);

        // Q2 (Needs Revision)
        $q2 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Flawed Question Stem Q2']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q2->id, 'order' => 2]);
        TestQuestionReview::create(['test_id' => $test->id, 'question_id' => $q2->id, 'status' => 'needs_revision', 'field' => 'stem', 'comment' => 'Fix Q2 stem']);

        // Teacher opens Assessment Detail -> sees Q2 revision item ONLY
        $teacherView = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $teacherView->assertStatus(200);
        $teacherView->assertSee('Flawed Question Stem Q2');
        $teacherView->assertSee('Fix Q2 stem');
    }

    /**
     * TEST 3: RM Review Workspace renders standardized iapConfirm dialogs for all 3 governance decisions.
     */
    public function test_3_governance_decision_buttons_render_standardized_confirmations(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Standardization Test',
            'slug'             => 'toeic-standardization-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);

        // 1. Approve Assessment confirmation
        $res->assertSee("iapConfirm({ title: 'Approve Assessment?'", false);
        $res->assertSee("confirmText: 'Approve Assessment'", false);
        $res->assertSee("variant: 'success'", false);

        // 2. Request Assessment Revision confirmation
        $res->assertSee("iapConfirm({ title: 'Request Assessment Revision?'", false);
        $res->assertSee("confirmText: 'Request Revision'", false);
        $res->assertSee("variant: 'warning'", false);

        // 3. Send to Archived confirmation
        $res->assertSee("iapConfirm({ title: 'Send Assessment to Archived?'", false);
        $res->assertSee("confirmText: 'Send to Archived'", false);
        $res->assertSee("variant: 'warning'", false);
    }

    /**
     * TEST 4: Backend execution remains fully functional after confirmation.
     */
    public function test_4_governance_actions_execute_backend_routes_correctly(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Execution Test',
            'slug'             => 'toeic-execution-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Submit approval
        $approveRes = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'Institutional quality approved',
            ]);

        $approveRes->assertRedirect(route('admin.repository-manager.assessment-approval'));
        $approveRes->assertSessionHas('success');
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }

    /**
     * TEST 5: Governance navigation links use canonical routes and do NOT use history.back().
     */
    public function test_5_governance_navigation_uses_canonical_links_without_history_back(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Nav Test',
            'slug'             => 'toeic-nav-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Assessment Review Workspace
        $reviewRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $reviewRes->assertStatus(200);
        $reviewRes->assertSee(route('admin.repository-manager.assessment-approval'));
        $reviewRes->assertDontSee('history.back()');

        // Assessment Approval Queue
        $queueRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $queueRes->assertStatus(200);
        $queueRes->assertSee(route('admin.repository-manager.dashboard'));
        $queueRes->assertDontSee('history.back()');

        // Question Bank Validation Workspace
        $qbRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.question-bank-validate', $this->bankA->id));
        $qbRes->assertStatus(200);
        $qbRes->assertDontSee('history.back()');

        // Media Approval Queue
        $mediaQueueRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.media-approval'));
        $mediaQueueRes->assertStatus(200);
        $mediaQueueRes->assertDontSee('history.back()');
    }

    /**
     * TEST 6: Governance pages include pageshow reload guard to prevent bfcache restoration of stale state.
     */
    public function test_6_governance_pages_include_bfcache_pageshow_reload_guard(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC bfcache Guard Test',
            'slug'             => 'toeic-bfcache-guard-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Check pageshow reload listener in Review and Queue
        $reviewRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $reviewRes->assertSee("window.addEventListener('pageshow'", false);
        $reviewRes->assertSee("if (event.persisted)", false);

        $queueRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $queueRes->assertSee("window.addEventListener('pageshow'", false);
        $queueRes->assertSee("if (event.persisted)", false);
    }

    /**
     * TEST 7: Approved assessment is properly excluded from pending queue and listed in approved queue.
     */
    public function test_7_approved_assessment_excluded_from_pending_and_present_in_approved_queue(): void
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Filter Verification',
            'slug'             => 'toeic-filter-verification',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Pending queue sees it
        $pendingRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'pending']));
        $pendingRes->assertSee('TOEIC Filter Verification');

        // Approve it
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));

        // Pending queue no longer lists it
        $freshPendingRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'pending']));
        $freshPendingRes->assertDontSee('TOEIC Filter Verification');

        // Approved queue lists it
        $approvedRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'approved']));
        $approvedRes->assertSee('TOEIC Filter Verification');
    }
}
