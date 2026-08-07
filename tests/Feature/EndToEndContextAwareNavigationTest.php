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
}
