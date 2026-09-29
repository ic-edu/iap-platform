<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Models\QuestionGenerationBatch;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use App\Services\NavigationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherQuestionBankDraftDeleteAndGeneratorNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $otherTeacher;

    protected User $admin;

    protected User $superAdmin;

    protected User $repositoryManager;

    protected User $student;

    protected AssessmentStandard $toeicStd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['status' => 'active']);
        $this->otherTeacher->assignRole('teacher');

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('super-admin');

        $this->repositoryManager = User::factory()->create(['status' => 'active']);
        $this->repositoryManager->assignRole('repository-manager');

        $this->student = User::factory()->create(['status' => 'active']);
        $this->student->assignRole('student');

        $this->toeicStd = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'ETS',
            'status' => StandardStatus::Active,
            'structure_definition' => ['sections' => ['listening', 'reading']],
        ]);
    }

    protected function createQuestionBank(array $attributes = []): QuestionBank
    {
        return QuestionBank::create(array_merge([
            'title' => 'Test Bank '.uniqid(),
            'slug' => 'test-bank-'.uniqid(),
            'test_type' => TestType::Toeic,
            'status' => 'draft',
            'created_by' => $this->teacher->id,
            'current_version' => '1.0',
        ], $attributes));
    }

    // 1. Teacher can delete own draft question bank
    public function test_teacher_can_delete_own_draft_question_bank(): void
    {
        $bank = $this->createQuestionBank(['status' => 'draft']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertRedirect(route('admin.question-banks.index'));
        $response->assertSessionHas('status');

        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    // 2. Draft deletion logs activity
    public function test_draft_deletion_creates_activity_log(): void
    {
        $bank = $this->createQuestionBank(['status' => 'draft', 'title' => 'My Draft Bank']);

        $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'question_bank_draft_deleted',
            'user_id' => $this->teacher->id,
            'subject_id' => $bank->id,
        ]);

        $log = ActivityLog::where('action', 'question_bank_draft_deleted')
            ->where('subject_id', $bank->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('My Draft Bank', $log->description);
    }

    // 3. Teacher cannot delete another teacher's draft bank (403)
    public function test_teacher_cannot_delete_another_teachers_draft_bank(): void
    {
        $bank = $this->createQuestionBank([
            'created_by' => $this->otherTeacher->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 4. Teacher cannot delete own bank in needs_revision (403)
    public function test_teacher_cannot_delete_own_bank_in_needs_revision(): void
    {
        $bank = $this->createQuestionBank(['status' => 'needs_revision']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 5. Teacher cannot delete own bank in rejected (403)
    public function test_teacher_cannot_delete_own_bank_in_rejected(): void
    {
        $bank = $this->createQuestionBank(['status' => 'rejected']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 6. Teacher cannot delete own bank in pending_approval (403)
    public function test_teacher_cannot_delete_own_bank_in_pending_approval(): void
    {
        $bank = $this->createQuestionBank(['status' => 'pending_approval']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 7. Teacher cannot delete own bank in approved (403)
    public function test_teacher_cannot_delete_own_bank_in_approved(): void
    {
        $bank = $this->createQuestionBank(['status' => 'approved']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 8. Teacher cannot delete own bank in published (403)
    public function test_teacher_cannot_delete_own_bank_in_published(): void
    {
        $bank = $this->createQuestionBank([
            'status' => 'published',
            'is_published' => true,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 9. Teacher cannot delete own bank in archived (403)
    public function test_teacher_cannot_delete_own_bank_in_archived(): void
    {
        $bank = $this->createQuestionBank(['status' => 'archived']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 10. Unauthenticated guest cannot delete question bank
    public function test_guest_cannot_delete_question_bank(): void
    {
        $bank = $this->createQuestionBank(['status' => 'draft']);

        $response = $this->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 11. Super Admin can soft delete question bank directly
    public function test_super_admin_can_soft_delete_question_bank(): void
    {
        $bank = $this->createQuestionBank(['status' => 'draft']);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertRedirect(route('admin.question-banks.index'));
        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    // 12. Navigation service includes Question Generator for teacher under Authoring
    public function test_teacher_sidebar_navigation_contains_question_generator(): void
    {
        $menuItems = NavigationService::getMenuItems($this->teacher);

        $authoringItems = array_values(array_filter($menuItems, fn ($item) => $item['section'] === 'Authoring'));
        $labels = array_column($authoringItems, 'label');

        $this->assertContains('Question Generator', $labels);

        $generatorItem = collect($authoringItems)->firstWhere('label', 'Question Generator');
        $this->assertNotNull($generatorItem);
        $this->assertEquals('teacher.question-generator.index', $generatorItem['route']);
        $this->assertEquals('teacher/question-generator*', $generatorItem['active_pattern']);
    }

    // 13. Navigation service does NOT include Question Generator for non-teachers
    public function test_non_teacher_sidebar_navigation_does_not_contain_question_generator(): void
    {
        $adminMenu = NavigationService::getMenuItems($this->admin);
        $this->assertNotContains('Question Generator', array_column($adminMenu, 'label'));

        $rmMenu = NavigationService::getMenuItems($this->repositoryManager);
        $this->assertNotContains('Question Generator', array_column($rmMenu, 'label'));

        $superAdminMenu = NavigationService::getMenuItems($this->superAdmin);
        $this->assertNotContains('Question Generator', array_column($superAdminMenu, 'label'));
    }

    // 14. Teacher can access Question Generator landing page
    public function test_teacher_can_access_question_generator_landing_page(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertViewIs('question_bank::generation_landing');
    }

    // 15. Non-teacher roles cannot access Question Generator landing page
    public function test_non_teachers_cannot_access_question_generator_landing_page(): void
    {
        // Admin
        $response = $this->actingAs($this->admin)
            ->get(route('teacher.question-generator.index'));
        $response->assertStatus(403);

        // Repository Manager
        $response = $this->actingAs($this->repositoryManager)
            ->get(route('teacher.question-generator.index'));
        $response->assertStatus(403);

        // Student
        $response = $this->actingAs($this->student)
            ->get(route('teacher.question-generator.index'));
        $response->assertStatus(403);
    }

    // 16. Guest cannot access Question Generator landing page
    public function test_guest_cannot_access_question_generator_landing_page(): void
    {
        $response = $this->get(route('teacher.question-generator.index'));
        $response->assertRedirect(route('login'));
    }

    // 17. Question Generator landing page lists only teacher's owned editable banks
    public function test_question_generator_landing_lists_only_owned_editable_banks(): void
    {
        $draftBank = $this->createQuestionBank(['title' => 'My Draft Bank', 'status' => 'draft']);
        $needsRevisionBank = $this->createQuestionBank(['title' => 'My Revision Bank', 'status' => 'needs_revision']);
        $rejectedBank = $this->createQuestionBank(['title' => 'My Rejected Bank', 'status' => 'rejected']);
        $publishedBank = $this->createQuestionBank(['title' => 'My Published Bank', 'status' => 'published', 'is_published' => true]);
        $pendingBank = $this->createQuestionBank(['title' => 'My Pending Bank', 'status' => 'pending_approval']);
        $otherTeacherBank = $this->createQuestionBank(['title' => 'Other Teacher Bank', 'status' => 'draft', 'created_by' => $this->otherTeacher->id]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('My Draft Bank');
        $response->assertSee('My Revision Bank');
        $response->assertSee('My Rejected Bank');
        $response->assertDontSee('My Published Bank');
        $response->assertDontSee('My Pending Bank');
        $response->assertDontSee('Other Teacher Bank');
    }

    // 18. Question Generator landing page shows empty state when teacher has no editable banks
    public function test_question_generator_landing_shows_empty_state_when_no_editable_banks(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('No Editable Question Banks Found');
        $response->assertSee('Create New Question Bank');
    }

    // 19. Question Bank index renders Delete Draft action only for teacher owner on draft banks
    public function test_question_bank_index_renders_delete_draft_for_teacher_owner(): void
    {
        $draftBank = $this->createQuestionBank(['title' => 'Deleteable Draft Bank', 'status' => 'draft']);
        $otherBank = $this->createQuestionBank(['title' => 'Other Bank', 'status' => 'draft', 'created_by' => $this->otherTeacher->id]);

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.index'));

        $response->assertOk();
        $response->assertSee('Deleteable Draft Bank');
        $response->assertSee('Delete Draft');
    }

    // 20. Contextual Generate Questions button is present on Question Bank show page for editable banks
    public function test_question_bank_show_page_has_generate_questions_link_for_editable_bank(): void
    {
        $draftBank = $this->createQuestionBank(['title' => 'Draft Bank Show', 'status' => 'draft']);

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $draftBank->id));

        $response->assertOk();
        $response->assertSee('Generate Questions');
        $response->assertSee(route('admin.question-banks.generation.index', $draftBank->id));
        $response->assertSee('Delete Draft');
    }

    // 21. Teacher cannot delete bank in pending_deletion (403)
    public function test_teacher_cannot_delete_own_bank_in_pending_deletion(): void
    {
        $bank = $this->createQuestionBank(['status' => 'pending_deletion']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'deleted_at' => null,
        ]);
    }

    // 22. Soft delete of Question Bank preserves child question rows in DB without affecting active authoring lists
    public function test_soft_delete_preserves_child_question_rows_and_isolates_active_authoring(): void
    {
        $draftBank = $this->createQuestionBank([
            'title' => 'Draft Bank With Questions',
            'status' => 'draft',
        ]);

        // 1. Manual child question
        $manualQuestion = Question::create([
            'question_bank_id' => $draftBank->id,
            'prompt' => 'Manual test question prompt?',
            'question_type' => QuestionType::MultipleChoice,
            'section' => SectionType::Reading,
            'difficulty' => DifficultyLevel::Medium,
            'points' => 1,
            'content_origin' => ContentOrigin::Manual,
        ]);

        // 2. Generated batch and child question
        $batch = QuestionGenerationBatch::create([
            'question_bank_id' => $draftBank->id,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStd->id,
            'standard_version' => '2026.1',
            'planner_type' => 'toeic_blueprint_planner',
            'planner_strategy_version' => '2026.1',
            'plan_fingerprint' => 'test-fingerprint',
            'status' => GenerationBatchStatus::Completed,
            'idempotency_key' => 'batch-test-soft-del-'.uniqid(),
            'prompt_contract_version' => 'question_generation_v1',
            'total_slots' => 1,
            'pending_slots' => 0,
            'processing_slots' => 0,
            'generated_slots' => 0,
            'validated_slots' => 1,
            'failed_slots' => 0,
        ]);

        $generatedQuestion = Question::create([
            'question_bank_id' => $draftBank->id,
            'prompt' => 'AI generated question prompt?',
            'question_type' => QuestionType::MultipleChoice,
            'section' => SectionType::Reading,
            'difficulty' => DifficultyLevel::Medium,
            'points' => 1,
            'content_origin' => ContentOrigin::Generated,
            'generation_batch_id' => $batch->id,
        ]);

        $genItem = QuestionGenerationItem::create([
            'generation_batch_id' => $batch->id,
            'slot_sequence' => 1,
            'slot_fingerprint' => 'test-slot-soft-del',
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStd->id,
            'standard_version' => '2026.1',
            'section' => 'reading',
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'status' => GenerationItemStatus::Materialized,
            'question_id' => $generatedQuestion->id,
        ]);

        // Execute Teacher Draft Delete
        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', $draftBank->id));

        $response->assertRedirect(route('admin.question-banks.index'));

        // 1. QuestionBank is soft-deleted
        $this->assertSoftDeleted('question_banks', ['id' => $draftBank->id]);

        // 2. Child Question rows remain in DB under the parent ID without hard deletion or corruption
        $this->assertDatabaseHas('questions', [
            'id' => $manualQuestion->id,
            'question_bank_id' => $draftBank->id,
        ]);
        $this->assertDatabaseHas('questions', [
            'id' => $generatedQuestion->id,
            'question_bank_id' => $draftBank->id,
        ]);
        $this->assertDatabaseHas('question_generation_batches', [
            'id' => $batch->id,
            'question_bank_id' => $draftBank->id,
        ]);

        // 3. Bank disappears from normal Teacher Question Bank index (excluded from collection)
        $indexResponse = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.index'));
        $indexResponse->assertOk();
        $this->assertFalse($indexResponse->viewData('banks')->pluck('id')->contains($draftBank->id));

        // 4. Bank disappears from Question Generator landing (excluded from collection and not visible)
        $landingResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.question-generator.index'));
        $landingResponse->assertOk();
        $this->assertFalse($landingResponse->viewData('questionBanks')->pluck('id')->contains($draftBank->id));
        $landingResponse->assertDontSee('Draft Bank With Questions');

        // 5. Direct access to bank show or workspace yields 404 (soft-deleted model binding)
        $showResponse = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $draftBank->id));
        $showResponse->assertNotFound();

        $genResponse = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.generation.index', $draftBank->id));
        $genResponse->assertNotFound();
    }
}
