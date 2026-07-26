<?php

namespace App\Integrations;

interface ZoomIntegrationInterface
{
    /**
     * Create meeting room for live proctoring or online class.
     *
     * @return array{meeting_id: string, join_url: string, start_url: string}
     */
    public function createMeeting(string $topic, string $startTime, int $durationMinutes): array;
}
