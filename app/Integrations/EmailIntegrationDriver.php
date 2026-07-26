<?php

namespace App\Integrations;

class EmailIntegrationDriver implements EmailIntegrationInterface
{
    public function sendEmail(string $recipientEmail, string $subject, string $body): array
    {
        return [
            'message_id' => 'EML-'.now()->timestamp,
            'status' => 'queued',
        ];
    }
}
