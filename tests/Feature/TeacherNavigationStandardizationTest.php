<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

class TeacherNavigationStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;
    protected Test $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Nav Standard Teacher',
            'email'  => 'teacher_nav_std@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Nav Standard',
            'email'  => 'repomanager_nav_std@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'IELTS General Reading Test Bank',
            'slug'        => 'ielts-general-reading-test-bank',
            'test_type'   => 'ielts',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'Test bank for navigation validation',
        ]);

        $this->test = Test::create([
            'title'       => 'TOEIC Full Practice Test 2026',
            'slug'        => 'toeic-full-practice-test-2026',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'Test assessment for navigation validation',
        ]);
    }

    /**
     * TEST 1: Teacher header topbar standardization (+ Quick Action replaced with 🏠 Dashboard).
     */
    public function test_1_teacher_topbar_renders_dashboard_button_instead_of_quick_action()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('<span>Dashboard</span>', false);
        $response->assertDontSee('+ Quick Action');
        $response->assertDontSee('🏠 Dashboard');
    }

    /**
     * TEST 2: Question Bank Detail context-aware navigation.
     */
    public function test_2_question_bank_detail_preserves_originating_context()
    {
        // 2a. Context: dashboard
        $respDashboard = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', ['questionBank' => $this->bank->id, 'from' => 'dashboard']));
        $respDashboard->assertStatus(200);
        $respDashboard->assertSee(route('teacher.dashboard'));
        $respDashboard->assertSee('← Back to Teacher Dashboard');

        // 2b. Context: test_builder
        $respTestBuilder = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', ['questionBank' => $this->bank->id, 'from' => 'test_builder', 'test_id' => $this->test->id]));
        $respTestBuilder->assertStatus(200);
        $respTestBuilder->assertSee(route('teacher.tests.show', $this->test->id));
        $respTestBuilder->assertSee('← Back to Assessment Test Builder');

        // 2c. Context: revision_center
        $respRevCenter = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', ['questionBank' => $this->bank->id, 'from' => 'revision_center']));
        $respRevCenter->assertStatus(200);
        $respRevCenter->assertSee(route('teacher.revision-center'));
        $respRevCenter->assertSee('← Back to Revision Center');

        // 2d. Default fallback for Teacher role
        $respTeacherDefault = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $this->bank->id));
        $respTeacherDefault->assertStatus(200);
        $respTeacherDefault->assertSee(route('teacher.question-banks.index'));

        // 2e. Default fallback for Repository Manager role
        $respRepoMgrDefault = $this->actingAs($this->repoManager)
            ->get(route('admin.question-banks.show', $this->bank->id));
        $respRepoMgrDefault->assertStatus(200);
        $respRepoMgrDefault->assertSee(route('admin.repository-manager.questions-approval'));
    }

    /**
     * TEST 3: Academic Library role boundary fallback.
     */
    public function test_3_academic_library_back_button_is_role_safe()
    {
        // 3a. Teacher role Back points to teacher.dashboard
        $respTeacher = $this->actingAs($this->teacher)->get(route('admin.academic-library.index'));
        $respTeacher->assertStatus(200);
        $respTeacher->assertSee(route('teacher.dashboard'));
        $respTeacher->assertDontSee('admin/repository-manager/dashboard');

        // 3b. Repository Manager role Back points to admin.repository-manager.dashboard
        $respRepoMgr = $this->actingAs($this->repoManager)->get(route('admin.academic-library.index'));
        $respRepoMgr->assertStatus(200);
        $respRepoMgr->assertSee(route('admin.repository-manager.dashboard'));
    }

    /**
     * TEST 4: Teacher Revision Center & Repository Revision pages have deterministic Back and Dashboard buttons.
     */
    public function test_4_revision_pages_have_deterministic_back_and_dashboard_buttons()
    {
        // 4a. Teacher Revision Center
        $respRevCenter = $this->actingAs($this->teacher)->get(route('teacher.revision-center'));
        $respRevCenter->assertStatus(200);
        $respRevCenter->assertSee(route('teacher.dashboard'));

        // 4b. Repository Revision Task Detail
        $revRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Revise rubric options.',
        ]);

        $respTaskShow = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revRequest->id));
        $respTaskShow->assertStatus(200);
        $respTaskShow->assertSee(route('teacher.repository-revisions.index'));
        $respTaskShow->assertSee(route('teacher.dashboard'));
    }
}
