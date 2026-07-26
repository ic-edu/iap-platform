<?php

namespace App\Integrations;

class WhatsAppIntegrationDriver implements WhatsAppIntegrationInterface
{
    public function sendMessage(string $phoneNumber, string $text): array
    {
        return [
            'message_id' => 'WA-'.now()->timestamp,
            'status' => 'queued',
        ];
    }
}
