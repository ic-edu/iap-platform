<?php

namespace App\Modules\QuestionEngine\Behaviours;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Contracts\AssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use InvalidArgumentException;

class IeltsAssessmentBehaviour implements AssessmentBehaviour
{
    public function family(): AssessmentFamily
    {
        return AssessmentFamily::Ielts;
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
        return true;
    }

    public function resolveSection(?int $partNumber = null, ?string $taskType = null, ?string $section = null): ?SectionType
    {
        if ($partNumber !== null) {
            throw new InvalidArgumentException('IELTS does not support TOEIC part numbers.');
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
            throw new InvalidArgumentException('IELTS does not use TOEIC Part 1–7 semantics. Structure is section and task-type driven.');
        }

        $sectionRaw = $data['section'] ?? null;
        if (!empty($sectionRaw)) {
            $validSections = ['reading', 'listening', 'speaking', 'writing'];
            if (!in_array($sectionRaw, $validSections, true)) {
                throw new InvalidArgumentException("Invalid section '{$sectionRaw}' for IELTS.");
            }
        }

        return [
            'assessment_family' => AssessmentFamily::Ielts->value,
            'section' => $sectionRaw,
            'task_type' => $data['task_type'] ?? null,
            'skill' => $data['skill'] ?? null,
        ];
    }

    public function getStructuralDimensions(): array
    {
        return [
            'section',
            'task_type',
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
            supportsClaims: false,
            supportsAdaptiveBlueprint: false,
            supportsFixedFullTestBlueprint: true,
            supportsDomainSpecificity: false,
            supportsCefrTargeting: true,
            supportedSections: ['listening', 'reading', 'writing', 'speaking']
        );
    }
}
