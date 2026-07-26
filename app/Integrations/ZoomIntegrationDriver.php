<?php

namespace App\Integrations;

class ZoomIntegrationDriver implements ZoomIntegrationInterface
{
    public function createMeeting(string $topic, string $startTime, int $durationMinutes): array
    {
        return [
            'meeting_id' => 'ZM-999-888-777',
            'join_url' => 'https://zoom.us/j/999888777',
            'start_url' => 'https://zoom.us/s/999888777',
        ];
    }
}
