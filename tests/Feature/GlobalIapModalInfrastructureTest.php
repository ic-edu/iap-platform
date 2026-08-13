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
        $response->assertSee('id="iap-global-modal"', false);
        $response->assertSee('flex items-center justify-center', false);
        $response->assertSee('id="iap-modal-backdrop"', false);
        $response->assertSee('id="iap-modal-panel"', false);
        $response->assertSee('id="iap-modal-body"', false);
        $response->assertSee('id="iap-modal-actions"', false);
        $response->assertSee('flex-shrink-0', false);
        $response->assertSee('id="iap-modal-cancel-btn"', false);
        $response->assertSee('id="iap-modal-confirm-btn"', false);
        $response->assertSee('display: inline-flex', false);
        $response->assertSee('ensureDocumentBodyPlacement', false);
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
        $response->assertSee('iapConfirm({ title: \'Submit Question Bank for Approval?\'', false);
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

        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));

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
        $response->assertSee('iapConfirm({ title: \'Submit Question Bank for Approval?\'', false);
        $response->assertSee('confirmText: \'Submit for Approval\'', false);
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
}
