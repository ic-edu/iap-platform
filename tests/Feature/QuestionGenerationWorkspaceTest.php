<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Models\QuestionGenerationBatch;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use App\Modules\QuestionEngine\Services\GenerationBatchFactory;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuestionGenerationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $otherTeacher;

    protected User $student;

    protected User $repositoryManager;

    protected User $admin;

    protected User $superAdmin;

    protected QuestionBank $questionBank;

    protected AssessmentStandard $toeicStd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['status' => 'active']);
        $this->otherTeacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => 'active']);
        $this->student->assignRole('student');

        $this->repositoryManager = User::factory()->create(['status' => 'active']);
        $this->repositoryManager->assignRole('repository-manager');

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('super-admin');

        $this->questionBank = QuestionBank::create([
            'title' => 'TOEIC Reading Test Bank',
            'slug' => 'toeic-reading-test-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => false,
            'status' => 'draft',
            'test_type' => TestType::Toeic,
        ]);

        $this->toeicStd = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'ETS',
            'status' => StandardStatus::Active,
            'structure_definition' => ['sections' => ['listening', 'reading']],
        ]);

        config([
            'question_generation.provider' => 'groq',
            'question_generation.groq.api_key' => 'gsk-test-groq-key-12345',
        ]);
    }

    protected function validCandidatePayload(): array
    {
        return [
            'schema_version' => 'generated_question_candidate_v1',
            'prompt' => 'The quarterly revenue report will _____ at next Monday\'s board meeting.',
            'passage_text' => null,
            'audio_script' => null,
            'choices' => [
                ['label' => 'A', 'content' => 'present', 'is_correct' => false, 'explanation' => 'Active form incorrect.'],
                ['label' => 'B', 'content' => 'be presented', 'is_correct' => true, 'explanation' => 'Passive voice correct following modal will.'],
                ['label' => 'C', 'content' => 'presenting', 'is_correct' => false, 'explanation' => 'Participle incorrect.'],
                ['label' => 'D', 'content' => 'presentation', 'is_correct' => false, 'explanation' => 'Noun incorrect.'],
            ],
            'correct_answer' => 'B',
            'explanation' => 'Passive auxiliary "be presented" correctly follows modal "will".',
        ];
    }

    /**
     * Test A: Route authorization for unauthenticated and student users (GET workspace)
     */
    public function test_a_unauthenticated_and_student_users_denied_access(): void
    {
        // Unauthenticated -> redirect to login
        $res = $this->get(route('admin.question-banks.generation.index', $this->questionBank->id));
        $res->assertRedirect(route('login'));

        // Student / Candidate -> 403 Forbidden
        $res = $this->actingAs($this->student)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
        $res->assertStatus(403);
    }

    /**
     * Test B: Unauthenticated guest cannot submit POST generation or retry requests
     */
    public function test_b_guest_cannot_post_generation_or_retries(): void
    {
        // 1. Guest POST generate -> redirect to login
        $genRes = $this->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);
        $genRes->assertRedirect(route('login'));

        // Create dummy batch & item for retry routes
        $batch = QuestionGenerationBatch::create([
            'question_bank_id' => $this->questionBank->id,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStd->id,
            'standard_version' => '2026.1',
            'planner_type' => 'toeic_blueprint_planner',
            'planner_strategy_version' => '2026.1',
            'plan_fingerprint' => 'test-fingerprint',
            'status' => GenerationBatchStatus::Failed,
            'idempotency_key' => 'batch-test-guest',
            'prompt_contract_version' => 'question_generation_v1',
            'total_slots' => 1,
            'pending_slots' => 0,
            'processing_slots' => 0,
            'generated_slots' => 0,
            'validated_slots' => 0,
            'failed_slots' => 1,
        ]);

        $item = QuestionGenerationItem::create([
            'generation_batch_id' => $batch->id,
            'slot_sequence' => 1,
            'slot_fingerprint' => 'test-slot-guest',
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStd->id,
            'standard_version' => '2026.1',
            'section' => 'reading',
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'status' => GenerationItemStatus::Failed,
            'attempt_count' => 1,
            'last_error_code' => GenerationErrorCode::ProviderError->value,
        ]);

        // 2. Guest POST retry item -> redirect to login
        $retryItemRes = $this->post(route('admin.question-banks.generation.retry-item', [
            'questionBank' => $this->questionBank->id,
            'item' => $item->id,
        ]));
        $retryItemRes->assertRedirect(route('login'));

        // 3. Guest POST retry batch -> redirect to login
        $retryBatchRes = $this->post(route('admin.question-banks.generation.retry-batch', [
            'questionBank' => $this->questionBank->id,
            'batch' => $batch->id,
        ]));
        $retryBatchRes->assertRedirect(route('login'));
    }

    /**
     * Test C: Owner Teacher can view generation workspace
     */
    public function test_c_owner_teacher_can_view_workspace(): void
    {
        $res = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
        $res->assertStatus(200);
        $res->assertSee('Question Generation Workspace');
        $res->assertSee('TOEIC Reading Test Bank');
        $res->assertSee('Part 5 — Incomplete Sentences');
        $res->assertSee('Execute Question Generation');
    }

    /**
     * Test D: Non-owner Teacher cannot view or submit to workspace
     */
    public function test_d_non_owner_teacher_cannot_view_or_submit(): void
    {
        // View access denied
        $res = $this->actingAs($this->otherTeacher)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
        $res->assertStatus(403);

        // POST submission denied
        $res = $this->actingAs($this->otherTeacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);
        $res->assertStatus(403);
    }

    /**
     * Test E: Governance and admin roles cannot use Teacher generation workspace
     */
    public function test_e_governance_and_admin_roles_cannot_generate(): void
    {
        foreach ([$this->repositoryManager, $this->admin, $this->superAdmin] as $governanceUser) {
            $res = $this->actingAs($governanceUser)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
            $res->assertStatus(403);

            $res = $this->actingAs($governanceUser)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
                'quantity' => 1,
                'assessment' => 'toeic',
                'part' => 5,
            ]);
            $res->assertStatus(403);
        }
    }

    /**
     * Test F: Editable lifecycle enforcement for generation workspace
     */
    public function test_f_editable_lifecycle_enforcement(): void
    {
        // 1. Allowed editable statuses: draft, needs_revision, rejected
        foreach (['draft', 'needs_revision', 'rejected'] as $editableStatus) {
            $this->questionBank->update(['status' => $editableStatus, 'is_published' => false]);
            $res = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
            $res->assertStatus(200);
        }

        // 2. Locked statuses: pending_approval, approved, published, archived
        foreach (['pending_approval', 'submitted', 'approved', 'published', 'archived'] as $lockedStatus) {
            $this->questionBank->update(['status' => $lockedStatus, 'is_published' => ($lockedStatus === 'published')]);
            $res = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
            $res->assertStatus(403);

            $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
                'quantity' => 1,
                'assessment' => 'toeic',
                'part' => 5,
            ]);
            $res->assertStatus(403);
        }
    }

    /**
     * Test G: Form renders validated controls and read-only locked parameters
     */
    public function test_g_form_renders_validated_controls(): void
    {
        $res = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
        $res->assertStatus(200);
        $res->assertSee('TOEIC (ETS 2026.1)');
        $res->assertSee('Part 5 — Incomplete Sentences');
        $res->assertSee('General Workplace');
        $res->assertSee('name="quantity"', false);
        $res->assertSee('name="difficulty"', false);
        $res->assertSee('name="proficiency_target"', false);
        $res->assertSee('name="construct"', false);
    }

    /**
     * Test H: Unsupported Part or Assessment rejected by validation
     */
    public function test_h_unsupported_part_or_assessment_rejected(): void
    {
        // Part 6 is unsupported in v1
        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 6,
        ]);
        $res->assertSessionHasErrors(['part']);

        // TOEFL is unsupported in TOEIC bank
        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toefl',
            'part' => 5,
        ]);
        $res->assertSessionHasErrors(['assessment']);
    }

    /**
     * Test I: Quantity validation bounds (1..5)
     */
    public function test_i_quantity_validation(): void
    {
        // Quantity 0 rejected
        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 0,
            'assessment' => 'toeic',
            'part' => 5,
        ]);
        $res->assertSessionHasErrors(['quantity']);

        // Quantity 6 rejected
        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 6,
            'assessment' => 'toeic',
            'part' => 5,
        ]);
        $res->assertSessionHasErrors(['quantity']);
    }

    /**
     * Test J: Custom difficulty, proficiency, and construct values accepted by planner
     */
    public function test_j_custom_difficulty_proficiency_and_construct_accepted_by_planner(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'id' => 'chatcmpl-custom-options',
                'model' => 'openai/gpt-oss-20b',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        $difficulties = ['easy', 'medium', 'hard'];
        $proficiencies = ['b1_low', 'b1_standard', 'b2_low', 'b2_standard'];
        $constructs = ['grammar', 'vocabulary'];

        foreach ($difficulties as $diff) {
            foreach ($proficiencies as $prof) {
                foreach ($constructs as $const) {
                    $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
                        'quantity' => 1,
                        'assessment' => 'toeic',
                        'part' => 5,
                        'difficulty' => $diff,
                        'proficiency_target' => $prof,
                        'construct' => $const,
                    ]);
                    $res->assertRedirect();
                    $res->assertSessionHas('success');
                }
            }
        }
    }

    /**
     * Test K: Successful generation batch creates batch and items with completed status
     */
    public function test_k_successful_batch_generation_creates_batch_and_items(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'id' => 'chatcmpl-batch-k',
                'model' => 'openai/gpt-oss-20b',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
                'usage' => ['prompt_tokens' => 150, 'completion_tokens' => 80, 'total_tokens' => 230],
            ], 200),
        ]);

        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 2,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $this->assertNotNull($batch);
        $this->assertSame(GenerationBatchStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->total_slots);
        $this->assertSame(2, $batch->validated_slots);
        $this->assertCount(2, $batch->items);
    }

    /**
     * Test L: Generated questions materialize as draft in target Question Bank
     */
    public function test_l_generated_questions_materialize_as_draft_in_target_bank(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $question = Question::where('question_bank_id', $this->questionBank->id)->first();
        $this->assertNotNull($question);
        $this->assertSame(ContentOrigin::Generated, $question->content_origin);
        $this->assertSame('The quarterly revenue report will _____ at next Monday\'s board meeting.', $question->prompt);
        $correctChoice = $question->choices()->where('is_correct', true)->first();
        $this->assertNotNull($correctChoice);
        $this->assertSame('be presented', $correctChoice->content);
    }

    /**
     * Test M: Failed quality gate candidate does not materialize as question
     */
    public function test_m_failed_quality_gate_candidate_does_not_materialize(): void
    {
        $invalidCandidate = $this->validCandidatePayload();
        $invalidCandidate['prompt'] = 'TODO: Placeholder prompt failing quality gate';

        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($invalidCandidate)]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $this->assertSame(0, Question::where('question_bank_id', $this->questionBank->id)->count());
        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $this->assertSame(GenerationBatchStatus::Failed, $batch->status);
        $item = $batch->items->first();
        $this->assertSame(GenerationItemStatus::ValidationFailed, $item->status);
        $this->assertNull($item->question_id);
    }

    /**
     * Test N: Generated Question is visible on Question Bank show page
     */
    public function test_n_generated_question_visible_in_question_bank(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $showRes = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $this->questionBank->id));
        $showRes->assertStatus(200);
        $showRes->assertSee('The quarterly revenue report will _____ at next Monday\'s board meeting.');
    }

    /**
     * Test O: No auto-publish and no approval bypass for generated questions
     */
    public function test_o_no_auto_publish_or_approval_bypass(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $this->questionBank->refresh();
        $this->assertSame('draft', $this->questionBank->status);
        $this->assertFalse((bool) $this->questionBank->is_published);

        // Teacher cannot publish Question Bank directly (must undergo Repository Manager review)
        $publishRes = $this->actingAs($this->teacher)->post(route('admin.question-banks.publish', $this->questionBank->id));
        $publishRes->assertStatus(403);
    }

    /**
     * Test P: Failed provider result displayed safely in workspace
     */
    public function test_p_failed_provider_result_displayed_safely(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded.'],
            ], 429),
        ]);

        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('error');

        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $this->assertSame(GenerationBatchStatus::Failed, $batch->status);

        // View workspace with active batch
        $viewRes = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', [
            'questionBank' => $this->questionBank->id,
            'batch_id' => $batch->id,
        ]));
        $viewRes->assertStatus(200);
        $viewRes->assertSee('provider_error');
        $viewRes->assertSee('Retryable Transient');
    }

    /**
     * Test Q: Retryable failure allows manual retry from workspace
     */
    public function test_q_retryable_failure_allows_manual_retry(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limit']], 429)
                ->push([
                    'id' => 'chatcmpl-retry-success',
                    'model' => 'openai/gpt-oss-20b',
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                    ],
                ], 200),
        ]);

        // 1. Initial attempt fails with 429
        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $item = $batch->items->first();
        $this->assertSame(GenerationItemStatus::Failed, $item->status);
        $this->assertTrue($item->isEligibleForRetry(3));

        // 2. Teacher executes manual retry on the item
        $retryRes = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-item', [
            'questionBank' => $this->questionBank->id,
            'item' => $item->id,
        ]));

        $retryRes->assertRedirect();
        $retryRes->assertSessionHas('success');

        $item->refresh();
        $this->assertSame(GenerationItemStatus::Materialized, $item->status);
        $this->assertSame(2, $item->attempt_count);
        $this->assertNotNull($item->question_id);
    }

    /**
     * Test R: Non-retryable failure cannot be retried
     */
    public function test_r_non_retryable_failure_cannot_be_retried(): void
    {
        $invalidCandidate = $this->validCandidatePayload();
        $invalidCandidate['prompt'] = 'TODO: Quality Gate Failure Placeholder';

        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($invalidCandidate)]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $item = $batch->items->first();
        $this->assertSame(GenerationItemStatus::ValidationFailed, $item->status);
        $this->assertFalse($item->isEligibleForRetry(3));

        // Attempt manual retry on non-retryable item
        $retryRes = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-item', [
            'questionBank' => $this->questionBank->id,
            'item' => $item->id,
        ]));

        $retryRes->assertSessionHas('error');
        $item->refresh();
        $this->assertSame(1, $item->attempt_count);
    }

    /**
     * Test S: Max-attempt exhaustion prevents further retry
     */
    public function test_s_max_attempt_exhaustion_prevents_retry(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response(['error' => ['message' => '500 Server Error']], 500),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $item = $batch->items->first();

        // Retry 1 (attempt 2)
        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-item', [
            'questionBank' => $this->questionBank->id,
            'item' => $item->id,
        ]));

        // Retry 2 (attempt 3 - reaches max)
        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-item', [
            'questionBank' => $this->questionBank->id,
            'item' => $item->id,
        ]));

        $item->refresh();
        $this->assertSame(3, $item->attempt_count);
        $this->assertFalse($item->isEligibleForRetry(3));

        // Retry 3 (blocked)
        $blockedRes = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-item', [
            'questionBank' => $this->questionBank->id,
            'item' => $item->id,
        ]));
        $blockedRes->assertSessionHas('error');
        $this->assertSame(3, $item->fresh()->attempt_count);
    }

    /**
     * Test T: Batch retry triggers only retryable items
     */
    public function test_t_batch_retry_triggers_only_retryable_items(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response(['error' => ['message' => '500 Server Error']], 500),
        ]);

        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 2,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $batch = QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->first();
        $this->assertCount(2, $batch->items);

        // Batch retry
        $retryRes = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-batch', [
            'questionBank' => $this->questionBank->id,
            'batch' => $batch->id,
        ]));
        $retryRes->assertRedirect();

        $batch->refresh();
        foreach ($batch->items as $item) {
            $this->assertSame(2, $item->attempt_count);
        }
    }

    /**
     * Test U: Generation history visibility is scoped to the specific Question Bank
     */
    public function test_u_generation_history_scoped_to_bank(): void
    {
        $secondBank = QuestionBank::create([
            'title' => 'Second Teacher Bank',
            'slug' => 'second-teacher-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => false,
            'status' => 'draft',
            'test_type' => TestType::Toeic,
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        // Generate in Bank 1
        $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        // View Bank 2 -> should show empty history
        $resBank2 = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $secondBank->id));
        $resBank2->assertStatus(200);
        $resBank2->assertSee('No previous generation runs recorded');
    }

    /**
     * Test V: Idempotency contract prevents duplicate batch & item creation
     */
    public function test_v_idempotency_contract_prevents_duplicate_batch_and_items(): void
    {
        $planner = app(ToeicBlueprintPlanner::class);
        $factory = app(GenerationBatchFactory::class);

        $blueprintReq = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 1,
            'content_mode' => 'general',
            'domain' => 'general_workplace',
        ]);
        $plan = $planner->plan($blueprintReq);

        $idempotencyKey = 'deterministic-test-idempotency-key-12345';

        // Execution 1: creates batch
        $batch1 = $factory->createFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'requested_by' => $this->teacher->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertSame(1, QuestionGenerationBatch::where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(1, QuestionGenerationItem::where('generation_batch_id', $batch1->id)->count());

        // Execution 2: same idempotency key -> returns existing batch without duplicate rows
        $batch2 = $factory->createFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'requested_by' => $this->teacher->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertSame($batch1->id, $batch2->id);
        $this->assertSame(1, QuestionGenerationBatch::where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(1, QuestionGenerationItem::where('generation_batch_id', $batch1->id)->count());
    }

    /**
     * Test W: Secret leakage prevention in rendered HTML and flash responses
     */
    public function test_w_secret_leakage_prevention_in_ui(): void
    {
        $sentinelSecret = 'gsk-SUPER-SECRET-GROQ-KEY-UI-AUDIT-999';
        config(['question_generation.groq.api_key' => $sentinelSecret]);

        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'error' => ['message' => 'Unauthorized: '.$sentinelSecret],
            ], 401),
        ]);

        $res = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
            'quantity' => 1,
            'assessment' => 'toeic',
            'part' => 5,
        ]);

        $workspaceRes = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $this->questionBank->id));
        $content = $workspaceRes->getContent();

        $this->assertStringNotContainsString($sentinelSecret, $content);
        $this->assertStringNotContainsString('SUPER-SECRET-GROQ', $content);
        $this->assertStringNotContainsString('Bearer ', $content);
    }

    /**
     * Test X: Question Bank entry point button is rendered on show view when editable
     */
    public function test_x_entry_point_rendered_on_editable_bank(): void
    {
        $res = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $this->questionBank->id));
        $res->assertStatus(200);
        $res->assertSee('Generate Questions');
        $res->assertSee(route('admin.question-banks.generation.index', $this->questionBank->id));

        // When locked, button is not rendered
        $this->questionBank->update(['status' => 'approved']);
        $lockedRes = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', $this->questionBank->id));
        $lockedRes->assertStatus(200);
        $lockedRes->assertDontSee(route('admin.question-banks.generation.index', $this->questionBank->id));
    }

    /**
     * Test Y: Direct POST authorization matrix independently of button visibility
     */
    public function test_y_direct_post_authorization_matrix(): void
    {
        // 1. Create a failed batch & item for direct POST retry testing
        $batch = QuestionGenerationBatch::create([
            'question_bank_id' => $this->questionBank->id,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStd->id,
            'standard_version' => '2026.1',
            'planner_type' => 'toeic_blueprint_planner',
            'planner_strategy_version' => '2026.1',
            'plan_fingerprint' => 'test-fingerprint-matrix',
            'status' => GenerationBatchStatus::Failed,
            'idempotency_key' => 'batch-test-matrix',
            'prompt_contract_version' => 'question_generation_v1',
            'total_slots' => 1,
            'pending_slots' => 0,
            'processing_slots' => 0,
            'generated_slots' => 0,
            'validated_slots' => 0,
            'failed_slots' => 1,
        ]);

        $item = QuestionGenerationItem::create([
            'generation_batch_id' => $batch->id,
            'slot_sequence' => 1,
            'slot_fingerprint' => 'test-slot-matrix',
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStd->id,
            'standard_version' => '2026.1',
            'section' => 'reading',
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'status' => GenerationItemStatus::Failed,
            'attempt_count' => 1,
            'last_error_code' => GenerationErrorCode::ProviderError->value,
        ]);

        $unauthorizedUsers = [
            'Teacher non-owner' => $this->otherTeacher,
            'Repository Manager' => $this->repositoryManager,
            'Admin' => $this->admin,
            'Super Admin' => $this->superAdmin,
        ];

        // A. Direct POST for unauthorized roles on editable bank -> 403
        foreach ($unauthorizedUsers as $roleName => $user) {
            $postStore = $this->actingAs($user)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
                'quantity' => 1,
                'assessment' => 'toeic',
                'part' => 5,
            ]);
            $postStore->assertStatus(403);

            $postRetryItem = $this->actingAs($user)->post(route('admin.question-banks.generation.retry-item', [
                'questionBank' => $this->questionBank->id,
                'item' => $item->id,
            ]));
            $postRetryItem->assertStatus(403);

            $postRetryBatch = $this->actingAs($user)->post(route('admin.question-banks.generation.retry-batch', [
                'questionBank' => $this->questionBank->id,
                'batch' => $batch->id,
            ]));
            $postRetryBatch->assertStatus(403);
        }

        // B. Direct POST for Teacher owner on locked / published / archived banks -> 403
        foreach (['pending_approval', 'submitted', 'approved', 'published', 'archived'] as $lockedStatus) {
            $this->questionBank->update(['status' => $lockedStatus, 'is_published' => ($lockedStatus === 'published')]);

            $postStore = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.store', $this->questionBank->id), [
                'quantity' => 1,
                'assessment' => 'toeic',
                'part' => 5,
            ]);
            $postStore->assertStatus(403);

            $postRetryItem = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-item', [
                'questionBank' => $this->questionBank->id,
                'item' => $item->id,
            ]));
            $postRetryItem->assertStatus(403);

            $postRetryBatch = $this->actingAs($this->teacher)->post(route('admin.question-banks.generation.retry-batch', [
                'questionBank' => $this->questionBank->id,
                'batch' => $batch->id,
            ]));
            $postRetryBatch->assertStatus(403);
        }
    }
}
