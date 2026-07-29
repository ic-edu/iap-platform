<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    /**
     * Display Super Admin Approval Center for tests & content.
     */
    public function index(): View
    {
        $pendingTests = Test::with(['creator', 'sections'])
            ->whereIn('status', ['pending_approval', 'draft'])
            ->orWhereNull('status')
            ->latest()
            ->paginate(10);

        $publishedCount = Test::where('is_published', true)->count();
        $pendingCount = Test::where('status', 'pending_approval')->count();

        return view('admin.approvals.index', compact('pendingTests', 'publishedCount', 'pendingCount'));
    }

    /**
     * Approve an assessment test (Super Admin Only).
     * Changes status from pending_approval to approved.
     */
    public function approve(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $test->update([
            'status' => 'approved',
            'is_published' => false,
        ]);

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' approved successfully. It is now ready for Admin publication.");
    }

    /**
     * Reject an assessment test back to draft (Super Admin Only).
     */
    public function reject(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Requires revisions before publication.');

        $test->update([
            'status' => 'rejected',
            'is_published' => false,
        ]);

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' rejected. Reason: {$reason}");
    }
}
