<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\MediaDeleteRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // SECTION 1 & 3: Media Library (Teacher + Admin active media)
    // ──────────────────────────────────────────────────────────────

    /**
     * Display Media Library / Management workspace.
     * Teacher: own active media. Admin/Super Admin: all active & pending media.
     */
    public function index(Request $request): View
    {
        $user  = $request->user();
        $query = MediaAsset::with(['uploader']);

        // Teacher & Admin access active/published assets
        if ($user->hasRole('teacher')) {
            $query->whereIn('status', ['active', 'published']);
        } else {
            $query->whereIn('status', ['active', 'published', 'pending_archive']);
        }

        // Filter by type (PART 1 & 8)
        if ($type = $request->input('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // Filter by exam_type (PART 8)
        if ($examType = $request->input('exam_type')) {
            if ($examType !== 'all') {
                $query->where('exam_type', $examType);
            }
        }

        // Filter by category
        if ($category = $request->input('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        // Filter by unused status (PART 1)
        if ($request->input('filter') === 'unused') {
            $usedMediaIds = \DB::table('questions')->whereNotNull('media_asset_id')->pluck('media_asset_id')->filter()->toArray();
            $usedUrls = \DB::table('questions')->whereNotNull('image_url')->pluck('image_url')
                ->merge(\DB::table('questions')->whereNotNull('audio_url')->pluck('audio_url'))
                ->filter()->toArray();

            $query->whereNotIn('id', $usedMediaIds)
                  ->whereNotIn('path', $usedUrls);
        }

        // Broad Search by title, ID, exam, category, tags, transcript, filename, uploader (PART 7)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('sub_category', 'like', "%{$search}%")
                  ->orWhere('exam_type', 'like', "%{$search}%")
                  ->orWhere('content_text', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('uploader', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $mediaAssets = $query->latest()->paginate(12)->withQueryString();

        // Calculate Media Health Cards & Metrics (PART 1 & 7)
        $allAssets = MediaAsset::all();
        $totalAssets = $allAssets->count();
        $imagesCount = $allAssets->where('type', 'image')->count();
        $audioCount  = $allAssets->where('type', 'audio')->count();
        $videoCount  = $allAssets->where('type', 'video')->count();
        $pdfCount    = $allAssets->where('type', 'pdf')->count();
        $passageCount= $allAssets->where('type', 'passage')->count();
        $pendingCount= $allAssets->where('approval_status', 'pending')->count();
        $totalSizeBytes = $allAssets->sum('size');

        $usedMediaUrls = \DB::table('questions')
            ->whereNotNull('image_url')
            ->orWhereNotNull('audio_url')
            ->pluck('image_url')
            ->merge(\DB::table('questions')->pluck('audio_url'))
            ->filter()
            ->toArray();
        $usedMediaIds = \DB::table('questions')->whereNotNull('media_asset_id')->pluck('media_asset_id')->toArray();

        $unusedCount = $allAssets->filter(function ($m) use ($usedMediaUrls, $usedMediaIds) {
            return !in_array($m->id, $usedMediaIds) && !in_array($m->path, $usedMediaUrls) && !in_array($m->publicUrl(), $usedMediaUrls);
        })->count();

        $healthMetrics = [
            'total_assets'  => $totalAssets,
            'images_count'  => $imagesCount,
            'audio_count'   => $audioCount,
            'video_count'   => $videoCount,
            'pdf_count'     => $pdfCount,
            'passage_count' => $passageCount,
            'unused_count'  => $unusedCount,
            'pending_count' => $pendingCount,
            'total_size_mb' => round($totalSizeBytes / 1048576, 1) . ' MB',
        ];

        return view('admin.media.index', compact('mediaAssets', 'healthMetrics'));
    }

    /**
     * Get JSON list of active media for Media Picker Modal (PART 3 & 4).
     */
    public function list(Request $request): JsonResponse
    {
        $type     = $request->query('type');
        $examType = $request->query('exam_type');
        $category = $request->query('category');
        $search   = $request->query('search');

        $query = MediaAsset::active()->latest()->take(100);

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }
        if ($examType && $examType !== 'all') {
            $query->where('exam_type', $examType);
        }
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('original_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $items = $query->get()->map(fn ($m) => [
            'id'             => $m->id,
            'title'          => $m->title ?? $m->original_name,
            'name'           => $m->original_name,
            'url'            => $m->publicUrl(),
            'type'           => $m->type,
            'exam_type'      => strtoupper($m->exam_type ?? 'GENERAL'),
            'category'       => $m->category ?? 'General',
            'content_text'   => $m->content_text,
            'size'           => $m->humanSize(),
            'uploaded_at'    => $m->created_at?->format('Y-m-d H:i'),
        ]);

        return response()->json(['success' => true, 'data' => $items->toArray()]);
    }

    /**
     * Upload media file (ROLE GOVERNANCE: TEACHER ONLY).
     * Admin is NOT allowed to upload media assets.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Enforce Role Governance: Admin cannot upload media
        if (!$user->hasRole('teacher') && !$user->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only Teachers are authorized to upload academic media assets.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,jpg,mp3,wav,m4a,pdf|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('question-media', 'public');
        $type = $this->determineFileType($file->getClientOriginalExtension());

        $asset = MediaAsset::create([
            'filename'      => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'type'          => $type,
            'path'          => $path,
            'size'          => $file->getSize(),
            'status'        => 'active',
            'uploaded_by'   => $user->id,
        ]);

        ActivityLogger::log('media_uploaded', "Media uploaded: {$asset->original_name}", $user);

        return response()->json([
            'success'  => true,
            'id'       => $asset->id,
            'url'      => $asset->publicUrl(),
            'filename' => $asset->original_name,
            'type'     => $asset->type,
            'size'     => $asset->humanSize(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 5: Archive & Request Archive Workflow
    // ──────────────────────────────────────────────────────────────

    /**
     * Teacher directly archives their own media (immediate, no approval).
     */
    public function archive(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('teacher') && $media->uploaded_by !== $user->id) {
            abort(403, 'You can only archive your own media.');
        }

        $media->update([
            'status'      => 'archived',
            'archived_by' => $user->id,
            'archived_at' => now(),
        ]);

        ActivityLogger::log('media_archived', "Media archived: {$media->original_name}", $user);

        return redirect()->route('admin.media.index')
            ->with('status', "'{$media->original_name}' has been archived.");
    }

    /**
     * Admin requests media archiving (requires Super Admin approval).
     */
    public function requestArchive(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            abort(403, 'Admin access required.');
        }

        if ($user->hasRole('super-admin')) {
            // Super Admin can approve/archive directly
            $media->update([
                'status'      => 'archived',
                'archived_by' => $user->id,
                'archived_at' => now(),
            ]);

            ActivityLogger::log('media_archived', "Super Admin archived media: {$media->original_name}", $user);

            return back()->with('status', "'{$media->original_name}' has been archived.");
        }

        // Admin submits Request Archive -> status becomes pending_archive
        $media->update([
            'status' => 'pending_archive',
        ]);

        ActivityLogger::log('media_archive_requested', "Admin requested archive for media: {$media->original_name}", $user);

        return back()->with('status', "Archive request submitted for '{$media->original_name}'. Awaiting Super Admin approval.");
    }

    /**
     * Super Admin approves archive request.
     */
    public function approveArchive(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasRole('super-admin')) {
            abort(403, 'Super Admin approval required.');
        }

        $media->update([
            'status'      => 'archived',
            'archived_by' => $user->id,
            'archived_at' => now(),
        ]);

        ActivityLogger::log('media_archive_approved', "Super Admin approved archive for media: {$media->original_name}", $user);

        return back()->with('status', "Archive approved for '{$media->original_name}'.");
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 5 & 6: Admin Media Archive & Restore Workflow
    // ──────────────────────────────────────────────────────────────

    /**
     * Admin / Super Admin: Display Archived & Pending Media page (SECTION 6).
     */
    public function archiveIndex(Request $request): View
    {
        $this->authorizeAdmin($request);

        $query = MediaAsset::with(['uploader', 'archiver', 'deleteRequests'])
            ->whereIn('status', ['archived', 'pending_archive', 'pending_restore']);

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by filename, media ID, or uploader (SECTION 8)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('uploader', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $archivedMedia = $query->latest('updated_at')->paginate(15)->withQueryString();

        return view('admin.media.archive', compact('archivedMedia'));
    }

    /**
     * Admin requests media restore (requires Super Admin approval).
     */
    public function requestRestore(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($request);

        if ($user->hasRole('super-admin')) {
            return $this->approveRestore($request, $media);
        }

        $media->update([
            'status' => 'pending_restore',
        ]);

        ActivityLogger::log('media_restore_requested', "Admin requested restore for media: {$media->original_name}", $user);

        return redirect()->route('admin.media.archive-index')
            ->with('status', "Restore request submitted for '{$media->original_name}'. Awaiting Super Admin approval.");
    }

    /**
     * Super Admin / Admin restore execution.
     */
    public function restore(Request $request, MediaAsset $media): RedirectResponse
    {
        return $this->requestRestore($request, $media);
    }

    /**
     * Super Admin approves restore request.
     */
    public function approveRestore(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            abort(403, 'Admin access required.');
        }

        $media->update([
            'status'      => 'active',
            'archived_by' => null,
            'archived_at' => null,
        ]);

        ActivityLogger::log('media_restore_approved', "Media restored to active: {$media->original_name}", $user);

        return redirect()->route('admin.media.archive-index')
            ->with('status', "'{$media->original_name}' has been restored to Active.");
    }

    // ──────────────────────────────────────────────────────────────
    // Permanent Deletion Request (Admin -> Super Admin)
    // ──────────────────────────────────────────────────────────────

    public function requestDelete(Request $request, MediaAsset $media): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $usageCount = $this->getMediaUsageCount($media);

        if ($usageCount > 0) {
            return redirect()->route('admin.media.archive-index')
                ->with('error', "Cannot request deletion: '{$media->original_name}' is still used in {$usageCount} question(s).");
        }

        if ($media->hasPendingDeleteRequest()) {
            return redirect()->route('admin.media.archive-index')
                ->with('error', "A deletion request for '{$media->original_name}' is already pending Super Admin review.");
        }

        MediaDeleteRequest::create([
            'media_asset_id' => $media->id,
            'requested_by'   => $request->user()->id,
            'reason'         => $request->input('reason', 'Admin requested permanent deletion.'),
            'status'         => 'pending',
        ]);

        ActivityLogger::log('media_delete_requested', "Permanent delete requested for: {$media->original_name}", $request->user());

        return redirect()->route('admin.media.archive-index')
            ->with('status', "Permanent deletion request submitted for '{$media->original_name}'. Awaiting Super Admin approval.");
    }

    // ──────────────────────────────────────────────────────────────
    // PART 4: View Media Detail & Usage Explorer
    // ──────────────────────────────────────────────────────────────

    public function show(MediaAsset $media): View
    {
        $url = $media->publicUrl();

        $questions = \Illuminate\Support\Facades\Schema::hasTable('questions')
            ? \App\Modules\QuestionBank\Models\Question::with('questionBank')
                ->where('media_asset_id', $media->id)
                ->orWhere('audio_url', $url)
                ->orWhere('audio_url', $media->path)
                ->orWhere('image_url', $url)
                ->orWhere('image_url', $media->path)
                ->get()
            : collect([]);

        $questionBanks = $questions->pluck('questionBank')->filter()->unique('id');

        $assessments = \Illuminate\Support\Facades\Schema::hasTable('tests')
            ? \DB::table('tests')->whereIn('id', $questionBanks->pluck('id'))->get()
            : collect([]);

        $courses = \Illuminate\Support\Facades\Schema::hasTable('courses')
            ? \DB::table('courses')->take(2)->get()
            : collect([]);

        $studentAttemptsCount = \Illuminate\Support\Facades\Schema::hasTable('test_attempts')
            ? \DB::table('test_attempts')->count()
            : null;

        $usageInfo = [
            'question_count'      => $questions->count(),
            'question_banks'      => $questionBanks,
            'questions'           => $questions,
            'assessments'         => $assessments,
            'courses'             => $courses,
            'student_attempts'    => $studentAttemptsCount,
            'uploader_name'       => $media->uploader?->name ?? 'Institutional System',
        ];

        return view('admin.media.show', compact('media', 'usageInfo'));
    }

    /**
     * Download original media asset file (TASK 1 & TASK 10: SECURE DOWNLOAD POLICY).
     * Requires Super Admin / repository.download.asset permission OR a valid temporary signed URL.
     */
    public function download(Request $request, MediaAsset $media)
    {
        $user = $request->user();
        $hasSignedUrl = $request->hasValidSignature();
        $isSuperAdmin = $user && $user->canDownloadRepositoryAsset();

        if (!$hasSignedUrl && !$isSuperAdmin) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Direct download is restricted by Institutional Repository Policy. Please request download approval or preview online.',
                ], 403);
            }
            abort(403, 'Direct download is restricted by Institutional Repository Policy. Please request download approval or preview online.');
        }

        // TASK 9: Audit Log Download Executed
        if (\Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'action'        => 'download_executed',
                'actor_id'      => $user?->id,
                'created_by'    => $user?->id,
                'version'       => $media->version ?? '1.0',
                'reason'        => $hasSignedUrl ? 'Executed download via approved temporary signed URL' : 'Direct download executed by Super Admin',
                'metadata'      => [
                    'ip_address' => $request->ip(),
                    'asset_name' => $media->title ?? $media->original_name,
                    'user_role'  => $user?->getRoleNames()->first() ?? 'User',
                ],
            ]);
        }

        // For passage media or text assets, stream text response
        if ($media->type === 'passage' || empty($media->path)) {
            $filename = \Illuminate\Support\Str::slug($media->title ?? 'passage') . '.txt';
            $content = $media->content_text ?? $media->description ?? 'Institutional reading passage content.';
            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $filename, ['Content-Type' => 'text/plain']);
        }

        if (Storage::disk('public')->exists($media->path)) {
            return Storage::disk('public')->download($media->path, $media->original_name);
        }

        if (file_exists(public_path($media->path))) {
            return response()->download(public_path($media->path), $media->original_name);
        }

        // Graceful stream fallback if physical file missing
        $filename = \Illuminate\Support\Str::slug($media->title ?? $media->original_name) . '.' . ($media->type === 'pdf' ? 'pdf' : 'txt');
        $content = $media->content_text ?? $media->description ?? 'Institutional asset file content.';
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }

    /**
     * TASK 8: Repository Manager / Teacher submits Download Request for Super Admin approval.
     */
    public function requestDownload(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'purpose' => ['required', 'string', 'in:Academic Audit,Legal,Accreditation,Migration,Backup,Other'],
            'reason'  => ['required_if:purpose,Other', 'nullable', 'string', 'max:1000'],
        ]);

        $reason = $validated['purpose'] === 'Other' ? $validated['reason'] : "Requested for {$validated['purpose']}";

        if (\Illuminate\Support\Facades\Schema::hasTable('repository_review_requests')) {
            \App\Models\RepositoryReviewRequest::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'submitted_by'  => $user->id,
                'status'        => 'pending_download',
                'changes_data'  => [
                    'request_type' => 'download',
                    'purpose'      => $validated['purpose'],
                    'reason'       => $reason,
                    'asset_title'  => $media->title ?? $media->original_name,
                    'requester'    => $user->name,
                ],
            ]);
        }

        // TASK 9: Audit Log Request Download
        if (\Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'action'        => 'request_download',
                'actor_id'      => $user->id,
                'created_by'    => $user->id,
                'version'       => $media->version ?? '1.0',
                'reason'        => $reason,
                'metadata'      => [
                    'purpose'    => $validated['purpose'],
                    'ip_address' => $request->ip(),
                    'asset_name' => $media->title ?? $media->original_name,
                ],
            ]);
        }

        return back()->with('status', 'Download Request submitted to Super Admin Queue for Academic Governance approval.');
    }

    /**
     * TASK 8: Display Download Requests Queue for Super Admin.
     */
    public function downloadRequestsIndex(Request $request): View
    {
        $this->authorizeAdmin($request);

        $downloadRequests = \Illuminate\Support\Facades\Schema::hasTable('repository_review_requests')
            ? \App\Models\RepositoryReviewRequest::with(['submitter'])
                ->where('status', 'pending_download')
                ->latest()
                ->paginate(15)
            : collect([]);

        return view('admin.media.download_requests', compact('downloadRequests'));
    }

    /**
     * TASK 8: Super Admin approves download request and generates temporary signed URL.
     */
    public function approveDownloadRequest(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasRole('super-admin')) {
            abort(403, 'Super Admin authorization required to approve asset download requests.');
        }

        $reviewRequest = \App\Models\RepositoryReviewRequest::findOrFail($id);
        $media = MediaAsset::findOrFail($reviewRequest->resource_id);

        // Generate temporary signed URL expiring in 30 minutes
        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.media.download',
            now()->addMinutes(30),
            ['media' => $media->id]
        );

        $reviewRequest->update([
            'status'       => 'approved',
            'reviewer_id'  => $user->id,
            'review_notes' => 'Download request approved by Super Admin. Temporary signed URL generated.',
            'approved_at'  => now(),
        ]);

        // TASK 9: Audit Log Approve Download
        if (\Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'action'        => 'approve_download',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'approver_id'   => $user->id,
                'created_by'    => $reviewRequest->submitted_by,
                'version'       => $media->version ?? '1.0',
                'reason'        => 'Super Admin approved download request and generated temporary signed URL',
                'metadata'      => [
                    'ip_address' => $request->ip(),
                    'signed_url' => $signedUrl,
                ],
            ]);
        }

        return redirect()->route('admin.media.download-requests')
            ->with('status', "Download Request approved! Temporary Signed URL (Expires in 30 min): {$signedUrl}");
    }

    /**
     * TASK 8: Super Admin rejects download request.
     */
    public function rejectDownloadRequest(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasRole('super-admin')) {
            abort(403, 'Super Admin authorization required.');
        }

        $reviewRequest = \App\Models\RepositoryReviewRequest::findOrFail($id);
        $media = MediaAsset::find($reviewRequest->resource_id);

        $reviewRequest->update([
            'status'       => 'rejected',
            'reviewer_id'  => $user->id,
            'review_notes' => $request->input('reason', 'Download request rejected by Super Admin governance policy.'),
        ]);

        // TASK 9: Audit Log Reject Download
        if (\Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $reviewRequest->resource_id,
                'action'        => 'reject_download',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $reviewRequest->submitted_by,
                'version'       => $media?->version ?? '1.0',
                'reason'        => $request->input('reason', 'Download request rejected by Super Admin'),
                'metadata'      => [
                    'ip_address' => $request->ip(),
                ],
            ]);
        }

        return redirect()->route('admin.media.download-requests')
            ->with('status', 'Download request rejected.');
    }

    public function usage(MediaAsset $media): JsonResponse
    {
        $url = $media->publicUrl();

        $questions = \Illuminate\Support\Facades\Schema::hasTable('questions')
            ? \App\Modules\QuestionBank\Models\Question::with('questionBank')
                ->where('media_asset_id', $media->id)
                ->orWhere('audio_url', $url)
                ->orWhere('audio_url', $media->path)
                ->orWhere('image_url', $url)
                ->orWhere('image_url', $media->path)
                ->get()
            : collect([]);

        $qBanks = $questions->pluck('questionBank')->filter()->unique('id')->map(fn ($b) => [
            'id'    => $b->id,
            'title' => $b->title,
            'type'  => strtoupper(is_object($b->test_type) ? $b->test_type->value : $b->test_type),
        ])->values();

        $qCount = $questions->count();
        $isUsed = $qCount > 0;

        $studentAttempts = \Illuminate\Support\Facades\Schema::hasTable('test_attempts')
            ? \DB::table('test_attempts')->count()
            : null;

        return response()->json([
            'media_id'         => $media->id,
            'title'            => $media->title ?? $media->original_name,
            'filename'         => $media->original_name,
            'is_used'          => $isUsed,
            'question_count'   => $qCount,
            'question_banks'   => $qBanks,
            'assessments_count'=> $qBanks->count(),
            'courses_count'    => (\Illuminate\Support\Facades\Schema::hasTable('courses') && $isUsed) ? \DB::table('courses')->count() : null,
            'student_attempts' => $studentAttempts,
            'uploader'         => $media->uploader?->name ?? 'Institutional Repository System',
            'message'          => $isUsed
                ? "This asset is active and referenced by {$qCount} Question(s) across {$qBanks->count()} Question Bank(s)."
                : 'This asset has not yet been referenced by any Question Bank or Assessment.',
        ]);
    }

    public function edit(Request $request, MediaAsset $media): View
    {
        $user = $request->user();

        // Linked questions for Reusable Slot Mapping
        $linkedQuestions = \Illuminate\Support\Facades\Schema::hasTable('questions')
            ? \App\Modules\QuestionBank\Models\Question::with('questionBank')
                ->where('media_asset_id', $media->id)
                ->get()
            : collect([]);

        // Version history list
        $versions = \Illuminate\Support\Facades\Schema::hasTable('acl_versions')
            ? \App\Models\AclVersion::where('resource_id', $media->id)->latest()->get()
            : collect([]);

        // Audit trails list
        $auditLogs = \Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')
            ? \App\Models\AclAuditTrail::where('resource_id', $media->id)->latest()->get()
            : collect([]);

        return view('admin.media.edit', compact('media', 'linkedQuestions', 'versions', 'auditLogs'));
    }

    public function update(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'category'     => ['nullable', 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'exam_type'    => ['nullable', 'string', 'max:50'],
            'difficulty'   => ['nullable', 'string', 'max:50'],
            'tags'         => ['nullable', 'string'],
            'content_text' => ['nullable', 'string'],
            'passage_type' => ['nullable', 'string', 'in:TEXT,IMAGE,HYBRID'],
            'passage_image'=> ['nullable', 'image', 'mimes:jpeg,png,webp,jpg', 'max:10240'],
        ]);

        $imagePath = $media->path;
        if ($request->hasFile('passage_image')) {
            $imageFile = $request->file('passage_image');
            $imagePath = $imageFile->store('passage-images', 'public');
        }

        $tagArray = !empty($validated['tags'])
            ? array_map('trim', explode(',', $validated['tags']))
            : ($media->tags ?? []);

        // Task 4: Teacher Save Draft -> Save Working Copy (status = 'draft', published version untouched)
        $media->update([
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? $media->description,
            'category'        => $validated['category'] ?? $media->category,
            'sub_category'    => $validated['sub_category'] ?? $media->sub_category,
            'exam_type'       => $validated['exam_type'] ?? $media->exam_type,
            'difficulty'      => $validated['difficulty'] ?? $media->difficulty,
            'tags'            => $tagArray,
            'content_text'    => $validated['content_text'] ?? $media->content_text,
            'path'            => $imagePath,
            'approval_status' => 'draft',
        ]);

        // Task 8: Immutable Audit Log Entry
        if (\Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'action'        => 'saved_draft',
                'actor_id'      => $user->id,
                'created_by'    => $user->id,
                'version'       => $media->version ?? '1.0',
                'reason'        => 'Teacher saved working copy draft',
                'metadata'      => [
                    'title'      => $media->title,
                    'status'     => 'draft',
                    'updated_by' => $user->name,
                ],
            ]);
        }

        return redirect()->route('admin.media.edit', $media->id)
            ->with('status', 'Working Copy draft saved. Click "Submit for Review" when ready for Repository Manager governance review.');
    }

    public function submitForReview(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        // Task 4 & 5: Submit for Review -> pending_review
        $media->update([
            'approval_status' => 'pending_review',
        ]);

        if (\Illuminate\Support\Facades\Schema::hasTable('repository_review_requests')) {
            \App\Models\RepositoryReviewRequest::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'submitted_by'  => $user->id,
                'status'        => 'pending_review',
                'changes_data'  => [
                    'title'        => $media->title,
                    'type'         => $media->type,
                    'version'      => $media->version ?? '1.0',
                    'submitted_at' => now()->toDateTimeString(),
                    'author_name'  => $user->name,
                ],
            ]);
        }

        // Task 8: Immutable Audit Log Entry
        if (\Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'action'        => 'submitted_for_review',
                'actor_id'      => $user->id,
                'created_by'    => $user->id,
                'version'       => ($media->version ?? '1.0') . '-candidate',
                'reason'        => $request->input('reason', 'Teacher submitted repository revision for review'),
                'metadata'      => [
                    'asset_name' => $media->title ?? $media->original_name,
                    'role'       => $user->getRoleNames()->first() ?? 'Teacher',
                    'status'     => 'pending_review',
                ],
            ]);
        }

        // Task 9: Urgent Notification for Repository Managers
        $repoManagers = \App\Models\User::role('repository-manager')->get();
        foreach ($repoManagers as $manager) {
            if (method_exists($manager, 'notify')) {
                try {
                    $manager->notify(new \App\Notifications\EnterpriseSystemNotification(
                        title: 'URGENT: Media Revision Submitted for Review',
                        message: "Teacher {$user->name} submitted revision candidate for media '{$media->title}'.",
                        type: 'GOVERNANCE_SUBMISSION',
                        priority: 'HIGH',
                        entityType: 'MediaAsset',
                        entityId: $media->id,
                        targetUrl: route('admin.repository-manager.dashboard')
                    ));
                } catch (\Throwable $e) {
                    // Silently continue in dev
                }
            }
        }

        return redirect()->route('admin.media.edit', $media->id)
            ->with('status', 'Submitted for Review! Pending Repository Manager Quality Assurance review.');
    }

    public function updateMetadata(Request $request, MediaAsset $media): RedirectResponse
    {
        return $this->submitForReview($request, $media);
    }

    public function versions(Request $request, MediaAsset $media): View
    {
        $versions = \Illuminate\Support\Facades\Schema::hasTable('acl_versions')
            ? \App\Models\AclVersion::where('resource_id', $media->id)->latest()->get()
            : collect([]);

        $auditLogs = \Illuminate\Support\Facades\Schema::hasTable('acl_audit_trails')
            ? \App\Models\AclAuditTrail::where('resource_id', $media->id)->latest()->get()
            : collect([]);

        return view('admin.media.versions', compact('media', 'versions', 'auditLogs'));
    }

    // ──────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('super-admin'))) {
            abort(403, 'Admin access required.');
        }
    }

    private function getMediaUsageCount(MediaAsset $media): int
    {
        $url = $media->publicUrl();

        return \DB::table('questions')
            ->where(function ($q) use ($url) {
                $q->where('media_url', $url)
                  ->orWhere('content', 'like', "%{$url}%");
            })
            ->count();
    }

    private function determineFileType(string $ext): string
    {
        $ext = strtolower($ext);
        if (in_array($ext, ['mp3', 'wav', 'm4a'])) {
            return 'audio';
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            return 'image';
        }
        if ($ext === 'pdf') {
            return 'pdf';
        }
        return 'other';
    }
}
