<?php

namespace Tests\Feature;

use App\Models\RepositoryFinding;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndContextAwareNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'E2E Teacher Nav',
            'email'  => 'e2e_teacher_nav@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'E2E Manager Nav',
            'email'  => 'e2e_manager_nav@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    protected function createTestBank(array $attributes = []): QuestionBank
    {
        return QuestionBank::create(array_merge([
            'title'           => 'E2E Nav Test Bank',
            'slug'            => 'e2e-nav-test-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'description'     => 'E2E Nav Test Description',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'acl_category_id' => null,
            'created_by'      => $this->teacher->id,
        ], $attributes));
    }

    /**
     * TEST A: Dashboard -> Explorer -> IRQA Overview -> Back -> Explorer.
     */
    public function test_a_dashboard_to_explorer_to_quality_back_returns_to_explorer()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', [
                'from'   => 'explorer',
                'filter' => 'all',
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.explorer', ['filter' => 'all']);
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST B: Explorer filter=reviewed_issues -> IRQA Overview -> Back -> Explorer filter=reviewed_issues.
     */
    public function test_b_explorer_reviewed_issues_to_quality_back_preserves_reviewed_issues_filter()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', [
                'from'   => 'explorer',
                'filter' => 'reviewed_issues',
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']);
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST C: Explorer filter=needs_improvement -> IRQA Overview -> Back -> Explorer filter=needs_improvement.
     */
    public function test_c_explorer_needs_improvement_to_quality_back_preserves_needs_improvement_filter()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', [
                'from'   => 'explorer',
                'filter' => 'needs_improvement',
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.explorer', ['filter' => 'needs_improvement']);
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST D: Dashboard -> IRQA Overview -> Back -> Dashboard.
     */
    public function test_d_dashboard_to_quality_back_returns_to_dashboard()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', ['from' => 'dashboard']));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.repository-manager.dashboard');
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST E: IRQA Overview -> Explorer -> Back -> IRQA Overview.
     */
    public function test_e_quality_to_explorer_back_returns_to_quality()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['from' => 'quality']));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.quality');
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST F: Invalid from parameter resolves to safe default.
     */
    public function test_f_invalid_from_value_returns_safe_default()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', ['from' => 'https://evil.com/phishing']));

        $response->assertStatus(200);
        $expectedSafeUrl = route('admin.repository-manager.dashboard');
        $this->assertStringContainsString($expectedSafeUrl, $response->getContent());
        $this->assertStringNotContainsString('evil.com', $response->getContent());
    }

    /**
     * TEST G: Navigation does not modify governance or finding state.
     */
    public function test_g_navigation_does_not_change_state()
    {
        $bank = $this->createTestBank(['status' => 'needs_revision']);

        $finding = RepositoryFinding::create([
            'question_bank_id' => $bank->id,
            'finding_code'     => 'IRQA_WARN',
            'title'            => 'Missing Category association',
            'description'      => 'Missing Category association',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', [
                'from'   => 'explorer',
                'filter' => 'reviewed_issues',
            ]));

        $bank->refresh();
        $finding->refresh();

        $this->assertEquals('needs_revision', is_object($bank->status) ? $bank->status->value : $bank->status);
        $this->assertEquals('OPEN', $finding->status);
    }

    /**
     * TEST H: Explorer filter=needs_improvement -> Validation Workspace -> Back -> Explorer filter=needs_improvement.
     */
    public function test_h_explorer_needs_improvement_to_validation_back_preserves_filter()
    {
        $bank = $this->createTestBank(['status' => 'pending_approval']);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', [
                'questionBank' => $bank->id,
                'from'         => 'explorer',
                'filter'       => 'needs_improvement',
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.explorer', ['filter' => 'needs_improvement']);
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST I: Question Banks Approval Queue -> Validation Workspace -> Back -> Questions Approval.
     */
    public function test_i_approval_queue_to_validation_back_returns_to_questions_approval()
    {
        $bank = $this->createTestBank(['status' => 'pending_approval']);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', [
                'questionBank' => $bank->id,
                'from'         => 'approval_queue',
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.repository-manager.questions-approval');
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST J: Teacher Revision Task -> Focused Question Editor -> Back -> Revision Task.
     */
    public function test_j_teacher_revision_task_to_editor_back_returns_to_task_detail()
    {
        $bank = $this->createTestBank(['status' => 'needs_revision']);

        $question = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Essay Question Prompt',
            'question_type'    => 'essay',
            'explanation'      => 'Detailed explanation.',
            'difficulty'       => 'medium',
            'points'           => 10,
        ]);

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Please update prompt.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $bank->id,
            'question_id'                    => $question->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Missing category',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [
                'revisionRequest' => $revisionRequest->id,
                'item'            => $item->id,
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('teacher.repository-revisions.show', $revisionRequest->id);
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST K: Direct access fallback without parameters returns safe default.
     */
    public function test_k_direct_access_validation_workspace_fallback()
    {
        $bank = $this->createTestBank(['status' => 'needs_revision']);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $bank->id));

        $response->assertStatus(200);
        $expectedDefaultBackUrl = route('admin.repository-manager.questions-approval');
        $this->assertStringContainsString($expectedDefaultBackUrl, $response->getContent());
    }
}
