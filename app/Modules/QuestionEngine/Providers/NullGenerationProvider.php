<?php

namespace App\Modules\QuestionEngine\Providers;

use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\GenerationProviderResponse;
use App\Modules\QuestionEngine\Enums\GenerationErrorCode;

class NullGenerationProvider implements QuestionGenerationProvider
{
    public function __construct(
        protected ?string $failureReason = 'Null provider cannot generate questions in this environment.'
    ) {}

    public function getProviderName(): string
    {
        return 'null';
    }

    public function generate(GenerationProviderRequest $request): GenerationProviderResponse
    {
        return GenerationProviderResponse::failure(
            errorCode: GenerationErrorCode::ProviderUnavailable,
            errorMessage: $this->failureReason ?? 'Provider unavailable.',
            providerName: $this->getProviderName(),
            latencyMs: 1,
            metadata: ['batch_id' => $request->batchId, 'item_id' => $request->itemId]
        );
    }
}
