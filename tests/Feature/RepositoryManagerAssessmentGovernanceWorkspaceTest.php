<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Modules\Organization\Models\Organization;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepositoryManagerAssessmentGovernanceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $repoManager;
    protected User $teacher;
    protected User $raUser;
    protected Organization $organization;
    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'regional-admin']);
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'coordinator']);

        $this->superAdmin = User::factory()->create([
            'email' => 'sa_gov_workspace@test.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->repoManager = User::factory()->create([
            'email' => 'rm_gov_workspace@test.com',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create([
            'email' => 'teacher_gov_workspace@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->raUser = User::factory()->create([
            'email' => 'ra_gov_workspace@test.com',
            'status' => 'active',
        ]);
        $this->raUser->assignRole('regional-admin');

        $this->organization = Organization::create([
            'name' => 'Test University',
            'slug' => 'test-university-' . uniqid(),
            'status' => 'active',
        ]);

        $this->bank = QuestionBank::create([
            'title'      => 'Core Bank ' . uniqid(),
            'slug'       => 'core-bank-' . uniqid(),
            'test_type'  => 'toeic',
            'status'     => 'approved',
            'created_by' => $this->teacher->id,
        ]);
    }

    private function createAssessmentWithQuestion(string $status = 'pending_approval', bool $isPublished = false, ?int $createdBy = null): AssessmentTest
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Governance Test ' . uniqid(),
            'slug'             => 'toeic-gov-' . uniqid(),
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => $status,
            'is_published'     => $isPublished,
            'created_by'       => $createdBy ?? $this->teacher->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening Section', 'order' => 1]);
        $q = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Select the best response for audio question',
            'type'             => 'single_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

        return $test;
    }

    /**
     * RM-GOV-01: RM Dashboard Card 2 renders ONE clear primary CTA to Assessment Governance.
     */
    public function test_rm_gov_01_dashboard_card_renders_one_clear_primary_cta(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Assessment Governance');
        $res->assertSee('Pending Review');
        $res->assertSee('Ready to Publish');
        $res->assertSee('Open Assessment Governance');
        $res->assertSee(route('admin.repository-manager.assessment-governance'));

        // Assert old 3 small stacked links are removed from Card 2
        $res->assertDontSee('Request Intake Queue →');
        $res->assertDontSee('Assessment Queue →');
    }

    /**
     * RM-GOV-02: Assessment Governance Workspace renders all 4 lifecycle tabs and KPI counters.
     */
    public function test_rm_gov_02_workspace_renders_tabs_and_kpis(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance'));

        $res->assertStatus(200);
        $res->assertSee('Assessment Governance Workspace');
        $res->assertSee('Manage assessment requests, repository review, publication readiness, and published assessment governance.');

        // Verify tabs
        $res->assertSee('Request Intake');
        $res->assertSee('Pending Review');
        $res->assertSee('Ready to Publish');
        $res->assertSee('Published');

        // Verify query parameter support
        $resIntake = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'request-intake']));
        $resIntake->assertStatus(200);
        $resIntake->assertSee('Assessment Request Intake Queue');

        $resPending = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'pending-review']));
        $resPending->assertStatus(200);
        $resPending->assertSee('Active Review Queue');

        $resReady = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'ready-to-publish']));
        $resReady->assertStatus(200);
        $resReady->assertSee('Approved Assessments Ready for Publication');

        $resPub = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'published']));
        $resPub->assertStatus(200);
        $resPub->assertSee('Published Assessments Registry');
    }

    /**
     * RM-GOV-03: Request Intake Tab renders intake requests, draft creation modal, and RM read-only draft inspection.
     */
    public function test_rm_gov_03_request_intake_tab_renders_intake_queue_and_actions(): void
    {
        $req = AssessmentRequest::create([
            'requested_by' => $this->raUser->id,
            'title' => 'Midterm English Assessment 2026',
            'test_type' => 'toeic',
            'notes' => 'Need 100 questions',
            'status' => 'pending',
        ]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'request-intake']));

        $res->assertStatus(200);
        $res->assertSee('Midterm English Assessment 2026');
        $res->assertSee('Create Draft &amp; Assign', false);
        $res->assertSee('openAssignDraftModal');
    }

    /**
     * RM-GOV-04: Submitted assessments appear in Pending Review; Approved assessments are excluded; Publish action is excluded.
     */
    public function test_rm_gov_04_pending_review_tab_shows_only_submitted_and_no_publish_button(): void
    {
        $pendingTest = $this->createAssessmentWithQuestion('pending_approval', false);
        $approvedTest = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'pending-review']));

        $res->assertStatus(200);
        // Pending test is visible with Smart Review CTA
        $res->assertSee($pendingTest->title);
        $res->assertSee(route('admin.repository-manager.assessment-review', $pendingTest->id));
        $res->assertSee('🔍 Smart Review');

        // Approved test must NOT be in the pending review list
        $res->assertDontSee($approvedTest->title);

        // Publish action MUST NOT exist in the pending review tab
        $res->assertDontSee('🚀 Publish');
        $res->assertDontSee(route('admin.publications.assessments.publish', $pendingTest->id));
    }

    /**
     * RM-GOV-05: Approved + unpublished assessments appear in Ready to Publish with Publish action.
     */
    public function test_rm_gov_05_ready_to_publish_tab_is_canonical_queue_with_publish_action(): void
    {
        $approvedTest = $this->createAssessmentWithQuestion('approved', false);
        $pendingTest = $this->createAssessmentWithQuestion('pending_approval', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'ready-to-publish']));

        $res->assertStatus(200);
        // Approved test is visible with Publish CTA
        $res->assertSee($approvedTest->title);
        $res->assertSee('🚀 Publish');
        $res->assertSee(route('admin.publications.assessments.publish', $approvedTest->id));

        // Pending test is NOT in ready-to-publish
        $res->assertDontSee($pendingTest->title);

        // Review action is NOT on ready-to-publish
        $res->assertDontSee(route('admin.repository-manager.assessment-review', $approvedTest->id));
    }

    /**
     * RM-GOV-06: Published assessments appear in Published tab with Unpublish action.
     */
    public function test_rm_gov_06_published_tab_shows_live_registry_with_unpublish_action(): void
    {
        $publishedTest = $this->createAssessmentWithQuestion('published', true);
        $approvedTest = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'published']));

        $res->assertStatus(200);
        $res->assertSee($publishedTest->title);
        $res->assertSee('⏸ Unpublish');
        $res->assertSee(route('admin.publications.assessments.unpublish', $publishedTest->id));

        // Approved unpublished test is not in published tab
        $res->assertDontSee($approvedTest->title);
    }

    /**
     * RM-GOV-07: Legacy route /admin/repository-manager/assessments forwards/renders governance workspace.
     */
    public function test_rm_gov_07_legacy_assessment_approval_route_delegates_to_governance_workspace(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));

        $res->assertStatus(200);
        $res->assertSee('Assessment Governance Workspace');
        $res->assertSee('Request Intake');
        $res->assertSee('Pending Review');
    }

    /**
     * RM-GOV-08: Legacy publication queue route delegates to governance workspace ready-to-publish tab.
     */
    public function test_rm_gov_08_legacy_publication_queue_route_delegates_to_ready_to_publish_tab(): void
    {
        $approvedTest = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments'));

        $res->assertStatus(200);
        $res->assertSee('Assessment Governance Workspace');
        $res->assertSee('Approved Assessments Ready for Publication');
        $res->assertSee($approvedTest->title);
        $res->assertSee('🚀 Publish');
    }

    /**
     * RM-GOV-09: Smart Review engine is strictly accessible only for pending assessments.
     */
    public function test_rm_gov_09_smart_review_engine_boundary_enforcement(): void
    {
        $pendingTest = $this->createAssessmentWithQuestion('pending_approval', false);
        $approvedTest = $this->createAssessmentWithQuestion('approved', false);

        // Pending -> 200 OK
        $resPending = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $pendingTest->id));
        $resPending->assertStatus(200);
        $resPending->assertSee('Review by Exception Mode');

        // Approved -> Redirects to Ready to Publish with status notification
        $resApproved = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $approvedTest->id));
        $resApproved->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $approvedTest->id]));
    }

    /**
     * RM-GOV-10: Canonical publish action publishes test fixture.
     */
    public function test_rm_gov_10_canonical_publish_action_executes_successfully(): void
    {
        $approvedTest = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $approvedTest->id));

        $res->assertRedirect();
        $approvedTest->refresh();
        $this->assertEquals('published', $approvedTest->status);
        $this->assertTrue((bool) $approvedTest->is_published);
    }

    /**
     * RM-GOV-11: Canonical unpublish action unpublishes test fixture back to approved.
     */
    public function test_rm_gov_11_canonical_unpublish_action_executes_successfully(): void
    {
        $publishedTest = $this->createAssessmentWithQuestion('published', true);

        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $publishedTest->id));

        $res->assertRedirect();
        $publishedTest->refresh();
        $this->assertEquals('approved', $publishedTest->status);
        $this->assertFalse((bool) $publishedTest->is_published);
    }

    /**
     * RM-GOV-12: RM read-only draft inspection remains strictly accessible from Request Intake.
     */
    public function test_rm_gov_12_rm_draft_show_read_only_governed_inspection_remains_accessible(): void
    {
        $draftTest = $this->createAssessmentWithQuestion('draft', false, $this->teacher->id);
        $req = AssessmentRequest::create([
            'requested_by' => $this->raUser->id,
            'title' => 'Midterm English Assessment 2026',
            'test_type' => 'toeic',
            'test_id' => $draftTest->id,
            'status' => 'draft_in_progress',
        ]);
        $draftTest->update(['assessment_request_id' => $req->id]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-requests.assessment-show', $req->id));

        $res->assertStatus(200);
        $res->assertSee('Draft — In Authoring');
        $res->assertSee($draftTest->title);
    }

    /**
     * RM-GOV-13: Needs revision oversight panel renders non-actionable awaiting teacher resubmission status.
     */
    public function test_rm_gov_13_needs_revision_oversight_panel_is_rendered_read_only(): void
    {
        $revTest = $this->createAssessmentWithQuestion('needs_revision', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-governance', ['tab' => 'pending-review']));

        $res->assertStatus(200);
        $res->assertSee('Needs Revision (Returned to Teacher)');
        $res->assertSee($revTest->title);
        $res->assertSee('📝 Awaiting Teacher Resubmission');
        $res->assertDontSee(route('admin.repository-manager.assessment-review', $revTest->id));
    }
}
