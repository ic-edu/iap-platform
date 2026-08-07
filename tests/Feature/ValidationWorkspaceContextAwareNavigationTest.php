<?php

namespace Tests\Feature;

use App\Models\RepositoryFinding;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationWorkspaceContextAwareNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->repoManager = User::factory()->create([
            'name'   => 'Nav Manager Audit',
            'email'  => 'nav_mgr@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    protected function createTestBank(array $attributes = []): QuestionBank
    {
        return QuestionBank::create(array_merge([
            'title'           => 'Nav Test Bank',
            'slug'            => 'nav-test-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'description'     => 'Audit test repository description.',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'acl_category_id' => null,
            'created_by'      => $this->repoManager->id,
        ], $attributes));
    }

    /**
     * TEST 1 & 3: Validation Workspace opened with from=explorer & filter=reviewed_issues -> Back links to Explorer.
     */
    public function test_1_and_3_validation_workspace_opened_from_explorer_links_back_to_explorer()
    {
        $bank = $this->createTestBank(['status' => 'needs_revision']);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', [
                'questionBank' => $bank->id,
                'from'         => 'explorer',
                'filter'       => 'reviewed_issues',
            ]));

        $response->assertStatus(200);

        // Assert Back button links back to IRQA Explorer with filter=reviewed_issues
        $expectedUrl = route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']);
        $response->assertSee($expectedUrl, false);
        $this->assertStringContainsString($expectedUrl, $response->getContent());
    }

    /**
     * TEST 2 & 4: Validation Workspace opened without from parameter -> Back links to Question Banks Approval Queue.
     */
    public function test_2_and_4_validation_workspace_opened_without_from_param_links_back_to_questions_approval()
    {
        $bank = $this->createTestBank(['status' => 'pending_approval']);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $bank->id));

        $response->assertStatus(200);

        // Assert Back button links back to Question Banks Approval Queue
        $expectedUrl = route('admin.repository-manager.questions-approval');
        $response->assertSee($expectedUrl, false);
        $this->assertStringContainsString($expectedUrl, $response->getContent());
    }

    /**
     * TEST 5, 6 & 7: Navigation retains repository ID and does not alter governance or finding state.
     */
    public function test_5_6_and_7_navigation_does_not_alter_governance_or_finding_state()
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

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', [
                'questionBank' => $bank->id,
                'from'         => 'explorer',
                'filter'       => 'reviewed_issues',
            ]));

        $response->assertStatus(200);

        $bank->refresh();
        $finding->refresh();

        // Assert governance status and finding status are completely unchanged
        $this->assertEquals('needs_revision', is_object($bank->status) ? $bank->status->value : $bank->status);
        $this->assertEquals('OPEN', $finding->status);
    }
}
