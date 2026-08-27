@extends('layouts.admin')

@section('title', 'Repository Revision Workspace — iC.edu Platform')

@push('styles')
<style>
.trr-workspace { display: flex; flex-direction: column; gap: 1.75rem; width: 100%; }
.trr-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .trr-panel, html[data-theme="dark"] .trr-panel {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: none;
}
.trr-item-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .trr-item-card, html[data-theme="dark"] .trr-item-card {
    background: #080f1d;
    border-color: #1e293b;
}
.trr-item-card--closed {
    border-color: rgba(5,150,105,.3);
    background: rgba(5,150,105,.05);
}
html.dark .trr-item-card--closed, html[data-theme="dark"] .trr-item-card--closed {
    border-color: rgba(52,211,153,.3);
    background: rgba(52,211,153,.05);
}
</style>
@endpush

@section('content')
<div class="trr-workspace">

    {{-- Breadcrumbs / Back Navigation --}}
    <div class="flex items-center gap-4 mb-2">
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-bold transition-colors">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-bold transition-colors">
            ← Back
        </a>
        @else
        <a href="{{ route('teacher.repository-revisions.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-bold transition-colors">
            ← Back to Revision Tasks
        </a>
        @endif
        <a href="{{ route('teacher.dashboard') }}" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-semibold transition-colors">
            Dashboard
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
            $topBadgeClass = 'bg-amber-50 dark:bg-amber-500/15 border border-amber-300 dark:border-amber-500/40 text-amber-700 dark:text-amber-400';
        } elseif ($totCount > 0 && $openCount === 0) {
            $topStatusLabel = 'READY FOR RESUBMISSION';
            $topBadgeClass = 'bg-emerald-50 dark:bg-emerald-500/15 border border-emerald-300 dark:border-emerald-500/40 text-emerald-700 dark:text-emerald-300';
        } else {
            $topStatusLabel = 'OPEN';
            $topBadgeClass = 'bg-amber-50 dark:bg-amber-500/15 border border-amber-300 dark:border-amber-500/40 text-amber-700 dark:text-amber-400';
        }
    @endphp

    {{-- Repository Header Card --}}
    <div class="trr-panel">
        <div class="flex justify-between items-start gap-4 flex-wrap">
            <div>
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Repository Revision Task</span>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1 mb-1">
                    {{ $revisionRequest->questionBank?->title }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 m-0">
                    Exam Type: <strong class="text-emerald-600 dark:text-emerald-400 uppercase font-bold">{{ $revisionRequest->questionBank?->test_type }}</strong> • Requested By Reviewer: <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $revisionRequest->requestedBy?->name ?? 'Repository Manager' }}</strong>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-lg text-xs font-extrabold uppercase {{ $topBadgeClass }}">
                    Status: {{ $topStatusLabel }}
                </span>
            </div>
        </div>

        @if($isBankLocked)
        <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-lg flex items-center gap-2.5 text-xs text-amber-800 dark:text-amber-300">
            <span class="text-base">🔒</span>
            <span><strong>Repository locked while awaiting governance review.</strong> Editing actions are disabled.</span>
        </div>
        @endif

        <div class="mt-4 p-4 bg-slate-50 dark:bg-slate-800/80 border-l-4 border-indigo-600 dark:border-indigo-500 rounded-lg">
            <div class="text-xs font-bold text-indigo-700 dark:text-indigo-400 uppercase mb-1">Reviewer Feedback Notes:</div>
            <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed italic">
                "{{ $revisionRequest->notes }}"
            </div>
        </div>
    </div>

    {{-- Actionable Issues & Findings List (PART 3) --}}
    <div class="trr-panel">
        <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
            @if($isBankLocked)
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white m-0">
                📋 Repository Findings ({{ $totCount }})
            </h3>
            <span class="text-xs text-amber-700 dark:text-amber-400 font-medium">Repository has been resubmitted and is awaiting governance review.</span>
            @elseif($openCount === 0)
            <h3 class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 m-0">
                ✔ All Findings Resolved ({{ $totCount }})
            </h3>
            <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">All issues fixed. You can now resubmit the repository below.</span>
            @else
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white m-0">
                ⚠️ Actionable Quality Findings ({{ $openCount }} remaining)
            </h3>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Fix remaining issues below and resubmit for automatic IRQA verification.</span>
            @endif
        </div>

        @foreach($revisionRequest->items as $index => $item)
        @php
            $isQuestionFinding = !empty($item->question_id);
            $fbLower = strtolower($item->feedback ?? '');
        @endphp
        <div class="trr-item-card {{ $item->status === 'CLOSED' ? 'trr-item-card--closed' : '' }}">
            <div class="flex justify-between items-start gap-4 mb-2">
                <div class="flex items-center gap-2.5">
                    <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">#{{ $index + 1 }}</span>
                    <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $item->feedback }}</span>
                </div>
                @if($item->status === 'CLOSED')
                <span class="px-2.5 py-0.5 rounded text-[11px] font-extrabold uppercase bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                    CLOSED
                </span>
                @elseif($isBankLocked)
                <span class="px-2.5 py-0.5 rounded text-[11px] font-extrabold uppercase bg-amber-500/20 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                    SUBMITTED — AWAITING REVIEW
                </span>
                @else
                <span class="px-2.5 py-0.5 rounded text-[11px] font-extrabold uppercase bg-rose-500/20 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                    OPEN
                </span>
                @endif
            </div>

            @if($item->suggested_fix)
            <p class="text-xs text-slate-600 dark:text-slate-300 mb-2">
                <strong class="text-indigo-600 dark:text-indigo-400">Suggested Fix:</strong> {{ $item->suggested_fix }}
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

            <div class="flex gap-2 flex-wrap mt-3">
                <a href="{{ $actionUrl }}" class="px-3.5 py-2 {{ $isBankLocked ? 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700' : 'bg-indigo-600 hover:bg-indigo-500 text-white' }} rounded-lg font-bold text-xs inline-flex items-center gap-1.5 shadow-sm transition-colors">
                    {{ $actionLabel }}
                </a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Bottom Resubmit Section (PART 3 & PART 6) --}}
    @if($isBankLocked)
    <div class="trr-panel bg-amber-50/50 dark:bg-amber-500/5 border-amber-200 dark:border-amber-500/30">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🔒</span>
            <div>
                <h4 class="text-sm font-bold text-amber-800 dark:text-amber-400 mb-0.5">Repository Locked For Governance Approval</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 m-0">This repository is currently awaiting governance review. Editing is disabled until a Repository Manager acts on the submission.</p>
            </div>
        </div>
    </div>
    @elseif($openCount === 0)
    <div class="trr-panel border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/30">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h3 class="text-base font-black text-emerald-800 dark:text-white mb-1">✔ All Findings Resolved — Ready to Resubmit</h3>
                <p class="text-xs text-emerald-700 dark:text-emerald-300 m-0">Submitting will automatically execute an IRQA re-scan, lock the repository, and notify the Repository Manager.</p>
            </div>

            <form method="POST" action="{{ route('teacher.repository-revisions.resubmit', $revisionRequest->id) }}">
                @csrf
                <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-emerald-950/20 transition-all inline-flex items-center gap-2 cursor-pointer">
                    🚀 Resubmit Repository &amp; Trigger IRQA Re-Scan
                </button>
            </form>
        </div>
    </div>
    @else
    <div class="trr-panel border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Resolve All Findings to Resubmit</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 m-0">There are <strong>{{ $openCount }}</strong> outstanding finding(s) remaining. Fix all findings to enable resubmission.</p>
            </div>

            <button type="button" disabled class="px-6 py-3 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-extrabold text-xs rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed inline-flex items-center gap-2" title="All findings must be resolved before resubmitting">
                🔒 Resubmit Disabled ({{ $openCount }} Remaining)
            </button>
        </div>
    </div>
    @endif

</div>
@endsection

