@extends('layouts.admin')

@section('title', 'Repository Revision Workspace — iC.edu Platform')

@push('styles')
<style>
.trr-workspace { display: flex; flex-direction: column; gap: 1.75rem; width: 100%; }
.trr-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem;
}
.trr-item-card {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.trr-item-card--closed {
    border-color: rgba(52,211,153,.3);
    background: rgba(52,211,153,.05);
}
</style>
@endpush

@section('content')
<div class="trr-workspace">

    {{-- Breadcrumbs / Back Navigation --}}
    <div style="display:flex;gap:1.25rem;align-items:center;margin-bottom:1rem;">
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        @else
        <a href="{{ route('teacher.repository-revisions.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Revision Tasks
        </a>
        @endif
        <a href="{{ route('teacher.dashboard') }}" style="color:#cbd5e1;font-size:.82rem;font-weight:700;text-decoration:none;">
            🏠 Dashboard
        </a>
    </div>

    @php
        $bank = $revisionRequest->questionBank;
        $isBankLocked = in_array($bank?->status, ['pending_approval', 'submitted', 'approved', 'published', 'pending_archive_approval', 'pending_restore_approval'], true)
            || in_array($revisionRequest->status, ['RESUBMITTED', 'CLOSED'], true);
        $totCount = $revisionRequest->items->count();
        $clsCount = $revisionRequest->items->where('status', 'CLOSED')->count();
        $openCount = max(0, $totCount - $clsCount);

        if ($revisionRequest->status === 'RESUBMITTED' || $isBankLocked) {
            $topStatusLabel = 'RESUBMITTED — AWAITING REVIEW';
            $topBadgeStyle = 'background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#fbbf24;';
        } elseif ($totCount > 0 && $openCount === 0) {
            $topStatusLabel = 'READY FOR RESUBMISSION';
            $topBadgeStyle = 'background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.4);color:#34d399;';
        } else {
            $topStatusLabel = 'OPEN';
            $topBadgeStyle = 'background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#fbbf24;';
        }
    @endphp

    {{-- Repository Header Card --}}
    <div class="trr-panel">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
            <div>
                <span style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;">Repository Revision Task</span>
                <h1 style="font-size:1.5rem;font-weight:900;color:#fff;margin:.2rem 0 .25rem;">
                    {{ $revisionRequest->questionBank?->title }}
                </h1>
                <p style="font-size:.82rem;color:#94a3b8;margin:0;">
                    Exam Type: <strong style="color:#34d399;text-transform:uppercase;">{{ $revisionRequest->questionBank?->test_type }}</strong> • Requested By Reviewer: <strong style="color:#e2e8f0;">{{ $revisionRequest->requestedBy?->name ?? 'Repository Manager' }}</strong>
                </p>
            </div>

            <div style="display:flex;gap:.65rem;align-items:center;">
                <span style="padding:.4rem .9rem;border-radius:.6rem;font-size:.78rem;font-weight:800;text-transform:uppercase;{{ $topBadgeStyle }}">
                    Status: {{ $topStatusLabel }}
                </span>
            </div>
        </div>

        @if($isBankLocked)
        <div style="margin-top:1rem;padding:.75rem 1rem;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:.6rem;display:flex;align-items:center;gap:.6rem;font-size:.82rem;color:#fbbf24;">
            <span>🔒</span>
            <span><strong>Repository locked while awaiting governance review.</strong> Editing actions are disabled.</span>
        </div>
        @endif

        <div style="margin-top:1.25rem;padding:1rem 1.25rem;background:#1e293b;border-left:4px solid #6366f1;border-radius:.65rem;">
            <div style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;margin-bottom:.25rem;">Reviewer Feedback Notes:</div>
            <div style="font-size:.88rem;color:#f1f5f9;line-height:1.5;">
                "{{ $revisionRequest->notes }}"
            </div>
        </div>
    </div>

    {{-- Actionable Issues & Findings List (PART 3) --}}
    <div class="trr-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            @if($isBankLocked)
            <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;">
                📋 Repository Findings ({{ $totCount }})
            </h3>
            <span style="font-size:.78rem;color:#fbbf24;">Repository has been resubmitted and is awaiting governance review.</span>
            @elseif($openCount === 0)
            <h3 style="font-size:1.1rem;font-weight:800;color:#34d399;margin:0;">
                ✔ All Findings Resolved ({{ $totCount }})
            </h3>
            <span style="font-size:.78rem;color:#34d399;">All issues fixed. You can now resubmit the repository below.</span>
            @else
            <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;">
                ⚠️ Actionable Quality Findings ({{ $openCount }} remaining)
            </h3>
            <span style="font-size:.78rem;color:#94a3b8;">Fix remaining issues below and resubmit for automatic IRQA verification.</span>
            @endif
        </div>

        @foreach($revisionRequest->items as $index => $item)
        @php
            $isQuestionFinding = !empty($item->question_id);
            $fbLower = strtolower($item->feedback ?? '');
        @endphp
        <div class="trr-item-card {{ $item->status === 'CLOSED' ? 'trr-item-card--closed' : '' }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:.5rem;">
                <div style="display:flex;align-items:center;gap:.6rem;">
                    <span style="font-size:.85rem;font-weight:900;color:#818cf8;">#{{ $index + 1 }}</span>
                    <span style="font-size:.88rem;font-weight:800;color:#fff;">{{ $item->feedback }}</span>
                </div>
                @if($item->status === 'CLOSED')
                <span style="padding:.2rem .55rem;border-radius:.4rem;font-size:.68rem;font-weight:800;text-transform:uppercase;background:rgba(52,211,153,.2);color:#34d399;">
                    CLOSED
                </span>
                @elseif($isBankLocked)
                <span style="padding:.2rem .55rem;border-radius:.4rem;font-size:.68rem;font-weight:800;text-transform:uppercase;background:rgba(251,191,36,.2);color:#fbbf24;border:1px solid rgba(251,191,36,.4);">
                    SUBMITTED — AWAITING REVIEW
                </span>
                @else
                <span style="padding:.2rem .55rem;border-radius:.4rem;font-size:.68rem;font-weight:800;text-transform:uppercase;background:rgba(244,63,94,.2);color:#fb7185;">
                    OPEN
                </span>
                @endif
            </div>

            @if($item->suggested_fix)
            <p style="font-size:.82rem;color:#cbd5e1;margin:0 0 .5rem;">
                <strong style="color:#818cf8;">Suggested Fix:</strong> {{ $item->suggested_fix }}
            </p>
            @endif

            @php
                if ($isBankLocked) {
                    $actionUrl = route('admin.question-banks.show', [
                        $revisionRequest->question_bank_id,
                        'from'                => 'revision_task',
                        'revision_request_id' => $revisionRequest->id,
                    ]);
                    $actionLabel = '👁 View Repository →';
                } elseif ($isQuestionFinding) {
                    $actionUrl = route('teacher.repository-revisions.edit-question', array_filter([$revisionRequest->id, $item->id, 'from' => request('from')]));
                    $actionLabel = '🛠 Open Focused Question Editor →';
                } else {
                    $actionUrl = route('admin.question-banks.show', [
                        $revisionRequest->question_bank_id,
                        'from'                => 'revision_task',
                        'revision_request_id' => $revisionRequest->id,
                    ]);
                    $actionLabel = '🛠 Edit Repository Details →';
                }
            @endphp

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;">
                <a href="{{ $actionUrl }}" class="px-3.5 py-2 {{ $isBankLocked ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700' : 'bg-indigo-600 hover:bg-indigo-500 text-white' }} rounded-lg font-bold text-xs inline-flex items-center gap-1.5 shadow-sm">
                    {{ $actionLabel }}
                </a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Bottom Resubmit Section (PART 3 & PART 6) --}}
    @if($isBankLocked)
    <div class="trr-panel" style="border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.05);">
        <div style="display:flex;align-items:center;gap:.85rem;">
            <span style="font-size:1.4rem;">🔒</span>
            <div>
                <h4 style="font-size:.95rem;font-weight:800;color:#fbbf24;margin:0 0 .2rem;">Repository Locked For Governance Approval</h4>
                <p style="font-size:.8rem;color:#94a3b8;margin:0;">This repository is currently awaiting governance review. Editing is disabled until a Repository Manager acts on the submission.</p>
            </div>
        </div>
    </div>
    @elseif($openCount === 0)
    <div class="trr-panel" style="border-color:#059669;background:linear-gradient(135deg, #0f172a 0%, #064e3b 100%);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
                <h3 style="font-size:1.15rem;font-weight:900;color:#fff;margin:0 0 .25rem;">✔ All Findings Resolved — Ready to Resubmit</h3>
                <p style="font-size:.82rem;color:#a7f3d0;margin:0;">Submitting will automatically execute an IRQA re-scan, lock the repository, and notify the Repository Manager.</p>
            </div>

            <form method="POST" action="{{ route('teacher.repository-revisions.resubmit', $revisionRequest->id) }}">
                @csrf
                <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-emerald-950/50 transition-all inline-flex items-center gap-2 cursor-pointer">
                    🚀 Resubmit Repository &amp; Trigger IRQA Re-Scan
                </button>
            </form>
        </div>
    </div>
    @else
    <div class="trr-panel" style="border-color:#334155;background:#0f172a;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
                <h3 style="font-size:1.05rem;font-weight:800;color:#cbd5e1;margin:0 0 .25rem;">Resolve All Findings to Resubmit</h3>
                <p style="font-size:.82rem;color:#64748b;margin:0;">There are <strong>{{ $openCount }}</strong> outstanding finding(s) remaining. Fix all findings to enable resubmission.</p>
            </div>

            <button type="button" disabled class="px-6 py-3 bg-slate-800 text-slate-500 font-extrabold text-xs rounded-xl border border-slate-700 cursor-not-allowed inline-flex items-center gap-2" title="All findings must be resolved before resubmitting">
                🔒 Resubmit Disabled ({{ $openCount }} Remaining)
            </button>
        </div>
    </div>
    @endif

</div>
@endsection
