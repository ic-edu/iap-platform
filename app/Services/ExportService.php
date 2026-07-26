<?php

namespace App\Services;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Events\ExportGenerated;

class ExportService
{
    /**
     * Export attempts to CSV string.
     */
    public function exportAttemptsToCsv(): string
    {
        $attempts = Attempt::with(['user', 'test'])->latest()->get();

        $output = "Attempt ID,Student Name,Email,Test Title,Status,Score,Submitted At\n";

        foreach ($attempts as $att) {
            $user = str_replace('"', '""', $att->user->name ?? 'Student');
            $email = str_replace('"', '""', $att->user->email ?? 'N/A');
            $test = str_replace('"', '""', $att->test->title ?? 'Test');
            $status = $att->status->label();
            $score = $att->total_score ?? '0';
            $submitted = $att->submitted_at?->toIso8601String() ?? 'N/A';

            $output .= "\"{$att->id}\",\"{$user}\",\"{$email}\",\"{$test}\",\"{$status}\",\"{$score}\",\"{$submitted}\"\n";
        }

        event(new ExportGenerated('attempts_csv', count($attempts)));

        return $output;
    }
}
