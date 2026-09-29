<?php

namespace Tests\Feature;

use App\Integrations\QuestionGeneration\GroqQuestionGenerationProvider;
use App\Integrations\QuestionGeneration\OpenAIQuestionGenerationProvider;
use App\Models\User;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\ToeflBlueprintRequest;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuestionEngineGroqProviderIntegrationTest extends TestCase
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
            'title' => 'Sprint 7C Groq Test Bank',
            'slug' => 'sprint-7c-groq-test-bank-'.uniqid(),
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

    /**
     * Test Provider Resolution for Groq, OpenAI, Fake, and Null fallbacks
     */
    public function test_groq_provider_resolution_is_supported_and_fail_closed(): void
    {
        // Default / null -> NullGenerationProvider
        Config::set('question_generation.provider', 'null');
        $resolvedDefault = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(NullGenerationProvider::class, $resolvedDefault);
        $this->assertSame('null', $resolvedDefault->getProviderName());

        // Groq configured -> GroqQuestionGenerationProvider
        Config::set('question_generation.provider', 'groq');
        Config::set('question_generation.groq.api_key', 'test-groq-key');
        $resolvedGroq = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(GroqQuestionGenerationProvider::class, $resolvedGroq);
        $this->assertSame('groq', $resolvedGroq->getProviderName());

        // OpenAI configured -> OpenAIQuestionGenerationProvider
        Config::set('question_generation.provider', 'openai');
        Config::set('question_generation.openai.api_key', 'test-openai-key');
        $resolvedOpenAI = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(OpenAIQuestionGenerationProvider::class, $resolvedOpenAI);
        $this->assertSame('openai', $resolvedOpenAI->getProviderName());

        // Fake provider
        Config::set('question_generation.provider', 'fake');
        $resolvedFake = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(FakeGenerationProvider::class, $resolvedFake);
        $this->assertSame('fake', $resolvedFake->getProviderName());

        // Unknown provider fails closed to NullGenerationProvider
        Config::set('question_generation.provider', 'unknown_vendor_xyz');
        $resolvedUnknown = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(NullGenerationProvider::class, $resolvedUnknown);
        $this->assertSame('null', $resolvedUnknown->getProviderName());
    }

    /**
     * Test Missing API key fails safely (fail-closed)
     */
    public function test_groq_missing_api_key_fails_safely(): void
    {
        $provider = new GroqQuestionGenerationProvider(apiKey: '');

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $processedItem = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Failed, $processedItem->status);
        $this->assertSame(GenerationErrorCode::ProviderUnavailable->value, $processedItem->last_error_code);
        $this->assertStringContainsString('Groq API key is missing', (string) $processedItem->last_error_message);
        $this->assertNull($processedItem->question_id);
    }

    /**
     * Test Correct endpoint, request payload structure, prompts and strict schema sent to Groq
     */
    public function test_correct_endpoint_and_request_structure_emitted_to_groq(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-groq-test-123',
                'object' => 'chat.completion',
                'model' => 'openai/gpt-oss-20b',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'The finance committee will _____ the budget proposals during Thursday\'s session.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'review', 'is_correct' => true, 'explanation' => 'Base verb following modal will.'],
                                    ['label' => 'B', 'content' => 'reviews', 'is_correct' => false, 'explanation' => 'Third-person singular verb incorrect.'],
                                    ['label' => 'C', 'content' => 'reviewing', 'is_correct' => false, 'explanation' => 'Participle incorrect.'],
                                    ['label' => 'D', 'content' => 'reviewed', 'is_correct' => false, 'explanation' => 'Past tense incorrect.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'The modal auxiliary "will" requires the base form of the verb.',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 240,
                    'completion_tokens' => 110,
                    'total_tokens' => 350,
                ],
            ], 200),
        ]);

        $provider = new GroqQuestionGenerationProvider(
            apiKey: 'gsk-test-groq-key-12345',
            model: 'openai/gpt-oss-20b',
            baseUrl: 'https://api.groq.com/openai/v1'
        );

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $processedItem = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::Materialized, $processedItem->status);

        Http::assertSent(function (Request $req) {
            $hasAuth = $req->hasHeader('Authorization', 'Bearer gsk-test-groq-key-12345');
            $isCorrectUrl = $req->url() === 'https://api.groq.com/openai/v1/chat/completions';
            $body = $req->data();

            $hasModel = ($body['model'] ?? '') === 'openai/gpt-oss-20b';
            $messages = $body['messages'] ?? [];
            $hasSystemPrompt = isset($messages[0]['content']) && str_contains($messages[0]['content'], 'professional TOEIC-style assessment item writer');
            $hasUserPrompt = isset($messages[1]['content']) && str_contains($messages[1]['content'], 'Part 5');
            $responseFormat = $body['response_format'] ?? [];
            $hasStrictJsonSchema = ($responseFormat['type'] ?? '') === 'json_schema'
                && ($responseFormat['json_schema']['strict'] ?? false) === true
                && isset($responseFormat['json_schema']['schema']);

            return $hasAuth && $isCorrectUrl && $hasModel && $hasSystemPrompt && $hasUserPrompt && $hasStrictJsonSchema;
        });
    }

    /**
     * Test Runtime scope gating (only TOEIC Reading Part 5 accepted)
     */
    public function test_groq_runtime_scope_enforces_only_toeic_reading_part_5(): void
    {
        Http::fake();

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-key');

        $toeflPlanner = new ToeflBlueprintPlanner($this->registry);

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            new QuestionPromptComposerResolver([new ToeicPromptComposer, new ToeflPromptComposer]),
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        // 1. Part 1 (Listening) -> Rejected
        $reqPart1 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 1, 'item_count' => 1]);
        $planPart1 = $this->toeicPlanner->plan($reqPart1);
        $batchPart1 = $this->batchFactory->createFromPlan($planPart1, ['question_bank_id' => $this->questionBank->id]);
        $itemPart1 = $batchPart1->items->first();

        $resPart1 = $orchestrator->processItem($itemPart1);
        $this->assertSame(GenerationItemStatus::Failed, $resPart1->status);
        $this->assertSame(GenerationErrorCode::StandardMismatch->value, $resPart1->last_error_code);

        // 2. Part 6 (Reading) -> Rejected
        $reqPart6 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 6, 'item_count' => 4]);
        $planPart6 = $this->toeicPlanner->plan($reqPart6);
        $batchPart6 = $this->batchFactory->createFromPlan($planPart6, ['question_bank_id' => $this->questionBank->id]);
        $itemPart6 = $batchPart6->items->first();

        $resPart6 = $orchestrator->processItem($itemPart6);
        $this->assertSame(GenerationItemStatus::Failed, $resPart6->status);
        $this->assertSame(GenerationErrorCode::StandardMismatch->value, $resPart6->last_error_code);

        // 3. TOEFL -> Rejected
        $toeflStd = $this->registry->registerStandard(ToeflIbt2026StandardDefinition::getDefinition());
        $this->registry->activateStandard($toeflStd);

        $reqToefl = ToeflBlueprintRequest::fromArray(['mode' => 'task', 'task_type' => 'read_in_daily_life', 'task_count' => 1, 'allow_practice_counts' => true]);
        $planToefl = $toeflPlanner->plan($reqToefl);
        $batchToefl = $this->batchFactory->createFromPlan($planToefl, ['question_bank_id' => $this->questionBank->id]);
        $itemToefl = $batchToefl->items->first();

        $resToefl = $orchestrator->processItem($itemToefl);
        $this->assertSame(GenerationItemStatus::Failed, $resToefl->status);
        $this->assertSame(GenerationErrorCode::StandardMismatch->value, $resToefl->last_error_code);

        Http::assertNothingSent();
    }

    /**
     * Test Groq Error and Retryability Translation Matrix
     */
    public function test_groq_error_translation_and_retryability_matrix(): void
    {
        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-key');

        $composer = new ToeicPromptComposer;
        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();
        $promptComp = $composer->compose($item);

        $genRequest = new GenerationProviderRequest(
            batchId: $batch->id,
            itemId: $item->id,
            slotSequence: 1,
            promptComposition: $promptComp
        );

        // 1. Missing key
        $noKeyProvider = new GroqQuestionGenerationProvider(apiKey: '');
        $resNoKey = $noKeyProvider->generate($genRequest);
        $this->assertFalse($resNoKey->isSuccess);
        $this->assertSame(GenerationErrorCode::ProviderUnavailable, $resNoKey->errorCode);
        $this->assertFalse($resNoKey->metadata['retryable'] ?? true);

        // 2. HTTP status matrix
        $statuses = [
            400 => ['retryable' => false, 'code' => GenerationErrorCode::ProviderError],
            401 => ['retryable' => false, 'code' => GenerationErrorCode::ProviderUnavailable],
            403 => ['retryable' => false, 'code' => GenerationErrorCode::ProviderUnavailable],
            404 => ['retryable' => false, 'code' => GenerationErrorCode::ProviderError],
            408 => ['retryable' => true, 'code' => GenerationErrorCode::ProviderTimeout],
            429 => ['retryable' => true, 'code' => GenerationErrorCode::ProviderError],
            500 => ['retryable' => true, 'code' => GenerationErrorCode::ProviderError],
            502 => ['retryable' => true, 'code' => GenerationErrorCode::ProviderError],
            503 => ['retryable' => true, 'code' => GenerationErrorCode::ProviderError],
            504 => ['retryable' => true, 'code' => GenerationErrorCode::ProviderTimeout],
        ];

        $sequence = Http::fakeSequence('https://api.groq.com/openai/v1/*');
        foreach ($statuses as $statusCode => $expected) {
            $sequence->push(['error' => ['message' => "Groq HTTP Error status {$statusCode}"]], $statusCode);
        }
        $sequence->push(['choices' => [['message' => ['role' => 'assistant', 'refusal' => 'Refused by Groq safety']]]], 200);
        $sequence->push(['choices' => [['message' => ['role' => 'assistant', 'content' => '{malformed invalid json']]]], 200);

        foreach ($statuses as $statusCode => $expected) {
            $resp = $provider->generate($genRequest);
            $this->assertFalse($resp->isSuccess);
            $this->assertSame($expected['code'], $resp->errorCode, "HTTP {$statusCode} expected {$expected['code']->value}");
            $this->assertSame($expected['retryable'], $resp->metadata['retryable'] ?? null, "HTTP {$statusCode} retryable expected ".($expected['retryable'] ? 'true' : 'false'));
        }

        // 3. Refusal
        $resRefusal = $provider->generate($genRequest);
        $this->assertFalse($resRefusal->isSuccess);
        $this->assertSame(GenerationErrorCode::ProviderError, $resRefusal->errorCode);
        $this->assertFalse($resRefusal->metadata['retryable'] ?? true);

        // 4. Malformed JSON
        $resMalformed = $provider->generate($genRequest);
        $this->assertFalse($resMalformed->isSuccess);
        $this->assertSame(GenerationErrorCode::InvalidProviderResponse, $resMalformed->errorCode);
        $this->assertFalse($resMalformed->metadata['retryable'] ?? true);
    }

    /**
     * Test Secret Leakage Prevention across all Groq structures
     */
    public function test_groq_secret_leakage_prevention(): void
    {
        $sentinelSecret = 'gsk-SUPER-SECRET-GROQ-KEY-9876543210-NEVER-LEAK';

        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => json_encode([
                                    'schema_version' => 'generated_question_candidate_v1',
                                    'prompt' => 'All attendees must _____ their registration pass at the door.',
                                    'passage_text' => null,
                                    'audio_script' => null,
                                    'choices' => [
                                        ['label' => 'A', 'content' => 'display', 'is_correct' => true, 'explanation' => 'Base verb.'],
                                        ['label' => 'B', 'content' => 'displays', 'is_correct' => false, 'explanation' => 'Inflected.'],
                                        ['label' => 'C', 'content' => 'displaying', 'is_correct' => false, 'explanation' => 'Participle.'],
                                        ['label' => 'D', 'content' => 'displayed', 'is_correct' => false, 'explanation' => 'Past tense.'],
                                    ],
                                    'correct_answer' => 'A',
                                    'explanation' => 'Modal verb must require base form.',
                                ]),
                            ],
                        ],
                    ],
                ], 200)
                ->push(['error' => ['message' => 'Unauthorized key: '.$sentinelSecret]], 401),
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: $sentinelSecret);

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 2]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $items = $batch->items;

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $item1 = $orchestrator->processItem($items[0]);
        $this->assertSame(GenerationItemStatus::Materialized, $item1->status);

        $item2 = $orchestrator->processItem($items[1]);
        $this->assertSame(GenerationItemStatus::Failed, $item2->status);

        foreach ([$item1, $item2] as $processed) {
            $itemDump = json_encode($processed->fresh()->toArray());
            $this->assertStringNotContainsString($sentinelSecret, (string) $itemDump);
            $this->assertStringNotContainsString('SUPER-SECRET-GROQ', (string) $itemDump);

            if ($processed->last_error_message) {
                $this->assertStringNotContainsString($sentinelSecret, $processed->last_error_message);
            }
        }

        $question = Question::find($item1->question_id);
        $this->assertNotNull($question);
        $this->assertStringNotContainsString($sentinelSecret, json_encode($question->toArray()));
        $this->assertSame('groq', $question->generation_metadata['provider_name']);
    }

    /**
     * Test End-to-End Orchestration from Groq to Draft Materialization
     */
    public function test_end_to_end_orchestrated_flow_from_groq_to_draft(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'id' => 'chatcmpl-groq-e2e',
                'model' => 'openai/gpt-oss-20b',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'The revised proposal was _____ received by the board members.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'favorably', 'is_correct' => true, 'explanation' => 'Adverb modifying past participle received.'],
                                    ['label' => 'B', 'content' => 'favorable', 'is_correct' => false, 'explanation' => 'Adjective cannot modify verb received.'],
                                    ['label' => 'C', 'content' => 'favor', 'is_correct' => false, 'explanation' => 'Noun cannot modify verb.'],
                                    ['label' => 'D', 'content' => 'favoring', 'is_correct' => false, 'explanation' => 'Participle cannot modify verb.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'An adverb is required to modify the participle "received".',
                            ]),
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 210, 'completion_tokens' => 95, 'total_tokens' => 305],
            ], 200),
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-e2e-key');

        $blueprintReq = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 1,
        ]);

        $plan = $this->toeicPlanner->plan($blueprintReq);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $processedBatch = $orchestrator->processBatch($batch);

        $this->assertSame(GenerationBatchStatus::Completed, $processedBatch->status);
        $items = $processedBatch->items;
        $this->assertCount(1, $items);

        $item = $items->first();
        $this->assertSame(GenerationItemStatus::Materialized, $item->status);
        $this->assertNotNull($item->question_id);

        /** @var Question $question */
        $question = Question::find($item->question_id);
        $this->assertNotNull($question);

        // Invariants
        $this->assertSame(ContentOrigin::Generated, $question->content_origin);
        $this->assertFalse($this->questionBank->fresh()->is_published);
        $this->assertSame('draft', $this->questionBank->fresh()->status);
        $this->assertSame($this->questionBank->id, $question->question_bank_id);
        $this->assertSame(5, $question->part_number);
        $this->assertSame(SectionType::Reading, $question->section);
        $this->assertCount(4, $question->choices);
        $this->assertSame('A', $question->choices()->where('is_correct', true)->first()->label);
        $this->assertSame('groq', $question->generation_metadata['provider_name']);
    }

    /**
     * Test Candidate failing quality gate does not materialize
     */
    public function test_groq_candidate_failing_quality_gate_does_not_materialize(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'TODO: [placeholder sentence for Part 5]',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'Option A', 'is_correct' => true, 'explanation' => null],
                                    ['label' => 'B', 'content' => 'Option B', 'is_correct' => false, 'explanation' => null],
                                    ['label' => 'C', 'content' => 'Option C', 'is_correct' => false, 'explanation' => null],
                                    ['label' => 'D', 'content' => 'Option D', 'is_correct' => false, 'explanation' => null],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'Placeholder explanation',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-invalid');

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            $this->composerResolver,
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        $processedItem = $orchestrator->processItem($item);

        $this->assertSame(GenerationItemStatus::ValidationFailed, $processedItem->status);
        $this->assertNull($processedItem->question_id);
    }

    /**
     * Test exact PromptComposition schema provenance, no provider-side mutation, and prompt hash stability
     */
    public function test_groq_preserves_exact_prompt_composition_schema_and_prompt_hash(): void
    {
        $capturedPayload = null;

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => function (Request $request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'id' => 'chatcmpl-groq-schema-provenance',
                    'object' => 'chat.completion',
                    'model' => 'openai/gpt-oss-20b',
                    'choices' => [
                        [
                            'index' => 0,
                            'message' => [
                                'role' => 'assistant',
                                'content' => json_encode([
                                    'schema_version' => 'generated_question_candidate_v1',
                                    'prompt' => 'Mr. Tanaka requested that the delivery date be _____ to next Monday.',
                                    'passage_text' => null,
                                    'audio_script' => null,
                                    'choices' => [
                                        ['label' => 'A', 'content' => 'moved', 'is_correct' => true, 'explanation' => 'Past participle in subjunctive passive.'],
                                        ['label' => 'B', 'content' => 'moving', 'is_correct' => false, 'explanation' => 'Active participle incorrect.'],
                                        ['label' => 'C', 'content' => 'moves', 'is_correct' => false, 'explanation' => 'Present tense incorrect.'],
                                        ['label' => 'D', 'content' => 'move', 'is_correct' => false, 'explanation' => 'Base form incorrect after be.'],
                                    ],
                                    'correct_answer' => 'A',
                                    'explanation' => 'Passive subjunctive requires "be moved".',
                                ]),
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => [
                        'prompt_tokens' => 195,
                        'completion_tokens' => 88,
                        'total_tokens' => 283,
                    ],
                ], 200);
            },
        ]);

        $composer = new ToeicPromptComposer;
        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();
        $promptComp = $composer->compose($item);

        $initialPromptHash = $promptComp->computePromptHash();
        $this->assertNotEmpty($promptComp->schemaDefinition);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-schema-key', model: 'openai/gpt-oss-20b');
        $genReq = new GenerationProviderRequest(
            batchId: $batch->id,
            itemId: $item->id,
            slotSequence: 1,
            promptComposition: $promptComp
        );

        $response = $provider->generate($genReq);

        $this->assertTrue($response->isSuccess);
        $this->assertSame($initialPromptHash, $response->metadata['prompt_hash']);
        $this->assertSame($initialPromptHash, $promptComp->computePromptHash());

        $this->assertNotNull($capturedPayload);
        $this->assertSame('openai/gpt-oss-20b', $capturedPayload['model']);
        $this->assertSame('json_schema', $capturedPayload['response_format']['type']);
        $this->assertTrue($capturedPayload['response_format']['json_schema']['strict']);
        $this->assertSame('generated_question_candidate', $capturedPayload['response_format']['json_schema']['name']);

        // Assert exact schema definition identity without provider-side mutation
        $this->assertSame($promptComp->schemaDefinition, $capturedPayload['response_format']['json_schema']['schema']);
    }

    /**
     * Test transport ConnectionException maps to ProviderTimeout with retryable true
     */
    public function test_groq_handles_transport_connection_exception_as_retryable_timeout(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/*' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
            },
        ]);

        $provider = new GroqQuestionGenerationProvider(apiKey: 'gsk-test-timeout-key');

        $composer = new ToeicPromptComposer;
        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();
        $promptComp = $composer->compose($item);

        $genReq = new GenerationProviderRequest(
            batchId: $batch->id,
            itemId: $item->id,
            slotSequence: 1,
            promptComposition: $promptComp
        );

        $response = $provider->generate($genReq);

        $this->assertFalse($response->isSuccess);
        $this->assertSame(GenerationErrorCode::ProviderTimeout, $response->errorCode);
        $this->assertTrue($response->metadata['retryable']);
        $this->assertStringContainsString('Groq connection/timeout failure', $response->errorMessage);
    }
}
