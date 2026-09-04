<?php

namespace App\Modules\Organization\Enums;

enum GroupType: string
{
    case ClassGroup = 'class';
    case Cohort = 'cohort';
    case Department = 'department';
    case TrainingBatch = 'training_batch';
    case Team = 'team';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ClassGroup => 'Class',
            self::Cohort => 'Academic Cohort',
            self::Department => 'Department / Division',
            self::TrainingBatch => 'Training Batch',
            self::Team => 'Project Team',
            self::Other => 'Other Group',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
