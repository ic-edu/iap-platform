<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentAuthoringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $teacherB;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_authoring@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->teacherB = User::factory()->create([
            'name'   => 'Prof. Marcus Brody',
            'email'  => 'brody_authoring@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherB->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Authoring',
            'email'  => 'repomanager_authoring@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: Revision Center Revise Assessment link opens Assessment Detail page (200 OK).
     */
    public function test_1_revision_center_revise_assessment_opens_detail_page()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Simulation Test Revision 01',
            'slug'             => 'toeic-simulation-test-revision-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $res->assertStatus(200);
        $res->assertSee('TOEIC Simulation Test Revision 01');
        $res->assertSee('Assessment Authoring Editor');
    }

    /**
     * TEST 2: Assessment Detail displays Repository Manager feedback notes and governance timeline.
     */
    public function test_2_assessment_detail_displays_feedback_and_timeline()
    {
        $test = AssessmentTest::create([
            'title'            => 'IELTS Academic Mock 02',
            'slug'             => 'ielts-academic-mock-02',
            'test_type'        => 'ielts',
            'duration_minutes' => 160,
            'pass_score'       => 75,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $this->teacherA->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'revision_requested',
            'approval_note' => 'Reading section duration should be set to 60 minutes instead of 160.',
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $res->assertStatus(200);
        $res->assertSee('Reading section duration should be set to 60 minutes instead of 160.');
        $res->assertSee('Repository Manager Authoring');
        $res->assertSee('Governance Timeline');
    }

    /**
     * TEST 3: Teacher updates assessment details (Save Draft) -> status remains needs_revision.
     */
    public function test_3_teacher_save_draft_keeps_status_as_needs_revision()
    {
        $test = AssessmentTest::create([
            'title'            => 'Draft Title Original',
            'slug'             => 'draft-title-original',
            'test_type'        => 'toefl',
            'duration_minutes' => 90,
            'pass_score'       => 500,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherA)->put(route('teacher.tests.update', $test->id), [
            'title'            => 'Draft Title Updated by Teacher',
            'test_type'        => 'toefl',
            'duration_minutes' => 80,
            'pass_score'       => 520,
        ]);

        $res->assertRedirect(route('teacher.tests.show', $test->id));

        $test->refresh();
        $this->assertEquals('Draft Title Updated by Teacher', $test->title);
        $this->assertEquals(80, $test->duration_minutes);
        $this->assertEquals('needs_revision', $test->status);
    }

    /**
     * TEST 4: Teacher clicks Resubmit for Review -> status changes to pending_approval and appears in Repository Queue.
     */
    public function test_4_teacher_resubmit_updates_status_to_pending_approval_and_appears_in_queue()
    {
        $test = AssessmentTest::create([
            'title'            => 'Resubmitted Full Test',
            'slug'             => 'resubmitted-full-test',
            'test_type'        => 'general',
            'duration_minutes' => 120,
            'pass_score'       => 750,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = \App\Modules\Assessment\Models\TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Core Section',
            'order'   => 1,
        ]);
        $bank = \App\Modules\QuestionBank\Models\QuestionBank::create([
            'title'       => 'Test Bank A',
            'slug'        => 'test-bank-a-' . \Illuminate\Support\Str::random(5),
            'test_type'   => 'general',
            'status'      => 'published',
            'created_by'  => $this->teacherA->id,
        ]);
        $q = \App\Modules\QuestionBank\Models\Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Valid question stem?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'difficulty'       => 'medium',
        ]);
        \App\Modules\QuestionBank\Models\QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'A',
            'content'     => 'Choice A',
            'is_correct'  => true,
        ]);
        \App\Modules\Assessment\Models\TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $q->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $res = $this->actingAs($this->teacherA)->post(route('teacher.tests.resubmit', $test->id));
        $res->assertRedirect(route('teacher.tests.show', $test->id));

        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);

        // Verify Repository Manager Assessment Queue lists the resubmitted test
        $queueRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $queueRes->assertStatus(200);
        $queueRes->assertSee('Resubmitted Full Test');
    }

    /**
     * TEST 5: Teacher B cannot view, update, or resubmit Teacher A's assessment.
     */
    public function test_5_teacher_b_cannot_access_or_modify_teacher_a_assessment()
    {
        $testA = AssessmentTest::create([
            'title'            => 'Teacher A Confidential Assessment',
            'slug'             => 'teacher-a-confidential-assessment',
            'test_type'        => 'ielts',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $resView = $this->actingAs($this->teacherB)->get(route('teacher.tests.show', $testA->id));
        $resView->assertStatus(403);

        $resUpdate = $this->actingAs($this->teacherB)->put(route('teacher.tests.update', $testA->id), [
            'title'            => 'Hacked Title',
            'test_type'        => 'ielts',
            'duration_minutes' => 60,
            'pass_score'       => 70,
        ]);
        $resUpdate->assertStatus(403);

        $resResubmit = $this->actingAs($this->teacherB)->post(route('teacher.tests.resubmit', $testA->id));
        $resResubmit->assertStatus(403);
    }
}
