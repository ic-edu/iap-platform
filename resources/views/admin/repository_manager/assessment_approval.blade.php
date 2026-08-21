@extends('layouts.admin')

@section('title', 'Assessment Approval Queue — Repository Manager')

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .3rem;">📋 Assessment Approval Queue</h1>
            <p style="font-size:.88rem;color:#94a3b8;margin:0;">Review, validate, and approve submitted Assessment Tests from Teachers for institutional deployment.</p>
        </div>
        <div>
            <a href="{{ route('admin.repository-manager.dashboard') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ← Back
            </a>
        </div>
    </div>

    {{-- Filter & Counter Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:1.5rem;">
        <a href="{{ route('admin.repository-manager.assessment-approval', ['status' => 'pending']) }}" style="background:#0f172a;border:1px solid {{ $status === 'pending' ? '#6366f1' : '#1e293b' }};border-radius:1rem;padding:1.25rem;text-decoration:none;display:block;">
            <div style="font-size:.72rem;font-weight:700;color:#fbbf24;text-transform:uppercase;letter-spacing:.06em;">Pending Approval</div>
            <div style="font-size:1.8rem;font-weight:900;color:#fff;margin-top:.2rem;">{{ $pendingCount }}</div>
            <div style="font-size:.72rem;color:#64748b;margin-top:.1rem;">Awaiting Repository Review</div>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-approval', ['status' => 'approved']) }}" style="background:#0f172a;border:1px solid {{ $status === 'approved' ? '#10b981' : '#1e293b' }};border-radius:1rem;padding:1.25rem;text-decoration:none;display:block;">
            <div style="font-size:.72rem;font-weight:700;color:#34d399;text-transform:uppercase;letter-spacing:.06em;">Approved Tests</div>
            <div style="font-size:1.8rem;font-weight:900;color:#fff;margin-top:.2rem;">{{ $approvedCount }}</div>
            <div style="font-size:.72rem;color:#64748b;margin-top:.1rem;">Live in Academic Repository</div>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-approval', ['status' => 'needs_revision']) }}" style="background:#0f172a;border:1px solid {{ $status === 'needs_revision' ? '#f43f5e' : '#1e293b' }};border-radius:1rem;padding:1.25rem;text-decoration:none;display:block;">
            <div style="font-size:.72rem;font-weight:700;color:#fb7185;text-transform:uppercase;letter-spacing:.06em;">Needs Revision</div>
            <div style="font-size:1.8rem;font-weight:900;color:#fff;margin-top:.2rem;">{{ $needsRevisionCount }}</div>
            <div style="font-size:.72rem;color:#64748b;margin-top:.1rem;">Returned to Author</div>
        </a>
    </div>

    {{-- Assessments Queue Table --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #1e293b;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">Assessment Submissions</h3>
            <span style="font-size:.78rem;color:#94a3b8;">Showing {{ $assessments->count() }} of {{ $assessments->total() }} items</span>
        </div>

        @if($assessments->isEmpty())
        <div style="padding:4rem 2rem;text-align:center;color:#64748b;">
            <div style="font-size:2.5rem;margin-bottom:.5rem;">🎉</div>
            <div style="font-size:1rem;font-weight:700;color:#cbd5e1;">Queue Empty</div>
            <div style="font-size:.82rem;margin-top:.25rem;">There are no assessment test submissions matching your current filter.</div>
        </div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:.85rem;">
                <thead>
                    <tr style="background:#1e293b;color:#94a3b8;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">
                        <th style="padding:.85rem 1.25rem;">Title & Type</th>
                        <th style="padding:.85rem 1.25rem;">Teacher Author</th>
                        <th style="padding:.85rem 1.25rem;">Sections & Duration</th>
                        <th style="padding:.85rem 1.25rem;">Status</th>
                        <th style="padding:.85rem 1.25rem;">Submitted At</th>
                        <th style="padding:.85rem 1.25rem;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y:1px solid #1e293b;color:#e2e8f0;">
                    @foreach($assessments as $item)
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:1rem 1.25rem;">
                            <div style="font-weight:800;color:#fff;font-size:.9rem;">{{ $item->title }}</div>
                            <div style="font-size:.72rem;color:#818cf8;margin-top:.15rem;text-transform:uppercase;font-weight:700;">
                                {{ is_object($item->test_type) ? $item->test_type->value : $item->test_type }}
                            </div>
                        </td>
                        <td style="padding:1rem 1.25rem;">
                            <div style="font-weight:700;color:#cbd5e1;">{{ $item->creator?->name ?? 'Institutional System' }}</div>
                            <div style="font-size:.72rem;color:#64748b;">{{ $item->creator?->email }}</div>
                        </td>
                        <td style="padding:1rem 1.25rem;">
                            <div style="font-weight:700;color:#e2e8f0;">{{ $item->sections->count() }} Sections</div>
                            <div style="font-size:.72rem;color:#64748b;">⏱ {{ $item->duration_minutes }} Mins</div>
                        </td>
                        <td style="padding:1rem 1.25rem;">
                            @if(in_array($item->status, ['pending', 'pending_approval']))
                                <span style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.3);padding:.25rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:800;">
                                    ⏳ Pending Approval
                                </span>
                            @elseif($item->status === 'approved')
                                <span style="background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3);padding:.25rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:800;">
                                    ✓ Approved
                                </span>
                            @else
                                <span style="background:rgba(251,113,133,.12);color:#fb7185;border:1px solid rgba(251,113,133,.3);padding:.25rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:800;">
                                    ⚠️ Needs Revision
                                </span>
                            @endif
                        </td>
                        <td style="padding:1rem 1.25rem;font-size:.78rem;color:#94a3b8;">
                            {{ $item->updated_at?->diffForHumans() ?? 'Recently' }}
                        </td>
                        <td style="padding:1rem 1.25rem;text-align:right;">
                            @if(in_array($item->status, ['pending', 'pending_approval']))
                                <a href="{{ route('admin.repository-manager.assessment-review', $item->id) }}" style="padding:.45rem .85rem;background:#4338ca;color:#fff;border-radius:.5rem;font-size:.78rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                                    🔍 Review & Governance
                                </a>
                            @elseif(in_array($item->status, ['needs_revision', 'revision_requested']))
                                <span style="padding:.45rem .85rem;background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);border-radius:.5rem;font-size:.78rem;font-weight:800;display:inline-flex;align-items:center;gap:.3rem;">
                                    📝 Awaiting Teacher Resubmission
                                </span>
                            @elseif($item->status === 'approved')
                                <span style="padding:.45rem .85rem;background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);border-radius:.5rem;font-size:.78rem;font-weight:800;display:inline-flex;align-items:center;gap:.3rem;">
                                    ✓ Governance Complete
                                </span>
                            @else
                                <span style="padding:.45rem .85rem;background:#1e293b;color:#94a3b8;border:1px solid #334155;border-radius:.5rem;font-size:.78rem;font-weight:700;">
                                    {{ ucfirst($item->status) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:1rem;border-top:1px solid #1e293b;">
            {{ $assessments->links() }}
        </div>
        @endif
    </div>
</div>
<script>
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
@endsection
