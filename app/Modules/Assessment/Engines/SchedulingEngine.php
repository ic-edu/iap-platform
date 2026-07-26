<?php

namespace App\Modules\Assessment\Engines;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use Illuminate\Support\Carbon;

class SchedulingEngine
{
    /**
     * Validate whether a candidate can access a test right now.
     *
     * @return array{can_access: bool, reason: string|null}
     */
    public function validateCandidateAccess(Test $test, User $candidate, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        if (!$test->is_published) {
            return ['can_access' => false, 'reason' => 'Test is currently in draft mode.'];
        }

        // Max Attempts Check
        $existingCount = Attempt::where('test_id', $test->id)
            ->where('user_id', $candidate->id)
            ->whereIn('status', ['submitted', 'completed'])
            ->count();

        if ($existingCount >= 3) {
            return ['can_access' => false, 'reason' => 'Maximum allowed attempt limit (3) reached.'];
        }

        return ['can_access' => true, 'reason' => null];
    }
}
