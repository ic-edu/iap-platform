<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;

class TimerEngine
{
    /**
     * Calculate remaining time in seconds for an attempt based on server time.
     */
    public function getRemainingSeconds(Attempt $attempt): int
    {
        if (!$attempt->started_at) {
            return 0;
        }

        $durationMinutes = $attempt->test ? $attempt->test->duration_minutes : 60;
        $endTime = $attempt->started_at->copy()->addMinutes($durationMinutes);
        $now = now();

        if ($now->greaterThanOrEqualTo($endTime)) {
            return 0;
        }

        return (int) $now->diffInSeconds($endTime);
    }

    /**
     * Check if an attempt duration has expired.
     */
    public function isExpired(Attempt $attempt): bool
    {
        return $this->getRemainingSeconds($attempt) <= 0;
    }
}
