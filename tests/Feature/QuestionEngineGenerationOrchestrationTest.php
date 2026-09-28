<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\PromptComposition;
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
use App\Modules\QuestionEngine\Providers\FakeGenerationProvider;
use App\Modules\QuestionEngine\Providers\NullGenerationProvider;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\GeneratedQuestionMaterializer;
use App\Modules\QuestionEngine\Services\GeneratedQuestionNormalizer;
use App\Modules\QuestionEngine\Services\GeneratedQuestionQualityGate;
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

    protected AssessmentStandardRegistry $registry;

    protected ToeicBlueprintPlanner $toeicPlanner;

    protected ToeflBlueprintPlanner $toeflPlanner;

    protected GenerationBatchFactory $batchFactory;

    protected QuestionPromptComposerResolver $composerResolver;

    protected GeneratedQuestionNormalizer $normalizer;

    protected GeneratedQuestionQualityGate $qualityGate;

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
            'is_published' => true,
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
        $this->materializer = new GeneratedQuestionMaterializer;
        $this->fakeProvider = new FakeGenerationProvider;

        $this->orchestrator = new QuestionGenerationOrchestrator(
            batchFactory: $this->batchFactory,
            composerResolver: $this->composerResolver,
            normalizer: $this->normalizer,
            qualityGate: $this->qualityGate,
            materializer: $this->materializer,
            defaultProvider: $this->fakeProvider
        );
    }

    /**
     * TEST 1: GenerationBatchFactory creates persistent batch and items from ToeicGenerationPlan.
     */
    public function test_factory_creates_batch_and_items_from_toeic_plan(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 4,
            'seed' => 12345,
        ]);
        $plan = $this->toeicPlanner->plan($request);

        $batch = $this->batchFactory->createFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'requested_by' => $this->teacher->id,
            'idempotency_key' => 'toeic_test_idempotency_1',
        ]);

        $this->assertInstanceOf(QuestionGenerationBatch::class, $batch);
        $this->assertDatabaseHas('question_generation_batches', [
            'id' => $batch->id,
            'assessment_family' => AssessmentFamily::Toeic->value,
            'total_slots' => 4,
            'pending_slots' => 4,
            'status' => GenerationBatchStatus::Draft->value,
            'idempotency_key' => 'toeic_test_idempotency_1',
        ]);

        $this->assertCount(4, $batch->items);
        $this->assertEquals(1, $batch->items[0]->slot_sequence);
        $this->assertEquals(4, $batch->items[3]->slot_sequence);
        $this->assertEquals(5, $batch->items[0]->part_number);
        $this->assertEquals(GenerationItemStatus::Pending, $batch->items[0]->status);
    }

    /**
     * TEST 2: GenerationBatchFactory is idempotent when using same idempotency key.
     */
    public function test_factory_is_idempotent_with_same_key(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 2,
            'seed' => 1111,
        ]);
        $plan = $this->toeicPlanner->plan($request);

        $batch1 = $this->batchFactory->createFromPlan($plan, ['idempotency_key' => 'shared_key_123']);
        $batch2 = $this->batchFactory->createFromPlan($plan, ['idempotency_key' => 'shared_key_123']);

        $this->assertEquals($batch1->id, $batch2->id);
        $this->assertEquals(1, QuestionGenerationBatch::where('idempotency_key', 'shared_key_123')->count());
    }

    /**
     * TEST 3: GenerationBatchFactory creates batch and items from ToeflGenerationPlan.
     */
    public function test_factory_creates_batch_and_items_from_toefl_plan(): void
    {
        $request = ToeflBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_tasks' => [
                'read_in_daily_life' => 2,
                'write_an_email' => 1,
            ],
            'allow_practice_counts' => true,
            'seed' => 7777,
        ]);
        $plan = $this->toeflPlanner->plan($request);

        $batch = $this->batchFactory->createFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'requested_by' => $this->teacher->id,
        ]);

        $this->assertInstanceOf(QuestionGenerationBatch::class, $batch);
        $this->assertEquals(AssessmentFamily::ToeflIbt, $batch->assessment_family);
        $this->assertEquals(3, $batch->total_slots);
        $this->assertCount(3, $batch->items);
        $this->assertNull($batch->items[0]->part_number);
        $this->assertEquals('read_in_daily_life', $batch->items[0]->task_type);
        $this->assertEquals('write_an_email', $batch->items[2]->task_type);
    }

    /**
     * TEST 4: ToeicPromptComposer builds standard v1 prompt composition.
     */
    public function test_toeic_prompt_composer(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 2,
            'item_count' => 1,
            'seed' => 42,
        ]);
        $plan = $this->toeicPlanner->plan($request);

        $batch = $this->batchFactory->createFromPlan($plan);
        $item = $batch->items->first();

        $composer = new ToeicPromptComposer;
        $this->assertTrue($composer->supports(AssessmentFamily::Toeic));
        $this->assertFalse($composer->supports(AssessmentFamily::ToeflIbt));

        $composition = $composer->compose($item);
        $this->assertEquals('question_generation_v1', $composition->promptContractVersion);
        $this->assertStringContainsString('TOEIC', $composition->systemPrompt);
        $this->assertStringContainsString('Part 2', $composition->userPrompt);
        $this->assertEquals(3, $composition->structuralConstraints['choice_count']);
        $this->assertEquals(['A', 'B', 'C'], $composition->structuralConstraints['choice_labels']);
    }

    /**
     * TEST 5: ToeflPromptComposer builds standard v1 prompt composition.
     */
    public function test_toefl_prompt_composer(): void
    {
        $request = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'write_an_email',
            'task_count' => 1,
            'allow_practice_counts' => true,
            'seed' => 99,
        ]);
        $plan = $this->toeflPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan);
        $item = $batch->items->first();

        $composer = new ToeflPromptComposer;
        $this->assertTrue($composer->supports(AssessmentFamily::ToeflIbt));

        $composition = $composer->compose($item);
        $this->assertEquals('question_generation_v1', $composition->promptContractVersion);
        $this->assertStringContainsString('TOEFL', $composition->systemPrompt);
        $this->assertStringContainsString('write_an_email', $composition->userPrompt);
        $this->assertTrue($composition->structuralConstraints['is_constructed_response']);
    }

    /**
     * TEST 6: Composer resolver throws exception for unregistered assessment family.
     */
    public function test_composer_resolver_validation(): void
    {
        $resolver = new QuestionPromptComposerResolver;
        $this->assertInstanceOf(ToeicPromptComposer::class, $resolver->resolve(AssessmentFamily::Toeic));
        $this->assertInstanceOf(ToeflPromptComposer::class, $resolver->resolve(AssessmentFamily::ToeflIbt));

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve(AssessmentFamily::Ielts);
    }

    /**
     * TEST 7: NullGenerationProvider fails gracefully.
     */
    public function test_null_generation_provider(): void
    {
        $provider = new NullGenerationProvider;
        $this->assertEquals('null', $provider->getProviderName());

        $request = new GenerationProviderRequest(
            batchId: 'batch_1',
            itemId: 'item_1',
            slotSequence: 1,
            promptComposition: new PromptComposition('v1', 'sys', 'usr')
        );

        $response = $provider->generate($request);
        $this->assertFalse($response->isSuccess);
        $this->assertEquals(GenerationErrorCode::ProviderUnavailable, $response->errorCode);
    }

    /**
     * TEST 8: GeneratedQuestionNormalizer correctly parses markdown fenced JSON strings.
     */
    public function test_normalizer_parses_fenced_json_payloads(): void
    {
        $rawJson = <<<'JSON'
```json
{
    "schema_version": "generated_question_candidate_v1",
    "prompt": "What is the primary reason for the delay?",
    "choices": [
        {"label": "A", "content": "Adverse weather conditions", "is_correct": true, "explanation": "Stated clearly in paragraph 1"},
        {"label": "B", "content": "Mechanical failure", "is_correct": false},
        {"label": "C", "content": "Staff shortage", "is_correct": false},
        {"label": "D", "content": "Airport congestion", "is_correct": false}
    ],
    "correct_answer": "A",
    "explanation": "Paragraph 1 mentions heavy fog as the sole cause."
}
```
JSON;

        $candidate = $this->normalizer->normalize($rawJson);
        $this->assertInstanceOf(GeneratedQuestionCandidate::class, $candidate);
        $this->assertEquals('What is the primary reason for the delay?', $candidate->prompt);
        $this->assertCount(4, $candidate->choices);
        $this->assertEquals('A', $candidate->correctAnswer);
        $this->assertTrue($candidate->choices[0]['is_correct']);
        $this->assertFalse($candidate->choices[1]['is_correct']);
    }

    /**
     * TEST 9: QualityGate validates valid candidate and detects flaws.
     */
    public function test_quality_gate_checks(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan);
        $item = $batch->items->first();

        // Valid candidate
        $validCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Please submit the monthly sales reports _____ Friday at 5 PM.',
            choices: [
                ['label' => 'A', 'content' => 'before', 'is_correct' => true],
                ['label' => 'B', 'content' => 'prior', 'is_correct' => false],
                ['label' => 'C', 'content' => 'ahead', 'is_correct' => false],
                ['label' => 'D', 'content' => 'front', 'is_correct' => false],
            ],
            correctAnswer: 'A',
            explanation: '"Before" is the correct preposition.'
        );

        $res1 = $this->qualityGate->validate($validCandidate, $item);
        $this->assertTrue($res1->isValid);
        $this->assertEmpty($res1->violations);

        // Candidate with placeholder text
        $placeholderCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'TODO: Write question prompt here',
            choices: [
                ['label' => 'A', 'content' => 'Option A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Option B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option D', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $res2 = $this->qualityGate->validate($placeholderCandidate, $item);
        $this->assertFalse($res2->isValid);
        $this->assertEquals('PLACEHOLDER_DETECTED', $res2->violations[0]['code']);

        // Candidate with missing correct answer
        $noCorrectCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Valid question prompt text goes here without placeholder.',
            choices: [
                ['label' => 'A', 'content' => 'Option A', 'is_correct' => false],
                ['label' => 'B', 'content' => 'Option B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option D', 'is_correct' => false],
            ]
        );

        $res3 = $this->qualityGate->validate($noCorrectCandidate, $item);
        $this->assertFalse($res3->isValid);
        $this->assertEquals('INVALID_CORRECT_ANSWER_COUNT', $res3->violations[0]['code']);

        // Candidate with duplicate choice contents
        $duplicateCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Valid question prompt text goes here without placeholder.',
            choices: [
                ['label' => 'A', 'content' => 'Duplicate content', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Duplicate content', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Unique option C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Unique option D', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $res4 = $this->qualityGate->validate($duplicateCandidate, $item);
        $this->assertFalse($res4->isValid);
        $this->assertEquals('DUPLICATE_CHOICE_CONTENT', $res4->violations[0]['code']);
    }

    /**
     * TEST 10: GeneratedQuestionMaterializer creates draft Question with content_origin = generated.
     */
    public function test_materializer_creates_draft_question(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 1,
            'seed' => 888,
        ]);
        $plan = $this->toeicPlanner->plan($request);

        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'The merger will significantly _____ our presence in Southeast Asian markets.',
            choices: [
                ['label' => 'A', 'content' => 'expand', 'is_correct' => true],
                ['label' => 'B', 'content' => 'expansion', 'is_correct' => false],
                ['label' => 'C', 'content' => 'expansive', 'is_correct' => false],
                ['label' => 'D', 'content' => 'expansively', 'is_correct' => false],
            ],
            correctAnswer: 'A',
            explanation: '"Expand" is the correct verb form.'
        );

        $question = $this->materializer->materialize($item, $candidate, ['provider_name' => 'test-provider', 'latency_ms' => 45]);

        $this->assertInstanceOf(Question::class, $question);
        $this->assertEquals($this->questionBank->id, $question->question_bank_id);
        $this->assertEquals(SectionType::Reading, $question->section);
        $this->assertEquals(5, $question->part_number);
        $this->assertEquals(ContentOrigin::Generated, $question->content_origin);
        $this->assertEquals(AssessmentFamily::Toeic, $question->assessment_family);
        $this->assertEquals($batch->id, $question->generation_batch_id);
        $this->assertCount(4, $question->choices);

        $correctChoices = $question->choices->where('is_correct', true);
        $this->assertCount(1, $correctChoices);
        $this->assertEquals('A', $correctChoices->first()->label);
        $this->assertEquals('expand', $correctChoices->first()->content);

        // Check item update
        $item->refresh();
        $this->assertEquals(GenerationItemStatus::Materialized, $item->status);
        $this->assertEquals($question->id, $item->question_id);
    }

    /**
     * TEST 11: Materialization is idempotent (updating existing question rather than duplicating).
     */
    public function test_materializer_is_idempotent(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $candidate1 = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Initial prompt version.',
            choices: [
                ['label' => 'A', 'content' => 'Option 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Option 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option 4', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $q1 = $this->materializer->materialize($item, $candidate1);

        $candidate2 = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Updated prompt version.',
            choices: [
                ['label' => 'A', 'content' => 'Updated 1', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Updated 2', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Updated 3', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Updated 4', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $q2 = $this->materializer->materialize($item, $candidate2);

        $this->assertEquals($q1->id, $q2->id);
        $this->assertEquals('Updated prompt version.', $q2->fresh()->prompt);
        $this->assertEquals(1, Question::where('generation_batch_id', $batch->id)->count());
    }

    /**
     * TEST 12: End-to-end full batch execution successfully completes batch and items.
     */
    public function test_orchestrator_end_to_end_toeic_batch_processing(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 3,
            'seed' => 555,
        ]);
        $plan = $this->toeicPlanner->plan($request);

        $batch = $this->orchestrator->createBatchFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'requested_by' => $this->teacher->id,
        ]);

        $processedBatch = $this->orchestrator->processBatch($batch, $this->fakeProvider);

        $this->assertEquals(GenerationBatchStatus::Completed, $processedBatch->status);
        $this->assertNotNull($processedBatch->started_at);
        $this->assertNotNull($processedBatch->completed_at);
        $this->assertEquals(3, $processedBatch->total_slots);
        $this->assertEquals(3, $processedBatch->validated_slots);
        $this->assertEquals(0, $processedBatch->failed_slots);
        $this->assertEquals(0, $processedBatch->pending_slots);

        // Check that 3 questions are in database
        $this->assertEquals(3, Question::where('generation_batch_id', $batch->id)->count());
        foreach ($processedBatch->items as $item) {
            $this->assertEquals(GenerationItemStatus::Materialized, $item->status);
            $this->assertNotNull($item->question_id);
            $this->assertNotNull($item->normalized_output);
            $this->assertTrue($item->validation_result['is_valid']);
        }
    }

    /**
     * TEST 13: End-to-end full batch execution for TOEFL iBT items.
     */
    public function test_orchestrator_end_to_end_toefl_batch_processing(): void
    {
        $request = ToeflBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_tasks' => [
                'read_in_daily_life' => 1,
                'write_an_email' => 1,
            ],
            'allow_practice_counts' => true,
            'seed' => 333,
        ]);
        $plan = $this->toeflPlanner->plan($request);

        $batch = $this->orchestrator->createBatchFromPlan($plan, [
            'question_bank_id' => $this->questionBank->id,
            'requested_by' => $this->teacher->id,
        ]);

        $processedBatch = $this->orchestrator->processBatch($batch, $this->fakeProvider);

        $this->assertEquals(GenerationBatchStatus::Completed, $processedBatch->status);
        $this->assertEquals(2, $processedBatch->total_slots);
        $this->assertEquals(2, Question::where('generation_batch_id', $batch->id)->count());

        $readingQ = Question::where('generation_batch_id', $batch->id)->where('task_type', 'read_in_daily_life')->first();
        $this->assertNotNull($readingQ);
        $this->assertNotEmpty($readingQ->passage_text);
        $this->assertCount(4, $readingQ->choices);

        $writingQ = Question::where('generation_batch_id', $batch->id)->where('task_type', 'write_an_email')->first();
        $this->assertNotNull($writingQ);
        $this->assertNotEmpty($writingQ->prompt);
        $this->assertNotNull($writingQ->generation_metadata['rubric']);
    }

    /**
     * TEST 14: Provider errors trigger item failure and batch partial/failed state.
     */
    public function test_orchestrator_handles_provider_errors(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->orchestrator->createBatchFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $errorProvider = new FakeGenerationProvider;
        $errorProvider->triggerError(GenerationErrorCode::ProviderTimeout, 'Simulated timeout');

        $processedBatch = $this->orchestrator->processBatch($batch, $errorProvider);

        $this->assertEquals(GenerationBatchStatus::Failed, $processedBatch->status);
        $this->assertEquals(2, $processedBatch->failed_slots);
        $this->assertEquals(0, $processedBatch->validated_slots);

        $firstItem = $processedBatch->items->first();
        $this->assertEquals(GenerationItemStatus::Failed, $firstItem->status);
        $this->assertEquals(GenerationErrorCode::ProviderTimeout->value, $firstItem->last_error_code);
        $this->assertEquals('Simulated timeout', $firstItem->last_error_message);
    }

    /**
     * TEST 15: Quality gate failure triggers validation_failed status and partial batch completion.
     */
    public function test_orchestrator_handles_quality_gate_failure(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->orchestrator->createBatchFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $mixedProvider = new FakeGenerationProvider;
        // 1st candidate: valid
        $validCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Valid prompt for testing item 1',
            choices: [
                ['label' => 'A', 'content' => 'Option A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Option B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option D', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        // 2nd candidate: invalid (placeholder)
        $invalidCandidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'TODO: Incomplete question prompt',
            choices: [
                ['label' => 'A', 'content' => 'Option A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Option B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option D', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $mixedProvider->setPredefinedCandidates([$validCandidate, $invalidCandidate]);

        $processedBatch = $this->orchestrator->processBatch($batch, $mixedProvider);

        $this->assertEquals(GenerationBatchStatus::PartiallyCompleted, $processedBatch->status);
        $this->assertEquals(1, $processedBatch->validated_slots);
        $this->assertEquals(1, $processedBatch->failed_slots);

        $items = $processedBatch->items;
        $this->assertEquals(GenerationItemStatus::Materialized, $items[0]->status);
        $this->assertEquals(GenerationItemStatus::ValidationFailed, $items[1]->status);
        $this->assertEquals(GenerationErrorCode::QualityGateFailed->value, $items[1]->last_error_code);
    }

    /**
     * TEST 16: Retry failed items re-attempts failed slots and achieves completion.
     */
    public function test_orchestrator_retry_failed_items(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->orchestrator->createBatchFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        // Attempt 1: Provider error
        $failingProvider = new FakeGenerationProvider;
        $failingProvider->triggerError(GenerationErrorCode::ProviderUnavailable, 'Temporarily down');
        $this->orchestrator->processBatch($batch, $failingProvider);

        $this->assertEquals(GenerationBatchStatus::Failed, $batch->fresh()->status);
        $this->assertEquals(1, $batch->fresh()->items->first()->attempt_count);

        // Attempt 2: Retry with working provider
        $workingProvider = new FakeGenerationProvider;
        $retriedBatch = $this->orchestrator->retryFailedItems($batch->fresh(), $workingProvider);

        $this->assertEquals(GenerationBatchStatus::Completed, $retriedBatch->status);
        $this->assertEquals(2, $retriedBatch->items->first()->attempt_count);
        $this->assertEquals(GenerationItemStatus::Materialized, $retriedBatch->items->first()->status);
        $this->assertEquals(1, Question::where('generation_batch_id', $batch->id)->count());
    }

    /**
     * TEST 17: Cancel batch updates batch and pending items to cancelled.
     */
    public function test_orchestrator_cancel_batch(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 3]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->orchestrator->createBatchFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $cancelledBatch = $this->orchestrator->cancelBatch($batch, 'User requested abort');

        $this->assertEquals(GenerationBatchStatus::Cancelled, $cancelledBatch->status);
        $this->assertEquals('User requested abort', $cancelledBatch->metadata['cancellation_reason']);
        foreach ($cancelledBatch->items as $item) {
            $this->assertEquals(GenerationItemStatus::Cancelled, $item->status);
        }
    }

    /**
     * TEST 18: Enums helper functions verify correct transitions and classifications.
     */
    public function test_enum_helper_functions(): void
    {
        // GenerationBatchStatus
        $this->assertTrue(GenerationBatchStatus::Completed->isFinal());
        $this->assertTrue(GenerationBatchStatus::PartiallyCompleted->isFinal());
        $this->assertTrue(GenerationBatchStatus::Failed->isFinal());
        $this->assertTrue(GenerationBatchStatus::Cancelled->isFinal());
        $this->assertFalse(GenerationBatchStatus::Processing->isFinal());

        $this->assertTrue(GenerationBatchStatus::Draft->canCancel());
        $this->assertFalse(GenerationBatchStatus::Completed->canCancel());

        // GenerationItemStatus
        $this->assertTrue(GenerationItemStatus::Materialized->isFinal());
        $this->assertTrue(GenerationItemStatus::Cancelled->isFinal());
        $this->assertFalse(GenerationItemStatus::Processing->isFinal());

        $this->assertTrue(GenerationItemStatus::Pending->canAttempt());
        $this->assertTrue(GenerationItemStatus::ValidationFailed->canAttempt());
        $this->assertTrue(GenerationItemStatus::Failed->canAttempt());
        $this->assertFalse(GenerationItemStatus::Materialized->canAttempt());

        // GenerationErrorCode
        $this->assertTrue(GenerationErrorCode::ProviderUnavailable->isRetryable());
        $this->assertTrue(GenerationErrorCode::ProviderTimeout->isRetryable());
        $this->assertTrue(GenerationErrorCode::QualityGateFailed->isRetryable());
        $this->assertFalse(GenerationErrorCode::MaterializationFailed->isRetryable());
        $this->assertFalse(GenerationErrorCode::MaxAttemptsExceeded->isRetryable());
    }

    /**
     * TEST 19: GeneratedQuestionNormalizer fails on completely invalid JSON.
     */
    public function test_normalizer_fails_on_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize('This is not JSON at all!');
    }

    /**
     * TEST 20: Quality gate validates TOEIC Part 2 allows exactly 3 choices.
     */
    public function test_quality_gate_validates_part_2_three_choices(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 2, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->batchFactory->createFromPlan($plan);
        $item = $batch->items->first();

        // Valid 3-choice candidate
        $validP2 = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Where is the monthly financial review being held?',
            choices: [
                ['label' => 'A', 'content' => 'In Conference Room B.', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Yes, at 2:00 PM.', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Mr. Tanaka approved it.', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $result = $this->qualityGate->validate($validP2, $item);
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->violations);

        // Invalid 4-choice candidate for Part 2
        $invalidP2 = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Where is the monthly financial review being held?',
            choices: [
                ['label' => 'A', 'content' => 'In Conference Room B.', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Yes, at 2:00 PM.', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Mr. Tanaka approved it.', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Extra 4th choice.', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $resInvalid = $this->qualityGate->validate($invalidP2, $item);
        $this->assertFalse($resInvalid->isValid);
        $this->assertEquals('INVALID_CHOICE_COUNT', $resInvalid->violations[0]['code']);
    }

    /**
     * TEST 21: Materialized question strict draft governance.
     */
    public function test_materialized_question_strict_draft_governance(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($request);
        $batch = $this->orchestrator->createBatchFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $this->orchestrator->processBatch($batch, $this->fakeProvider);

        $question = Question::where('generation_batch_id', $batch->id)->first();
        $this->assertNotNull($question);
        $this->assertTrue($question->isGenerated());
        $this->assertEquals(ContentOrigin::Generated, $question->content_origin);
        $this->assertFalse((bool) $question->is_published, 'Generated questions MUST NOT be auto-published without Teacher/RM review.');
    }
}
