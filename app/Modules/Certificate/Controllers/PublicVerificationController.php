<?php

namespace App\Modules\Certificate\Controllers;

use App\Http\Controllers\Controller;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVerificationController extends Controller
{
    public function __construct(
        protected VerificationService $service
    ) {}

    public function show(Request $request): View
    {
        $code = $request->input('code');
        $result = $code ? $this->service->verify($code) : null;

        /** @var view-string $viewName */
        $viewName = 'certificate::verify';

        return view($viewName, compact('result', 'code'));
    }
}
