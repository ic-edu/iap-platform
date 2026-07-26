<?php

namespace App\Services;

use App\Modules\Certificate\Models\Certificate;

class PdfService
{
    /**
     * Generate HTML view or PDF stream for certificate.
     */
    public function renderCertificateHtml(Certificate $certificate): string
    {
        $certificate->loadMissing(['user', 'attempt.test']);

        /** @var view-string $viewName */
        $viewName = 'certificate::pdf_template';

        return view($viewName, compact('certificate'))->render();
    }
}
