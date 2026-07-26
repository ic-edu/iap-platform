<?php

namespace App\Integrations;

interface SMSIntegrationInterface
{
    /**
     * Send SMS notification message.
     *
     * @return array{message_id: string, status: string}
     */
    public function sendSms(string $phoneNumber, string $text): array;
}
