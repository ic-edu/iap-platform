<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Notifications\EnterpriseSystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AssessmentRequestController extends Controller
{
    /**
     * Display assessment requests index / RM Intake Queue.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isRm = $user && $user->hasRole('repository-manager');

        $query = AssessmentRequest::with(['requester', 'candidate', 'test.assignedTeacher']);

        if (!$isRm && $user && !$user->hasRole('super-admin')) {
            $query->where('requested_by', $user->id);
        }

        $requests = $query->latest()->paginate(15);
        $teachers = User::role('teacher')->where('status', 'active')->get();

        // Query eligible candidates (B2C Paid + B2B Active Seat Allocations) for RA request form
        $eligibleCandidates = app(\App\Services\AssessmentRequestEligibilityService::class)
            ->getEligibleCandidatesQuery()
            ->orderBy('name')
            ->get();

        $existingActiveRequest = null;
        if ($request->filled('candidate_id') && $request->filled('test_type')) {
            $existingActiveRequest = AssessmentRequest::resolveActiveRequirement(
                (int) $request->input('candidate_id'),
                $request->input('test_type'),
                $request->input('program_context')
            );
        }

        return view('admin.assessment_requests.index', compact('requests', 'teachers', 'eligibleCandidates', 'isRm', 'existingActiveRequest'));
    }

    /**
     * Regular Admin submits an Assessment Request / Brief.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('super-admin')) {
            abort(403, 'Super Admin cannot submit assessment requests directly.');
        }

        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'test_type'          => ['required', 'string', 'in:toeic,toefl,ielts,general'],
            'candidate_id'       => ['nullable', 'exists:users,id'],
            'program_context'    => ['nullable', 'string', 'max:255'],
            'required_sections'  => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'requested_deadline' => ['nullable', 'date'],
        ]);

        $candidateId = $validated['candidate_id'] ?? null;

        // Server-Side Enforcement: Verify Candidate Eligibility if candidate_id is provided
        if ($candidateId) {
            $candidate = User::findOrFail($candidateId);

            if (!$candidate->hasRole('student')) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "User '{$candidate->name}' is not a registered candidate.");
            }

            $isEligible = app(\App\Services\AssessmentRequestEligibilityService::class)->isCandidateEligible($candidate);

            if (!$isEligible) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Candidate '{$candidate->name}' is not Paid Eligible. Special Mock Test requests require a confirmed paid transaction or an active institutional seat allocation.");
            }
        }

        // Atomic Duplicate Check and Creation
        $createdRequest = null;
        $existingRequest = null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $candidateId, $user, &$createdRequest, &$existingRequest) {
            // 1. Candidate-Linked Active Requirement Check (matches candidate, test_type, program_context; ignores title)
            if ($candidateId) {
                $existing = AssessmentRequest::resolveActiveRequirement(
                    (int) $candidateId,
                    $validated['test_type'],
                    $validated['program_context'] ?? null,
                    lockForUpdate: true
                );

                if ($existing) {
                    $existingRequest = $existing;
                    return;
                }
            } else {
                // 2. Generic Candidate-Less Active Requirement Check (matching test_type and title)
                $existingGeneric = AssessmentRequest::whereIn('status', ['pending', 'draft_created'])
                    ->whereNull('candidate_id')
                    ->where('test_type', strtolower(trim($validated['test_type'])))
                    ->where('title', $validated['title'])
                    ->lockForUpdate()
                    ->first();

                if ($existingGeneric && $existingGeneric->isActive()) {
                    $existingRequest = $existingGeneric;
                    return;
                }
            }

            // 3. Create new AssessmentRequest
            $createdRequest = AssessmentRequest::create([
                'title'              => $validated['title'],
                'test_type'          => $validated['test_type'],
                'candidate_id'       => $candidateId,
                'program_context'    => $validated['program_context'] ?? null,
                'required_sections'  => $validated['required_sections'] ?? null,
                'notes'              => $validated['notes'] ?? null,
                'requested_deadline' => $validated['requested_deadline'] ?? null,
                'requested_by'       => $user->id,
                'status'             => 'pending',
            ]);
        });

        if ($existingRequest) {
            $candidateName = $candidateId ? (User::find($candidateId)?->name ?? 'Candidate') : null;
            $msg = $candidateName
                ? "An active assessment request for candidate '{$candidateName}' ('{$existingRequest->title}') already exists with status: {$existingRequest->getWorkflowStageLabel()}."
                : "A pending assessment request '{$existingRequest->title}' for this requirement already exists in the intake queue.";

            return redirect()->route('admin.assessment-requests.index')
                ->with('info', $msg);
        }

        // Notify Repository Managers ONLY when a new request is created
        if ($createdRequest) {
            $rms = User::role('repository-manager')->get();
            foreach ($rms as $rm) {
                try {
                    $rm->notify(new EnterpriseSystemNotification(
                        title: 'New Assessment Request Submitted',
                        message: "Regular Admin {$user->name} submitted an assessment request: '{$createdRequest->title}'.",
                        type: 'ASSESSMENT_REQUEST_SUBMITTED',
                        priority: 'MEDIUM',
                        entityType: 'assessment_request',
                        entityId: (string) $createdRequest->id,
                        targetUrl: route('admin.repository-manager.assessment-requests.index')
                    ));
                } catch (\Throwable $e) {
                    // Silently skip if mail fails
                }
            }

            return redirect()->route('admin.assessment-requests.index')
                ->with('status', "Assessment request '{$createdRequest->title}' submitted to Repository Manager.");
        }

        return redirect()->route('admin.assessment-requests.index');
    }

    /**
     * Repository Manager creates Assessment Draft from request and assigns Teacher.
     */
    public function createDraft(Request $request, AssessmentRequest $assessmentRequest): RedirectResponse
    {
        $user = $request->user();

        if (!$user || (!$user->hasRole('repository-manager') && !$user->hasRole('super-admin'))) {
            abort(403, 'Only Repository Managers can create drafts from requests and assign Teachers.');
        }

        $validated = $request->validate([
            'teacher_id'       => ['required', 'exists:users,id'],
            'title'            => ['required', 'string', 'max:255'],
            'test_type'        => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_score'       => ['required', 'integer', 'min:0'],
        ]);

        $teacher = User::findOrFail($validated['teacher_id']);

        $test = Test::create([
            'title'                 => $validated['title'],
            'slug'                  => Str::slug($validated['title']) . '-' . Str::random(5),
            'test_type'             => $validated['test_type'],
            'assessment_mode'       => 'real_test',
            'scoring_method'        => 'automatic',
            'duration_minutes'      => $validated['duration_minutes'],
            'pass_score'            => $validated['pass_score'],
            'created_by'            => $user->id,
            'assigned_to'           => $teacher->id,
            'assessment_request_id' => $assessmentRequest->id,
            'status'                => 'draft',
            'is_published'          => false,
        ]);

        // Default section 1
        TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1: General Core',
            'order'   => 1,
        ]);

        $assessmentRequest->update([
            'status'  => 'draft_created',
            'test_id' => $test->id,
        ]);

        // Notify Assigned Teacher
        try {
            $teacher->notify(new EnterpriseSystemNotification(
                title: 'New Assessment Draft Assigned',
                message: "Repository Manager {$user->name} assigned Assessment Draft '{$test->title}' to you for authoring.",
                type: 'ASSESSMENT_DRAFT_ASSIGNED',
                priority: 'HIGH',
                entityType: 'test',
                entityId: (string) $test->id,
                targetUrl: route('teacher.tests.show', $test->id)
            ));
        } catch (\Throwable $e) {
            // Silently skip
        }

        return redirect()->route('admin.repository-manager.assessment-requests.index')
            ->with('status', "Draft assessment '{$test->title}' created and assigned to Teacher {$teacher->name}.");
    }

    /**
     * Repository Manager inspects governed assessment draft in read-only mode.
     */
    public function showDraftAssessment(Request $request, AssessmentRequest $assessmentRequest): View
    {
        $user = $request->user();

        if (!$user || (!$user->hasRole('repository-manager') && !$user->hasRole('super-admin'))) {
            abort(403, 'Unauthorized access to repository draft inspection.');
        }

        if (!$assessmentRequest->test_id) {
            abort(404, 'No draft assessment has been generated for this request yet.');
        }

        $test = $assessmentRequest->test;

        if (!$test) {
            abort(404, 'Associated assessment draft record not found.');
        }

        // Strict Bidirectional Provenance Verification
        if ((string) $assessmentRequest->test_id !== (string) $test->id) {
            abort(403, 'Mismatched assessment request provenance.');
        }

        if ($test->assessment_request_id && (string) $test->assessment_request_id !== (string) $assessmentRequest->id) {
            abort(403, 'Mismatched assessment request provenance.');
        }

        $assessmentRequest->load(['requester', 'candidate']);
        $test->load([
            'sections.testQuestions.question.choices',
            'sections.testQuestions.question.questionBank',
            'sections.mediaAssets',
            'creator',
            'assignedTeacher',
        ]);

        return view('admin.repository_manager.assessment_draft_inspection', compact('assessmentRequest', 'test'));
    }
}
