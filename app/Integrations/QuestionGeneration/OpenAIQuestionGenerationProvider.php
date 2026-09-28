<?php

namespace App\Integrations\QuestionGeneration;

use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\GenerationProviderResponse;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIQuestionGenerationProvider implements QuestionGenerationProvider
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $model = null,
        protected ?string $baseUrl = null,
        protected int $timeout = 30,
        protected int $connectTimeout = 10
    ) {
        $this->apiKey = $apiKey ?? config('question_generation.openai.api_key');
        $this->model = $model ?? config('question_generation.openai.model', 'gpt-4o-mini');
        $this->baseUrl = rtrim($baseUrl ?? config('question_generation.openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->timeout = $timeout ?: (int) config('question_generation.openai.timeout', 30);
        $this->connectTimeout = $connectTimeout ?: (int) config('question_generation.openai.connect_timeout', 10);
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function generate(GenerationProviderRequest $request): GenerationProviderResponse
    {
        $startTime = microtime(true);

        // 1. Strict Sprint 7A Scope Validation
        // Allow only: assessment_family = toeic, section = reading, part_number = 5
        $targetMetadata = $request->promptComposition->targetMetadata;
        $family = $targetMetadata['assessment_family'] ?? null;
        $section = strtolower((string) ($targetMetadata['section'] ?? ''));
        $partNumber = isset($targetMetadata['part_number']) ? (int) $targetMetadata['part_number'] : null;

        $isFamilyToeic = ($family === AssessmentFamily::Toeic->value || $family === AssessmentFamily::Toeic);
        $isSectionReading = ($section === 'reading');
        $isPart5 = ($partNumber === 5);

        if (!$isFamilyToeic || !$isSectionReading || !$isPart5) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return GenerationProviderResponse::failure(
                errorCode: GenerationErrorCode::StandardMismatch,
                errorMessage: 'Sprint 7A OpenAI production generation provider currently only supports TOEIC Reading Part 5 items.',
                providerName: $this->getProviderName(),
                latencyMs: $latencyMs,
                metadata: [
                    'family' => is_object($family) ? $family->value : $family,
                    'section' => $section,
                    'part_number' => $partNumber,
                    'prompt_hash' => $request->promptComposition->computePromptHash(),
                ]
            );
        }

        // 2. Validate API Key Presence (Fail Closed)
        if (empty($this->apiKey)) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return GenerationProviderResponse::failure(
                errorCode: GenerationErrorCode::ProviderUnavailable,
                errorMessage: 'OpenAI API key is missing or not configured.',
                providerName: $this->getProviderName(),
                latencyMs: $latencyMs,
                metadata: [
                    'model' => $this->model,
                    'prompt_hash' => $request->promptComposition->computePromptHash(),
                ]
            );
        }

        // 3. Build Request Payload for OpenAI Responses with Structured Output
        $endpoint = "{$this->baseUrl}/chat/completions";

        $schemaDefinition = $request->promptComposition->schemaDefinition;
        $jsonSchema = !empty($schemaDefinition) ? $schemaDefinition : [
            'type' => 'object',
            'required' => ['schema_version', 'prompt', 'choices', 'correct_answer', 'explanation'],
            'properties' => [
                'schema_version' => ['type' => 'string'],
                'prompt' => ['type' => 'string'],
                'passage_text' => ['type' => ['string', 'null']],
                'audio_script' => ['type' => ['string', 'null']],
                'choices' => ['type' => 'array'],
                'correct_answer' => ['type' => 'string'],
                'explanation' => ['type' => 'string'],
                'metadata' => ['type' => 'object'],
            ],
        ];

        // Ensure additionalProperties is false if strict schema is required
        if (!isset($jsonSchema['additionalProperties'])) {
            $jsonSchema['additionalProperties'] = false;
        }

        $requestPayload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $request->promptComposition->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $request->promptComposition->userPrompt,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'generated_question_candidate',
                    'strict' => true,
                    'schema' => $jsonSchema,
                ],
            ],
            'temperature' => (float) ($request->generationParameters['temperature'] ?? 0.7),
        ];

        if (isset($request->generationParameters['max_tokens'])) {
            $requestPayload['max_tokens'] = (int) $request->generationParameters['max_tokens'];
        }

        // 4. Transport Execution with Latency and Safe Error Translation
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($endpoint, $requestPayload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            // Handle Non-2xx Responses
            if ($response->failed()) {
                $status = $response->status();
                $errorCode = match ($status) {
                    401, 403 => GenerationErrorCode::ProviderUnavailable,
                    408, 504 => GenerationErrorCode::ProviderTimeout,
                    429 => GenerationErrorCode::ProviderError,
                    default => GenerationErrorCode::ProviderError,
                };

                $errorData = $response->json('error');
                $errorMsg = is_array($errorData) && !empty($errorData['message'])
                    ? (string) $errorData['message']
                    : "OpenAI API request failed with HTTP status {$status}.";

                return GenerationProviderResponse::failure(
                    errorCode: $errorCode,
                    errorMessage: "Provider HTTP {$status}: {$errorMsg}",
                    providerName: $this->getProviderName(),
                    latencyMs: $latencyMs,
                    metadata: [
                        'status_code' => $status,
                        'model' => $this->model,
                        'prompt_hash' => $request->promptComposition->computePromptHash(),
                    ]
                );
            }

            // 5. Extract Choice and Structured Content
            $responseData = $response->json();
            if (!is_array($responseData) || empty($responseData['choices'][0]['message'])) {
                return GenerationProviderResponse::failure(
                    errorCode: GenerationErrorCode::InvalidProviderResponse,
                    errorMessage: 'OpenAI API returned an invalid response structure with no choices.',
                    providerName: $this->getProviderName(),
                    latencyMs: $latencyMs,
                    metadata: [
                        'model' => $this->model,
                        'prompt_hash' => $request->promptComposition->computePromptHash(),
                    ]
                );
            }

            $message = $responseData['choices'][0]['message'];

            // Check Provider Refusal
            if (!empty($message['refusal'])) {
                return GenerationProviderResponse::failure(
                    errorCode: GenerationErrorCode::ProviderError,
                    errorMessage: "OpenAI model refused generation: {$message['refusal']}",
                    providerName: $this->getProviderName(),
                    latencyMs: $latencyMs,
                    metadata: [
                        'refusal' => $message['refusal'],
                        'model' => $this->model,
                        'prompt_hash' => $request->promptComposition->computePromptHash(),
                    ]
                );
            }

            $rawContent = $message['content'] ?? null;
            if (empty($rawContent) || !is_string($rawContent)) {
                return GenerationProviderResponse::failure(
                    errorCode: GenerationErrorCode::InvalidProviderResponse,
                    errorMessage: 'OpenAI API returned empty or non-string message content.',
                    providerName: $this->getProviderName(),
                    latencyMs: $latencyMs,
                    metadata: [
                        'model' => $this->model,
                        'prompt_hash' => $request->promptComposition->computePromptHash(),
                    ]
                );
            }

            // Parse Structured JSON Content
            $parsedPayload = json_decode($rawContent, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedPayload)) {
                return GenerationProviderResponse::failure(
                    errorCode: GenerationErrorCode::InvalidProviderResponse,
                    errorMessage: 'OpenAI structured output failed to parse as valid JSON: '.json_last_error_msg(),
                    providerName: $this->getProviderName(),
                    latencyMs: $latencyMs,
                    metadata: [
                        'model' => $this->model,
                        'prompt_hash' => $request->promptComposition->computePromptHash(),
                    ]
                );
            }

            // Extract Token Usage
            $usage = $responseData['usage'] ?? [];
            $tokenUsage = [
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            ];

            return GenerationProviderResponse::success(
                providerName: $this->getProviderName(),
                modelName: (string) ($responseData['model'] ?? $this->model),
                content: $rawContent,
                parsedPayload: $parsedPayload,
                latencyMs: $latencyMs,
                tokenUsage: $tokenUsage,
                metadata: [
                    'prompt_hash' => $request->promptComposition->computePromptHash(),
                    'finish_reason' => $responseData['choices'][0]['finish_reason'] ?? 'stop',
                ]
            );
        } catch (ConnectionException $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return GenerationProviderResponse::failure(
                errorCode: GenerationErrorCode::ProviderTimeout,
                errorMessage: 'OpenAI connection/timeout failure: '.$e->getMessage(),
                providerName: $this->getProviderName(),
                latencyMs: $latencyMs,
                metadata: [
                    'model' => $this->model,
                    'prompt_hash' => $request->promptComposition->computePromptHash(),
                ]
            );
        } catch (RequestException $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return GenerationProviderResponse::failure(
                errorCode: GenerationErrorCode::ProviderError,
                errorMessage: 'OpenAI request failure: '.$e->getMessage(),
                providerName: $this->getProviderName(),
                latencyMs: $latencyMs,
                metadata: [
                    'model' => $this->model,
                    'prompt_hash' => $request->promptComposition->computePromptHash(),
                ]
            );
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return GenerationProviderResponse::failure(
                errorCode: GenerationErrorCode::ProviderError,
                errorMessage: 'OpenAI unhandled error: '.$e->getMessage(),
                providerName: $this->getProviderName(),
                latencyMs: $latencyMs,
                metadata: [
                    'model' => $this->model,
                    'prompt_hash' => $request->promptComposition->computePromptHash(),
                ]
            );
        }
    }
}
