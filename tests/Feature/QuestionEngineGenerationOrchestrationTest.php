<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\ToeflBlueprintRequest;
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
use App\Modules\QuestionEngine\Providers\FakeGenerationProvider;
use App\Modules\QuestionEngine\Providers\NullGenerationProvider;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\GeneratedQuestionMaterializer;
use App\Modules\QuestionEngine\Services\GeneratedQuestionNormalizer;
use App\Modules\QuestionEngine\Services\GeneratedQuestionQualityGate;
use App\Modules\QuestionEngine\Services\GeneratedQuestionTypeResolver;
use App\Modules\QuestionEngine\Services\GenerationBatchFactory;
use App\Modules\QuestionEngine\Services\QuestionGenerationOrchestrator;
use App\Modules\QuestionEngine\Services\QuestionPromptComposerResolver;
use App\Modules\QuestionEngine\Services\ToeflBlueprintPlanner;
use App\Modules\QuestionEngine\Services\ToeflIbt2026StandardDefinition;
use App\Modules\QuestionEngine\Services\ToeflPromptComposer;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use App\Modules\QuestionEngine\Services\ToeicPromptComposer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QuestionEngineGenerationOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected QuestionBank $secondQuestionBank;

    protected AssessmentStandardRegistry $registry;

    protected ToeicBlueprintPlanner $toeicPlanner;

    protected ToeflBlueprintPlanner $toeflPlanner;

    protected GenerationBatchFactory $batchFactory;

    protected QuestionPromptComposerResolver $composerResolver;

    protected GeneratedQuestionNormalizer $normalizer;

    protected GeneratedQuestionQualityGate $qualityGate;

    protected GeneratedQuestionTypeResolver $typeResolver;

    protected GeneratedQuestionMaterializer $materializer;

    protected FakeGenerationProvider $fakeProvider;

    protected QuestionGenerationOrchestrator $orchestrator;

    protected AssessmentStandard $toeicStd;

    protected AssessmentStandard $toeflStd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'Orchestration Test Bank',
            'slug' => 'orchestration-test-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => false,
            'status' => 'draft',
        ]);

        $this->secondQuestionBank = QuestionBank::create([
            'title' => 'Second Orchestration Bank',
            'slug' => 'second-orchestration-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => false,
            'status' => 'draft',
        ]);

        $this->registry = new AssessmentStandardRegistry;
        $this->toeicPlanner = new ToeicBlueprintPlanner($this->registry);
        $this->toeflPlanner = new ToeflBlueprintPlanner($this->registry);

        // Register Standards
        $this->toeflStd = $this->registry->registerStandard(ToeflIbt2026StandardDefinition::getDefinition());
        $this->registry->activateStandard($this->toeflStd);

        $this->toeicStd = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'ETS',
            'status' => StandardStatus::Active,
            'structure_definition' => ['sections' => ['listening', 'reading']],
        ]);
        $this->registry->activateStandard($this->toeicStd);

        $this->batchFactory = new GenerationBatchFactory;
        $this->composerResolver = new QuestionPromptComposerResolver;
        $this->normalizer = new GeneratedQuestionNormalizer;
        $this->qualityGate = new GeneratedQuestionQualityGate;
        $this->typeResolver = new GeneratedQuestionTypeResolver;
        $this->materializer = new GeneratedQuestionMaterializer($this->typeResolver);
        $this->fakeProvider = new FakeGenerationProvider;

        $this->orchestrator = new QuestionGenerationOrchestrator(
            batchFactory: $this->batchFactory,
            composerResolver: $this->composerResolver,
            normalizer: $this->normalizer,
            qualityGate: $this->qualityGate,
            materializer: $this->materializer,
            defaultProvider: new NullGenerationProvider
        );
    }

    /**
     * TEST A & B: Candidate missing family and standard version is populated from trusted slot context.
     */
    public function test_a_and_b_candidate_missing_family_and_version_populated_from_slot(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        // Raw output without family or standard_version
        $rawOutput = [
            'prompt' => 'Please review the draft agreement before Friday.',
            'choices' => [
                ['label' => 'A', 'content' => 'before', 'is_correct' => true],
                ['label' => 'B', 'content' => 'prior', 'is_correct' => false],
                ['label' => 'C', 'content' => 'ahead', 'is_correct' => false],
                ['label' => 'D', 'content' => 'front', 'is_correct' => false],
            ],
            'correct_answer' => 'A',
            'explanation' => 'Valid preposition.',
        ];

        $candidate = $this->normalizer->normalize($rawOutput, $item);
        $this->assertEquals(AssessmentFamily::Toeic, $candidate->assessmentFamily);
        $this->assertEquals('2026.1', $candidate->standardVersion);
        $this->assertEquals('reading', $candidate->section);
        $this->assertEquals(5, $candidate->partNumber);
    }

    /**
     * TEST C & D: Candidate missing TOEIC part / TOEFL task is populated from trusted slot.
     */
    public function test_c_and_d_missing_part_and_task_populated_from_slot(): void
    {
        // TOEIC Part 3 (audio groups of 3)
        $reqToeic = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 3, 'item_count' => 3]);
        $planToeic = $this->toeicPlanner->plan($reqToeic);
        $batchToeic = $this->batchFactory->createFromPlan($planToeic, ['question_bank_id' => $this->questionBank->id]);
        $itemToeic = $batchToeic->items->first();

        $cToeic = $this->normalizer->normalize(['prompt' => 'Where are the speakers?'], $itemToeic);
        $this->assertEquals(3, $cToeic->partNumber);
        $this->assertEquals('listening', $cToeic->section);

        // TOEFL
        $reqToefl = ToeflBlueprintRequest::fromArray(['mode' => 'task', 'task_type' => 'write_an_email', 'task_count' => 1, 'allow_practice_counts' => true]);
        $planToefl = $this->toeflPlanner->plan($reqToefl);
        $batchToefl = $this->batchFactory->createFromPlan($planToefl, ['question_bank_id' => $this->questionBank->id]);
        $itemToefl = $batchToefl->items->first();

        $cToefl = $this->normalizer->normalize(['prompt' => 'Write an email to Professor Higgins.'], $itemToefl);
        $this->assertEquals('write_an_email', $cToefl->taskType);
        $this->assertEquals(AssessmentFamily::ToeflIbt, $cToefl->assessmentFamily);
        $this->assertEquals('writing', $cToefl->section);
    }

    /**
     * TEST E through H: Explicit provider structural mismatches still fail quality gate.
     */
    public function test_e_through_h_explicit_provider_mismatches_fail_quality_gate(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        // E: Explicit Family mismatch
        $cFamily = $this->normalizer->normalize([
            'prompt' => 'Valid prompt text goes here.',
            'assessment_family' => 'toefl_ibt',
            'choices' => [
                ['label' => 'A', 'content' => 'Opt 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Opt 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Opt 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Opt 4', 'is_correct' => false],
            ],
            'correct_answer' => 'A',
        ], $item);
        $rFamily = $this->qualityGate->validate($cFamily, $item);
        $this->assertFalse($rFamily->isValid);
        $this->assertEquals('FAMILY_MISMATCH', $rFamily->violations[0]['code']);

        // F: Explicit Standard mismatch
        $cStd = $this->normalizer->normalize([
            'prompt' => 'Valid prompt text goes here.',
            'standard_version' => '1999.0',
            'choices' => [
                ['label' => 'A', 'content' => 'Opt 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Opt 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Opt 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Opt 4', 'is_correct' => false],
            ],
            'correct_answer' => 'A',
        ], $item);
        $rStd = $this->qualityGate->validate($cStd, $item);
        $this->assertFalse($rStd->isValid);
        $this->assertEquals('STANDARD_MISMATCH', $rStd->violations[0]['code']);

        // G: Explicit Part mismatch
        $cPart = $this->normalizer->normalize([
            'prompt' => 'Valid prompt text goes here.',
            'part_number' => 1,
            'choices' => [
                ['label' => 'A', 'content' => 'Opt 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Opt 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Opt 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Opt 4', 'is_correct' => false],
            ],
            'correct_answer' => 'A',
        ], $item);
        $rPart = $this->qualityGate->validate($cPart, $item);
        $this->assertFalse($rPart->isValid);
        $this->assertEquals('PART_MISMATCH', $rPart->violations[0]['code']);

        // H: Explicit Task mismatch for TOEFL
        $reqToefl = ToeflBlueprintRequest::fromArray(['mode' => 'task', 'task_type' => 'write_an_email', 'task_count' => 1, 'allow_practice_counts' => true]);
        $planToefl = $this->toeflPlanner->plan($reqToefl);
        $batchToefl = $this->batchFactory->createFromPlan($planToefl, ['question_bank_id' => $this->questionBank->id]);
        $itemToefl = $batchToefl->items->first();

        $cTask = $this->normalizer->normalize([
            'prompt' => 'Write prompt text here.',
            'task_type' => 'read_in_daily_life',
        ], $itemToefl);
        $rTask = $this->qualityGate->validate($cTask, $itemToefl);
        $this->assertFalse($rTask->isValid);
        $this->assertEquals('TASK_MISMATCH', $rTask->violations[0]['code']);
    }

    /**
     * TEST I & J: Final normalized candidates have complete required identity.
     */
    public function test_i_and_j_final_normalized_candidates_have_complete_identity(): void
    {
        // TOEIC
        $reqToeic = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $planToeic = $this->toeicPlanner->plan($reqToeic);
        $batchToeic = $this->batchFactory->createFromPlan($planToeic, ['question_bank_id' => $this->questionBank->id]);
        $itemToeic = $batchToeic->items->first();

        $normToeic = $this->normalizer->normalize(['prompt' => 'Toeic prompt sentence.'], $itemToeic);
        $this->assertNotNull($normToeic->assessmentFamily);
        $this->assertNotNull($normToeic->standardVersion);
        $this->assertNotNull($normToeic->section);
        $this->assertNotNull($normToeic->partNumber);
        $this->assertNotNull($normToeic->construct);
        $this->assertNotNull($normToeic->proficiencyTarget);
        $this->assertNotNull($normToeic->difficulty);

        // TOEFL
        $reqToefl = ToeflBlueprintRequest::fromArray(['mode' => 'task', 'task_type' => 'write_an_email', 'task_count' => 1, 'allow_practice_counts' => true]);
        $planToefl = $this->toeflPlanner->plan($reqToefl);
        $batchToefl = $this->batchFactory->createFromPlan($planToefl, ['question_bank_id' => $this->questionBank->id]);
        $itemToefl = $batchToefl->items->first();

        $normToefl = $this->normalizer->normalize(['prompt' => 'Toefl prompt sentence.'], $itemToefl);
        $this->assertNotNull($normToefl->assessmentFamily);
        $this->assertNotNull($normToefl->standardVersion);
        $this->assertNotNull($normToefl->section);
        $this->assertNotNull($normToefl->taskType);
        $this->assertNotNull($normToefl->claim);
        $this->assertNotNull($normToefl->skill);
        $this->assertNotNull($normToefl->proficiencyTarget);
        $this->assertNotNull($normToefl->difficulty);
    }

    /**
     * TEST K through N: Editable QuestionBank statuses (draft, needs_revision, revision_requested, rejected) accepted.
     */
    public function test_k_through_n_editable_question_bank_statuses_accepted(): void
    {
        $editableStatuses = ['draft', 'needs_revision', 'revision_requested', 'rejected'];
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);

        foreach ($editableStatuses as $st) {
            $bank = QuestionBank::create([
                'title' => "Editable Bank ({$st})",
                'slug' => "editable-bank-{$st}-".uniqid(),
                'created_by' => $this->teacher->id,
                'status' => $st,
                'is_published' => false,
            ]);

            $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $bank->id]);
            $this->assertInstanceOf(QuestionGenerationBatch::class, $batch);
            $this->assertEquals($bank->id, $batch->question_bank_id);
        }
    }

    /**
     * TEST O through S: Locked QuestionBank statuses rejected.
     */
    public function test_o_through_s_locked_question_bank_statuses_rejected(): void
    {
        $lockedStatuses = [
            'submitted',
            'pending_approval',
            'approved',
            'published',
            'archived',
            'pending_restore_approval',
            'restore_requested',
            'pending_archive_approval',
            'archive_requested',
            'unknown_locked_status',
        ];

        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);

        foreach ($lockedStatuses as $st) {
            $bank = QuestionBank::create([
                'title' => "Locked Bank ({$st})",
                'slug' => "locked-bank-{$st}-".uniqid(),
                'created_by' => $this->teacher->id,
                'status' => $st,
                'is_published' => $st === 'published',
            ]);

            try {
                $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $bank->id]);
                $this->fail("Locked status [{$st}] should have been rejected.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('locked or non-editable status', $e->getMessage());
            }
        }
    }

    /**
     * TEST T through V & Y: Sequential and race-safe idempotent batch creation prevents duplicate batches and items.
     */
    public function test_t_through_v_and_y_race_safe_idempotent_creation(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 3, 'seed' => 404]);
        $plan = $this->toeicPlanner->plan($request);

        // Caller 1
        $batch1 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        // Caller 2 (same identity)
        $batch2 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $this->assertSame($batch1->id, $batch2->id);
        $this->assertEquals(1, QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->count());
        $this->assertEquals(3, QuestionGenerationItem::where('generation_batch_id', $batch1->id)->count());
    }

    /**
     * TEST W & X: force_new_run and new_run_nonce create intentional separate batches.
     */
    public function test_w_and_x_intentional_separate_runs(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2, 'seed' => 404]);
        $plan = $this->toeicPlanner->plan($request);

        $batch1 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $batch2 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $batch3 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'new_run_nonce' => 'custom_run_nonce_999']);

        $this->assertNotEquals($batch1->id, $batch2->id);
        $this->assertNotEquals($batch1->id, $batch3->id);
        $this->assertNotEquals($batch2->id, $batch3->id);
        $this->assertEquals(3, QuestionGenerationBatch::where('question_bank_id', $this->questionBank->id)->count());
    }

    /**
     * TEST: ToeicPromptComposer and ToeflPromptComposer build standard v1 prompt compositions.
     */
    public function test_prompt_composers_build_valid_compositions(): void
    {
        $reqToeic = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 2, 'item_count' => 1]);
        $planToeic = $this->toeicPlanner->plan($reqToeic);
        $batchToeic = $this->batchFactory->createFromPlan($planToeic, ['question_bank_id' => $this->questionBank->id]);
        $itemToeic = $batchToeic->items->first();

        $compToeic = new ToeicPromptComposer;
        $resToeic = $compToeic->compose($itemToeic);
        $this->assertEquals('question_generation_v1', $resToeic->promptContractVersion);
        $this->assertEquals(3, $resToeic->structuralConstraints['choice_count']);

        $reqToefl = ToeflBlueprintRequest::fromArray(['mode' => 'task', 'task_type' => 'write_an_email', 'task_count' => 1, 'allow_practice_counts' => true]);
        $planToefl = $this->toeflPlanner->plan($reqToefl);
        $batchToefl = $this->batchFactory->createFromPlan($planToefl, ['question_bank_id' => $this->questionBank->id]);
        $itemToefl = $batchToefl->items->first();

        $compToefl = new ToeflPromptComposer;
        $resToefl = $compToefl->compose($itemToefl);
        $this->assertEquals('question_generation_v1', $resToefl->promptContractVersion);
        $this->assertTrue($resToefl->structuralConstraints['is_constructed_response']);
    }

    /**
     * TEST: Full orchestrator batch execution with explicit fake provider.
     */
    public function test_full_orchestrator_batch_lifecycle_and_retries(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        // Attempt 1: Provider error
        $failProv = new FakeGenerationProvider;
        $failProv->triggerError(GenerationErrorCode::ProviderTimeout, 'Timeout');
        $this->orchestrator->processBatch($batch, $failProv);

        $this->assertEquals(GenerationBatchStatus::Failed, $batch->fresh()->status);
        $this->assertEquals(2, $batch->fresh()->failed_slots);

        // Attempt 2: Retry with working provider
        $workProv = new FakeGenerationProvider;
        $retried = $this->orchestrator->retryFailedItems($batch->fresh(), $workProv);
        $this->assertEquals(GenerationBatchStatus::Completed, $retried->status);
        $this->assertEquals(2, $retried->validated_slots);
        $this->assertEquals(0, $retried->failed_slots);
        $this->assertEquals(2, Question::where('generation_batch_id', $batch->id)->count());
    }

    /**
     * TEST: Batch cancellation updates batch and items to cancelled.
     */
    public function test_batch_cancellation(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $cancelled = $this->orchestrator->cancelBatch($batch, 'User requested cancellation');
        $this->assertEquals(GenerationBatchStatus::Cancelled, $cancelled->status);
        foreach ($cancelled->items as $item) {
            $this->assertEquals(GenerationItemStatus::Cancelled, $item->status);
        }
    }

    /**
     * TEST AF: Question Bank governance and unpublished draft status are preserved upon full orchestration.
     */
    public function test_af_question_bank_governance_preserved(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $this->orchestrator->processBatch($batch, $this->fakeProvider);

        $questions = Question::where('generation_batch_id', $batch->id)->get();
        $this->assertCount(2, $questions);
        foreach ($questions as $q) {
            $this->assertEquals(ContentOrigin::Generated, $q->content_origin);
            $this->assertFalse((bool) $q->is_published);
            $this->assertEquals($this->questionBank->id, $q->question_bank_id);
        }
    }
}
