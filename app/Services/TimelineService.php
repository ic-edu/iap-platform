<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class TimelineService
{
    /**
     * Get candidate activity timeline.
     *
     * @return array<int, array{action: string, description: string, date: string}>
     */
    public function getUserTimeline(User $user): array
    {
        $logs = ActivityLog::where('user_id', $user->id)
            ->latest()
            ->take(15)
            ->get();

        $timeline = [];
        foreach ($logs as $log) {
            $timeline[] = [
                'action' => $log->action,
                'description' => $log->description,
                'date' => $log->created_at?->format('Y-m-d H:i') ?? '',
            ];
        }

        return $timeline;
    }
}
