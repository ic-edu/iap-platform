<?php

namespace App\Modules\QuestionEngine\Behaviours;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Contracts\AssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Services\PartConstructCompatibility;
use App\Services\ToeicQuestionValidator;
use InvalidArgumentException;

class ToeicAssessmentBehaviour implements AssessmentBehaviour
{
    public function family(): AssessmentFamily
    {
        return AssessmentFamily::Toeic;
    }

    public function supportsPartNumbers(): bool
    {
        return true;
    }

    public function supportsTaskTypes(): bool
    {
        return false;
    }

    public function supportsAdaptiveBlueprint(): bool
    {
        return false;
    }

    public function supportsFixedFullTestBlueprint(): bool
    {
        return true;
    }

    public function resolveSection(?int $partNumber = null, ?string $taskType = null, ?string $section = null): ?SectionType
    {
        if ($partNumber !== null && $partNumber >= 1 && $partNumber <= 7) {
            return PartConstructCompatibility::getCanonicalSectionForPart($partNumber);
        }

        if ($section !== null) {
            return SectionType::tryFrom($section);
        }

        return null;
    }

    public function validateStructure(AssessmentItemIdentity|array $identity): array
    {
        $data = $identity instanceof AssessmentItemIdentity ? $identity->toArray() : $identity;

        $partNumber = $data['part_number'] ?? null;
        if ($partNumber === null || !is_int($partNumber) || $partNumber < 1 || $partNumber > 7) {
            throw new InvalidArgumentException('TOEIC assessment requires a valid part_number between 1 and 7.');
        }

        $canonicalSection = PartConstructCompatibility::getCanonicalSectionForPart($partNumber);
        $section = $data['section'] ?? null;
        if ($section !== null && $section !== $canonicalSection->value) {
            throw new InvalidArgumentException("Section '{$section}' does not match canonical TOEIC Part {$partNumber} section '{$canonicalSection->value}'.");
        }

        return [
            'assessment_family' => AssessmentFamily::Toeic->value,
            'part_number' => $partNumber,
            'section' => $canonicalSection->value,
            'target_question_count' => ToeicQuestionValidator::getPartTargetQuestionCount($partNumber),
        ];
    }

    public function getStructuralDimensions(): array
    {
        return [
            'section',
            'part_number',
            'proficiency_target',
            'difficulty',
            'construct',
            'domain',
            'context',
        ];
    }

    public function getCapabilities(): AssessmentCapabilities
    {
        return new AssessmentCapabilities(
            supportsParts: true,
            supportsTaskTypes: false,
            supportsClaims: false,
            supportsAdaptiveBlueprint: false,
            supportsFixedFullTestBlueprint: true,
            supportsDomainSpecificity: true,
            supportsCefrTargeting: true,
            supportedSections: ['listening', 'reading']
        );
    }
}
