<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeDeleteAndContextNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Safe Delete',
            'email'  => 'teacher_safedelete@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Safe Delete',
            'email'  => 'repomanager_safedelete@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin Safe Delete',
            'email'  => 'superadmin_safedelete@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    /**
     * TEST 1: Deleting a published Question Bank by Non-Super-Admin enters governance pending_deletion without hard delete.
     */
    public function test_1_published_question_bank_deletion_enters_governance_workflow()
    {
        $bank = QuestionBank::create([
            'title'      => 'Published Academic Question Bank',
            'slug'       => 'published-academic-question-bank',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->repoManager)->delete(route('admin.question-banks.destroy', $bank->id));
        $res->assertRedirect(route('admin.question-banks.index'));
        $res->assertSessionHas('status', "Deletion requested for published question bank '{$bank->title}'. Awaiting Super Admin approval.");

        $bank->refresh();
        $this->assertEquals('pending_deletion', $bank->status);
        $this->assertNull($bank->deleted_at);
    }

    /**
     * TEST 2: Deleting a published Assessment Test by Non-Super-Admin enters governance pending_deletion and preserves activity log.
     */
    public function test_2_published_assessment_deletion_enters_governance_workflow()
    {
        $test = AssessmentTest::create([
            'title'            => 'Published Assessment Test 02',
            'slug'             => 'published-assessment-test-02',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->repoManager)->delete(route('admin.tests.destroy', $test->id));
        $res->assertRedirect(route('admin.tests.index'));
        $res->assertSessionHas('status', "Deletion requested for published assessment '{$test->title}'. Awaiting Super Admin approval.");

        $test->refresh();
        $this->assertEquals('pending_deletion', $test->status);
        $this->assertNull($test->deleted_at);

        $log = RepositoryActivityLog::where('resource_type', 'Test')
            ->where('resource_id', (string) $test->id)
            ->where('action', 'test_deletion_requested')
            ->first();

        $this->assertNotNull($log);
    }

    /**
     * TEST 3: Super Admin deletion soft-deletes the asset rather than hard-deleting.
     */
    public function test_3_super_admin_deletion_soft_deletes_published_asset()
    {
        $test = AssessmentTest::create([
            'title'            => 'Super Admin Deletion Test 03',
            'slug'             => 'super-admin-deletion-test-03',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->superAdmin)->delete(route('admin.tests.destroy', $test->id));
        $res->assertRedirect(route('admin.tests.index'));

        $this->assertSoftDeleted('tests', ['id' => $test->id]);
    }
}
