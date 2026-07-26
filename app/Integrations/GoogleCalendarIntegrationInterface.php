<?php

namespace App\Integrations;

interface GoogleCalendarIntegrationInterface
{
    /**
     * Create event on Google Calendar.
     *
     * @return array{event_id: string, html_link: string}
     */
    public function createEvent(string $title, string $startDateTime, string $endDateTime, string $attendeeEmail): array;
}
