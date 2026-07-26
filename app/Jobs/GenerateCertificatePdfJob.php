<?php

namespace App\Jobs;

use App\Modules\Certificate\Models\Certificate;
use App\Services\PdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Certificate $certificate
    ) {}

    public function handle(PdfService $pdfService): void
    {
        $html = $pdfService->renderCertificateHtml($this->certificate);
        // Save or cache PDF path
    }
}
