<?php

namespace App\Modules\QuestionEngine\Behaviours;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Contracts\AssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use InvalidArgumentException;

class GeneralEnglishAssessmentBehaviour implements AssessmentBehaviour
{
    public function family(): AssessmentFamily
    {
        return AssessmentFamily::GeneralEnglish;
    }

    public function supportsPartNumbers(): bool
    {
        return false;
    }

    public function supportsTaskTypes(): bool
    {
        return true;
    }

    public function supportsAdaptiveBlueprint(): bool
    {
        return false;
    }

    public function supportsFixedFullTestBlueprint(): bool
    {
        return false;
    }

    public function resolveSection(?int $partNumber = null, ?string $taskType = null, ?string $section = null): ?SectionType
    {
        if ($partNumber !== null) {
            throw new InvalidArgumentException('General English does not support TOEIC part numbers.');
        }

        if ($section !== null) {
            return SectionType::tryFrom($section);
        }

        return null;
    }

    public function validateStructure(AssessmentItemIdentity|array $identity): array
    {
        $data = $identity instanceof AssessmentItemIdentity ? $identity->toArray() : $identity;

        if (!empty($data['part_number'])) {
            throw new InvalidArgumentException('General English does not use TOEIC part numbers. Structure is CEFR and skill/curriculum-driven.');
        }

        return [
            'assessment_family' => AssessmentFamily::GeneralEnglish->value,
            'section' => $data['section'] ?? null,
            'skill' => $data['skill'] ?? null,
            'proficiency_target' => $data['proficiency_target'] ?? null,
            'construct' => $data['construct'] ?? null,
            'context' => $data['context'] ?? null,
        ];
    }

    public function getStructuralDimensions(): array
    {
        return [
            'skill',
            'proficiency_target',
            'construct',
            'context',
            'section',
            'difficulty',
        ];
    }

    public function getCapabilities(): AssessmentCapabilities
    {
        return new AssessmentCapabilities(
            supportsParts: false,
            supportsTaskTypes: true,
            supportsClaims: false,
            supportsAdaptiveBlueprint: false,
            supportsFixedFullTestBlueprint: false,
            supportsDomainSpecificity: true,
            supportsCefrTargeting: true,
            supportedSections: ['reading', 'listening', 'speaking', 'writing']
        );
    }
}
