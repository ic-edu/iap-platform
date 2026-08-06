<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AclCategory;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerBoundarySeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;
    protected AclCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Boundary Test',
            'email'  => 'teacher_boundary@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Boundary Test',
            'email'  => 'repomanager_boundary@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->category = AclCategory::firstOrCreate(
            ['slug' => 'toefl-reading-bs'],
            ['name' => 'TOEFL Reading Boundary Test', 'description' => 'Test Category']
        );

        $this->bank = QuestionBank::create([
            'title'           => 'Boundary Test Question Bank 01',
            'slug'            => 'boundary-test-qb-01',
            'test_type'       => 'toeic',
            'status'          => 'published',
            'acl_category_id' => $this->category->id,
            'created_by'      => $this->teacher->id,
        ]);
    }

    /**
     * TEST 1: Repository Explorer links open Governance Workspace, not Teacher Workspace.
     */
    public function test_1_explorer_links_open_governance_workspace()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.academic-library.explorer'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.question-bank-validate', $this->bank->id));
        $response->assertDontSee(route('admin.question-banks.show', $this->bank->id));
    }

    /**
     * TEST 2: Quality Audit Dashboard links open Governance Workspace, not Teacher Workspace.
     */
    public function test_2_quality_dashboard_links_open_governance_workspace()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.academic-library.quality'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.question-bank-validate', $this->bank->id));
        $response->assertDontSee(route('admin.question-banks.show', $this->bank->id));
    }

    /**
     * TEST 3: Category Detail Page links open Governance Workspace, not Teacher Workspace.
     */
    public function test_3_category_detail_links_open_governance_workspace()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.academic-library.show', $this->category->slug));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.question-bank-validate', $this->bank->id));
        $response->assertDontSee(route('admin.question-banks.show', $this->bank->id));
    }

    /**
     * TEST 4: Teacher Author Workspace remains intact and isolated for Teachers.
     */
    public function test_4_teacher_author_workspace_remains_isolated_and_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $this->bank->id));

        $response->assertStatus(200);
        $response->assertSee($this->bank->title);
    }
}
