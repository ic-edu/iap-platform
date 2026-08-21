@extends('layouts.admin')

@section('title', 'Media Approval Center — Enterprise Repository Management')

@push('styles')
<style>
.mac-container { display:flex; flex-direction:column; gap:1.5rem; width:100%; max-width:100%; }
.mac-card { background:#0f172a; border:1px solid #1e293b; border-radius:1.25rem; padding:1.5rem; }

.mac-pills { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.mac-pill {
    padding:.45rem 1rem; border-radius:99px; font-size:.78rem; font-weight:700;
    text-decoration:none; color:#94a3b8; background:#1e293b; border:1px solid #334155;
    transition: all .15s ease;
}
.mac-pill.active { background:#6366f1; color:#fff; border-color:#6366f1; }

.mac-table { width:100%; border-collapse:collapse; font-size:.84rem; text-align:left; }
.mac-table th { padding:.75rem 1rem; background:#1e293b; color:#94a3b8; font-weight:700; border-bottom:1px solid #334155; }
.mac-table td { padding:.85rem 1rem; border-bottom:1px solid #1e293b; color:#e2e8f0; }
.mac-table tr:hover td { background:rgba(30,41,59,.5); }
</style>
@endpush

@section('content')
<div class="mac-container">

    {{-- Top Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <a href="{{ route('admin.repository-manager.dashboard') }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
                ← Back
            </a>
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:.25rem 0 0;">
                Media Approval Center (QA Queue)
            </h1>
        </div>
    </div>

    {{-- Status & Type Filters (PART F) --}}
    <div class="mac-card">
        <div style="font-size:.75rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:.6rem;">Filter by Submission Status</div>
        <div class="mac-pills">
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'pending_review', 'type' => $type]) }}" class="mac-pill {{ $status === 'pending_review' ? 'active' : '' }}">
                ⏳ Pending Review Queue
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'approved', 'type' => $type]) }}" class="mac-pill {{ $status === 'approved' ? 'active' : '' }}">
                ✅ Approved Revisions
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'revision_requested', 'type' => $type]) }}" class="mac-pill {{ $status === 'revision_requested' ? 'active' : '' }}">
                ⚠️ Revision Requested
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'rejected', 'type' => $type]) }}" class="mac-pill {{ $status === 'rejected' ? 'active' : '' }}">
                ❌ Rejected
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'all', 'type' => $type]) }}" class="mac-pill {{ $status === 'all' ? 'active' : '' }}">
                🌐 All Submissions
            </a>
        </div>

        <div style="font-size:.75rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:.6rem;">Filter by Asset Type</div>
        <div class="mac-pills">
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status]) }}" class="mac-pill {{ empty($type) ? 'active' : '' }}">
                All Asset Types
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'audio']) }}" class="mac-pill {{ $type === 'audio' ? 'active' : '' }}">
                🎵 Audio Clips
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'image']) }}" class="mac-pill {{ $type === 'image' ? 'active' : '' }}">
                🖼 Images
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'passage']) }}" class="mac-pill {{ $type === 'passage' ? 'active' : '' }}">
                📖 Passages
            </a>
            <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'pdf']) }}" class="mac-pill {{ $type === 'pdf' ? 'active' : '' }}">
                📄 PDF Documents
            </a>
        </div>
    </div>

    {{-- Review Requests Table --}}
    <div class="mac-card">
        @if($reviewRequests->count() > 0)
            <table class="mac-table">
                <thead>
                    <tr>
                        <th>Asset Title / Request ID</th>
                        <th>Type</th>
                        <th>Submitter Teacher</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th>Reviewer Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reviewRequests as $req)
                    @php
                        $mediaType = $req->changes_data['media_type'] ?? 'Asset';
                    @endphp
                    <tr>
                        <td>
                            <div style="font-weight:700;color:#fff;">
                                {{ $req->changes_data['new_title'] ?? 'Media Revision Request' }}
                            </div>
                            <div style="font-size:.7rem;color:#64748b;">
                                Request ID: <code style="color:#a78bfa;">{{ $req->id }}</code> • Resource ID: {{ $req->resource_id }}
                            </div>
                        </td>
                        <td>
                            <span style="padding:.2rem .6rem;background:#1e293b;border:1px solid #334155;color:#818cf8;border-radius:.4rem;font-size:.72rem;font-weight:700;text-transform:uppercase;">
                                {{ $mediaType }}
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:600;color:#e2e8f0;">{{ $req->submitter?->name ?? 'Teacher' }}</div>
                            <div style="font-size:.7rem;color:#64748b;">{{ $req->submitter?->email }}</div>
                        </td>
                        <td style="font-size:.75rem;color:#94a3b8;">
                            {{ $req->created_at?->format('d M Y, H:i') }}
                        </td>
                        <td>
                            @if($req->status === 'pending_review')
                                <span style="padding:.2rem .6rem;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:.4rem;font-size:.72rem;font-weight:700;">
                                    ⏳ Pending QA Review
                                </span>
                            @elseif($req->status === 'approved')
                                <span style="padding:.2rem .6rem;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:#34d399;border-radius:.4rem;font-size:.72rem;font-weight:700;">
                                    ✅ Approved & Published
                                </span>
                            @elseif($req->status === 'revision_requested')
                                <span style="padding:.2rem .6rem;background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.3);color:#fb923c;border-radius:.4rem;font-size:.72rem;font-weight:700;">
                                    ⚠️ Revision Requested
                                </span>
                            @else
                                <span style="padding:.2rem .6rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:.4rem;font-size:.72rem;font-weight:700;">
                                    ❌ Rejected
                                </span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.repository-manager.media-review', $req->id) }}" style="padding:.45rem .9rem;background:#6366f1;color:#fff;border-radius:.5rem;font-size:.75rem;font-weight:800;text-decoration:none;display:inline-block;">
                                Review Diff →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:1.25rem;">
                {{ $reviewRequests->links() }}
            </div>
        @else
            <div style="text-align:center;padding:3rem;color:#64748b;">
                <div style="font-size:2.5rem;margin-bottom:.5rem;">✨</div>
                <div style="font-size:1rem;font-weight:700;color:#e2e8f0;margin-bottom:.25rem;">No Submissions Matching Selected Filter</div>
                <div style="font-size:.8rem;">All teacher media revision requests in this queue have been processed.</div>
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
