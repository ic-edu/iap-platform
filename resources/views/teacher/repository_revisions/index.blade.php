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
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        @else
        <a href="{{ route('teacher.dashboard') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        @endif
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
        @php
            $bank = $rr->questionBank;
            $isBankLocked = in_array($bank?->status, ['pending_approval', 'submitted', 'approved', 'published', 'pending_archive_approval', 'pending_restore_approval'], true)
                || in_array($rr->status, ['RESUBMITTED', 'CLOSED'], true);
            $totCount = $rr->items->count();
            $clsCount = $rr->items->where('status', 'CLOSED')->count();
            $openCount = max(0, $totCount - $clsCount);

            if ($rr->status === 'RESUBMITTED' || $isBankLocked) {
                $cardStatusLabel = 'RESUBMITTED — AWAITING REVIEW';
                $badgeClass = 'trr-badge--resubmitted';
            } elseif ($totCount > 0 && $openCount === 0) {
                $cardStatusLabel = 'READY FOR RESUBMISSION';
                $badgeClass = 'trr-badge--ready';
            } else {
                $cardStatusLabel = 'OPEN';
                $badgeClass = 'trr-badge--open';
            }
        @endphp
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
                    <span class="trr-badge {{ $badgeClass }}">
                        {{ $cardStatusLabel }}
                    </span>
                </div>

                <div style="font-size:.82rem;color:#cbd5e1;background:#080f1d;padding:.85rem 1rem;border-radius:.65rem;border:1px solid #1e293b;margin-bottom:1rem;">
                    <div style="font-size:.7rem;font-weight:800;color:#818cf8;text-transform:uppercase;margin-bottom:.25rem;">Reviewer Notes:</div>
                    "{{ Str::limit($rr->notes, 120) ?: 'Please review and fix indicated repository items.' }}"
                </div>

                @if($rr->status === 'RESUBMITTED' || $isBankLocked)
                <div style="margin-bottom:.5rem;">
                    <div style="font-size:.78rem;color:#fbbf24;display:flex;align-items:center;gap:.4rem;margin-bottom:.35rem;">
                        <span>🔒</span>
                        <span>Repository has been resubmitted and is awaiting governance review.</span>
                    </div>
                    <div style="font-size:.74rem;color:#64748b;">
                        <span>📋 <strong>{{ $totCount }}</strong> Total Findings</span> •
                        <span style="color:#34d399;">✅ <strong>{{ $clsCount }}</strong> Fixed</span>
                    </div>
                </div>
                @elseif($openCount === 0)
                <div style="margin-bottom:.5rem;">
                    <div style="font-size:.78rem;color:#34d399;display:flex;align-items:center;gap:.4rem;margin-bottom:.35rem;">
                        <span>✔</span>
                        <span>All findings resolved. Ready for resubmission.</span>
                    </div>
                    <div style="font-size:.74rem;color:#94a3b8;">
                        <span><strong>0</strong> Findings Remaining</span> •
                        <span>✅ <strong>{{ $clsCount }}</strong> Fixed</span>
                    </div>
                </div>
                @else
                <div style="font-size:.78rem;color:#94a3b8;display:flex;gap:1rem;margin-bottom:.5rem;">
                    <span>⚠️ <strong>{{ $openCount }}</strong> Findings Remaining</span>
                    <span>✅ <strong>{{ $clsCount }}</strong> Fixed</span>
                </div>
                @endif
            </div>

            <a href="{{ route('teacher.repository-revisions.show', array_filter([$rr->id, 'from' => request('from')])) }}" style="display:block;text-align:center;padding:.65rem;background:#6366f1;color:#fff;border-radius:.65rem;font-size:.82rem;font-weight:800;text-decoration:none;">
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
