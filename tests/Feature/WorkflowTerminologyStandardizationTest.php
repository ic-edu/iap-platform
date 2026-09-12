<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTerminologyStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $repoManager;
    protected QuestionBank $bankA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance Terminology',
            'email'  => 'vance_term@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Terminology',
            'email'  => 'repomanager_term@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Terminology Bank',
            'slug'       => 'terminology-bank',
            'test_type'  => 'general',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Initial Draft submission button displays 'Submit for Review'.
     */
    public function test_1_draft_assessment_displays_submit_for_review_button()
    {
        $test = AssessmentTest::create([
            'title'            => 'Draft Terminology Test 01',
            'slug'             => 'draft-terminology-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'draft',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question Stem 01']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $res->assertStatus(200);

        $res->assertSee('Submit for Review');
        $res->assertDontSee('Submit Again for Review');
    }

    /**
     * TEST 2: Revision workflow button displays 'Resubmit for Review' and confirmation dialog text.
     */
    public function test_2_needs_revision_assessment_displays_resubmit_for_review()
    {
        $test = AssessmentTest::create([
            'title'            => 'Needs Revision Terminology Test 02',
            'slug'             => 'needs-revision-terminology-test-02',
            'test_type'        => 'general',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Reading', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question Stem 02', 'question_type' => 'multiple_choice']);
        \App\Modules\QuestionBank\Models\QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $res->assertStatus(200);

        $res->assertSee('Resubmit for Review');
        $res->assertSee('Resubmit Assessment for Review?');
        $res->assertDontSee('Submit Again for Review');
    }

    /**
     * TEST 3: Resubmitting assessment records activity log action 'assessment_resubmitted'.
     */
    public function test_3_resubmit_action_creates_standardized_activity_log()
    {
        $test = AssessmentTest::create([
            'title'            => 'Resubmit Activity Log Test 03',
            'slug'             => 'resubmit-activity-log-test-03',
            'test_type'        => 'general',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Structure', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question Stem 03', 'question_type' => 'multiple_choice']);
        \App\Modules\QuestionBank\Models\QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->teacherA)->post(route('teacher.tests.resubmit', $test->id));
        $res->assertRedirect(route('teacher.tests.show', $test->id));
        $res->assertSessionHas('status', 'Assessment resubmitted successfully.');

        $log = RepositoryActivityLog::where('resource_type', 'Test')
            ->where('resource_id', (string) $test->id)
            ->where('action', 'assessment_resubmitted')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Assessment resubmitted for review.', $log->approval_note);
    }
}
