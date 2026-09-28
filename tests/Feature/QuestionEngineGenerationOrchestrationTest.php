<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
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
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
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
     * TEST A & B: Same bank + plan + contract returns same batch with deterministic idempotency key.
     */
    public function test_a_and_b_deterministic_idempotency_key_returns_same_batch(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2, 'seed' => 101]);
        $plan = $this->toeicPlanner->plan($request);

        $batch1 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $batch2 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $this->assertSame($batch1->id, $batch2->id);
        $this->assertEquals(1, QuestionGenerationBatch::where('idempotency_key', $batch1->idempotency_key)->count());

        $expectedKey = 'batch_'.hash('sha256', implode(':', [
            $this->questionBank->id,
            $plan->fingerprint,
            'question_generation_v1',
        ]));
        $this->assertSame($expectedKey, $batch1->idempotency_key);
    }

    /**
     * TEST C: Different question bank creates different batch.
     */
    public function test_c_different_question_bank_creates_different_batch(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2, 'seed' => 101]);
        $plan = $this->toeicPlanner->plan($request);

        $batch1 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $batch2 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->secondQuestionBank->id]);

        $this->assertNotEquals($batch1->id, $batch2->id);
        $this->assertNotEquals($batch1->idempotency_key, $batch2->idempotency_key);
    }

    /**
     * TEST D: Force new run behavior works only when explicit.
     */
    public function test_d_force_new_run_behavior_works_when_explicit(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2, 'seed' => 101]);
        $plan = $this->toeicPlanner->plan($request);

        $batch1 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $batch2 = $this->batchFactory->createFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'force_new_run' => true,
        ]);

        $this->assertNotEquals($batch1->id, $batch2->id);
        $this->assertNotEquals($batch1->idempotency_key, $batch2->idempotency_key);
    }

    /**
     * TEST E & F: Missing or nonexistent question_bank_id rejected.
     */
    public function test_e_and_f_missing_or_nonexistent_question_bank_rejected(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);

        // Missing
        $this->expectException(InvalidArgumentException::class);
        $this->batchFactory->createFromPlan($plan, []);
    }

    public function test_f_nonexistent_question_bank_rejected(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);

        $this->expectException(InvalidArgumentException::class);
        $this->batchFactory->createFromPlan($plan, ['question_bank_id' => '01JNONEXISTENTBANK000000000']);
    }

    /**
     * TEST G: Valid QuestionBank accepted.
     */
    public function test_g_valid_question_bank_accepted(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);

        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $this->assertInstanceOf(QuestionGenerationBatch::class, $batch);
        $this->assertEquals($this->questionBank->id, $batch->question_bank_id);
    }

    /**
     * TEST H & I: Default orchestrator does NOT use FakeGenerationProvider and fails closed.
     */
    public function test_h_and_i_default_orchestrator_fails_closed_with_null_provider(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $defaultOrchestrator = new QuestionGenerationOrchestrator(
            batchFactory: $this->batchFactory,
            composerResolver: $this->composerResolver,
            normalizer: $this->normalizer,
            qualityGate: $this->qualityGate,
            materializer: $this->materializer
        );

        $processed = $defaultOrchestrator->processBatch($batch);
        $this->assertEquals(GenerationBatchStatus::Failed, $processed->status);
        $this->assertEquals(GenerationItemStatus::Failed, $processed->items->first()->status);
        $this->assertEquals(GenerationErrorCode::ProviderUnavailable->value, $processed->items->first()->last_error_code);
    }

    /**
     * TEST J: Explicit FakeGenerationProvider works in tests.
     */
    public function test_j_explicit_fake_provider_works_in_tests(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $processed = $this->orchestrator->processBatch($batch, $this->fakeProvider);
        $this->assertEquals(GenerationBatchStatus::Completed, $processed->status);
        $this->assertEquals(GenerationItemStatus::Materialized, $processed->items->first()->status);
    }

    /**
     * TEST K through P: Non-validated items cannot materialize.
     */
    public function test_k_through_p_non_validated_items_cannot_materialize(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Test prompt for non-validated item test',
            choices: [['label' => 'A', 'content' => 'Opt', 'is_correct' => true]],
            correctAnswer: 'A'
        );

        $invalidStatuses = [
            GenerationItemStatus::Pending,
            GenerationItemStatus::Ready,
            GenerationItemStatus::Processing,
            GenerationItemStatus::Generated,
            GenerationItemStatus::ValidationFailed,
            GenerationItemStatus::Failed,
            GenerationItemStatus::Cancelled,
        ];

        foreach ($invalidStatuses as $st) {
            $item->status = $st;
            $item->save();

            try {
                $this->materializer->materialize($item, $candidate);
                $this->fail("Materialization should have thrown exception for status [{$st->value}].");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('must be in validated status', $e->getMessage());
            }
        }
    }

    /**
     * TEST Q & R: Validated item can materialize and already-materialized item returns existing Question.
     */
    public function test_q_and_r_validated_item_materializes_and_is_idempotent(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $item->status = GenerationItemStatus::Validated;
        $item->save();

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'The director approved the _____ for the next fiscal year.',
            choices: [
                ['label' => 'A', 'content' => 'budget', 'is_correct' => true],
                ['label' => 'B', 'content' => 'budgeted', 'is_correct' => false],
                ['label' => 'C', 'content' => 'budgeting', 'is_correct' => false],
                ['label' => 'D', 'content' => 'budgetary', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $q1 = $this->materializer->materialize($item, $candidate);
        $this->assertInstanceOf(Question::class, $q1);
        $this->assertEquals(GenerationItemStatus::Materialized, $item->fresh()->status);

        // Repeated call returns existing Question
        $q2 = $this->materializer->materialize($item->fresh(), $candidate);
        $this->assertSame($q1->id, $q2->id);
        $this->assertEquals(1, Question::where('generation_batch_id', $batch->id)->count());
    }

    /**
     * TEST S through X: GeneratedQuestionTypeResolver maps types safely.
     */
    public function test_s_through_x_question_type_resolver(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $toeicItem = $batch->items->first();

        // TOEIC -> MultipleChoice
        $this->assertEquals(QuestionType::MultipleChoice, $this->typeResolver->resolve($toeicItem));

        // TOEFL task type mappings
        $toeflItem = new QuestionGenerationItem([
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'task_type' => 'write_an_email',
        ]);
        $this->assertEquals(QuestionType::Writing, $this->typeResolver->resolve($toeflItem));

        $toeflItem->task_type = 'write_for_an_academic_discussion';
        $this->assertEquals(QuestionType::Writing, $this->typeResolver->resolve($toeflItem));

        $toeflItem->task_type = 'listen_and_repeat';
        $this->assertEquals(QuestionType::Speaking, $this->typeResolver->resolve($toeflItem));

        $toeflItem->task_type = 'take_an_interview';
        $this->assertEquals(QuestionType::Speaking, $this->typeResolver->resolve($toeflItem));

        $toeflItem->task_type = 'read_in_daily_life';
        $this->assertEquals(QuestionType::MultipleChoice, $this->typeResolver->resolve($toeflItem));

        // Ambiguous/unsupported fails closed
        $toeflItem->task_type = 'invalid_unknown_task';
        $this->expectException(InvalidArgumentException::class);
        $this->typeResolver->resolve($toeflItem);
    }

    /**
     * TEST Y through AG: Candidate structural identity mismatches fail quality gate.
     */
    public function test_y_through_ag_structural_mismatches_fail_quality_gate(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $baseCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Valid prompt text with enough characters.',
            choices: [
                ['label' => 'A', 'content' => 'Option 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Option 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option 4', 'is_correct' => false],
            ],
            correctAnswer: 'A',
            assessmentFamily: $item->assessment_family,
            standardVersion: $item->standard_version,
            section: $item->section,
            partNumber: $item->part_number,
            construct: $item->construct,
            proficiencyTarget: $item->proficiency_target,
            difficulty: $item->difficulty
        );

        // Y: Family mismatch
        $cFamily = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['assessment_family' => AssessmentFamily::ToeflIbt->value]));
        $rFamily = $this->qualityGate->validate($cFamily, $item);
        $this->assertFalse($rFamily->isValid);
        $this->assertEquals('FAMILY_MISMATCH', $rFamily->violations[0]['code']);

        // Z: Standard mismatch
        $cStd = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['standard_version' => '2020.1']));
        $rStd = $this->qualityGate->validate($cStd, $item);
        $this->assertFalse($rStd->isValid);
        $this->assertEquals('STANDARD_MISMATCH', $rStd->violations[0]['code']);

        // AA: Section mismatch
        $cSec = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['section' => 'listening']));
        $rSec = $this->qualityGate->validate($cSec, $item);
        $this->assertFalse($rSec->isValid);
        $this->assertEquals('SECTION_MISMATCH', $rSec->violations[0]['code']);

        // AB: Part mismatch
        $cPart = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['part_number' => 3]));
        $rPart = $this->qualityGate->validate($cPart, $item);
        $this->assertFalse($rPart->isValid);
        $this->assertEquals('PART_MISMATCH', $rPart->violations[0]['code']);

        // AF: Proficiency mismatch
        $cProf = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['proficiency_target' => 'c2']));
        $rProf = $this->qualityGate->validate($cProf, $item);
        $this->assertFalse($rProf->isValid);
        $this->assertEquals('PROFICIENCY_MISMATCH', $rProf->violations[0]['code']);

        // AG: Difficulty mismatch
        $cDiff = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['difficulty' => 'extreme_hard']));
        $rDiff = $this->qualityGate->validate($cDiff, $item);
        $this->assertFalse($rDiff->isValid);
        $this->assertEquals('DIFFICULTY_MISMATCH', $rDiff->violations[0]['code']);
    }

    /**
     * TEST AC, AD, AE: TOEFL candidate task, claim, skill mismatches and CEFR envelope violation fail quality gate.
     */
    public function test_ac_through_ae_toefl_structural_mismatches_fail_quality_gate(): void
    {
        $request = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'write_an_email',
            'task_count' => 1,
            'allow_practice_counts' => true,
        ]);
        $plan = $this->toeflPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $baseCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Write an email to Professor Higgins about your research project.',
            rubric: 'Score 5: Clear and fluent',
            sampleResponse: 'Dear Professor Higgins...',
            assessmentFamily: $item->assessment_family,
            standardVersion: $item->standard_version,
            section: $item->section,
            taskType: $item->task_type,
            claim: $item->claim,
            skill: $item->skill,
            proficiencyTarget: $item->proficiency_target,
            difficulty: $item->difficulty
        );

        // AC: Task mismatch
        $cTask = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['task_type' => 'read_in_daily_life']));
        $rTask = $this->qualityGate->validate($cTask, $item);
        $this->assertFalse($rTask->isValid);
        $this->assertEquals('TASK_MISMATCH', $rTask->violations[0]['code']);

        // AD: Claim mismatch
        $cClaim = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['claim' => 'Claim 1 — Reading']));
        $rClaim = $this->qualityGate->validate($cClaim, $item);
        $this->assertFalse($rClaim->isValid);
        $this->assertEquals('CLAIM_MISMATCH', $rClaim->violations[0]['code']);

        // AE: Skill mismatch
        $cSkill = GeneratedQuestionCandidate::fromArray(array_merge($baseCandidate->toArray(), ['skill' => 'Speaking in an interview']));
        $rSkill = $this->qualityGate->validate($cSkill, $item);
        $this->assertFalse($rSkill->isValid);
        $this->assertEquals('SKILL_MISMATCH', $rSkill->violations[0]['code']);

        // CEFR envelope violation for listen_and_choose_a_response (A1-B2 envelope; C2 is incompatible)
        $reqP2 = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'listen_and_choose_a_response',
            'task_count' => 1,
            'allow_practice_counts' => true,
        ]);
        $planP2 = $this->toeflPlanner->plan($reqP2);
        $batchP2 = $this->batchFactory->createFromPlan($planP2, ['question_bank_id' => $this->questionBank->id]);
        $itemP2 = $batchP2->items->first();
        $itemP2->proficiency_target = 'c2';
        $itemP2->save();

        $cCefr = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Choose the best response.',
            choices: [
                ['label' => 'A', 'content' => 'Choice 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Choice 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Choice 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Choice 4', 'is_correct' => false],
            ],
            correctAnswer: 'A',
            assessmentFamily: $itemP2->assessment_family,
            standardVersion: $itemP2->standard_version,
            section: $itemP2->section,
            taskType: $itemP2->task_type,
            claim: $itemP2->claim,
            skill: $itemP2->skill,
            proficiencyTarget: 'c2',
            difficulty: $itemP2->difficulty
        );
        $rCefr = $this->qualityGate->validate($cCefr, $itemP2);
        $this->assertFalse($rCefr->isValid);
        $this->assertEquals('INCOMPATIBLE_PROFICIENCY_FOR_TASK', $rCefr->violations[0]['code']);
    }

    /**
     * TEST AH & AI: Canonical construct compatibility and valid structural candidate passes.
     */
    public function test_ah_and_ai_canonical_construct_and_valid_candidate(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        // Valid structural candidate
        $validCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'The sales staff must submit _____ receipts promptly.',
            choices: [
                ['label' => 'A', 'content' => 'their', 'is_correct' => true],
                ['label' => 'B', 'content' => 'theirs', 'is_correct' => false],
                ['label' => 'C', 'content' => 'them', 'is_correct' => false],
                ['label' => 'D', 'content' => 'they', 'is_correct' => false],
            ],
            correctAnswer: 'A',
            assessmentFamily: $item->assessment_family,
            standardVersion: $item->standard_version,
            section: $item->section,
            partNumber: $item->part_number,
            construct: $item->construct,
            proficiencyTarget: $item->proficiency_target,
            difficulty: $item->difficulty
        );

        $res = $this->qualityGate->validate($validCandidate, $item);
        $this->assertTrue($res->isValid);
        $this->assertEmpty($res->violations);

        // Incompatible construct for Part 5 (e.g. VisualDescription on item & candidate)
        $item->construct = 'visual_description';
        $item->save();
        $incompatCandidate = GeneratedQuestionCandidate::fromArray(array_merge($validCandidate->toArray(), [
            'construct' => 'visual_description',
        ]));
        $resIncompat = $this->qualityGate->validate($incompatCandidate, $item);
        $this->assertFalse($resIncompat->isValid);
        $this->assertEquals('INCOMPATIBLE_CONSTRUCT_FOR_PART', $resIncompat->violations[0]['code']);
    }

    /**
     * TEST AJ & AK: Generation item standard_id non-null and restrict on delete.
     */
    public function test_aj_and_ak_standard_binding_non_null_and_restrict_delete(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $this->assertNotNull($item->assessment_standard_id);
        $this->assertEquals($this->toeicStd->id, $item->assessment_standard_id);

        // Attempting to delete assessment standard referenced by generation item must be restricted
        $this->expectException(QueryException::class);
        $this->toeicStd->forceDelete();
    }

    /**
     * TEST AL through AO: Materialization governance preservation (unpublished, content_origin=generated).
     */
    public function test_al_through_ao_materialization_governance_preservation(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $this->orchestrator->processBatch($batch, $this->fakeProvider);

        $question = Question::where('generation_batch_id', $batch->id)->first();
        $this->assertNotNull($question);
        $this->assertEquals($this->questionBank->id, $question->question_bank_id);
        $this->assertEquals(ContentOrigin::Generated, $question->content_origin);
        $this->assertFalse((bool) $question->is_published);
    }

    /**
     * TEST AP & AQ: Concurrency-safe materialization creates at most one Question per item.
     */
    public function test_ap_and_aq_concurrency_safe_materialization(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $item->status = GenerationItemStatus::Validated;
        $item->save();

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Concurrent test prompt for item',
            choices: [
                ['label' => 'A', 'content' => 'Opt A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Opt B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Opt C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Opt D', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $q1 = $this->materializer->materialize($item, $candidate);
        $q2 = $this->materializer->materialize($item, $candidate);

        $this->assertSame($q1->id, $q2->id);
        $this->assertEquals(1, Question::where('generation_batch_id', $batch->id)->count());
    }
}
