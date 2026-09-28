<?php

namespace Tests\Feature;

use App\Integrations\QuestionGeneration\OpenAIQuestionGenerationProvider;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuestionEngineOpenAIProviderIntegrationTest extends TestCase
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
            'title' => 'Sprint 7A OpenAI Test Bank',
            'slug' => 'sprint-7a-openai-test-bank-'.uniqid(),
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
     * Test A, B, C, D: Service container provider resolution & fail-closed fallback
     */
    public function test_provider_resolution_is_fail_closed_and_selects_openai_only_when_configured(): void
    {
        // Default / null config -> NullGenerationProvider
        Config::set('question_generation.provider', 'null');
        $resolvedDefault = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(NullGenerationProvider::class, $resolvedDefault);
        $this->assertSame('null', $resolvedDefault->getProviderName());

        // Unknown provider -> fails closed to NullGenerationProvider (never FakeGenerationProvider)
        Config::set('question_generation.provider', 'unsupported_vendor_xyz');
        $resolvedUnknown = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(NullGenerationProvider::class, $resolvedUnknown);
        $this->assertSame('null', $resolvedUnknown->getProviderName());

        // OpenAI configured -> OpenAIQuestionGenerationProvider
        Config::set('question_generation.provider', 'openai');
        Config::set('question_generation.openai.api_key', 'test-openai-key');
        $resolvedOpenAI = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(OpenAIQuestionGenerationProvider::class, $resolvedOpenAI);
        $this->assertSame('openai', $resolvedOpenAI->getProviderName());

        // Explicit Fake provider only when configured explicitly
        Config::set('question_generation.provider', 'fake');
        $resolvedFake = app(QuestionGenerationProvider::class);
        $this->assertInstanceOf(FakeGenerationProvider::class, $resolvedFake);
        $this->assertSame('fake', $resolvedFake->getProviderName());
    }

    /**
     * Test E: Missing API key fails safely without exposing secrets
     */
    public function test_missing_api_key_fails_safely(): void
    {
        $provider = new OpenAIQuestionGenerationProvider(apiKey: '');

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
        $this->assertStringContainsString('OpenAI API key is missing', (string) $processedItem->last_error_message);
        $this->assertNull($processedItem->question_id);
    }

    /**
     * Test F, G, H, I: Correct endpoint, request payload structure, prompts and schema sent
     */
    public function test_correct_endpoint_and_request_structure_emitted_to_openai(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-123',
                'object' => 'chat.completion',
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'Mr. Tanaka will _____ the annual financial report at tomorrow\'s meeting.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'present', 'is_correct' => true, 'explanation' => 'Correct base verb following modal will.'],
                                    ['label' => 'B', 'content' => 'presents', 'is_correct' => false, 'explanation' => 'Third-person singular verb cannot follow modal will.'],
                                    ['label' => 'C', 'content' => 'presentation', 'is_correct' => false, 'explanation' => 'Noun cannot occupy base verb position.'],
                                    ['label' => 'D', 'content' => 'presenting', 'is_correct' => false, 'explanation' => 'Present participle cannot follow modal will directly.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'The modal auxiliary "will" must be followed by a base form verb.',
                                'metadata' => ['focus' => 'modal_verbs'],
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 250,
                    'completion_tokens' => 120,
                    'total_tokens' => 370,
                ],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(
            apiKey: 'sk-proj-test-key-12345',
            model: 'gpt-4o-mini',
            baseUrl: 'https://api.openai.com/v1'
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
            $hasAuth = $req->hasHeader('Authorization', 'Bearer sk-proj-test-key-12345');
            $isCorrectUrl = $req->url() === 'https://api.openai.com/v1/chat/completions';
            $body = $req->data();

            $hasModel = ($body['model'] ?? '') === 'gpt-4o-mini';
            $messages = $body['messages'] ?? [];
            $hasSystemPrompt = isset($messages[0]['content']) && str_contains($messages[0]['content'], 'professional TOEIC-style assessment item writer');
            $hasUserPrompt = isset($messages[1]['content']) && str_contains($messages[1]['content'], 'Part 5');
            $hasResponseFormat = isset($body['response_format']['json_schema']['schema']);

            return $hasAuth && $isCorrectUrl && $hasModel && $hasSystemPrompt && $hasUserPrompt && $hasResponseFormat;
        });
    }

    /**
     * Test J, K, L, M: Strict Sprint 7A scope gating (only TOEIC Reading Part 5 accepted)
     */
    public function test_runtime_scope_enforces_only_toeic_reading_part_5(): void
    {
        Http::fake();

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'test-key');

        $toeflPlanner = new ToeflBlueprintPlanner($this->registry);

        $orchestrator = new QuestionGenerationOrchestrator(
            $this->batchFactory,
            new QuestionPromptComposerResolver([new ToeicPromptComposer, new ToeflPromptComposer]),
            $this->normalizer,
            $this->qualityGate,
            $this->materializer,
            $provider
        );

        // K. Part 1 (Listening) -> Rejected
        $reqPart1 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 1, 'item_count' => 1]);
        $planPart1 = $this->toeicPlanner->plan($reqPart1);
        $batchPart1 = $this->batchFactory->createFromPlan($planPart1, ['question_bank_id' => $this->questionBank->id]);
        $itemPart1 = $batchPart1->items->first();

        $resPart1 = $orchestrator->processItem($itemPart1);
        $this->assertSame(GenerationItemStatus::Failed, $resPart1->status);
        $this->assertSame(GenerationErrorCode::StandardMismatch->value, $resPart1->last_error_code);

        // L. Part 6 (Reading) -> Rejected
        $reqPart6 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 6, 'item_count' => 4]);
        $planPart6 = $this->toeicPlanner->plan($reqPart6);
        $batchPart6 = $this->batchFactory->createFromPlan($planPart6, ['question_bank_id' => $this->questionBank->id]);
        $itemPart6 = $batchPart6->items->first();

        $resPart6 = $orchestrator->processItem($itemPart6);
        $this->assertSame(GenerationItemStatus::Failed, $resPart6->status);
        $this->assertSame(GenerationErrorCode::StandardMismatch->value, $resPart6->last_error_code);

        // M. TOEFL -> Rejected
        $toeflStd = $this->registry->registerStandard(ToeflIbt2026StandardDefinition::getDefinition());
        $this->registry->activateStandard($toeflStd);

        $reqToefl = ToeflBlueprintRequest::fromArray(['mode' => 'task', 'task_type' => 'read_in_daily_life', 'task_count' => 1, 'allow_practice_counts' => true]);
        $planToefl = $toeflPlanner->plan($reqToefl);
        $batchToefl = $this->batchFactory->createFromPlan($planToefl, ['question_bank_id' => $this->questionBank->id]);
        $itemToefl = $batchToefl->items->first();

        $resToefl = $orchestrator->processItem($itemToefl);
        $this->assertSame(GenerationItemStatus::Failed, $resToefl->status);
        $this->assertSame(GenerationErrorCode::StandardMismatch->value, $resToefl->last_error_code);

        // Verify no external requests were sent for unsupported scopes
        Http::assertNothingSent();
    }

    /**
     * Test N, O, P, Q, R: Provider response handling (success, malformed, non-2xx, timeout, refusal)
     */
    public function test_provider_error_translation_matrix(): void
    {
        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');

        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 5]);
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

        // O. Malformed JSON response, P1. 429, P2. 401, Q. Timeout, R. Refusal
        Http::fake([
            'https://api.openai.com/v1/*' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Not valid JSON payload here {;;']]]], 200)
                ->push(['error' => ['message' => 'Rate limit reached for requests.', 'type' => 'requests']], 429)
                ->push(['error' => ['message' => 'Incorrect API key provided.', 'type' => 'invalid_request_error']], 401)
                ->push(['error' => ['message' => 'Gateway Timeout']], 504)
                ->push(['choices' => [['message' => ['role' => 'assistant', 'refusal' => 'I cannot generate questions on this topic due to safety policy.']]]], 200),
        ]);

        $resO = $orchestrator->processItem($items[0]);
        $this->assertSame(GenerationItemStatus::Failed, $resO->status);
        $this->assertSame(GenerationErrorCode::InvalidProviderResponse->value, $resO->last_error_code);

        $resP1 = $orchestrator->processItem($items[1]);
        $this->assertSame(GenerationItemStatus::Failed, $resP1->status);
        $this->assertSame(GenerationErrorCode::ProviderError->value, $resP1->last_error_code);

        $resP2 = $orchestrator->processItem($items[2]);
        $this->assertSame(GenerationItemStatus::Failed, $resP2->status);
        $this->assertSame(GenerationErrorCode::ProviderUnavailable->value, $resP2->last_error_code);

        $resQ = $orchestrator->processItem($items[3]);
        $this->assertSame(GenerationItemStatus::Failed, $resQ->status);
        $this->assertSame(GenerationErrorCode::ProviderTimeout->value, $resQ->last_error_code);

        $resR = $orchestrator->processItem($items[4]);
        $this->assertSame(GenerationItemStatus::Failed, $resR->status);
        $this->assertSame(GenerationErrorCode::ProviderError->value, $resR->last_error_code);
        $this->assertStringContainsString('refused', (string) $resR->last_error_message);
    }

    /**
     * Test S, T, U: Prompt hash existence, determinism, and absence of secrets in metadata
     */
    public function test_prompt_hash_is_deterministic_and_no_secrets_are_stored(): void
    {
        $apiKey = 'sk-super-secret-production-key-999';

        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'The manager requested that all employees _____ their timesheets by Friday.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'submit', 'is_correct' => true, 'explanation' => 'Subjunctive base verb.'],
                                    ['label' => 'B', 'content' => 'submits', 'is_correct' => false, 'explanation' => 'Incorrect inflection.'],
                                    ['label' => 'C', 'content' => 'submitted', 'is_correct' => false, 'explanation' => 'Past tense incorrect.'],
                                    ['label' => 'D', 'content' => 'submitting', 'is_correct' => false, 'explanation' => 'Participle incorrect.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'Subjunctive verb after demand/request.',
                            ]),
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: $apiKey);

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

        // Prompt hash assertions
        $promptPayload = $processedItem->prompt_payload;
        $this->assertNotNull($promptPayload);
        $this->assertArrayHasKey('prompt_hash', $promptPayload);
        $promptHash1 = $promptPayload['prompt_hash'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $promptHash1);

        // Recompute hash independently with same item & verify deterministic match
        $composer = new ToeicPromptComposer;
        $composition2 = $composer->compose($item);
        $promptHash2 = $composition2->computePromptHash();
        $this->assertSame($promptHash1, $promptHash2);

        // Security assertion: Ensure API key never appears in stored item columns or question metadata
        $itemArray = $processedItem->toArray();
        $itemJson = json_encode($itemArray);
        $this->assertStringNotContainsString($apiKey, (string) $itemJson);
        $this->assertStringNotContainsString('sk-super-secret', (string) $itemJson);

        $question = Question::find($processedItem->question_id);
        $this->assertNotNull($question);
        $questionJson = json_encode($question->toArray());
        $this->assertStringNotContainsString($apiKey, (string) $questionJson);
        $this->assertSame($promptHash1, $question->generation_metadata['prompt_hash']);
    }

    /**
     * Test V, W, X, Y, Z: Quality gate validation, draft materialization, invalid rejection, no auto-publish
     */
    public function test_end_to_end_orchestrated_flow_from_openai_to_governed_draft(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'id' => 'chatcmpl-e2e-1',
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'Due to inclement weather, the outdoor conference has been _____ until next Monday.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'postponed', 'is_correct' => true, 'explanation' => 'Passive voice participle.'],
                                    ['label' => 'B', 'content' => 'postponing', 'is_correct' => false, 'explanation' => 'Active participle incorrect.'],
                                    ['label' => 'C', 'content' => 'postpone', 'is_correct' => false, 'explanation' => 'Base verb incorrect.'],
                                    ['label' => 'D', 'content' => 'postpones', 'is_correct' => false, 'explanation' => 'Third-person singular incorrect.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'The passive construction "has been + past participle" is required.',
                            ]),
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 100, 'total_tokens' => 300],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-valid-key');

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

        // Governance Invariants:
        $this->assertSame(ContentOrigin::Generated, $question->content_origin);
        $this->assertFalse($this->questionBank->fresh()->is_published, 'QuestionBank must remain unpublished draft.');
        $this->assertSame('draft', $this->questionBank->fresh()->status);
        $this->assertSame($this->questionBank->id, $question->question_bank_id);
        $this->assertSame(5, $question->part_number);
        $this->assertCount(4, $question->choices);
        $this->assertSame('A', $question->choices()->where('is_correct', true)->first()->label);
    }

    /**
     * Test Y: Invalid candidate failing quality gate does NOT materialize into a question
     */
    public function test_candidate_failing_quality_gate_does_not_materialize(): void
    {
        // Provider returns invalid candidate with placeholder text
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
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

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-invalid');

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
        $this->assertFalse(Question::where('prompt', 'like', '%placeholder%')->exists());
    }
}
