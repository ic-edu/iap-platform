<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Assessment\Models\Test;

class CalendarService
{
    /**
     * Get upcoming assessment schedule events.
     *
     * @return array<int, array{id: string, title: string, date: string, type: string}>
     */
    public function getUpcomingEvents(?User $user = null): array
    {
        $tests = Test::where('is_published', true)->latest()->take(5)->get();
        $events = [];

        foreach ($tests as $test) {
            $events[] = [
                'id' => $test->id,
                'title' => $test->title,
                'date' => now()->addDays(2)->format('Y-m-d H:i'),
                'type' => 'assessment',
            ];
        }

        return $events;
    }
}
