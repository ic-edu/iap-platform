<?php

namespace App\Modules\Certificate\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Models\Certificate;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CertificateAdminController extends Controller
{
    public function __construct(
        protected CertificateEngine $engine,
        protected PdfService $pdfService
    ) {}

    /**
     * Display list of certificates.
     */
    public function index(Request $request): View
    {
        $query = Certificate::with(['user', 'attempt.test']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                    ->orWhere('verification_code', 'like', "%{$search}%");
            });
        }

        $certificates = $query->latest()->paginate(10)->withQueryString();

        /** @var view-string $viewName */
        $viewName = 'certificate::admin_index';

        return view($viewName, compact('certificates'));
    }

    /**
     * Reissue certificate.
     */
    public function reissue(Certificate $certificate): RedirectResponse
    {
        $this->engine->reissueCertificate($certificate);

        return redirect()->route('admin.certificates.index')->with('status', 'certificate-reissued');
    }

    /**
     * Revoke certificate.
     */
    public function revoke(Certificate $certificate): RedirectResponse
    {
        $this->engine->revokeCertificate($certificate);

        return redirect()->route('admin.certificates.index')->with('status', 'certificate-revoked');
    }

    /**
     * Download or print certificate HTML/PDF.
     */
    public function download(Certificate $certificate): Response
    {
        $html = $this->pdfService->renderCertificateHtml($certificate);

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="certificate-'.$certificate->certificate_number.'.html"');
    }
}
