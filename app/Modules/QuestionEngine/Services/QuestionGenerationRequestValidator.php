<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Exceptions\InvalidQuestionGenerationRequestException;

class QuestionGenerationRequestValidator
{
    /**
     * Validate a QuestionGenerationRequest instance or raw array data.
     *
     * @param  QuestionGenerationRequest|array<string, mixed>  $input
     * @return array<string, mixed> Validated data array
     *
     * @throws InvalidQuestionGenerationRequestException
     */
    public function validate(QuestionGenerationRequest|array $input): array
    {
        $errors = [];
        $data = $input instanceof QuestionGenerationRequest ? $input->toArray() : $input;

        // 1. Part Number Validation
        if (!isset($data['part_number']) && !isset($data['part'])) {
            $errors['part_number'] = 'The part_number is required for TOEIC question generation.';
            $partNumber = 0;
        } else {
            $partNumber = (int) ($data['part_number'] ?? $data['part']);
            if ($partNumber < 1 || $partNumber > 7) {
                $errors['part_number'] = 'The part_number must be an integer between 1 and 7 for TOEIC.';
            }
        }

        // 2. Section vs Part Compatibility
        $sectionRaw = $data['section'] ?? null;
        if ($sectionRaw !== null && $partNumber >= 1 && $partNumber <= 7) {
            $section = $sectionRaw instanceof SectionType ? $sectionRaw : SectionType::tryFrom((string) $sectionRaw);
            if ($section === null) {
                $errors['section'] = 'The section must be a valid SectionType.';
            } elseif (!PartConstructCompatibility::isSectionCompatibleWithPart($partNumber, $section)) {
                $expectedSection = PartConstructCompatibility::getCanonicalSectionForPart($partNumber)->value;
                $errors['section'] = "Section '{$section->value}' is incompatible with TOEIC Part {$partNumber} (expected '{$expectedSection}').";
            }
        }

        // 3. Content Mode & Domain Validation
        $contentModeRaw = $data['content_mode'] ?? ContentMode::General->value;
        $contentMode = $contentModeRaw instanceof ContentMode ? $contentModeRaw : ContentMode::tryFrom((string) $contentModeRaw);
        if ($contentMode === null) {
            $errors['content_mode'] = 'Invalid content_mode.';
        }

        $domainRaw = $data['domain'] ?? null;
        $domain = $domainRaw instanceof DomainTaxonomy
            ? $domainRaw
            : (is_string($domainRaw) && $domainRaw !== '' ? DomainTaxonomy::tryFrom($domainRaw) : null);

        if ($contentMode === ContentMode::General) {
            if ($domainRaw === null || $domainRaw === '' || $domain === DomainTaxonomy::GeneralWorkplace) {
                $domain = DomainTaxonomy::GeneralWorkplace;
            } else {
                $errors['domain'] = 'General content mode requires domain general_workplace.';
            }
        } elseif ($contentMode === ContentMode::DomainSpecific) {
            if (empty($domainRaw)) {
                $errors['domain'] = 'Domain-specific generation requires an explicit domain.';
            } elseif ($domain === null) {
                $errors['domain'] = "Invalid domain '{$domainRaw}' for domain-specific mode.";
            } elseif ($domain === DomainTaxonomy::GeneralWorkplace) {
                $errors['domain'] = 'Domain-specific content mode requires a specific domain other than general_workplace.';
            }
        }

        // 4. Proficiency Target Validation
        $proficiencyRaw = $data['proficiency_target'] ?? $data['proficiency'] ?? null;
        $proficiency = $proficiencyRaw instanceof ProficiencyTarget ? $proficiencyRaw : (is_string($proficiencyRaw) ? ProficiencyTarget::tryFrom($proficiencyRaw) : null);
        if ($proficiency === null) {
            $errors['proficiency_target'] = 'A valid proficiency_target is required.';
        }

        // 5. Difficulty Level Validation
        $difficultyRaw = $data['difficulty'] ?? null;
        $difficulty = $difficultyRaw instanceof DifficultyLevel ? $difficultyRaw : (is_string($difficultyRaw) ? DifficultyLevel::tryFrom($difficultyRaw) : null);
        if ($difficulty === null) {
            $errors['difficulty'] = 'A valid difficulty level (easy, medium, hard) is required.';
        }

        // 6. Construct Validation & Part Compatibility
        $constructRaw = $data['construct'] ?? null;
        $construct = $constructRaw instanceof ConstructTaxonomy ? $constructRaw : (is_string($constructRaw) ? ConstructTaxonomy::tryFrom($constructRaw) : null);
        if ($construct === null) {
            $errors['construct'] = 'A valid construct is required.';
        } elseif ($partNumber >= 1 && $partNumber <= 7) {
            if (!PartConstructCompatibility::isCompatible($partNumber, $construct)) {
                $compatibleList = implode(', ', array_map(fn ($c) => $c->value, PartConstructCompatibility::getCompatibleConstructs($partNumber)));
                $errors['construct'] = "Construct '{$construct->value}' is not compatible with TOEIC Part {$partNumber}. Compatible constructs: [{$compatibleList}].";
            }
        }

        // 7. Context Validation (Optional)
        $contextRaw = $data['context'] ?? null;
        if (!empty($contextRaw)) {
            $context = $contextRaw instanceof ContextTaxonomy ? $contextRaw : ContextTaxonomy::tryFrom((string) $contextRaw);
            if ($context === null) {
                $errors['context'] = "Invalid context '{$contextRaw}'.";
            }
        }

        // 8. Item Count Validation
        if (array_key_exists('item_count', $data) && $data['item_count'] !== null) {
            if (!is_numeric($data['item_count']) || (int) $data['item_count'] < 1) {
                $errors['item_count'] = 'The item_count must be a positive integer greater than or equal to 1.';
                $itemCount = 1;
            } else {
                $itemCount = (int) $data['item_count'];
            }
        } else {
            $itemCount = 1;
        }

        // 9. Test Type Validation & Scope Guard
        $testTypeRaw = $data['test_type'] ?? TestType::Toeic->value;
        $testType = $testTypeRaw instanceof TestType ? $testTypeRaw : TestType::tryFrom((string) $testTypeRaw);
        if ($testType === null) {
            $errors['test_type'] = 'Invalid test_type.';
        } elseif ($testType !== TestType::Toeic) {
            $errors['test_type'] = 'Question generation currently supports TOEIC only.';
        }

        if (!empty($errors)) {
            throw new InvalidQuestionGenerationRequestException(
                errors: array_values($errors),
                message: 'QuestionGenerationRequest failed validation.'
            );
        }

        return [
            'test_type' => $testType?->value ?? TestType::Toeic->value,
            'content_mode' => $contentMode->value,
            'domain' => $domain->value,
            'section' => $sectionRaw ? ($section?->value ?? PartConstructCompatibility::getCanonicalSectionForPart($partNumber)->value) : PartConstructCompatibility::getCanonicalSectionForPart($partNumber)->value,
            'part_number' => $partNumber,
            'proficiency_target' => $proficiency->value,
            'difficulty' => $difficulty->value,
            'construct' => $construct->value,
            'context' => !empty($contextRaw) ? ($context?->value ?? null) : null,
            'item_count' => $itemCount,
            'locale' => (string) ($data['locale'] ?? 'en'),
            'seed' => isset($data['seed']) ? (string) $data['seed'] : null,
            'metadata' => (array) ($data['metadata'] ?? []),
        ];
    }

    /**
     * Check if the given request or data is valid without throwing an exception.
     *
     * @param  QuestionGenerationRequest|array<string, mixed>  $input
     */
    public function isValid(QuestionGenerationRequest|array $input): bool
    {
        try {
            $this->validate($input);

            return true;
        } catch (InvalidQuestionGenerationRequestException) {
            return false;
        }
    }
}
