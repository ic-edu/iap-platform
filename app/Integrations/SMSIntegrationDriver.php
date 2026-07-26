<?php

namespace App\Integrations;

class SMSIntegrationDriver implements SMSIntegrationInterface
{
    public function sendSms(string $phoneNumber, string $text): array
    {
        return [
            'message_id' => 'SMS-'.now()->timestamp,
            'status' => 'sent',
        ];
    }
}
