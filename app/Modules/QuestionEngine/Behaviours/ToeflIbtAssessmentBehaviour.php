<?php

namespace App\Modules\QuestionEngine\Behaviours;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Contracts\AssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\DTO\ToeflSectionSpecification;
use App\Modules\QuestionEngine\DTO\ToeflTaskSpecification;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
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

    public function supportsClaims(): bool
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

    public function supportsCefrTargeting(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function supportedSections(): array
    {
        return ['reading', 'listening', 'writing', 'speaking'];
    }

    /**
     * @return list<ToeflTaskType>
     */
    public function taskTypesForSection(string|SectionType $section): array
    {
        return ToeflTaskType::forSection($section);
    }

    /**
     * Official section claim for a given task type.
     *
     * @return list<ToeflClaim>
     */
    public function claimsForTaskType(ToeflTaskType|string $taskType): array
    {
        $resolved = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown TOEFL task type '{$taskType}'.");
        }

        return [$resolved->claim()];
    }

    /**
     * Official blueprint skills/subskills mapped to a task type.
     *
     * @return list<ToeflSkill>
     */
    public function skillsForTaskType(ToeflTaskType|string $taskType): array
    {
        $resolved = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown TOEFL task type '{$taskType}'.");
        }

        return $resolved->skills();
    }

    public function isTaskTypeCompatibleWithSection(ToeflTaskType|string $taskType, string|SectionType $section): bool
    {
        $resolvedTask = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);
        if ($resolvedTask === null) {
            return false;
        }

        $secStr = $section instanceof SectionType ? $section->value : strtolower(trim((string) $section));

        return $resolvedTask->section() === $secStr;
    }

    public function isClaimCompatibleWithTaskType(ToeflClaim|string $claim, ToeflTaskType|string $taskType): bool
    {
        $resolvedTask = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);
        $resolvedClaim = ToeflClaim::resolve($claim);

        if ($resolvedTask === null || $resolvedClaim === null) {
            return false;
        }

        return $resolvedTask->claim() === $resolvedClaim;
    }

    public function isSkillCompatibleWithTaskType(ToeflSkill|string $skill, ToeflTaskType|string $taskType): bool
    {
        $resolvedTask = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);
        $resolvedSkill = $skill instanceof ToeflSkill ? $skill : ToeflSkill::tryFrom((string) $skill);

        if ($resolvedTask === null || $resolvedSkill === null) {
            return false;
        }

        return in_array($resolvedSkill, $resolvedTask->skills(), true);
    }

    public function getTaskSpecification(ToeflTaskType|string $taskType): ToeflTaskSpecification
    {
        return ToeflTaskSpecification::forTaskType($taskType);
    }

    public function getSectionSpecification(string|SectionType $section): ToeflSectionSpecification
    {
        return ToeflSectionSpecification::forSection($section);
    }

    public function resolveSection(?int $partNumber = null, ?string $taskType = null, ?string $section = null): ?SectionType
    {
        if ($partNumber !== null) {
            throw new InvalidArgumentException('TOEFL iBT does not support part-number resolution.');
        }

        if ($taskType !== null) {
            $resolvedTask = ToeflTaskType::tryFrom($taskType);
            if ($resolvedTask !== null) {
                return SectionType::tryFrom($resolvedTask->section());
            }
        }

        if ($section !== null) {
            return SectionType::tryFrom(strtolower(trim($section)));
        }

        return null;
    }

    /**
     * Validate the structural dimensions of a TOEFL item according to official ETS 2026 rules.
     *
     * @param  AssessmentItemIdentity|array<string, mixed>  $identity
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function validateStructure(AssessmentItemIdentity|array $identity): array
    {
        return $this->validateStructuralIdentity($identity);
    }

    /**
     * Canonical structural validation for TOEFL iBT items.
     *
     * @param  AssessmentItemIdentity|array<string, mixed>  $identity
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function validateStructuralIdentity(AssessmentItemIdentity|array $identity): array
    {
        $data = $identity instanceof AssessmentItemIdentity ? $identity->toArray() : $identity;

        // Invariant: part_number must be NULL for TOEFL
        if (array_key_exists('part_number', $data) && $data['part_number'] !== null) {
            throw new InvalidArgumentException('TOEFL iBT does not support TOEIC part numbers. Structure is section and task-type driven.');
        }

        $taskTypeObj = null;
        $taskTypeRaw = $data['task_type'] ?? null;
        if (!empty($taskTypeRaw)) {
            $taskTypeObj = $taskTypeRaw instanceof ToeflTaskType ? $taskTypeRaw : ToeflTaskType::tryFrom((string) $taskTypeRaw);
            if ($taskTypeObj === null) {
                throw new InvalidArgumentException("Invalid task_type '{$taskTypeRaw}' for TOEFL iBT.");
            }
        }

        $sectionRaw = $data['section'] ?? null;
        if (empty($sectionRaw) && $taskTypeObj !== null) {
            $sectionRaw = $taskTypeObj->section();
        }

        if (empty($sectionRaw)) {
            throw new InvalidArgumentException('TOEFL iBT requires a section identifier (reading, listening, speaking, writing) or a valid task_type.');
        }

        $sectionNormalized = strtolower(trim((string) $sectionRaw));
        if (!in_array($sectionNormalized, $this->supportedSections(), true)) {
            throw new InvalidArgumentException("Invalid section '{$sectionRaw}' for TOEFL iBT.");
        }

        if ($taskTypeObj !== null && $taskTypeObj->section() !== $sectionNormalized) {
            throw new InvalidArgumentException("Task type '{$taskTypeObj->value}' belongs to section '{$taskTypeObj->section()}', not '{$sectionNormalized}'.");
        }

        $claimObj = null;
        $claimRaw = $data['claim'] ?? null;
        if (!empty($claimRaw)) {
            $claimObj = ToeflClaim::resolve($claimRaw);
            if ($claimObj === null) {
                throw new InvalidArgumentException("Invalid claim '{$claimRaw}' for TOEFL iBT.");
            }

            if ($claimObj->section() !== $sectionNormalized) {
                throw new InvalidArgumentException("Claim '{$claimObj->value}' belongs to section '{$claimObj->section()}', not '{$sectionNormalized}'.");
            }
        }

        $skillObj = null;
        $skillRaw = $data['skill'] ?? null;
        if (!empty($skillRaw)) {
            $skillObj = $skillRaw instanceof ToeflSkill ? $skillRaw : ToeflSkill::tryFrom((string) $skillRaw);
            if ($skillObj === null) {
                throw new InvalidArgumentException("Invalid skill '{$skillRaw}' for TOEFL iBT.");
            }

            if ($skillObj->section() !== $sectionNormalized) {
                throw new InvalidArgumentException("Skill '{$skillObj->value}' belongs to section '{$skillObj->section()}', not '{$sectionNormalized}'.");
            }

            if ($taskTypeObj !== null && !in_array($skillObj, $taskTypeObj->skills(), true)) {
                throw new InvalidArgumentException("Skill '{$skillObj->value}' is not compatible with task type '{$taskTypeObj->value}'.");
            }
        }

        return [
            'assessment_family' => AssessmentFamily::ToeflIbt->value,
            'assessment_standard_id' => $data['assessment_standard_id'] ?? null,
            'standard_version' => $data['standard_version'] ?? null,
            'section' => $sectionNormalized,
            'part_number' => null,
            'task_type' => $taskTypeObj?->value,
            'claim' => $claimObj?->value,
            'skill' => $skillObj?->value,
            'proficiency_target' => $data['proficiency_target'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'language_use_context' => $data['language_use_context'] ?? null,
        ];
    }

    public function getStructuralDimensions(): array
    {
        return [
            'section',
            'task_type',
            'claim',
            'skill',
            'proficiency_target',
            'difficulty',
            'language_use_context',
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
            supportedSections: $this->supportedSections()
        );
    }
}
