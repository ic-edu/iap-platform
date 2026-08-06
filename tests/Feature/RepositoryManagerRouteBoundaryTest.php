<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerRouteBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;
    protected QuestionBank $bank;
    protected AssessmentTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Route Boundary',
            'email'  => 'teacher_rb@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Route Boundary',
            'email'  => 'repomanager_rb@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin Route Boundary',
            'email'  => 'superadmin_rb@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->bank = QuestionBank::create([
            'title'      => 'RB Bank 01',
            'slug'       => 'rb-bank-01',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $this->test = AssessmentTest::create([
            'title'            => 'RB Test 01',
            'slug'             => 'rb-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 700,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);
    }

    /**
     * TEST 1: Repository Manager Dashboard links "Review Bank" to Governance Workspace.
     */
    public function test_1_dashboard_links_review_bank_to_governance_workspace()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.question-bank-validate', $this->bank->id));
        $response->assertDontSee(route('admin.question-banks.show', $this->bank->id));
    }

    /**
     * TEST 2: Governance Workspace renders question_bank_validate.blade.php with context-aware '← Back'.
     */
    public function test_2_governance_workspace_renders_correct_view_and_back_button()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.question-bank-validate', $this->bank->id));

        $response->assertStatus(200);
        $response->assertSee('Validation Workspace');
        $response->assertSee('← Back');
        $response->assertDontSee('Back to Question Banks');
    }

    /**
     * TEST 3: Teacher Workspace continues functioning with Author Module route.
     */
    public function test_3_teacher_workspace_remains_intact()
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $this->bank->id));

        $response->assertStatus(200);
    }
}
