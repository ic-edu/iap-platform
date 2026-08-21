<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
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

        $query = AssessmentRequest::with(['requester', 'test.assignedTeacher']);

        if (!$isRm && $user && !$user->hasRole('super-admin')) {
            $query->where('requested_by', $user->id);
        }

        $requests = $query->latest()->paginate(15);
        $teachers = User::role('teacher')->where('status', 'active')->get();

        return view('admin.assessment_requests.index', compact('requests', 'teachers', 'isRm'));
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
            'program_context'    => ['nullable', 'string', 'max:255'],
            'required_sections'  => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'requested_deadline' => ['nullable', 'date'],
        ]);

        $assessmentRequest = AssessmentRequest::create([
            'title'              => $validated['title'],
            'test_type'          => $validated['test_type'],
            'program_context'    => $validated['program_context'] ?? null,
            'required_sections'  => $validated['required_sections'] ?? null,
            'notes'              => $validated['notes'] ?? null,
            'requested_deadline' => $validated['requested_deadline'] ?? null,
            'requested_by'       => $user->id,
            'status'             => 'pending',
        ]);

        // Notify Repository Managers of new Assessment Request
        $rms = User::role('repository-manager')->get();
        foreach ($rms as $rm) {
            try {
                $rm->notify(new EnterpriseSystemNotification(
                    title: 'New Assessment Request Submitted',
                    message: "Regular Admin {$user->name} submitted an assessment request: '{$assessmentRequest->title}'.",
                    type: 'ASSESSMENT_REQUEST_SUBMITTED',
                    priority: 'MEDIUM',
                    entityType: 'assessment_request',
                    entityId: (string) $assessmentRequest->id,
                    targetUrl: route('admin.repository-manager.assessment-requests.index')
                ));
            } catch (\Throwable $e) {
                // Silently skip if mail fails
            }
        }

        return redirect()->route('admin.assessment-requests.index')
            ->with('status', "Assessment request '{$assessmentRequest->title}' submitted to Repository Manager.");
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
}
