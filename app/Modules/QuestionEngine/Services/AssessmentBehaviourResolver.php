<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Behaviours\GeneralEnglishAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\IeltsAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeflIbtAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeicAssessmentBehaviour;
use App\Modules\QuestionEngine\Contracts\AssessmentBehaviour;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use InvalidArgumentException;

class AssessmentBehaviourResolver
{
    /**
     * Resolve the authoritative structural behaviour adapter for a given assessment family.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(AssessmentFamily|string $family): AssessmentBehaviour
    {
        $resolvedFamily = $family instanceof AssessmentFamily ? $family : AssessmentFamily::tryFrom((string) $family);

        if ($resolvedFamily === null) {
            throw new InvalidArgumentException("Unknown or unsupported AssessmentFamily '{$family}'.");
        }

        return match ($resolvedFamily) {
            AssessmentFamily::Toeic => app(ToeicAssessmentBehaviour::class),
            AssessmentFamily::ToeflIbt => app(ToeflIbtAssessmentBehaviour::class),
            AssessmentFamily::GeneralEnglish => app(GeneralEnglishAssessmentBehaviour::class),
            AssessmentFamily::Ielts => app(IeltsAssessmentBehaviour::class),
        };
    }
}
