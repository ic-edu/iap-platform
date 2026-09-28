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

    /**
     * Canonical official ETS Claim statement from the 2026 Test Blueprint and Specifications.
     */
    public function officialText(): string
    {
        return match ($this) {
            self::Claim1Reading => 'The test taker can read and understand academic and nonacademic written texts presented in a variety of formats.',
            self::Claim2Listening => 'The test taker can understand spoken English in academic and social contexts, including single exchanges, short conversations, announcements, and academic talks, in order to identify meaning, purpose, and appropriate responses.',
            self::Claim3Writing => 'The test taker can produce grammatically accurate and contextually appropriate written English across a range of academic and interpersonal situations, including sentence-level construction, multi-sentence responses, and extended academic discourse.',
            self::Claim4Speaking => 'The test taker can produce intelligible and coherent spoken English to effectively communicate in both brief and extended interactions across general and academic contexts.',
        };
    }

    /**
     * Short internal display label.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Claim1Reading => 'Claim 1 (Reading)',
            self::Claim2Listening => 'Claim 2 (Listening)',
            self::Claim3Writing => 'Claim 3 (Writing)',
            self::Claim4Speaking => 'Claim 4 (Speaking)',
        };
    }

    public function label(): string
    {
        return $this->officialText();
    }

    public function provenance(): string
    {
        return 'official_ets';
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
