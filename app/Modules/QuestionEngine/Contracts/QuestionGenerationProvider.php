<?php

namespace App\Modules\QuestionEngine\Contracts;

use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\GenerationProviderResponse;

interface QuestionGenerationProvider
{
    /**
     * Get the identifying name of this provider (e.g. 'fake', 'null', 'openai', 'anthropic', 'gemini').
     */
    public function getProviderName(): string;

    /**
     * Execute the question generation request against the underlying provider.
     */
    public function generate(GenerationProviderRequest $request): GenerationProviderResponse;
}
