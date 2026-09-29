<?php

namespace Tests\Feature;

use App\Integrations\QuestionGeneration\GroqQuestionGenerationProvider;
use App\Integrations\QuestionGeneration\OpenAIQuestionGenerationProvider;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\PromptComposition;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use App\Modules\QuestionEngine\Providers\NullGenerationProvider;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\GeneratedQuestionMaterializer;
use App\Modules\QuestionEngine\Services\GeneratedQuestionNormalizer;
use App\Modules\QuestionEngine\Services\GeneratedQuestionQualityGate;
use App\Modules\QuestionEngine\Services\GeneratedQuestionTypeResolver;
use App\Modules\QuestionEngine\Services\GenerationBatchFactory;
use App\Modules\QuestionEngine\Services\QuestionGenerationOrchestrator;
use App\Modules\QuestionEngine\Services\QuestionPromptComposerResolver;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use App\Modules\QuestionEngine\Services\ToeicPromptComposer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuestionEngineGenerationReliabilityAndTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected AssessmentStandardRegistry $registry;

    protected ToeicBlueprintPlanner $toeicPlanner;

    protected GenerationBatchFactory $batchFactory;

    protected QuestionPromptComposerResolver $composerResolver;

    protected GeneratedQuestionNormalizer $normalizer;

    protected GeneratedQuestionQualityGate $qualityGate;

    protected GeneratedQuestionTypeResolver $typeResolver;

    protected GeneratedQuestionMaterializer $materializer;

    protected AssessmentStandard $toeicStd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'Sprint 7D Reliability Test Bank',
            'slug' => 'sprint-7d-reliability-test-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => false,
            'status' => 'draft',
        ]);

        $this->registry = new AssessmentStandardRegistry;
        $this->toeicPlanner = new ToeicBlueprintPlanner($this->registry);

        $this->toeicStd = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'ETS',
            'status' => StandardStatus::Active,
            'structure_definition' => ['sections' => ['listening', 'reading']],
        ]);

        $this->batchFactory = new GenerationBatchFactory($this->registry);
        $this->composerResolver = new QuestionPromptComposerResolver([new ToeicPromptComposer]);
        $this->normalizer = new GeneratedQuestionNormalizer;
        $this->qualityGate = new GeneratedQuestionQualityGate;
        $this->typeResolver = new GeneratedQuestionTypeResolver;
        $this->materializer = new GeneratedQuestionMaterializer($this->typeResolver);
    }

    protected function validCandidatePayload(): array
    {
        return [
            'schema_version' => 'generated_question_candidate_v1',
            'prompt' => 'The executive committee will _____ the financial report during tomorrow\'s session.',
            'passage_text' => null,
            'audio_script' => null,
            'choices' => [
                ['label' => 'A', 'content' => 'review', 'is_correct' => true, 'explanation' => 'Base verb follows modal will.'],
                ['label' => 'B', 'content' => 'reviews', 'is_correct' => false, 'explanation' => 'Third person singular incorrect.'],
                ['label' => 'C', 'content' => 'reviewing', 'is_correct' => false, 'explanation' => 'Participle incorrect.'],
                ['label' => 'D', 'content' => 'reviewed', 'is_correct' => false, 'explanation' => 'Past tense incorrect.'],
            ],
            'correct_answer' => 'A',
            'explanation' => 'Modal auxiliary "will" requires the base verb "review".',
        ];
    }

    /**
     * Test A: Successful provider attempt telemetry
     */
    public function test_a_successful_provider_attempt_telemetry_persisted(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-success',
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode($this->validCandidatePayload()),
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 180, 'completion_tokens' => 95, 'total_tokens' => 275],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key', model: 'gpt-4o-mini');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Materialized, $processed->status);
        $this->assertSame(1, $processed->attempt_count);

        $telemetry = $processed->getOperationalTelemetry();
        $this->assertTrue($telemetry['is_success']);
        $this->assertSame('openai', $telemetry['provider_name']);
        $this->assertSame('gpt-4o-mini', $telemetry['model_name']);
        $this->assertSame(275, $telemetry['token_usage']['total_tokens']);
        $this->assertFalse($telemetry['retryable']);
        $this->assertNotEmpty($telemetry['prompt_hash']);
        $this->assertCount(1, $telemetry['attempts']);
        $this->assertTrue($telemetry['attempts'][0]['is_success']);
        $this->assertSame('provider_generation', $telemetry['attempts'][0]['stage']);
    }

    /**
     * Test B: Retryable 429 failure persisted correctly with operational metadata
     */
    public function test_b_retryable_429_failure_persisted_correctly(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded. Please retry in 2 seconds.'],
            ], 429),
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-key', model: 'openai/gpt-oss-20b');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(1, $processed->attempt_count);
        $this->assertSame(GenerationErrorCode::ProviderError->value, $processed->last_error_code);

        $telemetry = $processed->getOperationalTelemetry();
        $this->assertFalse($telemetry['is_success']);
        $this->assertSame('groq', $telemetry['provider_name']);
        $this->assertSame('openai/gpt-oss-20b', $telemetry['model_name']);
        $this->assertTrue($telemetry['retryable']);
        $this->assertSame(429, $telemetry['status_code']);
        $this->assertNotEmpty($telemetry['prompt_hash']);
        $this->assertCount(1, $telemetry['attempts']);
        $this->assertTrue($processed->isEligibleForRetry(3));
    }

    /**
     * Test C: Retryable 500 / timeout failure
     */
    public function test_c_retryable_500_and_timeout_failure(): void
    {
        $callCount = 0;
        Http::fake([
            'https://api.openai.com/v1/*' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return Http::response(['error' => ['message' => 'Internal server error']], 500);
                }

                throw new ConnectionException('Operation timed out after 30s');
            },
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        // Attempt 1: 500 Server error
        $processed1 = $orchestrator->processItem($item);
        $this->assertSame(GenerationItemStatus::Failed, $processed1->status);
        $this->assertTrue($processed1->isEligibleForRetry(3));
        $this->assertTrue($processed1->raw_output['retryable']);
        $this->assertSame(500, $processed1->raw_output['status_code']);

        // Attempt 2: Connection timeout
        $processed2 = $orchestrator->processItem($processed1);
        $this->assertSame(GenerationItemStatus::Failed, $processed2->status);
        $this->assertSame(2, $processed2->attempt_count);
        $this->assertTrue($processed2->isEligibleForRetry(3));
        $this->assertTrue($processed2->raw_output['retryable']);
        $this->assertSame(GenerationErrorCode::ProviderTimeout->value, $processed2->last_error_code);
        $this->assertCount(2, $processed2->raw_output['attempts']);
    }

    /**
     * Test D: Non-retryable HTTP 400 Bad Request
     */
    public function test_d_non_retryable_http_400_bad_request(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response(['error' => ['message' => 'HTTP 400 Bad Request']], 400),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(GenerationErrorCode::ProviderError->value, $processed->last_error_code);
        $this->assertFalse($processed->raw_output['retryable']);
        $this->assertFalse($processed->isEligibleForRetry(3));
    }

    /**
     * Test E: Non-retryable HTTP 401 Unauthorized
     */
    public function test_e_non_retryable_http_401_unauthorized(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response(['error' => ['message' => 'HTTP 401 Unauthorized']], 401),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(GenerationErrorCode::ProviderUnavailable->value, $processed->last_error_code);
        $this->assertFalse($processed->raw_output['retryable']);
        $this->assertFalse($processed->isEligibleForRetry(3));
    }

    /**
     * Test F: Non-retryable HTTP 403 Forbidden
     */
    public function test_f_non_retryable_http_403_forbidden(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response(['error' => ['message' => 'HTTP 403 Forbidden']], 403),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(GenerationErrorCode::ProviderUnavailable->value, $processed->last_error_code);
        $this->assertFalse($processed->raw_output['retryable']);
        $this->assertFalse($processed->isEligibleForRetry(3));
    }

    /**
     * Test G: schema_validation_failed is non-retryable
     */
    public function test_g_schema_validation_failed_is_non_retryable(): void
    {
        Http::fake();

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $promptComp = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'sys',
            userPrompt: 'user',
            schemaDefinition: [], // Empty schema
            targetMetadata: ['assessment_family' => 'toeic', 'section' => 'reading', 'part_number' => 5]
        );

        $genReq = new GenerationProviderRequest(
            batchId: 'batch-1',
            itemId: 'item-1',
            slotSequence: 1,
            promptComposition: $promptComp
        );

        $response = $provider->generate($genReq);

        $this->assertFalse($response->isSuccess);
        $this->assertSame(GenerationErrorCode::SchemaValidationFailed, $response->errorCode);
        $this->assertFalse($response->metadata['retryable']);
        Http::assertNothingSent();
    }

    /**
     * Test H: malformed provider response is non-retryable and not retried
     */
    public function test_h_malformed_provider_response_is_non_retryable(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => '{broken-invalid-json']],
                ],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(GenerationErrorCode::InvalidProviderResponse->value, $processed->last_error_code);
        $this->assertFalse($processed->raw_output['retryable']);
        $this->assertFalse($processed->isEligibleForRetry(3));

        $telemetry = $processed->getOperationalTelemetry();
        $this->assertFalse($telemetry['is_success']);
        $this->assertSame(GenerationErrorCode::InvalidProviderResponse->value, $telemetry['error_code']);
        $this->assertFalse($telemetry['retryable']);

        // retryFailedItems must NOT invoke provider again
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([], 200),
        ]);
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(1, $item->fresh()->attempt_count);
        Http::assertNothingSent();
    }

    /**
     * Test H.1: Normalization failure is non-retryable and not retried
     */
    public function test_h1_normalization_failure_is_non_retryable(): void
    {
        // Provider returns valid JSON but invalid schema missing required choices array
        $invalidCandidate = [
            'schema_version' => 'generated_question_candidate_v1',
            'prompt' => 'The executive committee will _____ the report.',
            'choices' => 'invalid-not-an-array',
        ];

        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        $failingNormalizer = new class extends GeneratedQuestionNormalizer
        {
            public function normalize($raw, $item = null, ?array $trustedMetadata = null): GeneratedQuestionCandidate
            {
                throw new \InvalidArgumentException('Candidate normalization parsing failed.');
            }
        };

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $failingNormalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(GenerationErrorCode::NormalizationFailed->value, $processed->last_error_code);
        $this->assertFalse($processed->raw_output['retryable']);
        $this->assertFalse($processed->isEligibleForRetry(3));

        $telemetry = $processed->getOperationalTelemetry();
        $this->assertFalse($telemetry['is_success']);
        $this->assertSame(GenerationErrorCode::NormalizationFailed->value, $telemetry['error_code']);
        $this->assertCount(2, $telemetry['attempts']);
        $this->assertSame('provider_generation', $telemetry['attempts'][0]['stage']);
        $this->assertSame('normalization', $telemetry['attempts'][1]['stage']);

        // retryFailedItems must NOT invoke provider again
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([], 200),
        ]);
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(1, $item->fresh()->attempt_count);
        Http::assertNothingSent();
    }

    /**
     * Test H.2: Materialization failure is non-retryable and not retried
     */
    public function test_h2_materialization_failure_is_non_retryable(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        // Create mock materializer that throws RuntimeException
        $failingMaterializer = new class extends GeneratedQuestionMaterializer
        {
            public function __construct()
            {
                parent::__construct(null);
            }

            public function materialize(QuestionGenerationItem $item, GeneratedQuestionCandidate $candidate, array $providerMetadata = []): Question
            {
                throw new \RuntimeException('Database constraint violation during materialization');
            }
        };

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $failingMaterializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processed->status);
        $this->assertSame(GenerationErrorCode::MaterializationFailed->value, $processed->last_error_code);
        $this->assertFalse($processed->raw_output['retryable']);
        $this->assertFalse($processed->isEligibleForRetry(3));

        $telemetry = $processed->getOperationalTelemetry();
        $this->assertFalse($telemetry['is_success']);
        $this->assertSame(GenerationErrorCode::MaterializationFailed->value, $telemetry['error_code']);
        $this->assertCount(2, $telemetry['attempts']);
        $this->assertSame('provider_generation', $telemetry['attempts'][0]['stage']);
        $this->assertSame('materialization', $telemetry['attempts'][1]['stage']);

        // retryFailedItems must NOT invoke provider again
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([], 200),
        ]);
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(1, $item->fresh()->attempt_count);
        Http::assertNothingSent();
    }

    /**
     * Test I: Quality gate failure is not automatically retried
     */
    public function test_i_quality_gate_failure_is_not_automatically_retried(): void
    {
        $invalidCandidate = $this->validCandidatePayload();
        $invalidCandidate['prompt'] = 'TODO: Placeholder prompt failing quality gate';

        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($invalidCandidate)]],
                ],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $processed = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::ValidationFailed, $processed->status);
        $this->assertFalse($processed->isEligibleForRetry(3));

        // Attempting retryFailedItems must NOT retry ValidationFailed items
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                ],
            ], 200),
        ]);

        $retriedBatch = $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(1, $processed->fresh()->attempt_count);
        $this->assertSame(GenerationItemStatus::ValidationFailed, $processed->fresh()->status);
    }

    /**
     * Test J: maxAttempts budget is strictly enforced
     */
    public function test_j_max_attempts_budget_is_strictly_enforced(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response(['error' => ['message' => '500 Server Error']], 500),
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        // Attempt 1 (initial)
        $orchestrator->processItem($item);
        $this->assertSame(1, $item->fresh()->attempt_count);
        $this->assertTrue($item->fresh()->isEligibleForRetry(3));

        // Attempt 2 (retry 1)
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(2, $item->fresh()->attempt_count);
        $this->assertTrue($item->fresh()->isEligibleForRetry(3));

        // Attempt 3 (retry 2 - reaches max 3)
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(3, $item->fresh()->attempt_count);
        $this->assertFalse($item->fresh()->isEligibleForRetry(3));

        // Attempt 4 (should be blocked by budget)
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(3, $item->fresh()->attempt_count);
    }

    /**
     * Test K & M: Retryable failure can succeed on a later attempt, with accurate attempt_count
     */
    public function test_k_and_m_retryable_failure_succeeds_on_later_attempt(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limit']], 429)
                ->push([
                    'id' => 'chatcmpl-retry-success',
                    'model' => 'gpt-4o-mini',
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                    ],
                    'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 100, 'total_tokens' => 300],
                ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        // 1. Initial process fails on 429
        $batch = $orchestrator->processBatch($batch, $provider);
        $item = $batch->items->first();
        $this->assertSame(GenerationItemStatus::Failed, $item->status);
        $this->assertSame(1, $item->attempt_count);
        $this->assertSame(GenerationBatchStatus::Failed, $batch->status);
        $this->assertTrue($item->isEligibleForRetry(3));

        // Verify Attempt 1 Telemetry
        $attempt1Telemetry = $item->getOperationalTelemetry();
        $this->assertFalse($attempt1Telemetry['is_success']);
        $this->assertSame('openai', $attempt1Telemetry['provider_name']);
        $this->assertSame('gpt-4o-mini', $attempt1Telemetry['model_name']);
        $this->assertSame(429, $attempt1Telemetry['status_code']);
        $this->assertTrue($attempt1Telemetry['retryable']);
        $this->assertCount(1, $attempt1Telemetry['attempts']);

        // 2. Retry succeeds on attempt 2
        $batch = $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $item = $batch->items->first();
        $this->assertSame(GenerationItemStatus::Materialized, $item->status);
        $this->assertSame(2, $item->attempt_count);
        $this->assertNotNull($item->question_id);

        // Verify batch counters after recovery
        $this->assertSame(GenerationBatchStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->total_slots);
        $this->assertSame(1, $batch->validated_slots);
        $this->assertSame(0, $batch->failed_slots);
        $this->assertSame(0, $batch->pending_slots);

        // Verify attempt history: Attempt 1 preserved, Attempt 2 appended
        $recoveredTelemetry = $item->getOperationalTelemetry();
        $this->assertTrue($recoveredTelemetry['is_success']);
        $this->assertNull($recoveredTelemetry['error_code']);
        $this->assertFalse($recoveredTelemetry['retryable']);
        $this->assertSame(300, $recoveredTelemetry['token_usage']['total_tokens']);
        $this->assertCount(2, $recoveredTelemetry['attempts']);

        // Attempt 1 record audit
        $this->assertSame(1, $recoveredTelemetry['attempts'][0]['attempt_number']);
        $this->assertFalse($recoveredTelemetry['attempts'][0]['is_success']);
        $this->assertSame(429, $recoveredTelemetry['attempts'][0]['status_code']);
        $this->assertTrue($recoveredTelemetry['attempts'][0]['retryable']);

        // Attempt 2 record audit
        $this->assertSame(2, $recoveredTelemetry['attempts'][1]['attempt_number']);
        $this->assertTrue($recoveredTelemetry['attempts'][1]['is_success']);
        $this->assertSame(300, $recoveredTelemetry['attempts'][1]['token_usage']['total_tokens']);

        // Assert provider invocation count = exactly 2
        Http::assertSentCount(2);
    }

    /**
     * Test L: Non-retryable failure results in zero subsequent provider calls
     */
    public function test_l_non_retryable_failure_results_in_zero_subsequent_provider_calls(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-invalid-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $orchestrator->processBatch($batch, $provider);
        $item = $batch->fresh(['items'])->items->first();
        $this->assertSame(GenerationItemStatus::Failed, $item->status);
        $this->assertFalse($item->isEligibleForRetry(3));

        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([], 200),
        ]);

        // Attempt retry - should make 0 HTTP requests
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        $this->assertSame(1, $item->fresh()->attempt_count);
        Http::assertNothingSent();
    }

    /**
     * Test N: Retry history remains auditable across multiple attempts
     */
    public function test_n_retry_history_remains_auditable(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limit']], 429)
                ->push(['error' => ['message' => 'Gateway timeout']], 504)
                ->push([
                    'id' => 'chatcmpl-audit-ok',
                    'model' => 'openai/gpt-oss-20b',
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]],
                    ],
                    'usage' => ['prompt_tokens' => 210, 'completion_tokens' => 105, 'total_tokens' => 315],
                ], 200),
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-key', model: 'openai/gpt-oss-20b');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        // Attempt 1 -> 429
        $orchestrator->processBatch($batch, $provider);
        // Attempt 2 -> 504
        $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);
        // Attempt 3 -> 200 OK
        $batch = $orchestrator->retryFailedItems($batch, $provider, maxAttempts: 3);

        $item = $batch->items->first();
        $this->assertSame(GenerationItemStatus::Materialized, $item->status);
        $this->assertSame(3, $item->attempt_count);

        $telemetry = $item->getOperationalTelemetry();
        $attempts = $telemetry['attempts'];
        $this->assertCount(3, $attempts);

        // Attempt 1 audit
        $this->assertSame(1, $attempts[0]['attempt_number']);
        $this->assertFalse($attempts[0]['is_success']);
        $this->assertSame(429, $attempts[0]['status_code']);
        $this->assertTrue($attempts[0]['retryable']);

        // Attempt 2 audit
        $this->assertSame(2, $attempts[1]['attempt_number']);
        $this->assertFalse($attempts[1]['is_success']);
        $this->assertSame(504, $attempts[1]['status_code']);
        $this->assertTrue($attempts[1]['retryable']);

        // Attempt 3 audit
        $this->assertSame(3, $attempts[2]['attempt_number']);
        $this->assertTrue($attempts[2]['is_success']);
        $this->assertSame(315, $attempts[2]['token_usage']['total_tokens']);
    }

    /**
     * Test O: Batch counters and status remain consistent
     */
    public function test_o_batch_counters_and_status_remain_consistent(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::sequence()
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]]],
                ], 200)
                ->push(['error' => ['message' => '401 Unauthorized']], 401),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $processedBatch = $orchestrator->processBatch($batch, $provider);

        $this->assertSame(GenerationBatchStatus::PartiallyCompleted, $processedBatch->status);
        $this->assertSame(2, $processedBatch->total_slots);
        $this->assertSame(1, $processedBatch->validated_slots);
        $this->assertSame(1, $processedBatch->failed_slots);
        $this->assertSame(0, $processedBatch->pending_slots);
    }

    /**
     * Test P, Q, R: OpenAI, Groq, and Null provider regressions
     */
    public function test_p_q_r_provider_regressions(): void
    {
        // 1. OpenAI happy path
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]]],
            ], 200),
        ]);

        $openaiProvider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');
        $orchestrator1 = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $openaiProvider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch1 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $res1 = $orchestrator1->processBatch($batch1, $openaiProvider);
        $this->assertSame(GenerationBatchStatus::Completed, $res1->status);

        // 2. Groq happy path
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->validCandidatePayload())]]],
            ], 200),
        ]);

        $groqProvider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-key');
        $orchestrator2 = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $groqProvider
        );

        $batch2 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $res2 = $orchestrator2->processBatch($batch2, $groqProvider);
        $this->assertSame(GenerationBatchStatus::Completed, $res2->status);

        // 3. Null Provider fails closed
        $nullProvider = new NullGenerationProvider;
        $orchestrator3 = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $nullProvider
        );

        $batch3 = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $res3 = $orchestrator3->processBatch($batch3, $nullProvider);
        $this->assertSame(GenerationBatchStatus::Failed, $res3->status);
        $this->assertFalse($res3->fresh(['items'])->items->first()->isEligibleForRetry(3));
    }

    /**
     * Test S: Secret leakage prevention across the complete persisted structure
     */
    public function test_s_secret_leakage_prevention_in_telemetry(): void
    {
        $sentinelGroqSecret = 'gsk-SUPER-SECRET-GROQ-TOKEN-NEVER-LEAK-987654';
        $sentinelOpenAISecret = 'sk-SUPER-SECRET-OPENAI-TOKEN-NEVER-LEAK-123456';

        // 1. Groq provider error with sentinel secret in upstream message
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'error' => ['message' => 'Unauthorized key: '.$sentinelGroqSecret],
            ], 401),
        ]);

        $groqProvider = new GroqQuestionGenerationProvider(apiKey: $sentinelGroqSecret);
        $orchestratorGroq = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $groqProvider
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batchGroq = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);

        $processedGroq = $orchestratorGroq->processBatch($batchGroq, $groqProvider);
        $itemGroq = $processedGroq->items->first()->fresh();

        $forbiddenTokensGroq = [
            $sentinelGroqSecret,
            'SUPER-SECRET-GROQ',
            'Bearer '.$sentinelGroqSecret,
            'Authorization',
        ];

        $structureToCheckGroq = [
            'prompt_payload' => json_encode($itemGroq->prompt_payload),
            'provider_request_metadata' => json_encode($itemGroq->provider_request_metadata),
            'raw_output' => json_encode($itemGroq->raw_output),
            'raw_output_attempts' => json_encode($itemGroq->raw_output['attempts'] ?? []),
            'normalized_output' => json_encode($itemGroq->normalized_output),
            'validation_result' => json_encode($itemGroq->validation_result),
            'last_error_message' => (string) $itemGroq->last_error_message,
            'telemetry' => json_encode($itemGroq->getOperationalTelemetry()),
        ];

        foreach ($structureToCheckGroq as $fieldName => $serializedContent) {
            foreach ($forbiddenTokensGroq as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $serializedContent,
                    "Field [{$fieldName}] must not contain secret token [{$token}]"
                );
            }
        }

        // 2. OpenAI provider error with sentinel secret in upstream message
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'error' => ['message' => 'Incorrect API key: '.$sentinelOpenAISecret],
            ], 401),
        ]);

        $openaiProvider = new OpenAIQuestionGenerationProvider(apiKey: $sentinelOpenAISecret);
        $orchestratorOpenAI = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $openaiProvider
        );

        $batchOpenAI = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id, 'force_new_run' => true]);
        $processedOpenAI = $orchestratorOpenAI->processBatch($batchOpenAI, $openaiProvider);
        $itemOpenAI = $processedOpenAI->items->first()->fresh();

        $forbiddenTokensOpenAI = [
            $sentinelOpenAISecret,
            'SUPER-SECRET-OPENAI',
            'Bearer '.$sentinelOpenAISecret,
            'Authorization',
        ];

        $structureToCheckOpenAI = [
            'prompt_payload' => json_encode($itemOpenAI->prompt_payload),
            'provider_request_metadata' => json_encode($itemOpenAI->provider_request_metadata),
            'raw_output' => json_encode($itemOpenAI->raw_output),
            'raw_output_attempts' => json_encode($itemOpenAI->raw_output['attempts'] ?? []),
            'normalized_output' => json_encode($itemOpenAI->normalized_output),
            'validation_result' => json_encode($itemOpenAI->validation_result),
            'last_error_message' => (string) $itemOpenAI->last_error_message,
            'telemetry' => json_encode($itemOpenAI->getOperationalTelemetry()),
        ];

        foreach ($structureToCheckOpenAI as $fieldName => $serializedContent) {
            foreach ($forbiddenTokensOpenAI as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $serializedContent,
                    "Field [{$fieldName}] must not contain secret token [{$token}]"
                );
            }
        }
    }
}
