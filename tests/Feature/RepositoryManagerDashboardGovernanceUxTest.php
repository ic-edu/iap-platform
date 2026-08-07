<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryFinding;
use App\Models\RepositoryReviewRequest;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepositoryManagerDashboardGovernanceUxTest extends TestCase
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
            'name'   => 'Governance UX Teacher',
            'email'  => 'gov_ux_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Governance UX Repo Manager',
            'email'  => 'gov_ux_repomanager@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'TOEIC Listening Practice & Audio Transcripts',
            'slug'        => 'toeic-listening-practice-audio-transcripts',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'Institutional repository for TOEIC Listening audio items.',
        ]);
    }

    /**
     * TEST 1: Governance Queue acts as the single source of truth for actionable RM tasks.
     */
    public function test_1_governance_queue_is_single_source_of_truth()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Governance Queue (1)');
        $response->assertSee('TOEIC Listening Practice &amp; Audio Transcripts', false);
    }

    /**
     * TEST 2: Redundant 'Pending Question Banks' KPI card is completely removed from dashboard.
     */
    public function test_2_pending_question_banks_kpi_card_is_removed()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Pending Question Banks');
        $response->assertSee('Pending Assessments');
        $response->assertSee('Pending Media');
        $response->assertSee('Repository Explorer');
    }

    /**
     * TEST 3: Urgent Academic Alerts excludes normal pending approvals and shows only critical findings.
     */
    public function test_3_urgent_academic_alerts_excludes_normal_pending_approvals()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        // Create a critical IRQA finding
        RepositoryFinding::create([
            'question_bank_id' => $this->bank->id,
            'finding_code'     => 'IRQA_CRITICAL_DUPLICATE',
            'title'            => 'Critical Audio File Missing',
            'description'      => 'Audio transcript missing file link.',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Critical IRQA Findings Requiring Review');
        $response->assertDontSee('Question Banks Awaiting Review');
    }

    /**
     * TEST 4: Clear semantic empty states render when no alerts or teacher revisions are pending.
     */
    public function test_4_semantic_empty_states_render_correctly()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('✓ No urgent academic alerts.');
        $response->assertSee('✓ No pending teacher revisions.');
    }

    /**
     * TEST 5: Teacher Revision Queue displays items in needs_revision workflow state.
     */
    public function test_5_teacher_revision_queue_displays_revision_items()
    {
        RepositoryReviewRequest::create([
            'resource_type' => 'media',
            'resource_id'   => '99',
            'submitter_id'  => $this->teacher->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes'       => ['title' => 'Updated Audio'],
        ]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Teacher Revision Queue');
        $response->assertSee($this->teacher->name);
    }

    /**
     * TEST 6: IRQA re-scan failure notification goes ONLY to Repository Manager.
     */
    public function test_6_irqa_rescan_failure_notification_goes_only_to_repository_manager()
    {
        $this->bank->update(['status' => 'needs_revision']);

        $revRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix audio file alignment.',
        ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revRequest->id));

        $rmNotifs = DB::table('notifications')
            ->where('notifiable_id', $this->repoManager->id)
            ->get();

        $teacherNotifs = DB::table('notifications')
            ->where('notifiable_id', $this->teacher->id)
            ->get();

        // Repository Manager receives notification
        $this->assertGreaterThan(0, $rmNotifs->count());

        // Teacher receives NO direct IRQA failure notification
        $teacherFailures = $teacherNotifs->filter(fn($n) => str_contains($n->type, 'irqa_failed'));
        $this->assertEquals(0, $teacherFailures->count());
    }
}
