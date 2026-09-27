<?php

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Assessment\Models\Test;
use Illuminate\Support\Carbon;

class ResultReleasePolicyService
{
    /**
     * Derive initial result release state and target timestamps for an assessment attempt.
     *
     * @return array{
     *     result_release_status: ResultReleaseStatus,
     *     result_release_at: Carbon,
     *     result_released_at: Carbon|null,
     *     result_released_by: int|null
     * }
     */
    public function deriveInitialReleaseState(Test $test, ?Carbon $completedAt = null): array
    {
        $timestamp = $completedAt ? $completedAt->copy() : now();

        if ($test->isSimulator() || !$test->usesDelayedResultRelease()) {
            return [
                'result_release_status' => ResultReleaseStatus::Released,
                'result_release_at' => $timestamp,
                'result_released_at' => $timestamp,
                'result_released_by' => null,
            ];
        }

        $delayHours = $test->getResultReleaseDelayHours();

        return [
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => $timestamp->copy()->addHours($delayHours),
            'result_released_at' => null,
            'result_released_by' => null,
        ];
    }
}
