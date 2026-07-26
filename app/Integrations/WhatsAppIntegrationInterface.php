<?php

namespace App\Integrations;

interface WhatsAppIntegrationInterface
{
    /**
     * Send WhatsApp notification message.
     *
     * @return array{message_id: string, status: string}
     */
    public function sendMessage(string $phoneNumber, string $text): array;
}
