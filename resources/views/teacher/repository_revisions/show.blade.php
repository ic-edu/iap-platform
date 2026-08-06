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
        <a href="{{ route('teacher.repository-revisions.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Revision Tasks
        </a>
        <a href="{{ route('teacher.dashboard') }}" style="color:#cbd5e1;font-size:.82rem;font-weight:700;text-decoration:none;">
            🏠 Dashboard
        </a>
    </div>

    {{-- Repository Header Card --}}
    <div class="trr-panel">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
            <div>
                <span style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;">Repository Revision Task</span>
                <h1 style="font-size:1.6rem;font-weight:900;color:#fff;margin:.25rem 0 .25rem;">
                    {{ $revisionRequest->questionBank?->title }}
                </h1>
                <p style="font-size:.85rem;color:#94a3b8;margin:0;">
                    Exam Type: <strong style="color:#34d399;text-transform:uppercase;">{{ $revisionRequest->questionBank?->test_type }}</strong> • Requested By Reviewer: <strong style="color:#e2e8f0;">{{ $revisionRequest->requestedBy?->name ?? 'Repository Manager' }}</strong>
                </p>
            </div>

            <div style="display:flex;gap:.65rem;align-items:center;">
                <span style="padding:.4rem .9rem;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#fbbf24;border-radius:.6rem;font-size:.78rem;font-weight:800;text-transform:uppercase;">
                    Status: {{ $revisionRequest->status }}
                </span>
            </div>
        </div>

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
            <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;">
                ⚠️ Actionable Quality Findings ({{ $revisionRequest->items->count() }})
            </h3>
            <span style="font-size:.78rem;color:#94a3b8;">Fix issues below and resubmit for automatic IRQA verification.</span>
        </div>

        @foreach($revisionRequest->items as $index => $item)
        <div class="trr-item-card {{ $item->status === 'CLOSED' ? 'trr-item-card--closed' : '' }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:.5rem;">
                <div style="display:flex;align-items:center;gap:.6rem;">
                    <span style="font-size:.85rem;font-weight:900;color:#818cf8;">#{{ $index + 1 }}</span>
                    <span style="font-size:.88rem;font-weight:800;color:#fff;">{{ $item->feedback }}</span>
                </div>
                <span style="padding:.2rem .55rem;border-radius:.4rem;font-size:.68rem;font-weight:800;text-transform:uppercase;{{ $item->status === 'CLOSED' ? 'background:rgba(52,211,153,.2);color:#34d399;' : 'background:rgba(244,63,94,.2);color:#fb7185;' }}">
                    {{ $item->status }}
                </span>
            </div>

            @if($item->suggested_fix)
            <div style="font-size:.8rem;color:#cbd5e1;margin-bottom:.75rem;">
                💡 <strong>Suggested Fix:</strong> {{ $item->suggested_fix }}
            </div>
            @endif

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;">
                <a href="{{ route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]) }}" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold text-xs inline-flex items-center gap-1.5 shadow-sm">
                    🛠 Open Focused Question Editor →
                </a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Bottom Resubmit Section (PART 3 & PART 6) --}}
    <div class="trr-panel" style="border-color:#3730a3;background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
                <h3 style="font-size:1.15rem;font-weight:900;color:#fff;margin:0 0 .25rem;">Ready to Resubmit Repository?</h3>
                <p style="font-size:.82rem;color:#94a3b8;margin:0;">Submitting will automatically execute an IRQA re-scan, close resolved findings, and notify the Repository Manager.</p>
            </div>

            <form method="POST" action="{{ route('teacher.repository-revisions.resubmit', $revisionRequest->id) }}">
                @csrf
                <button type="submit" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all inline-flex items-center gap-2">
                    🚀 Resubmit Repository & Trigger IRQA Re-Scan
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
