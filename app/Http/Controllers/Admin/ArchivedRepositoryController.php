<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AclAuditTrail;
use App\Models\RepositoryActivityLog;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ArchivedRepositoryController extends Controller
{
    /**
     * Super Admin: Display all archived question banks.
     */
    public function archivedIndex(Request $request): View
    {
        $query = QuestionBank::with(['category', 'questions', 'creator', 'archiveRequests'])
            ->where('status', 'archived');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $archivedBanks = $query->latest('updated_at')->paginate(12);

        return view('admin.archived_repositories.index', compact('archivedBanks'));
    }

    /**
     * Super Admin: Move an archived question bank to the Recycle Bin (Soft Delete).
     */
    public function moveToRecycleBin(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Unauthorized. Only Super Admin can move repositories to the Recycle Bin.');
        }

        // State Guard: Source state MUST be archived
        if ($questionBank->status !== 'archived') {
            return redirect()->back()->with('danger', "Only ARCHIVED question banks can be moved to the Recycle Bin. Current status: '{$questionBank->status}'.");
        }

        // Soft Delete the Question Bank
        $questionBank->delete();

        // Audit Trails
        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'moved_to_recycle_bin',
            'actor_id'      => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Super Admin moved archived repository to Recycle Bin.',
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $user->id,
            'action'        => 'moved_to_recycle_bin',
            'approval_note' => 'Moved to Recycle Bin by Super Admin.',
        ]);

        return redirect()->route('admin.archived-repositories.index')
            ->with('status', "Repository '{$questionBank->title}' moved to Recycle Bin.");
    }

    /**
     * Super Admin: Display all repositories currently in the Recycle Bin (onlyTrashed).
     */
    public function recycleBinIndex(Request $request): View
    {
        $query = QuestionBank::onlyTrashed()
            ->with(['category', 'questions', 'creator']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $trashedBanks = $query->latest('deleted_at')->paginate(12);

        return view('admin.recycle_bin.index', compact('trashedBanks'));
    }

    /**
     * Super Admin: Read-only inspection of a soft-deleted repository in Recycle Bin.
     */
    public function recycleBinShow(Request $request, string $id): View
    {
        $questionBank = QuestionBank::onlyTrashed()
            ->with(['category', 'questions.choices', 'creator', 'activityLogs.actor', 'auditTrails.actor'])
            ->findOrFail($id);

        return view('admin.recycle_bin.show', compact('questionBank'));
    }

    /**
     * Super Admin: Restore a repository from the Recycle Bin back to ARCHIVED status.
     */
    public function restoreFromRecycleBin(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Unauthorized. Only Super Admin can restore repositories from the Recycle Bin.');
        }

        $questionBank = QuestionBank::onlyTrashed()->findOrFail($id);

        // Restore from Soft Delete
        $questionBank->restore();

        // Ensure status is explicitly 'archived' and NOT published/approved
        $questionBank->status = 'archived';
        $questionBank->is_published = false;
        $questionBank->save();

        // Audit Trails
        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'restored_from_recycle_bin',
            'actor_id'      => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Super Admin restored repository from Recycle Bin back to Archived status.',
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $user->id,
            'action'        => 'restored_from_recycle_bin',
            'approval_note' => 'Restored from Recycle Bin to Archived status by Super Admin.',
        ]);

        return redirect()->route('admin.recycle-bin.index')
            ->with('status', "Repository '{$questionBank->title}' restored from Recycle Bin back to Archived status.");
    }
}
