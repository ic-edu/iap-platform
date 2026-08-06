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

        // 1. Audit Log: Teacher Resubmitted Repository
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_resubmitted_repository',
            'approval_note' => 'Teacher resubmitted repository for governance review.',
        ]);

        // 2. Update status
        $revisionRequest->status = 'RESUBMITTED';
        $revisionRequest->save();

        if ($bank) {
            $bank->status = 'pending_approval';
            $bank->save();
        }

        // 3. Audit Log: Automatic IRQA Scan Started
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'automatic_irqa_scan_started',
            'approval_note' => 'Automatic IRQA Re-Scan initiated by governance engine.',
        ]);

        // 4. Execute Automatic IRQA Re-Scan
        $qualityService = app(RepositoryQualityService::class);
        $rescanAudit = $qualityService->validateRepository($bank);
        $currentWarnings = $rescanAudit['warnings'] ?? [];

        foreach ($revisionRequest->items as $item) {
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

        // Audit Log: Automatic IRQA Scan Completed
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'automatic_irqa_scan_completed',
            'approval_note' => 'Automatic IRQA Re-Scan execution completed.',
        ]);

        $irqaPassed = empty($currentWarnings) && ($rescanAudit['health_score'] ?? 0) >= 90;

        if ($irqaPassed) {
            // Log IRQA Passed
            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $bank->id,
                'actor_id'      => Auth::id(),
                'reviewer_id'   => $revisionRequest->requested_by_id,
                'action'        => 'irqa_passed',
                'approval_note' => 'Automatic IRQA re-scan passed with zero remaining quality findings.',
            ]);

            // Notify Repository Manager (PART 3)
            if (Schema::hasTable('notifications')) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_resubmitted_irqa_passed',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $revisionRequest->requested_by_id,
                    'data'            => json_encode([
                        'title'   => 'Repository IRQA Verification Passed',
                        'message' => "Repository: '{$bank->title}'. Automatic IRQA verification completed. No remaining quality findings detected. Repository is ready for governance review.",
                        'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        } else {
            // Log IRQA Failed
            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $bank->id,
                'actor_id'      => Auth::id(),
                'reviewer_id'   => $revisionRequest->requested_by_id,
                'action'        => 'irqa_failed',
                'approval_note' => 'Automatic IRQA re-scan detected unresolved findings. Governance review required by Repository Manager.',
            ]);

            // Notify Repository Manager (PART 4)
            if (Schema::hasTable('notifications')) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_resubmitted_irqa_failed',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $revisionRequest->requested_by_id,
                    'data'            => json_encode([
                        'title'   => 'Governance Review Required',
                        'message' => "Repository: '{$bank->title}'. Automatic IRQA Re-Scan detected unresolved findings. Governance review required. Please inspect findings and decide whether to request another revision.",
                        'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        // Notify Teacher (PART 1 & PART 3 - Neutral notification only)
        if (Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'repository_resubmission_received',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => Auth::id(),
                'data'            => json_encode([
                    'title'   => 'Resubmission Received',
                    'message' => "Repository successfully resubmitted. Waiting Repository Manager review.",
                    'link'    => route('teacher.repository-revisions.index'),
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return redirect()->route('teacher.repository-revisions.index')
            ->with('success', 'Repository successfully resubmitted. Waiting Repository Manager review.');
    }
}
