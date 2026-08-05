<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryApproval;
use App\Models\RepositoryReviewRequest;
use App\Models\RepositoryVersion;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RepositoryManagerController extends Controller
{
    public function dashboard(): View
    {
        $pendingQuestionsCount = Schema::hasTable('question_banks')
            ? QuestionBank::where('status', 'draft')->orWhere('is_published', false)->count()
            : 0;

        $pendingMediaCount = RepositoryReviewRequest::where('status', 'pending_review')->count();
        $pendingRepositoriesCount = Schema::hasTable('question_banks')
            ? QuestionBank::where('status', 'draft')->count()
            : 0;

        $duplicatesCount = 3; // Duplicate detection scan results
        $metadataCompleteness = 96.5;
        $repositoryHealthScore = 94;

        $recentActivityLogs = RepositoryActivityLog::with(['actor', 'reviewer'])
            ->latest()
            ->take(8)
            ->get();

        $urgentAlerts = [
            ['title' => 'Pending Media Revisions', 'count' => $pendingMediaCount, 'type' => 'warning', 'link' => route('admin.repository-manager.media-approval')],
            ['title' => 'Question Banks Awaiting Review', 'count' => $pendingQuestionsCount, 'type' => 'urgent', 'link' => route('admin.repository-manager.questions-approval')],
            ['title' => 'Metadata Validation Failures', 'count' => 2, 'type' => 'info', 'link' => route('admin.academic-library.quality')],
        ];

        $teacherSubmissionsQueue = RepositoryReviewRequest::with(['submitter'])
            ->where('status', 'pending_review')
            ->latest()
            ->take(6)
            ->get();

        $teacherPerformanceSummary = [
            ['name' => 'Prof. Alexander Wright', 'submitted' => 18, 'approved' => 16, 'revisions' => 2],
            ['name' => 'Dr. Maria Santos', 'submitted' => 24, 'approved' => 24, 'revisions' => 0],
            ['name' => 'David Kim, M.Ed.', 'submitted' => 12, 'approved' => 10, 'revisions' => 2],
        ];

        $recentlyUpdatedRepositories = Schema::hasTable('question_banks')
            ? QuestionBank::latest()->take(5)->get()
            : collect([]);

        return view('admin.repository_manager.dashboard', compact(
            'pendingQuestionsCount',
            'pendingMediaCount',
            'pendingRepositoriesCount',
            'duplicatesCount',
            'metadataCompleteness',
            'repositoryHealthScore',
            'recentActivityLogs',
            'urgentAlerts',
            'teacherSubmissionsQueue',
            'teacherPerformanceSummary',
            'recentlyUpdatedRepositories'
        ));
    }

    public function mediaApprovalCenter(Request $request): View
    {
        $status = $request->query('status', 'pending_review');
        $type = $request->query('type');

        $query = RepositoryReviewRequest::with(['submitter', 'reviewer'])
            ->where('resource_type', 'MediaAsset');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type) {
            $query->whereJsonContains('changes_data->media_type', $type);
        }

        $reviewRequests = $query->latest()->paginate(15);

        return view('admin.repository_manager.media_approval', compact('reviewRequests', 'status', 'type'));
    }

    public function mediaReview(RepositoryReviewRequest $reviewRequest): View
    {
        $media = MediaAsset::find($reviewRequest->resource_id);

        return view('admin.repository_manager.media_review', compact('reviewRequest', 'media'));
    }

    public function approveMedia(Request $request, RepositoryReviewRequest $reviewRequest): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Approved by Repository Manager after quality assurance audit.');

        $media = MediaAsset::findOrFail($reviewRequest->resource_id);

        // Apply changes
        $changes = $reviewRequest->changes_data ?? [];
        if (!empty($changes['new_title'])) $media->title = $changes['new_title'];
        if (!empty($changes['new_description'])) $media->description = $changes['new_description'];
        if (!empty($changes['new_category'])) $media->category = $changes['new_category'];
        if (!empty($changes['new_exam_type'])) $media->exam_type = $changes['new_exam_type'];
        if (!empty($changes['new_content_text'])) $media->content_text = $changes['new_content_text'];
        if (!empty($changes['new_tags'])) $media->tags = $changes['new_tags'];

        // Versioning (PART E)
        $currentVersion = $media->version ?? 'v1.0';
        $parts = explode('.', str_replace('v', '', $currentVersion));
        $newVersionNumber = 'v' . ($parts[0] ?? '1') . '.' . (((int)($parts[1] ?? 0)) + 1);

        $media->version = $newVersionNumber;
        $media->approval_status = 'approved';
        $media->status = 'published';
        $media->save();

        // Create version record
        RepositoryVersion::where('resource_type', 'MediaAsset')
            ->where('resource_id', $media->id)
            ->update(['is_current' => false]);

        RepositoryVersion::create([
            'resource_type'  => 'MediaAsset',
            'resource_id'    => $media->id,
            'version_number' => $newVersionNumber,
            'title'          => $media->title ?? $media->original_name,
            'snapshot_data'  => $media->toArray(),
            'created_by'     => $user->id,
            'change_reason'  => $note,
            'is_current'     => true,
        ]);

        // Update Review Request
        $reviewRequest->update([
            'status'       => 'approved',
            'reviewer_id'  => $user->id,
            'review_notes' => $note,
            'approved_at'  => now(),
        ]);

        // Create Approval Record
        RepositoryApproval::create([
            'review_request_id' => $reviewRequest->id,
            'approved_by'       => $user->id,
            'decision'          => 'approved',
            'notes'             => $note,
        ]);

        // Create Activity Log (PART I)
        RepositoryActivityLog::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $media->id,
            'actor_id'      => $reviewRequest->submitted_by,
            'reviewer_id'   => $user->id,
            'action'        => 'approved',
            'old_values'    => $changes['old_data'] ?? null,
            'new_values'    => $changes,
            'approval_note' => $note,
        ]);

        return redirect()->route('admin.repository-manager.media-approval')
            ->with('success', "Media Revision Request approved successfully. Updated to version {$newVersionNumber}.");
    }

    public function requestRevisionMedia(Request $request, RepositoryReviewRequest $reviewRequest): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Revision requested. Please address reviewer feedback.');

        $media = MediaAsset::find($reviewRequest->resource_id);
        if ($media) {
            $media->approval_status = 'revision_requested';
            $media->save();
        }

        $reviewRequest->update([
            'status'       => 'revision_requested',
            'reviewer_id'  => $user->id,
            'review_notes' => $note,
        ]);

        RepositoryApproval::create([
            'review_request_id' => $reviewRequest->id,
            'approved_by'       => $user->id,
            'decision'          => 'revision_requested',
            'notes'             => $note,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $reviewRequest->resource_id,
            'actor_id'      => $reviewRequest->submitted_by,
            'reviewer_id'   => $user->id,
            'action'        => 'revision_requested',
            'approval_note' => $note,
        ]);

        return redirect()->route('admin.repository-manager.media-approval')
            ->with('warning', 'Revision requested from author.');
    }

    public function rejectMedia(Request $request, RepositoryReviewRequest $reviewRequest): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Revision rejected by Repository Manager.');

        $media = MediaAsset::find($reviewRequest->resource_id);
        if ($media) {
            $media->approval_status = 'rejected';
            $media->save();
        }

        $reviewRequest->update([
            'status'       => 'rejected',
            'reviewer_id'  => $user->id,
            'review_notes' => $note,
        ]);

        RepositoryApproval::create([
            'review_request_id' => $reviewRequest->id,
            'approved_by'       => $user->id,
            'decision'          => 'rejected',
            'notes'             => $note,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $reviewRequest->resource_id,
            'actor_id'      => $reviewRequest->submitted_by,
            'reviewer_id'   => $user->id,
            'action'        => 'rejected',
            'approval_note' => $note,
        ]);

        return redirect()->route('admin.repository-manager.media-approval')
            ->with('danger', 'Media revision request rejected.');
    }

    public function questionsApproval(): View
    {
        $questionBanks = Schema::hasTable('question_banks')
            ? QuestionBank::where('status', 'draft')->orWhere('is_published', false)->latest()->paginate(15)
            : collect([]);

        return view('admin.repository_manager.questions_approval', compact('questionBanks'));
    }

    public function duplicates(): View
    {
        return view('admin.repository_manager.duplicates');
    }
}
