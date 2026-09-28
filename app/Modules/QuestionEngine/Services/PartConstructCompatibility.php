<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use InvalidArgumentException;

class PartConstructCompatibility
{
    /**
     * Canonical Part-to-Construct compatibility mapping for TOEIC.
     *
     * @var array<int, list<ConstructTaxonomy>>
     */
    protected static array $partConstructMap = [
        1 => [
            ConstructTaxonomy::Detail,
            ConstructTaxonomy::VisualDescription,
            ConstructTaxonomy::Vocabulary,
        ],
        2 => [
            ConstructTaxonomy::Intent,
            ConstructTaxonomy::Detail,
            ConstructTaxonomy::PragmaticResponse,
            ConstructTaxonomy::Vocabulary,
        ],
        3 => [
            ConstructTaxonomy::Detail,
            ConstructTaxonomy::Purpose,
            ConstructTaxonomy::Inference,
            ConstructTaxonomy::Intent,
            ConstructTaxonomy::GraphicInterpretation,
            ConstructTaxonomy::MainIdea,
            ConstructTaxonomy::Vocabulary,
        ],
        4 => [
            ConstructTaxonomy::Detail,
            ConstructTaxonomy::Purpose,
            ConstructTaxonomy::Inference,
            ConstructTaxonomy::Intent,
            ConstructTaxonomy::GraphicInterpretation,
            ConstructTaxonomy::MainIdea,
            ConstructTaxonomy::Vocabulary,
        ],
        5 => [
            ConstructTaxonomy::Grammar,
            ConstructTaxonomy::Vocabulary,
        ],
        6 => [
            ConstructTaxonomy::Grammar,
            ConstructTaxonomy::Vocabulary,
            ConstructTaxonomy::TextCohesion,
            ConstructTaxonomy::Detail,
        ],
        7 => [
            ConstructTaxonomy::Detail,
            ConstructTaxonomy::MainIdea,
            ConstructTaxonomy::Purpose,
            ConstructTaxonomy::Inference,
            ConstructTaxonomy::Intent,
            ConstructTaxonomy::Reference,
            ConstructTaxonomy::TextCohesion,
            ConstructTaxonomy::GraphicInterpretation,
            ConstructTaxonomy::Vocabulary,
        ],
    ];

    /**
     * Get the list of compatible constructs for a specific TOEIC Part.
     *
     * @return list<ConstructTaxonomy>
     */
    public static function getCompatibleConstructs(int $partNumber): array
    {
        return self::$partConstructMap[$partNumber] ?? [];
    }

    /**
     * Check if a construct is compatible with a given TOEIC Part.
     */
    public static function isCompatible(int $partNumber, ConstructTaxonomy|string $construct): bool
    {
        $targetConstruct = is_string($construct)
            ? ConstructTaxonomy::tryFrom($construct)
            : $construct;

        if ($targetConstruct === null) {
            return false;
        }

        $compatible = self::getCompatibleConstructs($partNumber);

        return in_array($targetConstruct, $compatible, true);
    }

    /**
     * Get the canonical SectionType for a given TOEIC Part (1..4 = Listening, 5..7 = Reading).
     *
     * @throws InvalidArgumentException
     */
    public static function getCanonicalSectionForPart(int $partNumber): SectionType
    {
        return match ($partNumber) {
            1, 2, 3, 4 => SectionType::Listening,
            5, 6, 7 => SectionType::Reading,
            default => throw new InvalidArgumentException("Invalid TOEIC Part number: {$partNumber}. Must be between 1 and 7."),
        };
    }

    /**
     * Check if a Part number and SectionType match.
     */
    public static function isSectionCompatibleWithPart(int $partNumber, SectionType|string $section): bool
    {
        $targetSection = is_string($section)
            ? SectionType::tryFrom($section)
            : $section;

        if ($targetSection === null || $partNumber < 1 || $partNumber > 7) {
            return false;
        }

        return self::getCanonicalSectionForPart($partNumber) === $targetSection;
    }
}
