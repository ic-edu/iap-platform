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

        // Teacher sees active active media assets in institutional repository
        if ($user->hasRole('teacher')) {
            $query->whereIn('status', ['active', 'published']);
        } else {
            $query->whereIn('status', ['active', 'published', 'pending_archive']);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by exam_type
        if ($examType = $request->input('exam_type')) {
            $query->where('exam_type', $examType);
        }

        // Filter by category
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by filename, media ID, title, or uploader name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhereHas('uploader', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $mediaAssets = $query->latest()->paginate(12)->withQueryString();

        // Calculate Media Health Cards & Metrics (PART 7 & 10)
        $allAssets = MediaAsset::all();
        $totalAssets = $allAssets->count();
        $imagesCount = $allAssets->where('type', 'image')->count();
        $audioCount  = $allAssets->where('type', 'audio')->count();
        $videoCount  = $allAssets->where('type', 'video')->count();
        $pdfCount    = $allAssets->where('type', 'pdf')->count();
        $passageCount= $allAssets->where('type', 'passage')->count();
        $pendingCount= $allAssets->where('approval_status', 'pending')->count();
        $totalSizeBytes = $allAssets->sum('size');

        // Usage check
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
            'total_assets'      => $totalAssets,
            'images_count'      => $imagesCount,
            'audio_count'       => $audioCount,
            'video_count'       => $videoCount,
            'pdf_count'         => $pdfCount,
            'passage_count'     => $passageCount,
            'unused_count'      => $unusedCount,
            'pending_count'     => $pendingCount,
            'total_size_mb'     => round($totalSizeBytes / 1048576, 1) . ' MB',
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
    // View Usage & Metadata
    // ──────────────────────────────────────────────────────────────

    public function usage(MediaAsset $media): JsonResponse
    {
        $url = $media->publicUrl();

        $questionUsage = \DB::table('questions')
            ->where(function ($q) use ($url) {
                $q->where('media_url', $url)
                  ->orWhere('content', 'like', "%{$url}%");
            })
            ->count();

        return response()->json([
            'media_id'       => $media->id,
            'filename'       => $media->original_name,
            'question_count' => $questionUsage,
            'is_in_use'      => $questionUsage > 0,
            'message'        => $questionUsage > 0
                ? "Used in {$questionUsage} question(s)."
                : 'Not currently used in any question or assessment.',
        ]);
    }

    public function updateMetadata(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        // Teachers can only edit their own media; Admin cannot edit academic media content
        if ($user->hasRole('teacher') && $media->uploaded_by !== $user->id) {
            abort(403);
        }

        if (!$user->hasRole('teacher') && !$user->hasRole('super-admin')) {
            abort(403, 'Only Teachers can edit academic media metadata.');
        }

        $validated = $request->validate([
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $media->update($validated);

        ActivityLogger::log('media_metadata_updated', "Media metadata updated: {$media->original_name}", $user);

        return back()->with('status', 'Media metadata updated.');
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
