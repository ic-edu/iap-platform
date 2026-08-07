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
     * TEST 1: Dashboard -> IRQA Quality Overview -> Back -> Dashboard.
     */
    public function test_1_dashboard_to_irqa_overview_back_returns_to_dashboard()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', ['from' => 'dashboard']));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.repository-manager.dashboard');
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST 2: IRQA Quality Overview -> Explorer -> Back -> IRQA Quality Overview.
     */
    public function test_2_quality_overview_to_explorer_back_returns_to_quality_overview()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['from' => 'quality']));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.quality');
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST 3: Explorer filter=needs_improvement -> Validation Workspace -> Back -> Explorer filter=needs_improvement.
     */
    public function test_3_explorer_needs_improvement_to_validation_back_preserves_filter()
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
     * TEST 4: Explorer filter=reviewed_issues -> Validation Workspace -> Back -> Explorer filter=reviewed_issues.
     */
    public function test_4_explorer_reviewed_issues_to_validation_back_preserves_filter()
    {
        $bank = $this->createTestBank(['status' => 'needs_revision']);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', [
                'questionBank' => $bank->id,
                'from'         => 'explorer',
                'filter'       => 'reviewed_issues',
            ]));

        $response->assertStatus(200);
        $expectedBackUrl = route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']);
        $this->assertStringContainsString($expectedBackUrl, $response->getContent());
    }

    /**
     * TEST 5: Question Banks Approval Queue -> Validation Workspace -> Back -> Questions Approval.
     */
    public function test_5_approval_queue_to_validation_back_returns_to_questions_approval()
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
     * TEST 6: Teacher Revision Center -> Revision Task -> Editor -> Back -> Revision Task.
     */
    public function test_6_teacher_revision_task_to_editor_back_returns_to_task_detail()
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
     * TEST 7: Direct access fallback works safely.
     */
    public function test_7_direct_access_fallback_returns_safe_default()
    {
        $bank = $this->createTestBank(['status' => 'needs_revision']);

        // Direct access to Validation Workspace without parameters
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $bank->id));

        $response->assertStatus(200);
        $expectedDefaultBackUrl = route('admin.repository-manager.questions-approval');
        $this->assertStringContainsString($expectedDefaultBackUrl, $response->getContent());
    }

    /**
     * TEST 8: Security: External URL / open redirect injection via from parameter is sanitized to default fallback.
     */
    public function test_8_open_redirect_injection_is_sanitized_to_safe_default()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', ['from' => 'https://evil.com/phishing']));

        $response->assertStatus(200);
        $expectedSafeUrl = route('admin.repository-manager.dashboard');
        $this->assertStringContainsString($expectedSafeUrl, $response->getContent());
        $this->assertStringNotContainsString('evil.com', $response->getContent());
    }

    /**
     * TEST 9 & 10: Back navigation does not change governance or finding state.
     */
    public function test_9_and_10_navigation_does_not_change_state()
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
            ->get(route('admin.repository-manager.question-bank-validate', [
                'questionBank' => $bank->id,
                'from'         => 'explorer',
                'filter'       => 'reviewed_issues',
            ]));

        $bank->refresh();
        $finding->refresh();

        $this->assertEquals('needs_revision', is_object($bank->status) ? $bank->status->value : $bank->status);
        $this->assertEquals('OPEN', $finding->status);
    }
}
