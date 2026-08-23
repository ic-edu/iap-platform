<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentResetRequest;
use App\Modules\Assessment\Models\Test;
use App\Services\ContentReset\ContentResetApprovalService;
use App\Services\ContentReset\ContentResetAuditService;
use App\Services\ContentReset\ContentResetDomains;
use App\Services\ContentReset\ContentResetExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContentResetController extends Controller
{
    public function __construct(
        protected ContentResetAuditService $auditService,
        protected ContentResetApprovalService $approvalService,
        protected ContentResetExecutor $executor
    ) {}

    /**
     * Display list of content reset requests.
     */
    public function index(): View
    {
        $requests = ContentResetRequest::with(['requester', 'ceoApprover', 'saApprover', 'executor'])
            ->latest()
            ->paginate(15);

        return view('admin.content_reset.index', compact('requests'));
    }

    /**
     * Show form to create a new content reset request.
     */
    public function create(): View
    {
        $resettableDomains = ContentResetDomains::allResettableDomains();
        $protectedDomains = ContentResetDomains::allProtectedDomains();
        $availableTests = Test::orderBy('title')->get(['id', 'title', 'status', 'is_published']);

        return view('admin.content_reset.create', compact('resettableDomains', 'protectedDomains', 'availableTests'));
    }

    /**
     * Store and trigger forensic audit for content reset request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode'               => 'required|in:refresh,hard_reset',
            'reason'             => 'required|string|min:5',
            'scope'              => 'required|array|min:1',
            'target_assessments' => 'nullable|array',
        ]);

        $dateStr = now()->format('Ymd');
        $randomCode = strtoupper(Str::random(4));
        $requestNumber = "RST-{$dateStr}-{$randomCode}";

        $resetRequest = ContentResetRequest::create([
            'request_number'   => $requestNumber,
            'mode'             => $validated['mode'],
            'status'           => 'draft',
            'requested_by'     => $request->user()->id,
            'reason'           => $validated['reason'],
            'scope'            => $validated['scope'],
            'target_entities'  => !empty($validated['target_assessments']) ? ['assessment_ids' => $validated['target_assessments']] : null,
            'excluded_domains' => ContentResetDomains::allProtectedDomains(),
        ]);

        // Run Forensic Audit immediately
        $this->auditService->performForensicAudit($resetRequest);

        return redirect()->route('admin.content-reset.show', $resetRequest)
            ->with('status', "Reset request {$requestNumber} created and audited.");
    }

    /**
     * View detailed forensic audit report and approval dashboard.
     */
    public function show(ContentResetRequest $contentResetRequest): View
    {
        $contentResetRequest->loadMissing(['requester', 'ceoApprover', 'saApprover', 'executor', 'auditLogs.actor']);

        return view('admin.content_reset.show', compact('contentResetRequest'));
    }

    /**
     * CEO Approval Action.
     */
    public function approveCeo(Request $request, ContentResetRequest $contentResetRequest): RedirectResponse
    {
        try {
            $this->approvalService->approveCeo($contentResetRequest, $request->user(), $request->input('notes'));
            return redirect()->route('admin.content-reset.show', $contentResetRequest)
                ->with('status', 'CEO Approval recorded successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.content-reset.show', $contentResetRequest)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Super Admin Approval Action.
     */
    public function approveSa(Request $request, ContentResetRequest $contentResetRequest): RedirectResponse
    {
        try {
            $this->approvalService->approveSuperAdmin($contentResetRequest, $request->user(), $request->input('notes'));
            return redirect()->route('admin.content-reset.show', $contentResetRequest)
                ->with('status', 'Super Admin Approval recorded successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.content-reset.show', $contentResetRequest)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Execute Content Reset.
     */
    public function execute(Request $request, ContentResetRequest $contentResetRequest): RedirectResponse
    {
        $dryRun = $request->boolean('dry_run');

        try {
            $result = $this->executor->execute($contentResetRequest, $request->user(), $dryRun);
            $msg = $dryRun 
                ? 'Forensic dry-run completed successfully with zero mutations.'
                : 'Content reset executed and verified successfully.';

            return redirect()->route('admin.content-reset.show', $contentResetRequest)
                ->with('status', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('admin.content-reset.show', $contentResetRequest)
                ->with('error', 'Execution blocked: ' . $e->getMessage());
        }
    }
}
