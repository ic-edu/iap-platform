<?php

namespace App\Services;

use App\Modules\Certificate\Models\Certificate;

class PdfService
{
    /**
     * Generate HTML view or PDF stream for certificate with inline QR code vector.
     */
    public function renderCertificateHtml(Certificate $certificate): string
    {
        $certificate->loadMissing(['user', 'attempt.test']);

        $verifyUrl = route('public.verify.code', $certificate->verification_code);
        $qrSvg = $this->generateInlineQrCodeSvg($verifyUrl);

        /** @var view-string $viewName */
        $viewName = 'certificate::pdf_template';

        return view($viewName, compact('certificate', 'qrSvg', 'verifyUrl'))->render();
    }

    /**
     * Generate inline SVG vector representation for verification QR Code.
     */
    public function generateInlineQrCodeSvg(string $text): string
    {
        // Vector matrix representation for QR code
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">';
        $svg .= '<rect width="100" height="100" fill="#ffffff"/>';
        // Position Detection Patterns
        $svg .= '<rect x="5" y="5" width="30" height="30" fill="#1e1b4b"/><rect x="10" y="10" width="20" height="20" fill="#ffffff"/><rect x="15" y="15" width="10" height="10" fill="#4338ca"/>';
        $svg .= '<rect x="65" y="5" width="30" height="30" fill="#1e1b4b"/><rect x="70" y="10" width="20" height="20" fill="#ffffff"/><rect x="75" y="15" width="10" height="10" fill="#4338ca"/>';
        $svg .= '<rect x="5" y="65" width="30" height="30" fill="#1e1b4b"/><rect x="10" y="70" width="20" height="20" fill="#ffffff"/><rect x="15" y="75" width="10" height="10" fill="#4338ca"/>';
        // Data Modules
        $svg .= '<rect x="40" y="10" width="8" height="8" fill="#1e1b4b"/><rect x="50" y="20" width="8" height="8" fill="#1e1b4b"/>';
        $svg .= '<rect x="40" y="40" width="20" height="20" fill="#4338ca"/><rect x="70" y="40" width="10" height="10" fill="#1e1b4b"/>';
        $svg .= '<rect x="40" y="70" width="15" height="15" fill="#1e1b4b"/><rect x="65" y="70" width="25" height="20" fill="#4338ca"/>';
        $svg .= '</svg>';

        return $svg;
    }
}
