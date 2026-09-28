<?php

namespace App\Modules\QuestionEngine\Enums;

enum ToeflResponseMode: string
{
    case SelectedResponse = 'selected_response';
    case ConstructedWritten = 'constructed_written';
    case ConstructedSpoken = 'constructed_spoken';
    case Reconstruction = 'reconstruction';

    public function label(): string
    {
        return match ($this) {
            self::SelectedResponse => 'Selected Response (Multiple Choice / Multiple Selection)',
            self::ConstructedWritten => 'Constructed Written Response',
            self::ConstructedSpoken => 'Constructed Spoken Response',
            self::Reconstruction => 'Sentence / Text Reconstruction',
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
