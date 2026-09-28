<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\GenerationErrorCode;

final class GenerationProviderResponse
{
    /**
     * @param  array<string, mixed>|null  $parsedPayload
     * @param  array<string, int>  $tokenUsage
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $isSuccess,
        public readonly ?string $providerName = null,
        public readonly ?string $modelName = null,
        public readonly ?string $rawContent = null,
        public readonly ?array $parsedPayload = null,
        public readonly ?GenerationErrorCode $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly int $latencyMs = 0,
        public readonly array $tokenUsage = [],
        public readonly array $metadata = []
    ) {}

    /**
     * Factory for successful provider response.
     *
     * @param  string|array<string, mixed>  $content
     * @param  array<string, mixed>  $parsedPayload
     * @param  array<string, int>  $tokenUsage
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        string $providerName,
        string $modelName,
        string|array $content,
        array $parsedPayload = [],
        int $latencyMs = 0,
        array $tokenUsage = [],
        array $metadata = []
    ): self {
        $rawString = is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content;
        $parsed = !empty($parsedPayload) ? $parsedPayload : (is_array($content) ? $content : null);

        return new self(
            isSuccess: true,
            providerName: $providerName,
            modelName: $modelName,
            rawContent: $rawString,
            parsedPayload: $parsed,
            errorCode: null,
            errorMessage: null,
            latencyMs: $latencyMs,
            tokenUsage: $tokenUsage,
            metadata: $metadata,
        );
    }

    /**
     * Factory for failed provider response.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function failure(
        GenerationErrorCode $errorCode,
        string $errorMessage,
        ?string $providerName = null,
        int $latencyMs = 0,
        array $metadata = []
    ): self {
        return new self(
            isSuccess: false,
            providerName: $providerName,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            latencyMs: $latencyMs,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isSuccess: (bool) ($data['is_success'] ?? false),
            providerName: isset($data['provider_name']) ? (string) $data['provider_name'] : null,
            modelName: isset($data['model_name']) ? (string) $data['model_name'] : null,
            rawContent: isset($data['raw_content']) ? (string) $data['raw_content'] : null,
            parsedPayload: isset($data['parsed_payload']) && is_array($data['parsed_payload']) ? $data['parsed_payload'] : null,
            errorCode: isset($data['error_code']) ? ($data['error_code'] instanceof GenerationErrorCode ? $data['error_code'] : GenerationErrorCode::tryFrom((string) $data['error_code'])) : null,
            errorMessage: isset($data['error_message']) ? (string) $data['error_message'] : null,
            latencyMs: (int) ($data['latency_ms'] ?? 0),
            tokenUsage: (array) ($data['token_usage'] ?? []),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_success' => $this->isSuccess,
            'provider_name' => $this->providerName,
            'model_name' => $this->modelName,
            'raw_content' => $this->rawContent,
            'parsed_payload' => $this->parsedPayload,
            'error_code' => $this->errorCode?->value,
            'error_message' => $this->errorMessage,
            'latency_ms' => $this->latencyMs,
            'token_usage' => $this->tokenUsage,
            'metadata' => $this->metadata,
        ];
    }
}
