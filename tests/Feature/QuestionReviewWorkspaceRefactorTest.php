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

class QuestionReviewWorkspaceRefactorTest extends TestCase
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
            'email'  => 'vance_s11_3@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_3',
            'email'  => 'repomanager_s11_3@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Workspace Refactor Bank',
            'slug'       => 'workspace-refactor-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Question Review Workspace prioritizes inspection, Question Navigator, and Decision Panel over revision buttons.
     */
    public function test_1_workspace_renders_question_navigator_and_decision_panel()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Workspace Refactor 01',
            'slug'             => 'toeic-workspace-refactor-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);

        $q1 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Question 1 Workspace Stem',
            'question_type'    => 'multiple_choice',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $workspaceRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $workspaceRes->assertStatus(200);

        // Verify Question Navigator widget presence
        $workspaceRes->assertSee('Question Navigator');
        $workspaceRes->assertSee('Jump to question');
        $workspaceRes->assertSee('Q1');

        // Verify Review Decision Panel
        $workspaceRes->assertSee('Question Governance Review Decision');
        $workspaceRes->assertSee('🟢 Reviewed OK');
        $workspaceRes->assertSee('🟡 Needs Revision');
        $workspaceRes->assertSee('⚪ Skip for now');
    }
}
