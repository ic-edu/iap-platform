<?php

namespace App\Modules\QuestionEngine\Enums;

use App\Modules\QuestionBank\Enums\SectionType;
use InvalidArgumentException;

enum ToeflClaim: string
{
    case Claim1Reading = 'claim_1_reading';
    case Claim2Listening = 'claim_2_listening';
    case Claim3Writing = 'claim_3_writing';
    case Claim4Speaking = 'claim_4_speaking';

    public function label(): string
    {
        return match ($this) {
            self::Claim1Reading => 'Claim 1 (Reading): Process and understand academic and nonacademic written texts for meaning and form',
            self::Claim2Listening => 'Claim 2 (Listening): Understand conversational dialogue and extended monologic speech across academic and navigational contexts',
            self::Claim3Writing => 'Claim 3 (Writing): Reconstruct sentences and write effective responses in academic and interpersonal contexts',
            self::Claim4Speaking => 'Claim 4 (Speaking): Speak intelligibly and spontaneously in interview format and sentence repetition',
        };
    }

    public function section(): string
    {
        return match ($this) {
            self::Claim1Reading => 'reading',
            self::Claim2Listening => 'listening',
            self::Claim3Writing => 'writing',
            self::Claim4Speaking => 'speaking',
        };
    }

    /**
     * Resolve the canonical top-level claim for a section.
     */
    public static function forSection(string|SectionType $section): self
    {
        $normalized = $section instanceof SectionType
            ? strtolower($section->value)
            : strtolower(trim((string) $section));

        return match ($normalized) {
            'reading' => self::Claim1Reading,
            'listening' => self::Claim2Listening,
            'writing' => self::Claim3Writing,
            'speaking' => self::Claim4Speaking,
            default => throw new InvalidArgumentException("Unknown or unsupported section '{$section}' for TOEFL claim."),
        };
    }

    /**
     * Try resolving from claim machine value or section name.
     */
    public static function resolve(self|string $claimOrSection): ?self
    {
        if ($claimOrSection instanceof self) {
            return $claimOrSection;
        }

        $str = strtolower(trim((string) $claimOrSection));

        $direct = self::tryFrom($str);
        if ($direct !== null) {
            return $direct;
        }

        return match ($str) {
            'reading', '1', 'claim_1', 'claim1' => self::Claim1Reading,
            'listening', '2', 'claim_2', 'claim2' => self::Claim2Listening,
            'writing', '3', 'claim_3', 'claim3' => self::Claim3Writing,
            'speaking', '4', 'claim_4', 'claim4' => self::Claim4Speaking,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
