<?php

namespace App\Modules\QuestionEngine\Behaviours;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Contracts\AssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use InvalidArgumentException;

class ToeflIbtAssessmentBehaviour implements AssessmentBehaviour
{
    public function family(): AssessmentFamily
    {
        return AssessmentFamily::ToeflIbt;
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
        return true;
    }

    public function supportsFixedFullTestBlueprint(): bool
    {
        return false;
    }

    public function resolveSection(?int $partNumber = null, ?string $taskType = null, ?string $section = null): ?SectionType
    {
        if ($partNumber !== null) {
            throw new InvalidArgumentException('TOEFL iBT does not support part-number resolution.');
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
            throw new InvalidArgumentException('TOEFL iBT does not support TOEIC part numbers. Structure is section and task-type driven.');
        }

        $sectionRaw = $data['section'] ?? null;
        if (empty($sectionRaw)) {
            throw new InvalidArgumentException('TOEFL iBT requires a section identifier (reading, listening, speaking, writing).');
        }

        $validSections = ['reading', 'listening', 'speaking', 'writing'];
        if (!in_array($sectionRaw, $validSections, true)) {
            throw new InvalidArgumentException("Invalid section '{$sectionRaw}' for TOEFL iBT.");
        }

        return [
            'assessment_family' => AssessmentFamily::ToeflIbt->value,
            'section' => $sectionRaw,
            'task_type' => $data['task_type'] ?? null,
            'claim' => $data['claim'] ?? null,
        ];
    }

    public function getStructuralDimensions(): array
    {
        return [
            'section',
            'task_type',
            'claim',
            'skill',
            'construct',
            'proficiency_target',
            'difficulty',
        ];
    }

    public function getCapabilities(): AssessmentCapabilities
    {
        return new AssessmentCapabilities(
            supportsParts: false,
            supportsTaskTypes: true,
            supportsClaims: true,
            supportsAdaptiveBlueprint: true,
            supportsFixedFullTestBlueprint: false,
            supportsDomainSpecificity: false,
            supportsCefrTargeting: true,
            supportedSections: ['reading', 'listening', 'speaking', 'writing']
        );
    }
}
