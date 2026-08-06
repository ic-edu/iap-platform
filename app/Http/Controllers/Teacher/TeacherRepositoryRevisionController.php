<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Services\RepositoryQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TeacherRepositoryRevisionController extends Controller
{
    /**
     * Display listing of pending repository revision requests for teacher.
     */
    public function index(Request $request): View
    {
        $teacherId = Auth::id();

        $revisionRequests = RepositoryRevisionRequest::with(['questionBank', 'requestedBy', 'items'])
            ->where('teacher_id', $teacherId)
            ->latest()
            ->paginate(12);

        return view('teacher.repository_revisions.index', compact('revisionRequests'));
    }

    /**
     * Workspace for inspecting repository issues and preparing resubmission.
     */
    public function show(Request $request, RepositoryRevisionRequest $revisionRequest): View
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $revisionRequest->load(['questionBank.questions.choices', 'requestedBy', 'items.question']);

        return view('teacher.repository_revisions.show', compact('revisionRequest'));
    }

    /**
     * Resubmit repository & trigger Automatic IRQA Re-Scan (PART 6).
     */
    public function resubmit(Request $request, RepositoryRevisionRequest $revisionRequest): RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $bank = $revisionRequest->questionBank;

        // 1. Update status
        $revisionRequest->status = 'RESUBMITTED';
        $revisionRequest->save();

        if ($bank) {
            $bank->status = 'pending_approval';
            $bank->save();
        }

        // 2. PART 6: Automatic IRQA Re-Scan & Findings Resolution
        $qualityService = app(RepositoryQualityService::class);
        $rescanAudit = $qualityService->validateRepository($bank);
        $currentWarnings = $rescanAudit['warnings'] ?? [];

        foreach ($revisionRequest->items as $item) {
            // Check if warning still exists in current scan
            $stillPresent = false;
            foreach ($currentWarnings as $cw) {
                if (str_contains(strtolower($item->feedback), strtolower(substr($cw, 0, 15)))) {
                    $stillPresent = true;
                    break;
                }
            }

            if (!$stillPresent) {
                $item->status = 'CLOSED';
                $item->save();
            }
        }

        // 3. Send Notification to Repository Manager
        if (Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'repository_resubmitted',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $revisionRequest->requested_by_id,
                'data'            => json_encode([
                    'title'   => 'Repository Resubmitted',
                    'message' => "Teacher resubmitted repository '{$bank->title}'. Automatic IRQA Re-scan executed.",
                    'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // 4. Activity Audit Log
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'repository_resubmitted_irqa_rescanned',
            'approval_note' => 'Repository resubmitted by teacher. IRQA re-scan automatically completed.',
        ]);

        return redirect()->route('teacher.repository-revisions.index')
            ->with('success', 'Repository successfully resubmitted. Automatic IRQA re-scan verified your fixes.');
    }
}
