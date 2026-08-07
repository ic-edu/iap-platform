<?php

namespace Tests\Feature;

use App\Models\RepositoryFinding;
use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\RepositoryQualityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryFindingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected RepositoryQualityService $qualityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'IRQA Teacher Lifecycle',
            'email'  => 'teacher_lifecycle@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'IRQA Manager Lifecycle',
            'email'  => 'manager_lifecycle@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->qualityService = app(RepositoryQualityService::class);
    }

    protected function createValidBank(array $attributes = []): QuestionBank
    {
        $bank = QuestionBank::create(array_merge([
            'title'           => 'Valid Bank Title',
            'slug'            => 'valid-bank-slug-' . uniqid(),
            'test_type'       => 'toeic',
            'description'     => 'Comprehensive test repository description.',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'acl_category_id' => null,
            'created_by'      => $this->teacher->id,
        ], $attributes));

        // Add 3 questions of balanced difficulty (easy, medium, hard) to satisfy difficulty balance check
        foreach (['easy', 'medium', 'hard'] as $diff) {
            Question::create([
                'question_bank_id' => $bank->id,
                'prompt'           => "Sample Question Prompt {$diff}",
                'question_type'    => 'essay',
                'explanation'      => 'Detailed explanation for sample question.',
                'difficulty'       => $diff,
                'points'           => 10,
            ]);
        }

        return $bank;
    }

    /**
     * TEST 1: First Finding Creation.
     */
    public function test_1_first_finding_creation_creates_single_open_finding()
    {
        $bank = $this->createValidBank(['acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank);

        $openFindings = RepositoryFinding::where('question_bank_id', $bank->id)
            ->where('status', 'OPEN')
            ->get();

        $this->assertCount(1, $openFindings);
        $this->assertStringContainsString('Missing Category', $openFindings->first()->title);
    }

    /**
     * TEST 2: Re-scan Does Not Duplicate OPEN Findings.
     */
    public function test_2_rescan_does_not_duplicate_open_findings()
    {
        $bank = $this->createValidBank(['acl_category_id' => null]);

        // Scan 1
        $this->qualityService->syncRepositoryFindings($bank);

        // Scan 2 (Re-scan)
        $this->qualityService->syncRepositoryFindings($bank);

        // Scan 3 (Re-scan again)
        $this->qualityService->syncRepositoryFindings($bank);

        $openFindings = RepositoryFinding::where('question_bank_id', $bank->id)
            ->where('status', 'OPEN')
            ->where('title', 'like', '%Missing Category%')
            ->get();

        $this->assertCount(1, $openFindings);
    }

    /**
     * TEST 3: Different Findings Remain Separate.
     */
    public function test_3_different_findings_remain_separate()
    {
        $bank = $this->createValidBank(['acl_category_id' => null]);

        // Delete easy and hard questions so difficulty becomes 100% medium (triggers difficulty unbalanced warning)
        $bank->questions()->whereIn('difficulty', ['easy', 'hard'])->delete();

        $this->qualityService->syncRepositoryFindings($bank);

        $openFindings = RepositoryFinding::where('question_bank_id', $bank->id)
            ->where('status', 'OPEN')
            ->get();

        // 1 for Missing Category, 1 for Difficulty Unbalanced
        $this->assertCount(2, $openFindings);
    }

    /**
     * TEST 4: Resolved Finding when Issue Disappears.
     */
    public function test_4_resolved_finding_when_issue_disappears()
    {
        $bank = $this->createValidBank(['acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        // Fix the category
        $category = \App\Models\AclCategory::create(['name' => 'General', 'slug' => 'general', 'is_active' => true]);
        $bank->acl_category_id = $category->id;
        $bank->save();

        // Re-scan
        $this->qualityService->syncRepositoryFindings($bank);

        // Assert OPEN count is 0, and finding is FIXED
        $this->assertEquals(0, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'FIXED')->count());
    }

    /**
     * TEST 5: New Finding Created on Re-scan.
     */
    public function test_5_new_finding_created_on_rescan()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Grammar', 'slug' => 'grammar', 'is_active' => true]);

        $bank = $this->createValidBank(['acl_category_id' => $category->id]);

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(0, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        // Remove category to introduce a finding
        $bank->acl_category_id = null;
        $bank->save();

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
    }

    /**
     * TEST 6: Finding Reappears and is Reopened.
     */
    public function test_6_finding_reappears_and_is_reopened()
    {
        $bank = $this->createValidBank(['acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        // Fix
        $category = \App\Models\AclCategory::create(['name' => 'Vocab', 'slug' => 'vocab', 'is_active' => true]);
        $bank->acl_category_id = $category->id;
        $bank->save();
        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'FIXED')->count());

        // Re-introduce issue
        $bank->acl_category_id = null;
        $bank->save();
        $this->qualityService->syncRepositoryFindings($bank);

        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
        $this->assertEquals(0, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'FIXED')->count());
    }

    /**
     * TEST 7: Question Review Independence (TestQuestionReview does NOT resolve RepositoryFinding).
     */
    public function test_7_question_review_independence()
    {
        $bank = $this->createValidBank(['acl_category_id' => null]);
        $q = $bank->questions->first();

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        // Mark TestQuestionReview as reviewed_ok
        TestQuestionReview::create([
            'test_id'        => '01kz8tgwdv2nbms5h6yvzkvjfs',
            'question_id'    => (string) $q->id,
            'status'         => 'reviewed_ok',
            'reviewer_id'    => $this->repoManager->id,
            'reviewer_notes' => 'LGTM',
        ]);

        // Re-query finding
        $finding = RepositoryFinding::where('question_bank_id', $bank->id)->first();
        $this->assertEquals('OPEN', $finding->status);
    }

    /**
     * TEST 8: Published Repository False Positive Prevention (Oral/Essay Prompts).
     */
    public function test_8_oral_and_essay_prompts_do_not_generate_missing_choices_warning()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Speaking', 'slug' => 'speaking', 'is_active' => true]);

        $bank = $this->createValidBank([
            'title'           => 'IELTS Speaking Prompts',
            'slug'            => 'ielts-speaking-prompts',
            'test_type'       => 'ielts',
            'status'          => 'published',
            'acl_category_id' => $category->id,
        ]);

        // Clear default questions
        $bank->questions()->delete();

        // Speaking prompt with 0 choices
        Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Part 1 Interview: Do you prefer living in a house or apartment?',
            'question_type'    => 'speaking',
            'explanation'      => 'Detailed audio prompt explanation.',
            'difficulty'       => 'medium',
        ]);

        // Essay prompt with 0 choices
        Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Part 2 Cue Card: Describe your favorite trip.',
            'question_type'    => 'essay',
            'explanation'      => 'Detailed cue card explanation.',
            'difficulty'       => 'easy',
        ]);

        Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Part 3 Discussion: Public parks vs museums.',
            'question_type'    => 'speaking',
            'explanation'      => 'Detailed discussion explanation.',
            'difficulty'       => 'hard',
        ]);

        $audit = $this->qualityService->syncRepositoryFindings($bank);

        $this->assertEmpty($audit['warnings']);
        $this->assertEquals(0, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
    }

    /**
     * TEST 9: Multiple Choice Questions Without Choices DO Generate Warnings.
     */
    public function test_9_multiple_choice_without_choices_generates_warning()
    {
        $category = \App\Models\AclCategory::create(['name' => 'MCQ', 'slug' => 'mcq', 'is_active' => true]);

        $bank = $this->createValidBank([
            'title'           => 'MCQ Bank',
            'slug'            => 'mcq-bank',
            'test_type'       => 'toefl',
            'acl_category_id' => $category->id,
        ]);

        // Clear default questions
        $bank->questions()->delete();

        Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'MCQ Without Choices',
            'question_type'    => 'multiple_choice',
            'explanation'      => 'Detailed explanation.',
            'difficulty'       => 'medium',
        ]);

        $audit = $this->qualityService->syncRepositoryFindings($bank);

        $this->assertNotEmpty($audit['warnings']);
        $this->assertStringContainsString('has no answer choices attached', $audit['warnings'][0]);

        $openFinding = RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->first();
        $this->assertNotNull($openFinding);
        $this->assertEquals('OPEN', $openFinding->status);
    }

    /**
     * TEST 10: Dashboard Count Correctly Derived from Active OPEN Findings.
     */
    public function test_10_dashboard_count_correctly_derived_from_active_open_findings()
    {
        RepositoryFinding::query()->delete();

        $bank1 = $this->createValidBank(['acl_category_id' => null]);
        $bank2 = $this->createValidBank(['acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank1);
        $this->qualityService->syncRepositoryFindings($bank2);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('2'); // 2 active OPEN findings
    }

    /**
     * TEST 11: Teacher Resubmission Triggers Automatic IRQA Re-Scan.
     */
    public function test_11_teacher_resubmission_triggers_automatic_irqa_rescan()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Grammar', 'slug' => 'grammar', 'is_active' => true]);

        $bank = $this->createValidBank(['status' => 'needs_revision', 'acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        $revisionRequest = \App\Models\RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Please add category',
        ]);

        // Fix the category before resubmission
        $bank->acl_category_id = $category->id;
        $bank->save();

        // Teacher resubmits
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $response->assertStatus(302);

        // Assert automatic IRQA re-scan updated finding to FIXED
        $this->assertEquals(0, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'FIXED')->count());
    }

    /**
     * TEST 12: Failed Rescan Notification Routing to Repository Manager.
     */
    public function test_12_failed_rescan_notification_routing_to_repository_manager()
    {
        $bank = $this->createValidBank(['status' => 'needs_revision', 'acl_category_id' => null]);

        $revisionRequest = \App\Models\RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Please fix category',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $response->assertStatus(302);

        // Notification should be sent to Repository Manager, NOT Teacher
        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            $notif = \Illuminate\Support\Facades\DB::table('notifications')
                ->where('notifiable_id', $this->repoManager->id)
                ->first();
            $this->assertNotNull($notif);
        }
    }

    /**
     * TEST 13: Passed Rescan Resolves Findings.
     */
    public function test_13_passed_rescan_resolves_findings()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Reading', 'slug' => 'reading', 'is_active' => true]);

        $bank = $this->createValidBank(['status' => 'needs_revision', 'acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        $revisionRequest = \App\Models\RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix category',
        ]);

        // Fix issue
        $bank->acl_category_id = $category->id;
        $bank->save();

        $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $this->assertEquals(0, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'FIXED')->count());
    }
}
