<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UatCriticalBugFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $teacherB;
    protected User $repoManager;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Teacher Alpha',
            'email'  => 'teacher_a@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->teacherB = User::factory()->create([
            'name'   => 'Teacher Beta',
            'email'  => 'teacher_b@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherB->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager',
            'email'  => 'repomanager_uat@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin UAT',
            'email'  => 'superadmin_uat@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    /**
     * TEST 1: Teacher A accesses their own Needs Revision list -> 200 OK.
     */
    public function test_1_teacher_a_can_access_own_needs_revision_list_successfully()
    {
        QuestionBank::create([
            'title'      => 'Teacher A Bank Needs Revision',
            'slug'       => 'teacher-a-bank-needs-revision',
            'test_type'  => 'toeic',
            'status'     => 'needs_revision',
            'created_by' => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('admin.question-banks.index', ['status' => 'needs_revision']));
        $res->assertStatus(200);
        $res->assertSee('Teacher A Bank Needs Revision');
    }

    /**
     * TEST 2: Teacher B tries to access Teacher A's Question Bank details -> 403 Forbidden.
     */
    public function test_2_teacher_b_cannot_access_teacher_a_question_bank()
    {
        $bankA = QuestionBank::create([
            'title'      => 'Teacher A Private Bank',
            'slug'       => 'teacher-a-private-bank',
            'test_type'  => 'toeic',
            'status'     => 'needs_revision',
            'created_by' => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherB)->get(route('admin.question-banks.show', $bankA->id));
        $res->assertStatus(403);
    }

    /**
     * TEST 3: Repository Manager opens Review Queue -> 200 OK.
     */
    public function test_3_repository_manager_can_open_review_queue()
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.questions-approval'));
        $res->assertStatus(200);
    }

    /**
     * TEST 4: Repository Manager returns Question Bank for revision -> Question Bank AND linked Assessment become needs_revision.
     */
    public function test_4_returning_question_bank_for_revision_synchronizes_linked_assessment()
    {
        $bank = QuestionBank::create([
            'title'      => 'Linked Pool Bank',
            'slug'       => 'linked-pool-bank',
            'test_type'  => 'toeic',
            'status'     => 'pending_approval',
            'created_by' => $this->teacherA->id,
        ]);

        $question = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Linked Question Prompt',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
            'points'           => 10,
        ]);

        $test = AssessmentTest::create([
            'title'            => 'Linked Simulation Assessment',
            'slug'             => 'linked-simulation-assessment',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1',
            'order'   => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 10,
        ]);

        $res = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), [
                'notes' => 'Please add choice explanations.',
            ]);

        $res->assertRedirect();

        $bank->refresh();
        $test->refresh();

        $this->assertEquals('needs_revision', $bank->status);
        $this->assertEquals('needs_revision', $test->status);
        $this->assertFalse($test->is_published);

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => (string) $bank->id,
            'action'        => 'revision_requested',
        ]);

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'action'        => 'revision_requested',
        ]);
    }

    /**
     * TEST 5: Assessment Builder Workflow badge displays Needs Revision.
     */
    public function test_5_assessment_builder_badge_displays_needs_revision()
    {
        $test = AssessmentTest::create([
            'title'            => 'UAT Badge Assessment',
            'slug'             => 'uat-badge-assessment',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('admin.tests.index'));
        $res->assertStatus(200);
        $res->assertSee('Needs Revision');
    }
}
