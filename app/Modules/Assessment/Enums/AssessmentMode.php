<?php

namespace App\Modules\Assessment\Enums;

enum AssessmentMode: string
{
    case Simulator = 'simulator';
    case RealTest = 'real_test';

    public function label(): string
    {
        return match ($this) {
            self::Simulator => 'Test Simulator',
            self::RealTest => 'Mock Test',
        };
    }

    public function isSimulator(): bool
    {
        return $this === self::Simulator;
    }

    public function isRealTest(): bool
    {
        return $this === self::RealTest;
    }
}
