<?php

namespace Tests\Feature;

use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowRefactorReviewEntryPointTest extends TestCase
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
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_s11_2a@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_2A',
            'email'  => 'repomanager_s11_2a@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Hotfix Review Entry Bank',
            'slug'       => 'hotfix-review-entry-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Review & Governance entry point opens Question Review Workspace, NOT Revision dialog.
     */
    public function test_1_review_and_governance_opens_workspace_not_revision_dialog()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Hotfix Refactor 01',
            'slug'             => 'toeic-hotfix-refactor-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);

        $q1 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Question 1 Stem Text',
            'question_type'    => 'multiple_choice',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Assessment Queue page displays Review & Governance link pointing to assessment-review
        $queueRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $queueRes->assertStatus(200);
        $queueRes->assertSee(route('admin.repository-manager.assessment-review', $test->id));
        $queueRes->assertDontSee('openRevisionModal');

        // Review & Governance target route opens workspace
        $workspaceRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $workspaceRes->assertStatus(200);
        $workspaceRes->assertSee('Question Governance Review Decision');
        $workspaceRes->assertSee('🟢 Reviewed OK');
        $workspaceRes->assertSee('🟡 Needs Revision');
        $workspaceRes->assertSee('⚪ Skip for now');
    }
}
