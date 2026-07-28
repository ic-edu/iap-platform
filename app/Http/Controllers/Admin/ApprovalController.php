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
            ->where('is_published', false)
            ->latest()
            ->paginate(10);

        $publishedCount = Test::where('is_published', true)->count();
        $pendingCount = $pendingTests->total();

        return view('admin.approvals.index', compact('pendingTests', 'publishedCount', 'pendingCount'));
    }

    /**
     * Approve & Publish an assessment test.
     */
    public function approve(Request $request, Test $test): RedirectResponse
    {
        $test->update([
            'is_published' => true,
        ]);

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' approved and published successfully.");
    }

    /**
     * Reject an assessment test back to draft.
     */
    public function reject(Request $request, Test $test): RedirectResponse
    {
        $reason = $request->input('reason', 'Requires revisions before publication.');

        $test->update([
            'is_published' => false,
        ]);

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' rejected. Reason: {$reason}");
    }
}
