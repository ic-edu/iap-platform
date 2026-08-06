@extends('layouts.admin')

@section('title', 'Teacher Repository Revision Center — iC.edu Platform')

@push('styles')
<style>
.trr-container { display: flex; flex-direction: column; gap: 1.75rem; width: 100%; }
.trr-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #020617 100%);
    border: 1px solid #3730a3;
    border-radius: 1.25rem;
    padding: 2rem 2.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    box-shadow: 0 20px 40px -15px rgba(67,56,202,0.25);
}
.trr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}
.trr-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.25rem;
    transition: all .2s ease;
}
.trr-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px -8px rgba(99,102,241,0.25);
}
.trr-badge {
    padding: .25rem .65rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.trr-badge--open { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); }
.trr-badge--resubmitted { background: rgba(56,189,248,.15); color: #38bdf8; border: 1px solid rgba(56,189,248,.3); }
.trr-badge--verified { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
</style>
@endpush

@section('content')
<div class="trr-container">

    {{-- Navigation Breadcrumbs --}}
    <div style="display:flex;gap:1.25rem;align-items:center;margin-bottom:1rem;">
        <a href="{{ route('teacher.revision-center') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        <a href="{{ route('teacher.dashboard') }}" style="color:#cbd5e1;font-size:.82rem;font-weight:700;text-decoration:none;">
            🏠 Dashboard
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="trr-hero">
        <div>
            <div style="font-size:.75rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem;">Author Workspace • Governance Task Queue</div>
            <h1 style="font-size:1.75rem;font-weight:900;color:#fff;margin:0 0 .35rem;">🛠 Teacher Repository Revision Center</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Inspect quality findings requested by Repository Managers, fix items, and resubmit for automatic IRQA verification.</p>
        </div>
    </div>

    @if(session('success'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;">
        ✅ {{ session('success') }}
    </div>
    @endif

    {{-- Revision Requests Grid --}}
    @if($revisionRequests->count() > 0)
    <div class="trr-grid">
        @foreach($revisionRequests as $rr)
        <div class="trr-card">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                        <h3 style="font-size:1.05rem;font-weight:800;color:#f1f5f9;margin:0 0 .2rem;">
                            {{ $rr->questionBank?->title ?? 'Repository Asset' }}
                        </h3>
                        <div style="font-size:.72rem;color:#64748b;">
                            ID: {{ substr($rr->id, 0, 13) }} • {{ $rr->created_at?->diffForHumans() }}
                        </div>
                    </div>
                    @php
                        $totCount = $rr->items->count();
                        $clsCount = $rr->items->where('status', 'CLOSED')->count();
                        $isReadyResubmit = $totCount > 0 && $clsCount >= $totCount;
                    @endphp
                    @if($isReadyResubmit)
                    <span style="padding:.25rem .65rem;border-radius:99px;font-size:.68rem;font-weight:800;background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);">
                        ✔ All Findings Resolved — Ready For Resubmission
                    </span>
                    @else
                    <span class="trr-badge trr-badge--{{ strtolower($rr->status) }}">
                        {{ $rr->status }}
                    </span>
                    @endif
                </div>

                <div style="font-size:.82rem;color:#cbd5e1;background:#080f1d;padding:.85rem 1rem;border-radius:.65rem;border:1px solid #1e293b;margin-bottom:1rem;">
                    <div style="font-size:.7rem;font-weight:800;color:#818cf8;text-transform:uppercase;margin-bottom:.25rem;">Reviewer Notes:</div>
                    "{{ Str::limit($rr->notes, 120) ?: 'Please review and fix indicated repository items.' }}"
                </div>

                <div style="font-size:.78rem;color:#94a3b8;display:flex;gap:1rem;">
                    <span>⚠️ <strong>{{ $rr->items->count() }}</strong> Actionable Issues</span>
                    <span>✅ <strong>{{ $rr->items->where('status', 'CLOSED')->count() }}</strong> Fixed</span>
                </div>
            </div>

            <a href="{{ route('teacher.repository-revisions.show', $rr->id) }}" style="display:block;text-align:center;padding:.65rem;background:#6366f1;color:#fff;border-radius:.65rem;font-size:.82rem;font-weight:800;text-decoration:none;">
                Open Revision Workspace →
            </a>
        </div>
        @endforeach
    </div>

    <div style="margin-top:1rem;">
        {{ $revisionRequests->links() }}
    </div>
    @else
    <div style="text-align:center;padding:4rem 2rem;background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;color:#64748b;">
        <div style="font-size:2.5rem;margin-bottom:1rem;">✨</div>
        <h3 style="font-size:1.2rem;font-weight:800;color:#fff;margin:0 0 .5rem;">No Pending Repository Revisions</h3>
        <p style="font-size:.85rem;color:#94a3b8;margin:0;">All your published question bank repositories are fully synchronized and pass institutional quality standards.</p>
    </div>
    @endif

</div>
@endsection
