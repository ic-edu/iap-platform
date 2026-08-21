<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerHeaderNavigationTest extends TestCase
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
            'name'   => 'Teacher Header Test',
            'email'  => 'teacher_header@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Header Test',
            'email'  => 'repomanager_header@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'      => 'Header Test Question Bank 01',
            'slug'       => 'header-test-qb-01',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->teacher->id,
        ]);
    }

    /**
     * TEST 1: Repository Manager header renders Dashboard button instead of Quick Action.
     */
    public function test_1_repository_manager_header_renders_dashboard_button()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.academic-library.explorer'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.dashboard'));
        $response->assertSee('Dashboard');
        $response->assertDontSee('+ Quick Action');
    }

    /**
     * TEST 2: Repository Manager page retains the ← Back button alongside Dashboard.
     */
    public function test_2_back_button_remains_available_alongside_dashboard_header()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.question-bank-validate', $this->bank->id));

        $response->assertStatus(200);
        $response->assertSee('← Back');
        $response->assertSee(route('admin.repository-manager.dashboard'));
    }

    /**
     * TEST 3: Teacher header renders Dashboard button instead of Quick Action (TEACHER-NAV-STD).
     */
    public function test_3_teacher_header_renders_dashboard_button()
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.index'));

        $response->assertStatus(200);
        $response->assertSee('<span>Dashboard</span>', false);
        $response->assertDontSee('+ Quick Action');
        $response->assertDontSee('🏠 Dashboard');
    }
}
