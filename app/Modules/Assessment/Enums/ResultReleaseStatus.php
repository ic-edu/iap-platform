<?php

namespace App\Modules\Assessment\Enums;

enum ResultReleaseStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::Ready => 'Ready for Release',
            self::Released => 'Released',
        };
    }

    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }

    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    public function isReleased(): bool
    {
        return $this === self::Released;
    }
}
