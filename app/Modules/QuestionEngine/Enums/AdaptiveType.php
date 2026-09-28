<?php

namespace App\Modules\QuestionEngine\Enums;

enum AdaptiveType: string
{
    case Linear = 'linear';
    case TwoStage = 'two_stage';
    case ItemAdaptive = 'item_adaptive';

    public function label(): string
    {
        return match ($this) {
            self::Linear => 'Linear (Non-Adaptive)',
            self::TwoStage => 'Two-Stage Adaptive (Multi-Stage Testing / MST)',
            self::ItemAdaptive => 'Item-Level Adaptive (Computer Adaptive Testing / CAT)',
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
