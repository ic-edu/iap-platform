<?php

namespace App\Integrations;

interface EmailIntegrationInterface
{
    /**
     * Send email message.
     *
     * @return array{message_id: string, status: string}
     */
    public function sendEmail(string $recipientEmail, string $subject, string $body): array;
}
