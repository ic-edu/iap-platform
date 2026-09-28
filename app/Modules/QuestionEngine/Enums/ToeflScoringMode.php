<?php

namespace App\Modules\QuestionEngine\Enums;

enum ToeflScoringMode: string
{
    case Machine = 'machine';
    case AiScored = 'ai_scored';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Machine => 'Machine Scored',
            self::AiScored => 'AI / Automated Constructed Response Scored',
            self::Hybrid => 'Hybrid Machine & Human Scored',
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
