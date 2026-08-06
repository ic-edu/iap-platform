<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\RepositoryQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Workspace for inspecting repository issues.
     */
    public function show(Request $request, RepositoryRevisionRequest $revisionRequest): View
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $revisionRequest->question_bank_id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_opened_revision',
            'approval_note' => 'Teacher opened repository revision task.',
        ]);

        $revisionRequest->load(['questionBank.questions.choices', 'requestedBy', 'items.question']);

        return view('teacher.repository_revisions.show', compact('revisionRequest'));
    }

    /**
     * SPRINT RRUXO-ENTERPRISE PART 1 & 2 & 4: Focused Question Editor inside Revision Mode.
     */
    public function editQuestion(Request $request, RepositoryRevisionRequest $revisionRequest, RepositoryRevisionItem $item): View
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $revisionRequest->load(['questionBank', 'requestedBy', 'items']);
        $item->load(['question.choices']);

        $question = $item->question ?? $revisionRequest->questionBank->questions()->first();

        RepositoryActivityLog::create([
            'resource_type' => 'Question',
            'resource_id'   => (string) ($question->id ?? $item->id),
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_edited_question',
            'approval_note' => "Teacher editing question #{$question->id} in Focused Revision Mode.",
        ]);

        return view('teacher.repository_revisions.focused_editor', compact('revisionRequest', 'item', 'question'));
    }

    /**
     * SPRINT RRUXO-ENTERPRISE PART 5: Save & Validate Question inside Focused Editor.
     */
    public function updateQuestion(Request $request, RepositoryRevisionRequest $revisionRequest, RepositoryRevisionItem $item): RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $question = $item->question ?? Question::findOrFail($request->input('question_id'));

        $question->prompt      = $request->input('prompt', $question->prompt);
        $question->explanation = $request->input('explanation', $question->explanation);
        $question->difficulty  = $request->input('difficulty', $question->difficulty);
        $question->points      = $request->input('points', $question->points);
        $question->save();

        // Update answer choices if provided
        if ($request->has('choices')) {
            foreach ($request->input('choices', []) as $cId => $cData) {
                $choice = QuestionChoice::find($cId);
                if ($choice) {
                    $choice->content    = $cData['content'] ?? $choice->content;
                    $choice->is_correct = isset($cData['is_correct']) && $cData['is_correct'] == '1';
                    $choice->save();
                }
            }
        }

        // Validate issue resolution
        $qualityService = app(RepositoryQualityService::class);
        $rescanAudit = $qualityService->validateRepository($revisionRequest->questionBank);
        $currentWarnings = $rescanAudit['warnings'] ?? [];

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

        RepositoryActivityLog::create([
            'resource_type' => 'Question',
            'resource_id'   => (string) $question->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_saved_revision',
            'approval_note' => "Teacher saved question revision for item #{$item->id}.",
        ]);

        if ($item->status === 'CLOSED') {
            return redirect()->route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id])
                ->with('success', '✔ Finding Fixed! Issue resolved successfully. Stay inside editor to resubmit when all findings are complete.');
        }

        return redirect()->route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id])
            ->with('info', 'Question saved. Please ensure all required fields meet institutional quality standards.');
    }

    /**
     * Resubmit repository & trigger Automatic IRQA Re-Scan (PART 6, 7 & 8).
     */
    public function resubmit(Request $request, RepositoryRevisionRequest $revisionRequest): RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        // PART 7: Auto Validate Before Resubmit (Check remaining open findings)
        $unresolvedCount = $revisionRequest->items()->where('status', '!=', 'CLOSED')->count();
        if ($unresolvedCount > 0) {
            return redirect()->back()
                ->with('error', "Cannot resubmit repository. Remaining unresolved findings ({$unresolvedCount}) must be fixed first.");
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
            'action'        => 'irqa_scan_started',
            'approval_note' => 'Automatic IRQA Re-Scan initiated by governance engine.',
        ]);

        // 4. Execute Automatic IRQA Re-Scan
        $qualityService = app(RepositoryQualityService::class);
        $rescanAudit = $qualityService->validateRepository($bank);
        $currentWarnings = $rescanAudit['warnings'] ?? [];

        // Audit Log: Automatic IRQA Scan Completed
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'irqa_scan_completed',
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

            // Notify Repository Manager (PART 10)
            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_resubmitted_irqa_passed',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $revisionRequest->requested_by_id,
                    'data'            => json_encode([
                        'title'   => 'Repository IRQA Verification Passed',
                        'message' => "Repository has passed automatic IRQA verification. Ready for governance review.",
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

            // Notify Repository Manager (PART 10)
            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_resubmitted_irqa_failed',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $revisionRequest->requested_by_id,
                    'data'            => json_encode([
                        'title'   => 'Governance Review Required',
                        'message' => "Automatic IRQA detected remaining findings. Governance review required.",
                        'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        // Notify Teacher (PART 9 - Neutral notification only)
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
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
