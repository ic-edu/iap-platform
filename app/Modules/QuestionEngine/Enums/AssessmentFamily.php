<?php

namespace App\Modules\QuestionEngine\Enums;

use App\Modules\QuestionBank\Enums\TestType;
use InvalidArgumentException;

enum AssessmentFamily: string
{
    case Toeic = 'toeic';
    case ToeflIbt = 'toefl_ibt';
    case GeneralEnglish = 'general_english';
    case Ielts = 'ielts';

    public function label(): string
    {
        return match ($this) {
            self::Toeic => 'TOEIC (Test of English for International Communication)',
            self::ToeflIbt => 'TOEFL iBT (Test of English as a Foreign Language)',
            self::GeneralEnglish => 'General English (CEFR / Curriculum-Driven)',
            self::Ielts => 'IELTS (International English Language Testing System)',
        };
    }

    /**
     * Map the Question Engine assessment family to the existing QuestionBank TestType enum.
     */
    public function toTestType(): TestType
    {
        return match ($this) {
            self::Toeic => TestType::Toeic,
            self::ToeflIbt => TestType::Toefl,
            self::GeneralEnglish => TestType::General,
            self::Ielts => TestType::Ielts,
        };
    }

    /**
     * Resolve an AssessmentFamily from an existing QuestionBank TestType.
     */
    public static function fromTestType(TestType|string $testType): self
    {
        $resolved = $testType instanceof TestType ? $testType : TestType::tryFrom((string) $testType);

        return match ($resolved) {
            TestType::Toeic => self::Toeic,
            TestType::Toefl => self::ToeflIbt,
            TestType::General => self::GeneralEnglish,
            TestType::Ielts => self::Ielts,
            default => throw new InvalidArgumentException("Cannot map test type '{$testType}' to a known AssessmentFamily."),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $family) => [$family->value => $family->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
