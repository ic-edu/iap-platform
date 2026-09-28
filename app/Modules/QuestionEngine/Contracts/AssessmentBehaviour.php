<?php

namespace App\Modules\QuestionEngine\Contracts;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;

interface AssessmentBehaviour
{
    /**
     * Get the assessment family this behaviour manages.
     */
    public function family(): AssessmentFamily;

    /**
     * Check if this assessment family is part-number driven (e.g. TOEIC Part 1..7).
     */
    public function supportsPartNumbers(): bool;

    /**
     * Check if this assessment family is task-type driven (e.g. TOEFL/IELTS task types).
     */
    public function supportsTaskTypes(): bool;

    /**
     * Check if this assessment family supports adaptive multi-stage routing blueprints.
     */
    public function supportsAdaptiveBlueprint(): bool;

    /**
     * Check if this assessment family uses a canonical fixed full-test blueprint.
     */
    public function supportsFixedFullTestBlueprint(): bool;

    /**
     * Resolve the canonical SectionType based on part number, task type, or section identifier.
     */
    public function resolveSection(?int $partNumber = null, ?string $taskType = null, ?string $section = null): ?SectionType;

    /**
     * Validate the structural dimensions of an assessment item according to this family's rules.
     *
     * @param  AssessmentItemIdentity|array<string, mixed>  $identity
     * @return array<string, mixed> Validated structural parameters
     *
     * @throws \InvalidArgumentException
     */
    public function validateStructure(AssessmentItemIdentity|array $identity): array;

    /**
     * Get the list of structural dimension names relevant to this assessment family.
     *
     * @return list<string>
     */
    public function getStructuralDimensions(): array;

    /**
     * Get the standard capability model for this assessment family.
     */
    public function getCapabilities(): AssessmentCapabilities;
}
