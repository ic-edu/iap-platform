<?php

namespace Tests\Feature;

use App\Integrations\QuestionGeneration\OpenAIQuestionGenerationProvider;
use App\Models\User;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
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
        $correctChoice = $question->choices()->where('is_correct', true)->first();
        $this->assertNotNull($correctChoice);
        $this->assertSame('postponed', $correctChoice->content);
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

    /**
     * Test Prompt Hash Canonicalization Invariants (A through F)
     */
    public function test_prompt_hash_canonicalization_rules(): void
    {
        // A. Associative key ordering does not change hash
        $compA1 = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'System prompt instructions',
            userPrompt: 'User prompt instructions',
            structuralConstraints: ['z_key' => 'last', 'a_key' => 'first', 'nested' => ['beta' => 2, 'alpha' => 1]],
            targetMetadata: ['part' => 5, 'family' => 'toeic'],
            schemaDefinition: ['type' => 'object', 'properties' => ['b' => ['type' => 'string'], 'a' => ['type' => 'number']]],
            examples: []
        );

        $compA2 = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'System prompt instructions',
            userPrompt: 'User prompt instructions',
            structuralConstraints: ['a_key' => 'first', 'nested' => ['alpha' => 1, 'beta' => 2], 'z_key' => 'last'],
            targetMetadata: ['family' => 'toeic', 'part' => 5],
            schemaDefinition: ['properties' => ['a' => ['type' => 'number'], 'b' => ['type' => 'string']], 'type' => 'object'],
            examples: []
        );

        $this->assertSame($compA1->computePromptHash(), $compA2->computePromptHash(), 'Associative key ordering must NOT change prompt hash.');

        // B. List ordering DOES change hash
        $compB1 = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            examples: ['example_1', 'example_2']
        );

        $compB2 = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            examples: ['example_2', 'example_1']
        );

        $this->assertNotSame($compB1->computePromptHash(), $compB2->computePromptHash(), 'List / sequential array ordering MUST change prompt hash.');

        // C. Same PromptComposition produces same hash
        $this->assertSame($compA1->computePromptHash(), $compA1->computePromptHash(), 'Same PromptComposition must deterministically yield identical hash.');

        // D. Changing system prompt changes hash
        $compD = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'Different system prompt',
            userPrompt: 'User prompt instructions',
            structuralConstraints: ['a_key' => 'first']
        );
        $this->assertNotSame($compA1->computePromptHash(), $compD->computePromptHash(), 'Changing system prompt must change prompt hash.');

        // E. Changing user prompt changes hash
        $compE = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'System prompt instructions',
            userPrompt: 'Different user prompt instructions',
            structuralConstraints: ['a_key' => 'first']
        );
        $this->assertNotSame($compA1->computePromptHash(), $compE->computePromptHash(), 'Changing user prompt must change prompt hash.');

        // F. Changing schema changes hash
        $compF = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'System prompt instructions',
            userPrompt: 'User prompt instructions',
            schemaDefinition: ['type' => 'object', 'properties' => ['extra' => ['type' => 'boolean']]]
        );
        $this->assertNotSame($compA1->computePromptHash(), $compF->computePromptHash(), 'Changing schema definition must change prompt hash.');
    }

    /**
     * Test Secret Leakage Prevention across all lifecycle structures
     */
    public function test_secret_leakage_prevention_across_all_structures(): void
    {
        $sentinelSecret = 'sk-proj-SUPER-SECRET-SENTINEL-KEY-9876543210-NEVER-LEAK';

        Http::fake([
            'https://api.openai.com/v1/*' => Http::sequence()
                // 1. Success response
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => json_encode([
                                    'schema_version' => 'generated_question_candidate_v1',
                                    'prompt' => 'All visitors must _____ their identification badges at the reception desk.',
                                    'passage_text' => null,
                                    'audio_script' => null,
                                    'choices' => [
                                        ['label' => 'A', 'content' => 'show', 'is_correct' => true, 'explanation' => 'Base verb.'],
                                        ['label' => 'B', 'content' => 'shows', 'is_correct' => false, 'explanation' => 'Inflected verb.'],
                                        ['label' => 'C', 'content' => 'showing', 'is_correct' => false, 'explanation' => 'Participle.'],
                                        ['label' => 'D', 'content' => 'showed', 'is_correct' => false, 'explanation' => 'Past tense.'],
                                    ],
                                    'correct_answer' => 'A',
                                    'explanation' => 'Modal verb must require base form.',
                                ]),
                            ],
                        ],
                    ],
                ], 200)
                // 2. HTTP 401 error response
                ->push(['error' => ['message' => 'Unauthorized invalid key: '.$sentinelSecret]], 401),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: $sentinelSecret);

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

        // Success Item
        $item1 = $orchestrator->processItem($items[0]);
        $this->assertSame(GenerationItemStatus::Materialized, $item1->status);

        // Error Item
        $item2 = $orchestrator->processItem($items[1]);
        $this->assertSame(GenerationItemStatus::Failed, $item2->status);

        // Assert secret is NEVER stored in database columns or metadata
        foreach ([$item1, $item2] as $processed) {
            $itemDump = json_encode($processed->fresh()->toArray());
            $this->assertStringNotContainsString($sentinelSecret, (string) $itemDump, 'Item database columns must not contain secrets.');
            $this->assertStringNotContainsString('SUPER-SECRET-SENTINEL', (string) $itemDump);

            if ($processed->prompt_payload) {
                $this->assertStringNotContainsString($sentinelSecret, json_encode($processed->prompt_payload));
            }
            if ($processed->provider_request_metadata) {
                $this->assertStringNotContainsString($sentinelSecret, json_encode($processed->provider_request_metadata));
            }
            if ($processed->raw_output) {
                $this->assertStringNotContainsString($sentinelSecret, json_encode($processed->raw_output));
            }
            if ($processed->normalized_output) {
                $this->assertStringNotContainsString($sentinelSecret, json_encode($processed->normalized_output));
            }
            if ($processed->validation_result) {
                $this->assertStringNotContainsString($sentinelSecret, json_encode($processed->validation_result));
            }
            if ($processed->last_error_message) {
                $this->assertStringNotContainsString($sentinelSecret, $processed->last_error_message);
            }
        }

        // Check materialized Question record
        $question = Question::find($item1->question_id);
        $this->assertNotNull($question);
        $questionDump = json_encode($question->toArray());
        $this->assertStringNotContainsString($sentinelSecret, (string) $questionDump, 'Question entity must not contain secrets.');
        $this->assertStringNotContainsString($sentinelSecret, json_encode($question->generation_metadata));
    }

    /**
     * Test Retry Classification Matrix for all failure conditions
     */
    public function test_retryability_classification_matrix_precision(): void
    {
        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-key');

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

        // 1. Missing API key -> non-retryable
        $noKeyProvider = new OpenAIQuestionGenerationProvider(apiKey: '');
        $resNoKey = $noKeyProvider->generate($genRequest);
        $this->assertFalse($resNoKey->isSuccess);
        $this->assertSame(GenerationErrorCode::ProviderUnavailable, $resNoKey->errorCode);
        $this->assertFalse($resNoKey->metadata['retryable'] ?? true, 'Missing API key must not be retryable.');

        // 2. Unsupported scope -> non-retryable
        $unsupportedComp = new PromptComposition(
            promptContractVersion: 'v1',
            systemPrompt: 'sys',
            userPrompt: 'usr',
            targetMetadata: ['assessment_family' => 'toefl', 'section' => 'reading', 'part_number' => 1]
        );
        $resScope = $provider->generate(new GenerationProviderRequest(
            batchId: $batch->id,
            itemId: $item->id,
            slotSequence: 1,
            promptComposition: $unsupportedComp
        ));
        $this->assertFalse($resScope->isSuccess);
        $this->assertSame(GenerationErrorCode::StandardMismatch, $resScope->errorCode);
        $this->assertFalse($resScope->metadata['retryable'] ?? true, 'Unsupported scope must not be retryable.');

        // 3. HTTP status matrix
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

        $sequence = Http::fakeSequence('https://api.openai.com/v1/*');
        foreach ($statuses as $statusCode => $expected) {
            $sequence->push(['error' => ['message' => "Error status {$statusCode}"]], $statusCode);
        }
        $sequence->push(['choices' => [['message' => ['role' => 'assistant', 'refusal' => 'Content violated safety policy']]]], 200);
        $sequence->push(['choices' => [['message' => ['role' => 'assistant', 'content' => '{broken json invalid syntax']]]], 200);

        foreach ($statuses as $statusCode => $expected) {
            $resp = $provider->generate($genRequest);
            $this->assertFalse($resp->isSuccess);
            $this->assertSame($expected['code'], $resp->errorCode, "HTTP {$statusCode} expected code {$expected['code']->value}");
            $this->assertSame($expected['retryable'], $resp->metadata['retryable'] ?? null, "HTTP {$statusCode} retryable should be ".($expected['retryable'] ? 'true' : 'false'));
        }

        // 4. Provider Refusal -> non-retryable
        $resRefusal = $provider->generate($genRequest);
        $this->assertFalse($resRefusal->isSuccess);
        $this->assertSame(GenerationErrorCode::ProviderError, $resRefusal->errorCode);
        $this->assertFalse($resRefusal->metadata['retryable'] ?? true, 'Provider refusal must not be retryable.');

        // 5. Malformed JSON output -> non-retryable
        $resMalformed = $provider->generate($genRequest);
        $this->assertFalse($resMalformed->isSuccess);
        $this->assertSame(GenerationErrorCode::InvalidProviderResponse, $resMalformed->errorCode);
        $this->assertFalse($resMalformed->metadata['retryable'] ?? true, 'Malformed JSON output must not be retryable.');
    }

    /**
     * Test Dynamic Model Configuration
     */
    public function test_dynamic_model_configuration_passes_to_provider_request(): void
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'model' => 'gpt-4o-2024-08-06',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'The software update will _____ system performance significantly.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'enhance', 'is_correct' => true, 'explanation' => 'Base verb.'],
                                    ['label' => 'B', 'content' => 'enhances', 'is_correct' => false, 'explanation' => 'Inflected.'],
                                    ['label' => 'C', 'content' => 'enhancing', 'is_correct' => false, 'explanation' => 'Participle.'],
                                    ['label' => 'D', 'content' => 'enhancement', 'is_correct' => false, 'explanation' => 'Noun.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'Base verb follows modal will.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $customModelProvider = new OpenAIQuestionGenerationProvider(
            apiKey: 'sk-test-custom-model',
            model: 'gpt-4o-2024-08-06'
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
            $customModelProvider
        );

        $processed = $orchestrator->processItem($item);
        $this->assertSame(GenerationItemStatus::Materialized, $processed->status);
        $this->assertSame('gpt-4o-2024-08-06', $processed->raw_output['model_name']);

        Http::assertSent(function (Request $request) {
            return ($request->data()['model'] ?? '') === 'gpt-4o-2024-08-06';
        });
    }

    /**
     * Test Sprint 7A.2 Strict JSON Schema Compliance for Part 5 (Requirements A through L)
     */
    public function test_part5_schema_strict_json_schema_compliance_and_provenance(): void
    {
        $composer = new ToeicPromptComposer;
        $req = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 5, 'item_count' => 1]);
        $plan = $this->toeicPlanner->plan($req);
        $batch = $this->batchFactory->createFromPlan($plan, ['question_bank_id' => $this->questionBank->id]);
        $item = $batch->items->first();

        // 1. Compose PromptComposition from production composer
        $composition = $composer->compose($item);
        $schema = $composition->schemaDefinition;

        // A, B, C, D: Recursive Strict Schema Verification on PromptComposition
        $this->assertOpenAiStrictSchemaCompliant($schema);

        // Assert specific root requirements
        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertArrayNotHasKey('anyOf', $schema);

        // B: All root properties are required
        $expectedRootProps = ['schema_version', 'prompt', 'passage_text', 'audio_script', 'choices', 'correct_answer', 'explanation'];
        sort($expectedRootProps);
        $actualRootRequired = $schema['required'];
        sort($actualRootRequired);
        $this->assertSame($expectedRootProps, $actualRootRequired, 'All root properties must be explicitly in required array.');

        // E: Nullable Part 5 non-applicable fields are valid
        $this->assertSame(['string', 'null'], $schema['properties']['passage_text']['type']);
        $this->assertSame(['string', 'null'], $schema['properties']['audio_script']['type']);

        // Assert choices structure
        $choicesSchema = $schema['properties']['choices'];
        $this->assertSame('array', $choicesSchema['type']);
        $this->assertSame(4, $choicesSchema['minItems']);
        $this->assertSame(4, $choicesSchema['maxItems']);

        // C, D: All choice properties are required and additionalProperties=false
        $choiceItemSchema = $choicesSchema['items'];
        $this->assertSame('object', $choiceItemSchema['type']);
        $this->assertFalse($choiceItemSchema['additionalProperties']);
        $expectedChoiceProps = ['content', 'explanation', 'is_correct', 'label'];
        $actualChoiceRequired = $choiceItemSchema['required'];
        sort($actualChoiceRequired);
        $this->assertSame($expectedChoiceProps, $actualChoiceRequired);
        $this->assertSame(['string', 'null'], $choiceItemSchema['properties']['explanation']['type']);

        // Provider Execution & Request Schema Matching (F, G, H, I, J, K)
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response([
                'id' => 'chatcmpl-strict-test',
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'schema_version' => 'generated_question_candidate_v1',
                                'prompt' => 'The quarterly sales figures _____ substantial growth across all international divisions.',
                                'passage_text' => null,
                                'audio_script' => null,
                                'choices' => [
                                    ['label' => 'A', 'content' => 'indicate', 'is_correct' => true, 'explanation' => 'Plural verb matches figures.'],
                                    ['label' => 'B', 'content' => 'indicates', 'is_correct' => false, 'explanation' => 'Singular verb mismatch.'],
                                    ['label' => 'C', 'content' => 'indication', 'is_correct' => false, 'explanation' => 'Noun cannot occupy verb slot.'],
                                    ['label' => 'D', 'content' => 'indicative', 'is_correct' => false, 'explanation' => 'Adjective cannot occupy verb slot.'],
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'The plural subject "figures" requires the plural base verb "indicate".',
                            ]),
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 220, 'completion_tokens' => 90, 'total_tokens' => 310],
            ], 200),
        ]);

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-strict-schema-key');
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

        // F: Provider sends exact PromptComposition schema unchanged (no rewriting)
        Http::assertSent(function (Request $req) use ($schema) {
            $data = $req->data();
            $responseFormat = $data['response_format'] ?? [];
            $this->assertSame('json_schema', $responseFormat['type'] ?? null);
            $this->assertTrue($responseFormat['json_schema']['strict'] ?? false);
            $this->assertSame($schema, $responseFormat['json_schema']['schema']);

            return true;
        });

        // G: Prompt hash corresponds to exact schema sent
        $computedHash = $composition->computePromptHash();
        $this->assertSame($computedHash, $processedItem->prompt_payload['prompt_hash']);

        // H, I: Provider output without metadata recovers structural identity from trusted item
        $question = Question::find($processedItem->question_id);
        $this->assertNotNull($question);
        $this->assertSame(AssessmentFamily::Toeic, $question->assessment_family);
        $this->assertSame(5, $question->part_number);
        $this->assertSame(SectionType::Reading, $question->section);
        $this->assertSame($item->standard_version, $question->standard_version);
        $this->assertSame(ContentOrigin::Generated, $question->content_origin);
        $this->assertFalse($question->questionBank->is_published);
    }

    /**
     * Recursive validator ensuring strict schema compatibility with OpenAI strict mode.
     */
    protected function assertOpenAiStrictSchemaCompliant(array $schema): void
    {
        $this->assertArrayNotHasKey('anyOf', $schema, 'Schema must not use anyOf at root.');
        $this->assertSame('object', $schema['type'] ?? null, 'Root schema type must be object.');

        $this->recursivelyAssertStrictObjectSchema($schema);
    }

    protected function recursivelyAssertStrictObjectSchema(array $schema): void
    {
        if (($schema['type'] ?? null) === 'object' || isset($schema['properties'])) {
            $this->assertArrayHasKey('additionalProperties', $schema, 'Object schema must declare additionalProperties.');
            $this->assertFalse($schema['additionalProperties'], 'additionalProperties must be false.');

            $this->assertArrayHasKey('properties', $schema, 'Object schema must declare properties.');
            $this->assertArrayHasKey('required', $schema, 'Object schema must declare required array.');

            $propertyKeys = array_keys($schema['properties']);
            $requiredKeys = (array) $schema['required'];

            sort($propertyKeys);
            sort($requiredKeys);

            $this->assertSame(
                $propertyKeys,
                $requiredKeys,
                'Every field in properties must be listed in required, and vice versa.'
            );

            foreach ($schema['properties'] as $propName => $propDef) {
                if (is_array($propDef)) {
                    $this->recursivelyAssertStrictObjectSchema($propDef);
                }
            }
        }

        if (($schema['type'] ?? null) === 'array' && isset($schema['items']) && is_array($schema['items'])) {
            $this->recursivelyAssertStrictObjectSchema($schema['items']);
        }
    }

    /**
     * Test OpenAI provider fails closed when PromptComposition schemaDefinition is empty, sending zero network requests
     */
    public function test_openai_fails_closed_when_schema_definition_is_empty_and_sends_zero_requests(): void
    {
        Http::fake();

        $provider = new OpenAIQuestionGenerationProvider(apiKey: 'sk-test-empty-schema-key');

        $promptComp = new PromptComposition(
            promptContractVersion: 'question_generation_v1',
            systemPrompt: 'You are a test writer.',
            userPrompt: 'Generate a test item.',
            schemaDefinition: [], // Empty schema definition
            targetMetadata: [
                'assessment_family' => AssessmentFamily::Toeic->value,
                'section' => 'reading',
                'part_number' => 5,
            ]
        );

        $genReq = new GenerationProviderRequest(
            batchId: 'batch-test-1',
            itemId: 'item-test-1',
            slotSequence: 1,
            promptComposition: $promptComp
        );

        $response = $provider->generate($genReq);

        $this->assertFalse($response->isSuccess);
        $this->assertSame(GenerationErrorCode::SchemaValidationFailed, $response->errorCode);
        $this->assertFalse($response->metadata['retryable']);
        $this->assertSame($promptComp->computePromptHash(), $response->metadata['prompt_hash']);
        $this->assertStringContainsString('schemaDefinition is missing or empty', $response->errorMessage);

        // Prove zero network requests occur
        Http::assertNothingSent();
    }
}
