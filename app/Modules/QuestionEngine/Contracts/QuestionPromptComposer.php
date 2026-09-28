<?php

namespace App\Modules\QuestionEngine\Contracts;

use App\Modules\QuestionEngine\DTO\PromptComposition;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;

interface QuestionPromptComposer
{
    /**
     * Determine if this composer supports the given assessment family.
     */
    public function supports(AssessmentFamily $family): bool;

    /**
     * Compose a structured prompt payload for the given generation item.
     *
     * @param  array<string, mixed>  $config
     */
    public function compose(QuestionGenerationItem $item, array $config = []): PromptComposition;
}
