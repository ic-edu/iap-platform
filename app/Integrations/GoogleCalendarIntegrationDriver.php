<?php

namespace App\Integrations;

class GoogleCalendarIntegrationDriver implements GoogleCalendarIntegrationInterface
{
    public function createEvent(string $title, string $startDateTime, string $endDateTime, string $attendeeEmail): array
    {
        return [
            'event_id' => 'GCAL-'.now()->timestamp,
            'html_link' => 'https://calendar.google.com/calendar/event?eid=example',
        ];
    }
}
