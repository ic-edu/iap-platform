<?php

namespace App\Modules\QuestionBank\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AclAuditTrail;
use App\Models\AclCategory;
use App\Models\AclVersion;
use App\Models\QuestionBankArchiveRequest;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Notifications\SystemAlertNotification;
use App\Services\AclCoverageService;
use App\Services\AclHealthScoreService;
use App\Services\AclVersioningService;
use App\Services\ActivityLogger;
use App\Services\RepositoryQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    public function __construct(
        protected AclCoverageService $coverageService,
        protected AclHealthScoreService $healthScoreService,
        protected AclVersioningService $versioningService
    ) {}

    /**
     * Display listing of question banks with search, status filters, sorting, coverage indicators, and Content Health Score (TEACHER-003 / ACL).
     */
    public function index(Request $request): View
    {
        $user  = $request->user();
        $query = QuestionBank::with(['category', 'aclCategory', 'creator', 'questions', 'archiveRequests', 'versions', 'auditTrails']);

        // Filter by author=me or my=1
        if ($request->input('author') === 'me' || $request->has('my')) {
            $query->where('created_by', $user->id);
        }

        // Teacher sees only their own question banks unless admin
        if ($user && $user->hasRole('teacher')) {
            $query->where('created_by', $user->id);
        }

        // Search by Title, Keyword, Description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Test Type or ACL Category filter
        if ($type = $request->input('test_type')) {
            $query->where('test_type', $type);
        }

        if ($cat = $request->input('category')) {
            $aclCategory = AclCategory::where('id', $cat)
                ->orWhere('slug', $cat)
                ->orWhere('name', 'like', "%{$cat}%")
                ->first();
            if ($aclCategory) {
                $query->where(function ($q) use ($aclCategory) {
                    $q->where('acl_category_id', $aclCategory->id)
                      ->orWhere('slug', 'like', "%{$aclCategory->slug}%");
                });
            }
        }

        if ($aclCatId = $request->input('acl_category_id')) {
            $query->where('acl_category_id', $aclCatId);
        }

        // Workflow Status filter
        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->where(function ($q) {
                    $q->where('status', 'published')->orWhere('status', 'approved');
                });
            } elseif (in_array($status, ['needs_revision', 'rejected', 'revision_requested'], true)) {
                $query->whereIn('status', ['needs_revision', 'rejected', 'revision_requested']);
            } else {
                $query->where('status', $status);
            }
        }

        // Sorting
        $sort = $request->input('sort', 'updated');
        match ($sort) {
            'created'      => $query->latest('created_at'),
            'questions'    => $query->withCount('questions')->orderBy('questions_count', 'desc'),
            'alphabetical' => $query->orderBy('title', 'asc'),
            default        => $query->latest('updated_at'),
        };

        $banks = $query->paginate(10)->withQueryString();

        // Workspace KPI metrics for Teacher
        $myBanksQuery = QuestionBank::query();
        if ($user && $user->hasRole('teacher')) {
            $myBanksQuery->where('created_by', $user->id);
        }

        $totalBanks           = (clone $myBanksQuery)->count();
        $draftBanks           = (clone $myBanksQuery)->where('status', 'draft')->count();
        $pendingApprovalBanks = (clone $myBanksQuery)->whereIn('status', ['pending_approval', 'submitted', 'pending_archive_approval'])->count();
        $rejectedBanks        = (clone $myBanksQuery)->whereIn('status', ['needs_revision', 'rejected', 'revision_requested'])->count();
        $approvedBanks        = (clone $myBanksQuery)->whereIn('status', ['approved', 'published'])->count();

        // "Continue Working" spotlight card — last edited Question Bank
        $latestEditedBank = (clone $myBanksQuery)->with(['questions'])->latest('updated_at')->first();

        // Recent Activity
        $recentActivities = (clone $myBanksQuery)->latest('updated_at')->take(5)->get();

        // ACL Dynamic Coverage Indicators & Content Health Score
        $coverageReport = $this->coverageService->getCategoryCoverageReport();
        $healthData     = $this->healthScoreService->calculateHealthScore();
        $aclCategories  = AclCategory::where('is_active', true)->get();

        $categories = CourseCategory::all();

        /** @var view-string $viewName */
        $viewName = 'question_bank::index';

        return view($viewName, compact(
            'banks',
            'categories',
            'aclCategories',
            'totalBanks',
            'draftBanks',
            'pendingApprovalBanks',
            'rejectedBanks',
            'approvedBanks',
            'latestEditedBank',
            'recentActivities',
            'coverageReport',
            'healthData'
        ));
    }

    /**
     * Show detailed question bank authoring workspace.
     */
    public function show(Request $request, QuestionBank $questionBank): View
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $questionBank->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to question bank.');
        }

        $questionBank->load(['questions.choices', 'category', 'aclCategory', 'archiveRequests', 'versions.creator', 'auditTrails.actor']);
        $aclCategories = AclCategory::where('is_active', true)->get();

        /** @var view-string $viewName */
        $viewName = 'question_bank::show';

        return view($viewName, compact('questionBank', 'aclCategories'));
    }

    /**
     * Store a newly created Question Bank (Teacher Only).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('repository-manager'))) {
            abort(403, 'Repository Managers are Governance Authorities and cannot create question banks directly.');
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'test_type'       => ['required', 'string'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'category_id'     => ['nullable', 'exists:course_categories,id'],
            'acl_category_id' => ['nullable', 'exists:acl_categories,id'],
        ]);

        $aclCatId = $validated['acl_category_id'] ?? AclCategory::where('test_type', $validated['test_type'])->value('id');

        $bank = QuestionBank::create([
            'title'           => $validated['title'],
            'slug'            => Str::slug($validated['title']).'-'.Str::random(5),
            'test_type'       => $validated['test_type'],
            'description'     => $validated['description'] ?? null,
            'category_id'     => $validated['category_id'] ?? null,
            'acl_category_id' => $aclCatId,
            'status'          => 'draft',
            'current_version' => '1.0',
            'created_by'      => $user?->id,
        ]);

        ActivityLogger::log('question_bank_created', "Created question bank: {$bank->title}", $user);

        // Record Version 1.0 snapshot & Audit Log
        $this->versioningService->createVersion($bank, $user, 'Initial creation');

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'action'        => 'created',
            'actor_id'      => $user?->id,
            'created_by'    => $user?->id,
            'version'       => '1.0',
            'reason'        => 'Initial creation of academic question bank',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$bank->title}' created. Start adding questions below.");
    }

    /**
     * Update question bank details. If published, creates a new version snapshot.
     */
    public function update(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher')) {
            if ((int) $questionBank->created_by !== (int) $user->id) {
                abort(403, 'You can only update your own question banks.');
            }
            if (!in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null], true)) {
                abort(403, 'Repository is locked while awaiting governance approval.');
            }
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'test_type'       => ['required', 'string'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'acl_category_id' => ['nullable', 'exists:acl_categories,id'],
            'current_version' => ['nullable', 'string', 'max:50'],
        ]);

        $isPublished = $questionBank->status === 'published' || (bool) $questionBank->is_published;

        $questionBank->update($validated);

        // Auto-close metadata & repository-level findings if active revision exists and validation passes
        if (Schema::hasTable('repository_revision_requests')) {
            $activeRevision = RepositoryRevisionRequest::where('question_bank_id', $questionBank->id)
                ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                ->latest()
                ->first();

            if ($activeRevision) {
                $qualityService = app(RepositoryQualityService::class);
                $rescanAudit = $qualityService->validateRepository($questionBank);
                $currentWarnings = $rescanAudit['warnings'] ?? [];

                foreach ($activeRevision->items()->where('status', 'OPEN')->get() as $openItem) {
                    $fbLower = strtolower($openItem->feedback ?? '');
                    $stillPresent = false;

                    // 1. Exact match
                    foreach ($currentWarnings as $cw) {
                        if (trim(strtolower($cw)) === $fbLower) {
                            $stillPresent = true;
                            break;
                        }
                    }

                    // 2. Keyword-specific domain checks
                    if (!$stillPresent) {
                        if (str_contains($fbLower, 'description')) {
                            $stillPresent = empty(trim($questionBank->description ?? ''))
                                || collect($currentWarnings)->contains(fn($w) => str_contains(strtolower($w), 'description'));
                        } elseif (str_contains($fbLower, 'version')) {
                            $stillPresent = empty(trim($questionBank->current_version ?? ''))
                                || collect($currentWarnings)->contains(fn($w) => str_contains(strtolower($w), 'version'));
                        } elseif (str_contains($fbLower, 'category')) {
                            $stillPresent = empty($questionBank->acl_category_id)
                                || collect($currentWarnings)->contains(fn($w) => str_contains(strtolower($w), 'category'));
                        } elseif (str_contains($fbLower, 'insufficient question') || str_contains($fbLower, 'question count')) {
                            $stillPresent = $questionBank->questions()->count() === 0
                                || collect($currentWarnings)->contains(fn($w) => str_contains(strtolower($w), 'insufficient question') || str_contains(strtolower($w), 'question count'));
                        }
                    }

                    if (!$stillPresent) {
                        $openItem->status = 'CLOSED';
                        $openItem->save();

                        RepositoryActivityLog::create([
                            'resource_type' => 'QuestionBank',
                            'resource_id'   => (string) $questionBank->id,
                            'actor_id'      => $user?->id,
                            'action'        => 'teacher_resolved_finding',
                            'approval_note' => "Repository metadata update resolved finding #{$openItem->id}: {$openItem->feedback}",
                        ]);
                    }
                }
            }
        }

        ActivityLogger::log('QUESTION_BANK_EDITED', "Updated question bank: {$questionBank->title}", $user);

        // If content was published, create new version snapshot to preserve history
        if ($isPublished) {
            $this->versioningService->createVersion($questionBank, $user, 'Editing published content created new version');
        }

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'updated',
            'actor_id'      => $user?->id,
            'updated_by'    => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Updated question bank details',
        ]);

        $redirectParams = [$questionBank->id];
        if ($request->filled('from')) {
            $redirectParams['from'] = $request->input('from');
        }
        if ($request->filled('revision_request_id')) {
            $redirectParams['revision_request_id'] = $request->input('revision_request_id');
        }

        return redirect()->route('admin.question-banks.show', $redirectParams)
            ->with('status', 'Question Bank details updated.');
    }

    /**
     * Submit question bank for Super Admin approval.
     */
    public function submitForApproval(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();

        if ($user && $user->hasRole('teacher')) {
            if ((int) $questionBank->created_by !== (int) $user->id) {
                abort(403);
            }
            if (in_array($questionBank->status, ['pending_approval', 'approved', 'published'], true)) {
                abort(403, 'Question Bank has already been submitted for governance approval.');
            }

            // State Integrity Guard: If there is an active revision request, ensure all findings are CLOSED
            if (Schema::hasTable('repository_revision_requests')) {
                $activeRevision = RepositoryRevisionRequest::where('question_bank_id', $questionBank->id)
                    ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                    ->latest()
                    ->first();

                if ($activeRevision) {
                    $unresolvedCount = $activeRevision->items()->where('status', '!=', 'CLOSED')->count();
                    if ($unresolvedCount > 0) {
                        return redirect()->back()
                            ->with('error', "Cannot submit repository for approval. There are {$unresolvedCount} unresolved revision finding(s) that must be fixed first.");
                    }
                    $activeRevision->status = 'RESUBMITTED';
                    $activeRevision->save();
                }
            }
        }

        $questionBank->update(['status' => 'pending_approval']);

        // Guarantee GovernanceApprovalTask creation (Idempotent: prevents duplicates)
        if (\Illuminate\Support\Facades\Schema::hasTable('governance_approval_tasks')) {
            \App\Models\GovernanceApprovalTask::firstOrCreate([
                'question_bank_id' => $questionBank->id,
                'status'           => 'OPEN',
            ], [
                'teacher_id'       => $user?->id ?? $questionBank->created_by,
                'workflow'         => 'APPROVAL',
                'submitted_at'     => now(),
            ]);
        }

        // Dispatch Repository Manager Notification
        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            $repoManagers = \App\Models\User::role(['repository-manager', 'super-admin'])->get();
            foreach ($repoManagers as $rm) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_submitted_for_approval',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $rm->id,
                    'data'            => json_encode([
                        'title'        => 'New Repository Submitted',
                        'message'      => "Repository '{$questionBank->title}' submitted by {$user->name}",
                        'repository'   => $questionBank->title,
                        'submitted_by' => $user->name,
                        'link'         => route('admin.repository-manager.question-bank-validate', $questionBank->id),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        ActivityLogger::log('QUESTION_BANK_SUBMITTED', "Submitted question bank for approval: {$questionBank->title}", $user);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'submitted',
            'actor_id'      => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Submitted for Repository Manager governance approval',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$questionBank->title}' submitted for Super Admin approval.");
    }

    /**
     * Review Question Bank (Admin / Super Admin).
     */
    public function review(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot perform formal review actions.');
        }

        $questionBank->update(['status' => 'reviewed']);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'reviewed',
            'actor_id'      => $user?->id,
            'reviewer_id'   => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Reviewed academic content',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$questionBank->title}' marked as reviewed.");
    }

    /**
     * Request revision / reject (Super Admin).
     */
    public function requestRevision(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('super-admin') && !$user->hasRole('admin'))) {
            abort(403);
        }

        $reason = $request->input('reason', 'Revision requested by reviewer.');

        $questionBank->update(['status' => 'rejected']);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'revision_requested',
            'actor_id'      => $user->id,
            'reviewer_id'   => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => $reason,
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Revision requested for '{$questionBank->title}'.");
    }

    /**
     * Repository Manager publishes approved question bank.
     */
    public function publish(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();

        if (!$user || !$user->hasRole('repository-manager')) {
            abort(403, 'Publishing Question Banks is strictly reserved for Repository Managers.');
        }

        if ($questionBank->status !== 'approved') {
            abort(403, 'Cannot publish: Question Bank must be approved by Super Admin first.');
        }

        $questionBank->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        ActivityLogger::log('QUESTION_BANK_PUBLISHED', "Published Question Bank '{$questionBank->title}' live", $questionBank);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'published',
            'actor_id'      => $user?->id,
            'published_by'  => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Published live to institutional repository',
        ]);

        if ($questionBank->creator) {
            try {
                $questionBank->creator->notify(new SystemAlertNotification(
                    'Question Bank Published',
                    "Your Question Bank '{$questionBank->title}' has been published live by Repository Manager {$user->name}."
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question Bank '{$questionBank->title}' published live successfully.");
    }

    /**
     * Unpublish a Question Bank (Repository Manager Only).
     */
    public function unpublish(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('repository-manager')) {
            abort(403, 'Unpublishing Question Banks is strictly reserved for Repository Managers.');
        }

        $questionBank->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        ActivityLogger::log('QUESTION_BANK_UNPUBLISHED', "Unpublished Question Bank '{$questionBank->title}'", $questionBank);

        return redirect()->route('admin.question-banks.index')->with('status', "Question Bank '{$questionBank->title}' unpublished.");
    }

    /**
     * Request Question Bank Archive.
     */
    public function requestArchive(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        if ($questionBank->hasPendingArchiveRequest()) {
            return redirect()->back()->with('error', 'An archive request for this Question Bank is already pending Super Admin approval.');
        }

        $reason = $request->input('reason', 'Archive requested.');

        QuestionBankArchiveRequest::create([
            'question_bank_id' => $questionBank->id,
            'requested_by'      => $user?->id,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        $questionBank->update(['status' => 'pending_archive_approval']);

        AclAuditTrail::create([
            'resource_type'        => 'QuestionBank',
            'resource_id'          => $questionBank->id,
            'action'               => 'archive_requested',
            'actor_id'             => $user?->id,
            'archive_requested_by' => $user?->id,
            'version'              => $questionBank->current_version ?? '1.0',
            'reason'               => $reason,
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Archive request for Question Bank '{$questionBank->title}' submitted for Super Admin approval.");
    }

    /**
     * Request Question Bank Restoration from archive.
     */
    public function requestRestore(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Authorization: Owner Teacher or Super Admin ONLY (RM and non-owner Teacher forbidden)
        $isOwner = $questionBank->created_by === $user->id;
        $isSuperAdmin = $user->hasRole('super-admin');

        if (!$isOwner && !$isSuperAdmin) {
            abort(403, 'Unauthorized. Only the repository owner or Super Admin can request restoration.');
        }

        // State Guard: Source state MUST be archived
        if ($questionBank->status !== 'archived') {
            return redirect()->back()->with('danger', "Only ARCHIVED question banks can be requested for restoration. Current status: '{$questionBank->status}'.");
        }

        $reason = trim($request->input('reason', $request->input('restoration_reason', '')) ?: 'Requested restoration from archive');

        $questionBank->update(['status' => 'pending_restore_approval']);

        // Task Synchronization: Create GovernanceApprovalTask for restoration
        if (class_exists(\App\Models\GovernanceApprovalTask::class)) {
            \App\Models\GovernanceApprovalTask::create([
                'question_bank_id' => $questionBank->id,
                'teacher_id'       => $questionBank->created_by ?: $user->id,
                'workflow'         => 'APPROVAL',
                'status'           => 'OPEN',
                'submitted_at'     => now(),
            ]);
        }

        AclAuditTrail::create([
            'resource_type'        => 'QuestionBank',
            'resource_id'          => $questionBank->id,
            'action'               => 'restore_requested',
            'actor_id'             => $user->id,
            'restore_requested_by' => $user->id,
            'version'              => $questionBank->current_version ?? '1.0',
            'reason'               => $reason,
        ]);

        if (class_exists(\App\Models\RepositoryActivityLog::class)) {
            \App\Models\RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $questionBank->id,
                'actor_id'      => $user->id,
                'action'        => 'restore_requested',
                'approval_note' => $reason,
            ]);
        }

        // Notify Super Admin recipient(s)
        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            $superAdmins = \App\Models\User::role('super-admin')->get();
            foreach ($superAdmins as $superAdmin) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'question_bank_restoration_requested',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $superAdmin->id,
                    'data'            => json_encode([
                        'title'            => 'Question Bank Restoration Requested',
                        'message'          => "'{$user->name}' requested restoration for '{$questionBank->title}'. Reason: {$reason}",
                        'reason'           => $reason,
                        'question_bank_id' => $questionBank->id,
                        'link'             => route('admin.approvals.question-bank-restorations', ['from' => 'notifications']),
                        'priority'         => 'HIGH',
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        return redirect()->back()
            ->with('status', "Restore request for '{$questionBank->title}' submitted for Super Admin approval.");
    }

    /**
     * Super Admin Approves Restore.
     */
    public function approveRestore(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user() ?: request()->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can approve restoration.');
        }

        // State Guard: Source state MUST be pending_restore_approval
        if ($questionBank->status !== 'pending_restore_approval') {
            return redirect()->back()->with('danger', "Cannot approve restore for Question Bank not in pending_restore_approval status. Current status: '{$questionBank->status}'.");
        }

        $questionBank->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        // Resolve restoration task
        if (class_exists(\App\Models\GovernanceApprovalTask::class)) {
            \App\Models\GovernanceApprovalTask::where('question_bank_id', $questionBank->id)
                ->whereIn('status', ['OPEN', 'PENDING'])
                ->update([
                    'status'       => 'COMPLETED',
                    'completed_at' => now(),
                ]);
        }

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'restored',
            'actor_id'      => $user->id,
            'approver_id'   => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Approved restoration to active repository',
        ]);

        if (class_exists(\App\Models\RepositoryActivityLog::class)) {
            \App\Models\RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $questionBank->id,
                'actor_id'      => $user->id,
                'action'        => 'restore_approved',
                'approval_note' => 'Super Admin approved repository restoration',
            ]);
        }

        // Notify Repository Author
        $authorId = $questionBank->created_by;
        if ($authorId && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'question_bank_restoration_approved',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $authorId,
                'data'            => json_encode([
                    'title'            => 'Repository Restoration Approved',
                    'message'          => "Super Admin approved restoration for '{$questionBank->title}'. Status is now Approved.",
                    'question_bank_id' => $questionBank->id,
                    'link'             => route('admin.question-banks.show', $questionBank->id),
                    'priority'         => 'HIGH',
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return redirect()->back()
            ->with('status', "Question Bank '{$questionBank->title}' restored to approved status.");
    }

    /**
     * Super Admin Rejects Restore.
     */
    public function rejectRestore(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can reject restoration.');
        }

        // State Guard: Source state MUST be pending_restore_approval
        if ($questionBank->status !== 'pending_restore_approval') {
            return redirect()->back()->with('danger', "Cannot reject restore for Question Bank not in pending_restore_approval status. Current status: '{$questionBank->status}'.");
        }

        $reason = trim($request->input('reason', $request->input('restoration_rejection_reason', '')) ?: 'Restoration request rejected by Super Admin');

        $questionBank->update([
            'status'       => 'archived',
            'is_published' => false,
        ]);

        // Resolve restoration task as REJECTED
        if (class_exists(\App\Models\GovernanceApprovalTask::class)) {
            \App\Models\GovernanceApprovalTask::where('question_bank_id', $questionBank->id)
                ->whereIn('status', ['OPEN', 'PENDING'])
                ->update([
                    'status'       => 'REJECTED',
                    'completed_at' => now(),
                ]);
        }

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'restore_rejected',
            'actor_id'      => $user->id,
            'approver_id'   => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => $reason,
        ]);

        if (class_exists(\App\Models\RepositoryActivityLog::class)) {
            \App\Models\RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $questionBank->id,
                'actor_id'      => $user->id,
                'action'        => 'restore_rejected',
                'approval_note' => $reason,
            ]);
        }

        // Notify Repository Author with rejection reason
        $authorId = $questionBank->created_by;
        if ($authorId && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'question_bank_restoration_rejected',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $authorId,
                'data'            => json_encode([
                    'title'            => 'Repository Restoration Rejected',
                    'message'          => "Super Admin rejected restoration for '{$questionBank->title}'. Reason: {$reason}",
                    'rejection_reason' => $reason,
                    'question_bank_id' => $questionBank->id,
                    'link'             => route('admin.question-banks.show', $questionBank->id),
                    'priority'         => 'HIGH',
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return redirect()->back()
            ->with('status', "Restoration request for '{$questionBank->title}' rejected. Status returned to Archived.");
    }

    /**
     * Rollback to a specific snapshot version.
     */
    public function rollbackVersion(AclVersion $version): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot execute version rollbacks directly.');
        }

        $bank = $this->versioningService->rollbackVersion($version, $user);

        return redirect()->route('admin.question-banks.show', $bank->id)
            ->with('status', "Rolled back Question Bank '{$bank->title}' to version {$version->version_number}.");
    }

    /**
     * Author a new question into a question bank (QB-002: Teacher Only).
     */
    public function storeQuestion(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot author questions directly.');
        }

        if ($user && $user->hasRole('teacher')) {
            if ((int) $questionBank->created_by !== (int) $user->id) {
                abort(403, 'Unauthorized access to question bank.');
            }
            if (!in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null], true)) {
                abort(403, 'Repository is locked while awaiting governance approval.');
            }
        }

        $validated = $request->validate([
            'prompt'                => ['required', 'string'],
            'question_type'         => ['required', 'string'],
            'difficulty'            => ['required', 'string'],
            'points'                => ['required', 'integer', 'min:1'],
            'explanation'           => ['nullable', 'string'],
            'passage_text'          => ['nullable', 'string'],
            'audio_url'             => ['nullable', 'string'],
            'media_asset_id'        => ['nullable', 'string'],
            'choices'               => ['nullable', 'array'],
            'choices.*.label'       => ['nullable', 'string'],
            'choices.*.content'     => ['nullable', 'string'],
            'correct_choice'        => ['nullable'],
            'correct_choices'       => ['nullable', 'array'],
            'tf_correct_choice'     => ['nullable', 'string'],
            'short_answer_text'     => ['nullable', 'string'],
            'reference_answer_text' => ['nullable', 'string'],
        ]);

        $qType = $validated['question_type'];

        $question = Question::create([
            'question_bank_id' => $questionBank->id,
            'media_asset_id'   => $validated['media_asset_id'] ?? null,
            'prompt'           => $validated['prompt'],
            'question_type'    => $qType,
            'difficulty'        => $validated['difficulty'],
            'points'            => $validated['points'],
            'explanation'       => $validated['explanation'] ?? ($validated['reference_answer_text'] ?? null),
            'passage_text'      => $validated['passage_text'] ?? null,
            'audio_url'         => $validated['audio_url'] ?? null,
        ]);

        $this->saveChoicesForQuestion($question, $qType, $validated);

        return redirect()->route('admin.question-banks.show', $questionBank->id)
            ->with('status', "Question successfully authored and saved. Total questions in bank: {$questionBank->questions()->count()}.");
    }

    /**
     * Update an existing question (QB-002: Teacher Only).
     */
    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot edit questions directly.');
        }

        $questionBank = $question->questionBank;
        if ($user && $user->hasRole('teacher')) {
            if ($questionBank && (int) $questionBank->created_by !== (int) $user->id) {
                abort(403, 'Unauthorized access to question.');
            }
            if ($questionBank && !in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null], true)) {
                abort(403, 'Repository is locked while awaiting governance approval.');
            }
        }

        $validated = $request->validate([
            'prompt'                => ['required', 'string'],
            'question_type'         => ['required', 'string'],
            'difficulty'            => ['required', 'string'],
            'points'                => ['required', 'integer', 'min:1'],
            'explanation'           => ['nullable', 'string'],
            'passage_text'          => ['nullable', 'string'],
            'audio_url'             => ['nullable', 'string'],
            'media_asset_id'        => ['nullable', 'string'],
            'choices'               => ['nullable', 'array'],
            'choices.*.label'       => ['nullable', 'string'],
            'choices.*.content'     => ['nullable', 'string'],
            'correct_choice'        => ['nullable'],
            'correct_choices'       => ['nullable', 'array'],
            'tf_correct_choice'     => ['nullable', 'string'],
            'short_answer_text'     => ['nullable', 'string'],
            'reference_answer_text' => ['nullable', 'string'],
        ]);

        $qType = $validated['question_type'];

        $question->update([
            'prompt'         => $validated['prompt'],
            'media_asset_id' => $validated['media_asset_id'] ?? $question->media_asset_id,
            'question_type'  => $qType,
            'difficulty'     => $validated['difficulty'],
            'points'         => $validated['points'],
            'explanation'    => $validated['explanation'] ?? ($validated['reference_answer_text'] ?? null),
            'passage_text'   => $validated['passage_text'] ?? null,
            'audio_url'      => $validated['audio_url'] ?? null,
        ]);

        $question->choices()->delete();
        $this->saveChoicesForQuestion($question, $qType, $validated);

        return redirect()->route('admin.question-banks.show', $question->question_bank_id)
            ->with('status', 'Question updated successfully.');
    }

    /**
     * Duplicate a question inside a question bank (QB-002: Teacher Only).
     */
    public function duplicateQuestion(Request $request, Question $question): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot duplicate/author questions directly.');
        }

        $questionBank = $question->questionBank;
        if ($user && $user->hasRole('teacher')) {
            if ($questionBank && (int) $questionBank->created_by !== (int) $user->id) {
                abort(403, 'Unauthorized access to question.');
            }
            if ($questionBank && !in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null], true)) {
                abort(403, 'Repository is locked while awaiting governance approval.');
            }
        }

        $newQ = $question->replicate();
        $newQ->prompt = 'Copy of '.$question->prompt;
        $newQ->save();

        foreach ($question->choices as $choice) {
            $newC = $choice->replicate();
            $newC->question_id = (string) $newQ->id;
            $newC->save();
        }

        return redirect()->route('admin.question-banks.show', $question->question_bank_id)
            ->with('status', 'Question duplicated successfully.');
    }

    /**
     * Delete a question with QUESTION_DELETE audit log.
     */
    public function destroyQuestion(Question $question): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers are not permitted to delete questions.');
        }

        ActivityLogger::log(
            action: 'QUESTION_DELETE',
            description: "Deleted Question #{$question->id}: {$question->prompt}",
            subject: $question,
            properties: [
                'question_id'    => $question->id,
                'question_title' => $question->prompt,
            ]
        );

        $bankId = $question->question_bank_id;
        $question->delete();

        return redirect()->route('admin.question-banks.show', $bankId)->with('status', 'Question deleted successfully.');
    }

    /**
     * Delete question bank (Safe Delete Governance Workflow TASK 4).
     */
    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot delete question banks.');
        }

        // TASK 4 Safe Delete Governance: Published assets enter pending_deletion for Super Admin approval
        if (in_array($questionBank->status, ['published', 'approved']) && !$user->hasRole('super-admin')) {
            $questionBank->update(['status' => 'pending_deletion']);

            ActivityLogger::log(
                action: 'question_bank_deletion_requested',
                description: "Requested deletion for published question bank: {$questionBank->title}",
                subject: $questionBank
            );

            return redirect()->route('admin.question-banks.index')
                ->with('status', "Deletion requested for published question bank '{$questionBank->title}'. Awaiting Super Admin approval.");
        }

        $questionBank->delete();

        ActivityLogger::log('question_bank_deleted', "Soft deleted question bank: {$questionBank->title}", $user);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$questionBank->title}' deleted.");
    }

    /**
     * Duplicate a question bank repository (QB-003: Status-Aware Duplicate).
     */
    public function duplicate(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        // Governance Matrix Guard: Only draft, needs_revision, rejected, published are duplicable
        $allowedStatuses = ['draft', 'needs_revision', 'rejected', 'published'];
        if (! in_array($questionBank->status, $allowedStatuses, true)) {
            if ($request->expectsJson()) {
                abort(403, 'Question Bank cannot be duplicated in its current governance state.');
            }

            return redirect()->back()->with('danger', 'Question Bank cannot be duplicated in its current governance state.');
        }

        // Create new Question Bank in DRAFT status
        $newTitle = $questionBank->title . ' (Copy)';
        $newBank  = QuestionBank::create([
            'title'           => $newTitle,
            'slug'            => Str::slug($newTitle) . '-' . Str::random(5),
            'test_type'       => $questionBank->test_type,
            'description'     => $questionBank->description,
            'category_id'     => $questionBank->category_id,
            'acl_category_id' => $questionBank->acl_category_id,
            'status'          => 'draft',
            'current_version' => '1.0',
            'created_by'      => $user?->id,
            'is_published'    => false,
        ]);

        // Deep copy questions and choices
        $questionBank->load(['questions.choices']);
        foreach ($questionBank->questions as $oldQ) {
            $newQ = $newBank->questions()->create([
                'prompt'        => $oldQ->prompt,
                'question_type' => $oldQ->question_type,
                'difficulty'    => $oldQ->difficulty,
                'points'        => $oldQ->points,
            ]);

            foreach ($oldQ->choices as $oldC) {
                $newQ->choices()->create([
                    'label'      => $oldC->label,
                    'content'    => $oldC->content,
                    'is_correct' => $oldC->is_correct,
                ]);
            }
        }

        ActivityLogger::log('question_bank_duplicated', "Duplicated question bank: {$questionBank->title} into {$newBank->title}", $user);

        return redirect()->route('admin.question-banks.show', $newBank->id)
            ->with('status', "Question bank '{$questionBank->title}' duplicated into new draft '{$newBank->title}'.");
    }

    /**
     * Helper to save choices for a question based on its question_type.
     */
    private function saveChoicesForQuestion(Question $question, string $qType, array $validated): void
    {
        if (in_array($qType, ['single_choice', 'multiple_choice', 'listening', 'reading'])) {
            $choices = $validated['choices'] ?? [];
            $correctIdx = $validated['correct_choice'] ?? null;

            foreach ($choices as $idx => $choiceData) {
                if (!empty($choiceData['content']) || !empty($choiceData['label'])) {
                    QuestionChoice::create([
                        'question_id' => (string) $question->id,
                        'label'       => $choiceData['label'] ?? chr(65 + (int)$idx),
                        'content'     => $choiceData['content'] ?? '',
                        'is_correct'  => ((string) $idx === (string) $correctIdx),
                    ]);
                }
            }
        } elseif ($qType === 'multiple_response') {
            $choices = $validated['choices'] ?? [];
            $correctIndices = $validated['correct_choices'] ?? [];

            foreach ($choices as $idx => $choiceData) {
                if (!empty($choiceData['content']) || !empty($choiceData['label'])) {
                    QuestionChoice::create([
                        'question_id' => (string) $question->id,
                        'label'       => $choiceData['label'] ?? chr(65 + (int)$idx),
                        'content'     => $choiceData['content'] ?? '',
                        'is_correct'  => in_array((string) $idx, array_map('strval', $correctIndices), true),
                    ]);
                }
            }
        } elseif ($qType === 'true_false') {
            $tfCorrect = $validated['tf_correct_choice'] ?? 'true';
            QuestionChoice::create([
                'question_id' => (string) $question->id,
                'label'       => 'A',
                'content'     => 'True',
                'is_correct'  => ($tfCorrect === 'true'),
            ]);
            QuestionChoice::create([
                'question_id' => (string) $question->id,
                'label'       => 'B',
                'content'     => 'False',
                'is_correct'  => ($tfCorrect === 'false'),
            ]);
        } elseif (in_array($qType, ['short_answer', 'essay', 'speaking', 'writing'])) {
            $answerText = $validated['short_answer_text'] ?? ($validated['reference_answer_text'] ?? '');
            if (!empty($answerText)) {
                QuestionChoice::create([
                    'question_id' => (string) $question->id,
                    'label'       => 'Key',
                    'content'     => $answerText,
                    'is_correct'  => true,
                ]);
            }
        }
    }

    /**
     * Bulk import CSV questions into a question bank.
     */
    public function importQuestions(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot import questions directly.');
        }

        if ($user && $user->hasRole('teacher')) {
            if ((int) $questionBank->created_by !== (int) $user->id) {
                abort(403, 'Unauthorized access to question bank.');
            }
            if (!in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null], true)) {
                abort(403, 'Repository is locked while awaiting governance approval.');
            }
        }

        $csvContent = $request->input('csv_content', '');
        if (empty(trim($csvContent))) {
            return redirect()->back()->with('error', 'CSV content cannot be empty.');
        }

        $lines = explode("\n", str_replace("\r", "", $csvContent));
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) >= 6) {
                $prompt = $parts[0];
                $choiceA = $parts[1];
                $choiceB = $parts[2];
                $choiceC = $parts[3];
                $choiceD = $parts[4];
                $correctIdx = (int) $parts[5];

                $question = Question::create([
                    'question_bank_id' => $questionBank->id,
                    'prompt'           => $prompt,
                    'question_type'    => 'single_choice',
                    'difficulty'        => 'medium',
                    'points'            => 1,
                ]);

                $choices = [$choiceA, $choiceB, $choiceC, $choiceD];
                foreach ($choices as $idx => $content) {
                    QuestionChoice::create([
                        'question_id' => (string) $question->id,
                        'label'       => chr(65 + $idx),
                        'content'     => $content,
                        'is_correct'  => ($idx === $correctIdx),
                    ]);
                }
                $count++;
            }
        }

        return redirect()->route('admin.question-banks.show', $questionBank->id)
            ->with('status', "Imported {$count} questions successfully.");
    }
}
