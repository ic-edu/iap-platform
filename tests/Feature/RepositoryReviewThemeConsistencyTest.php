<?php

namespace Tests\Feature;

use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryReviewThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;
    protected AssessmentTest $test;
    protected Question $q1;
    protected Question $q2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Dr. Theme Tester',
            'email'  => 'theme_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Theme Auditor RM',
            'email'  => 'theme_rm@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'      => 'Theme Consistency Bank',
            'slug'       => 'theme-consistency-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacher->id,
        ]);

        $this->test = AssessmentTest::create([
            'title'            => 'Theme Consistency Assessment Test',
            'slug'             => 'theme-consistency-assessment-test',
            'test_type'        => 'toeic',
            'status'           => 'pending_approval',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->test->id,
            'title'   => 'Grammar & Vocabulary',
            'order'   => 1,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Sample Question 1 for Theme Check',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'explanation'      => 'Sample explanation for question 1.',
        ]);

        QuestionChoice::create([
            'question_id' => $this->q1->id,
            'label'       => 'A',
            'content'     => 'Option A Choice Text',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $this->q1->id,
            'label'       => 'B',
            'content'     => 'Option B Choice Text',
            'is_correct'  => false,
            'order'       => 2,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $this->q1->id,
            'order'           => 1,
        ]);

        $this->q2 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Sample Question 2 with Flagged Review',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'hard',
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $this->q2->id,
            'order'           => 2,
        ]);

        TestQuestionReview::create([
            'test_id'     => $this->test->id,
            'question_id' => $this->q2->id,
            'reviewer_id' => $this->repoManager->id,
            'status'      => 'needs_revision',
            'field'       => 'stem',
            'severity'    => 'warning',
            'comment'     => 'Stem needs more clarity for students.',
        ]);
    }

    /**
     * TEST: Verify workspace template file has no hardcoded dark hex inline leakage.
     */
    public function test_workspace_template_has_no_hardcoded_dark_hex_leakage(): void
    {
        $viewPath = resource_path('views/admin/repository_manager/assessment_review.blade.php');
        $this->assertFileExists($viewPath);
        $content = file_get_contents($viewPath);

        // Asserts no dark hex styles used on surfaces or text
        $this->assertStringNotContainsString('#0f172a', $content);
        $this->assertStringNotContainsString('#1e293b', $content);
        $this->assertStringNotContainsString('#334155', $content);
        $this->assertStringNotContainsString('color:#fff', $content);
        $this->assertStringNotContainsString('color: #fff', $content);
    }

    /**
     * TEST: Verify presence of adaptive Tailwind utility classes for light and dark modes.
     */
    public function test_workspace_renders_adaptive_theme_classes(): void
    {
        $res = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-review', $this->test->id));

        $res->assertStatus(200);

        // Primary surfaces use bg-white with dark:bg-slate-900
        $res->assertSee('bg-white dark:bg-slate-900', false);
        $res->assertSee('border-slate-200 dark:border-slate-800', false);

        // Primary headings use text-slate-900 with dark:text-white
        $res->assertSee('text-slate-900 dark:text-white', false);

        // Nested surfaces use bg-slate-50 with dark variants
        $res->assertSee('bg-slate-50 dark:bg-slate-800/40', false);
        $res->assertSee('bg-slate-50 dark:bg-slate-800/60', false);

        // Header and back button
        $res->assertSee('Repository Smart Review Engine');
        $res->assertSee('Review by Exception Mode');
        $res->assertSee('← Back');

        // Questions and Choices
        $res->assertSee('Sample Question 1 for Theme Check');
        $res->assertSee('Option A Choice Text');
        $res->assertSee('Option B Choice Text');
        $res->assertSee('Sample explanation for question 1.');

        // Review by Exception badge
        $res->assertSee('Default OK');
        $res->assertSee('Flagged Revisions');

        // Reviewer feedback on Q2
        $res->assertSee('Stem needs more clarity for students.');

        // Governance buttons
        $res->assertSee('Approve Assessment');
        $res->assertSee('Request Assessment Revision');
        $res->assertSee('Send to Archived');
    }

    /**
     * TEST: Governance integrity remains unaltered.
     */
    public function test_governance_actions_and_guards_remain_intact(): void
    {
        $res = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-review', $this->test->id));

        $res->assertStatus(200);

        // Form routes and confirmation modals
        $res->assertSee(route('admin.repository-manager.assessment-approve', $this->test->id));
        $res->assertSee(route('admin.repository-manager.assessment-revision', $this->test->id));
        $res->assertSee(route('admin.repository-manager.assessment-archive', $this->test->id));
        $res->assertSee("iapConfirm({ title: 'Approve Assessment?'", false);
        $res->assertSee("iapConfirm({ title: 'Request Assessment Revision?'", false);
        $res->assertSee("iapConfirm({ title: 'Send Assessment to Archived?'", false);
    }
}
