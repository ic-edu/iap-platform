<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalIapModalInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'email'  => 'teacher_modal@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->superAdmin = User::factory()->create([
            'email'  => 'superadmin_modal@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    /**
     * TEST 1: Layout admin renders the shared IAP modal component and JS engine.
     */
    public function test_admin_layout_includes_iap_modal_component()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('<dialog id="iap-global-dialog"', false);
        $response->assertSee('#iap-global-dialog::backdrop', false);
        $response->assertSee('id="iap-modal-panel"', false);
        $response->assertSee('id="iap-modal-body"', false);
        $response->assertSee('id="iap-modal-actions"', false);
        $response->assertSee('flex-shrink-0', false);
        $response->assertSee('id="iap-modal-cancel-btn"', false);
        $response->assertSee('id="iap-modal-confirm-btn"', false);
        $response->assertSee('display: inline-flex', false);
        $response->assertSee('min-height: 2.25rem', false);
        $response->assertSee('min-width: 5rem', false);
        $response->assertSee('background-color: #1e293b', false);
        $response->assertSee('background-color: #4f46e5', false);
        $response->assertSee('confirmBtn.style.backgroundColor = \'#e11d48\'', false);
        $response->assertSee('showModal', false);
        $response->assertSee('window.iapConfirm');
        $response->assertSee('window.iapAlert');
    }

    /**
     * TEST 2: Teacher Dashboard uses iapConfirm for Question Bank actions.
     */
    public function test_2_teacher_dashboard_uses_iap_confirm_for_actions()
    {
        QuestionBank::create([
            'title' => 'Draft QB for Teacher',
            'code' => 'QB-DRAFT-001',
            'slug' => 'draft-qb-for-teacher',
            'status' => 'draft',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="submit-trigger-btn-dash-', false);
        $response->assertSee('id="inline-submit-panel-dash-', false);
        $response->assertSee('Submit \'Draft QB for Teacher\' for Super Admin approval?', false);
        $response->assertSee('iapConfirm({ title: \'Duplicate Question Bank?\'', false);
        $response->assertDontSee('onsubmit="return confirm(');
    }

    /**
     * TEST 3: Approvals Queue uses iapConfirm with domain-specific button wording and variants.
     */
    public function test_3_approvals_queue_uses_iap_confirm_with_action_labels()
    {
        QuestionBank::create([
            'title' => 'Sample Pending QB',
            'code' => 'QB-TEST-MODAL-001',
            'slug' => 'sample-pending-qb',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.question-banks'));

        $response->assertStatus(200);
        $response->assertSee('iapConfirm({ title: \'Approve Question Bank?\'', false);
        $response->assertSee('confirmText: \'Approve & Publish\'', false);
        $response->assertSee('confirmText: \'Reject Repository\'', false);
        $response->assertDontSee('onsubmit="return confirm(');
    }

    /**
     * TEST 4: User Management uses iapConfirm for activation, deactivation, and soft delete.
     */
    public function test_user_management_uses_iap_confirm_for_status_and_deletion()
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertSee('iapConfirm({ title: \'Soft Delete User Account?\'', false);
        $response->assertSee('confirmText: \'Delete User Account\'', false);
        $response->assertSee('variant: \'danger\'', false);
        $response->assertDontSee('onsubmit="return confirm(');
    }

    /**
     * TEST 5: Test Builder Workspace show view header form uses iapConfirm interceptor.
     */
    public function test_test_builder_workspace_show_view_header_uses_iap_confirm()
    {
        $qb = QuestionBank::create([
            'title' => 'Test Builder Draft QB',
            'code' => 'QB-TB-DRAFT-001',
            'slug' => 'test-builder-draft-qb',
            'status' => 'draft',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $qb->id));

        $response->assertStatus(200);
        $response->assertSee('id="submit-trigger-btn"', false);
        $response->assertSee('id="inline-submit-panel"', false);
        $response->assertSee('Submit \'Test Builder Draft QB\' for Super Admin approval?', false);
        $response->assertSee('id="confirm-submit-btn"', false);
        $response->assertDontSee('onsubmit="return confirm(');
    }

    /**
     * TEST 6: Static check ensuring no native window.confirm or window.alert remain in workflow templates.
     */
    public function test_zero_native_dialogs_in_workflow_templates()
    {
        $viewsDir = base_path('resources/views');
        $modulesDir = base_path('app/Modules');

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        $moduleIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulesDir));

        $nativePatterns = [
            '/onsubmit=["\']return\s+confirm\(/i',
            '/onclick=["\']return\s+confirm\(/i',
            '/window\.confirm\(/i',
            '/window\.alert\(/i',
            '/window\.prompt\(/i',
        ];

        foreach ([$iterator, $moduleIterator] as $it) {
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    if ($file->getFilename() === 'iap-modal.blade.php') {
                        continue;
                    }
                    $content = file_get_contents($file->getPathname());
                    foreach ($nativePatterns as $pattern) {
                        $this->assertDoesNotMatchRegularExpression($pattern, $content, "Native browser dialog pattern {$pattern} found in {$file->getPathname()}");
                    }
                }
            }
        }
    }

    /**
     * TEST 7: Teacher cannot mutate QuestionBank or Questions when status is PENDING_APPROVAL.
     */
    public function test_pending_approval_repository_is_read_only_for_teacher()
    {
        $qb = QuestionBank::create([
            'title' => 'Pending Approval Repository',
            'code' => 'QB-PENDING-001',
            'slug' => 'pending-approval-repository',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $q = \App\Modules\QuestionBank\Models\Question::create([
            'question_bank_id' => $qb->id,
            'prompt' => 'Question inside pending approval bank',
            'question_type' => \App\Modules\QuestionBank\Enums\QuestionType::MultipleChoice,
            'difficulty' => 'medium',
            'points' => 1,
        ]);

        // UI check: show view hides mutation actions and displays locked banner
        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $qb->id));
        $response->assertStatus(200);
        $response->assertSee('🔒 Repository locked while awaiting governance approval.', false);
        $response->assertDontSee('✏ Edit Bank Details', false);
        $response->assertDontSee('+ Add Question', false);
        $response->assertDontSee('📥 Bulk Import', false);
        $response->assertSee('👁 View', false);
        $response->assertDontSee('✏ Edit', false);

        // Backend HTTP 403 checks for mutations
        $updateResp = $this->actingAs($this->teacher)->put(route('admin.question-banks.update', $qb->id), [
            'title' => 'Attempted Title Change',
            'test_type' => 'UTBK',
        ]);
        $updateResp->assertStatus(403);

        $addQResp = $this->actingAs($this->teacher)->post(route('admin.question-banks.store-question', $qb->id), [
            'prompt' => 'New Question Attempt',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'points' => 1,
        ]);
        $addQResp->assertStatus(403);

        $editQResp = $this->actingAs($this->teacher)->put(route('admin.question-banks.update-question', $q->id), [
            'prompt' => 'Edited Question Attempt',
            'question_type' => 'multiple_choice',
            'difficulty' => 'hard',
            'points' => 2,
        ]);
        $editQResp->assertStatus(403);

        $dupeQResp = $this->actingAs($this->teacher)->post(route('admin.question-banks.duplicate-question', $q->id));
        $dupeQResp->assertStatus(403);

        $importResp = $this->actingAs($this->teacher)->post(route('admin.question-banks.import', $qb->id), [
            'csv_content' => 'Sample, A, B, C, D, 0',
        ]);
        $importResp->assertStatus(403);
    }

    /**
     * TEST 8: Global dialog component contains explicit closed-state rule #iap-global-dialog:not([open]) { display: none !important; }.
     */
    public function test_global_dialog_closed_state_has_display_none_rule()
    {
        $componentPath = resource_path('views/components/iap-modal.blade.php');
        $content = file_get_contents($componentPath);

        $this->assertStringContainsString('#iap-global-dialog:not([open])', $content);
        $this->assertStringContainsString('display: none !important;', $content);
    }

    /**
     * TEST 9: Rejecting a QuestionBank closes active GovernanceApprovalTask and excludes it from RM Dashboard KPI.
     */
    public function test_reject_question_bank_closes_governance_task_and_removes_from_rm_kpi()
    {
        $rmUser = User::factory()->create();
        $rmUser->assignRole('repository-manager');

        $qb = QuestionBank::create([
            'title' => 'QB To Be Rejected',
            'code' => 'QB-REJECT-001',
            'slug' => 'qb-to-be-rejected',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $task = \App\Models\GovernanceApprovalTask::create([
            'question_bank_id' => $qb->id,
            'teacher_id' => $this->teacher->id,
            'workflow' => 'APPROVAL',
            'status' => 'OPEN',
            'submitted_at' => now(),
        ]);

        $rejectResp = $this->actingAs($rmUser)->post(route('admin.repository-manager.question-bank-reject', $qb->id), [
            'notes' => 'Content rejected due to institutional policy.',
        ]);

        $rejectResp->assertStatus(302);
        $this->assertEquals('archived', $qb->fresh()->status);
        $this->assertEquals('COMPLETED', $task->fresh()->status);

        $dashboardResp = $this->actingAs($rmUser)->get(route('admin.repository-manager.dashboard'));
        $dashboardResp->assertStatus(200);

        // Verify that open approval tasks query in dashboard does not include archived QB
        $openTasks = $dashboardResp->viewData('openApprovalTasks');
        $this->assertFalse($openTasks->contains('question_bank_id', $qb->id));
    }

    /**
     * TEST 10: Validation Workspace contains contextual Back navigation with history.back() and safe local fallbacks.
     */
    public function test_validation_workspace_back_navigation_context_and_fallback()
    {
        $rmUser = User::factory()->create();
        $rmUser->assignRole('repository-manager');

        $qb = QuestionBank::create([
            'title' => 'Validation Back Test Bank',
            'code' => 'QB-BACK-001',
            'slug' => 'validation-back-test-bank',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        // Default back link check
        $response = $this->actingAs($rmUser)->get(route('admin.repository-manager.question-bank-validate', $qb->id));
        $response->assertStatus(200);
        $response->assertSee('id="validation-back-link"', false);
        $response->assertDontSee('window.history.back()', false);
        $response->assertSee('href="' . route('admin.repository-manager.questions-approval') . '"', false);

        // Safe local from_url check
        $localResp = $this->actingAs($rmUser)->get(route('admin.repository-manager.question-bank-validate', [$qb->id, 'from_url' => '/admin/repository-manager/dashboard']));
        $localResp->assertStatus(200);
        $localResp->assertSee('href="/admin/repository-manager/dashboard"', false);

        // Unsafe external from_url check (must reject external redirect and fallback to default approval queue)
        $externalResp = $this->actingAs($rmUser)->get(route('admin.repository-manager.question-bank-validate', [$qb->id, 'from_url' => 'https://evil.com/phish']));
        $externalResp->assertStatus(200);
        $externalResp->assertDontSee('href="https://evil.com/phish"', false);
        $externalResp->assertSee('href="' . route('admin.repository-manager.questions-approval') . '"', false);
    }

    /**
     * TEST 11: Global modal closed state and descendant hierarchy integrity.
     */
    public function test_global_modal_closed_state_and_descendant_hierarchy()
    {
        $componentPath = resource_path('views/components/iap-modal.blade.php');
        $content = file_get_contents($componentPath);

        $this->assertStringContainsString('#iap-global-dialog:not([open])', $content);
        $this->assertStringContainsString('#iap-global-dialog[open]', $content);
        $this->assertStringContainsString('display: none !important;', $content);

        // Confirm buttons are descendants of #iap-modal-actions -> #iap-modal-panel -> #iap-global-dialog
        $this->assertMatchesRegularExpression('/<dialog id="iap-global-dialog".*?<div id="iap-modal-panel".*?<div id="iap-modal-actions".*?<button id="iap-modal-cancel-btn".*?<button id="iap-modal-confirm-btn"/s', $content);

        // Verify layout HTML parsing integrity: back-to-top-btn is properly closed before x-iap-modal
        $layoutPath = resource_path('views/layouts/admin.blade.php');
        $layoutContent = file_get_contents($layoutPath);
        $this->assertMatchesRegularExpression('/id="back-to-top-btn".*?<\/button>\s*<!-- Global IAP Modal System -->\s*<x-iap-modal \/>/s', $layoutContent);
    }
}
