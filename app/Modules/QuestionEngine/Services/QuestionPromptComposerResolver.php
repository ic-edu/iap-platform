<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Contracts\QuestionPromptComposer;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use InvalidArgumentException;

class QuestionPromptComposerResolver
{
    /**
     * @var list<QuestionPromptComposer>
     */
    protected array $composers = [];

    /**
     * @param  list<QuestionPromptComposer>  $composers
     */
    public function __construct(array $composers = [])
    {
        if (empty($composers)) {
            $this->composers = [
                new ToeicPromptComposer,
                new ToeflPromptComposer,
            ];
        } else {
            $this->composers = $composers;
        }
    }

    public function registerComposer(QuestionPromptComposer $composer): self
    {
        $this->composers[] = $composer;

        return $this;
    }

    public function resolve(AssessmentFamily|string $family): QuestionPromptComposer
    {
        $familyEnum = $family instanceof AssessmentFamily ? $family : AssessmentFamily::from((string) $family);

        foreach ($this->composers as $composer) {
            if ($composer->supports($familyEnum)) {
                return $composer;
            }
        }

        throw new InvalidArgumentException("No prompt composer registered for assessment family [{$familyEnum->value}].");
    }
}
