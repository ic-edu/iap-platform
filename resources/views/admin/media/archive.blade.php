@extends('layouts.admin')

@section('title', 'Media Archive — iC.edu Platform')

@push('styles')
<style>
/* ── Media Archive — MEDIA-002 §6 ── */
.ma-page { display:flex; flex-direction:column; gap:1.5rem; }
.ma-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.ma-title { font-size:1.45rem; font-weight:800; color:#f1f5f9; margin:0 0 .25rem; }
.ma-sub   { font-size:.82rem; color:#64748b; margin:0; }

.ma-panel { background:#0f172a; border:1px solid #1e293b; border-radius:1rem; overflow:hidden; }
.ma-panel__head { padding:1rem 1.25rem; border-bottom:1px solid #1e293b; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.ma-panel__title { font-size:.9rem; font-weight:700; color:#f1f5f9; }

.ma-filter { background:#0f172a; border:1px solid #1e293b; border-radius:.85rem; padding:.85rem 1.1rem; display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; }
.ma-search { flex:1; min-width:160px; background:#1e293b; border:1px solid #334155; border-radius:.5rem; color:#e2e8f0; font-size:.82rem; padding:.45rem .85rem; outline:none; }
.ma-search:focus { border-color:#6366f1; }
.ma-filter-pills { display:flex; gap:.4rem; flex-wrap:wrap; }
.ma-pill { padding:.25rem .7rem; border-radius:99px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; border:1px solid #334155; color:#94a3b8; background:#1e293b; cursor:pointer; text-decoration:none; transition:background .15s; white-space:nowrap; }
.ma-pill:hover, .ma-pill--active { background:#6366f1; color:#fff; border-color:#6366f1; }

.ma-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.ma-table th { padding:.65rem 1rem; background:#080f1d; font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#475569; text-align:left; border-bottom:1px solid #1e293b; }
.ma-table td { padding:.8rem 1rem; border-bottom:1px solid #1e293b; vertical-align:middle; }
.ma-table tbody tr:last-child td { border-bottom:none; }
.ma-table tbody tr:hover { background:rgba(99,102,241,.04); }

.ma-code { font-family:monospace; font-size:.72rem; color:#475569; word-break:break-all; }
.ma-name { font-weight:600; color:#e2e8f0; font-size:.82rem; }
.ma-meta { font-size:.72rem; color:#64748b; }
.ma-used { font-size:.72rem; color:#fb7185; font-weight:700; }
.ma-free { font-size:.72rem; color:#64748b; font-style:italic; }

/* Type badges */
.ma-type { display:inline-block; padding:.18rem .6rem; border-radius:99px; font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; border:1px solid; }
.ma-type--audio   { background:rgba(139,92,246,.12); color:#a78bfa; border-color:rgba(139,92,246,.25); }
.ma-type--image   { background:rgba(52,211,153,.12);  color:#34d399; border-color:rgba(52,211,153,.25); }
.ma-type--pdf     { background:rgba(251,191,36,.12);  color:#fbbf24; border-color:rgba(251,191,36,.25); }
.ma-type--passage { background:rgba(99,102,241,.12);  color:#818cf8; border-color:rgba(99,102,241,.25); }
.ma-type--other   { background:rgba(100,116,139,.12); color:#94a3b8; border-color:rgba(100,116,139,.2); }

/* Status Badges (SECTION 7) */
.ma-badge { display:inline-block; padding:.18rem .6rem; border-radius:99px; font-size:.65rem; font-weight:800; text-transform:uppercase; border:1px solid; white-space:nowrap; }
.ma-badge--archived        { background:rgba(100,116,139,.1); color:#94a3b8; border-color:rgba(100,116,139,.2); }
.ma-badge--pending_archive { background:rgba(251,191,36,.12); color:#fbbf24; border-color:rgba(251,191,36,.3); }
.ma-badge--pending_restore { background:rgba(99,102,241,.12); color:#818cf8; border-color:rgba(99,102,241,.3); }
.ma-badge--del_pending     { background:rgba(251,113,133,.12); color:#fb7185; border-color:rgba(251,113,133,.3); }

/* Actions */
.ma-acts { display:flex; gap:.3rem; justify-content:flex-end; flex-wrap:wrap; }
.ma-act { font-size:.7rem; font-weight:700; padding:.22rem .55rem; border-radius:.35rem; border:none; cursor:pointer; text-decoration:none; transition:background .15s; background:none; white-space:nowrap; }
.ma-act--restore { color:#34d399; }
.ma-act--restore:hover { background:rgba(52,211,153,.1); }
.ma-act--approve { color:#818cf8; }
.ma-act--approve:hover { background:rgba(99,102,241,.12); }
.ma-act--delete  { color:#fb7185; }
.ma-act--delete:hover  { background:rgba(251,113,133,.1); }
.ma-act--disabled { color:#334155; cursor:not-allowed; }
.ma-act--view    { color:#818cf8; }
.ma-act--view:hover    { background:rgba(99,102,241,.12); }

.ma-alert { padding:.85rem 1.1rem; border-radius:.75rem; font-size:.82rem; font-weight:600; }
.ma-alert--success { background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.2); color:#34d399; }
.ma-alert--error   { background:rgba(251,113,133,.08); border:1px solid rgba(251,113,133,.2); color:#fb7185; }

.ma-empty { padding:3.5rem 1.5rem; text-align:center; display:flex; flex-direction:column; align-items:center; gap:.65rem; }
.ma-empty__icon  { font-size:2.5rem; opacity:.4; }
.ma-empty__title { font-size:1rem; font-weight:700; color:#475569; }
.ma-empty__sub   { font-size:.82rem; color:#334155; }
</style>
@endpush

@section('content')
<div class="ma-page">

    {{-- Header --}}
    <div class="ma-header">
        <div>
            <h1 class="ma-title">📦 Media Archive &amp; Governance</h1>
            <p class="ma-sub">
                Archived and pending media governance queue. Request or approve restore, or request permanent deletion.
                <a href="{{ route('admin.media.index') }}" style="color:#818cf8;font-weight:600;text-decoration:none;">← Media Management</a>
            </p>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('status'))
    <div class="ma-alert ma-alert--success">✅ {{ session('status') }}</div>
    @endif
    @if(session('error'))
    <div class="ma-alert ma-alert--error">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Info banner --}}
    <div style="background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.18);border-radius:.75rem;padding:.85rem 1.1rem;font-size:.78rem;color:#fbbf24;display:flex;gap:.65rem;align-items:flex-start;">
        <span>⚠️</span>
        <div>
            <strong>Archive is NOT deletion.</strong>
            Archived media files remain stored. Admins can request restore or permanent deletion.
            Super Admin approves archive, restore, and permanent deletion requests. Media in active use cannot be deleted.
        </div>
    </div>

    {{-- SECTION 8: Search & Filter (Filename, Media ID, Uploader, Type, Status) --}}
    <div class="ma-filter">
        <form method="GET" action="{{ route('admin.media.archive-index') }}" style="display:flex;gap:.65rem;flex:1;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search by filename, ID, or uploader…" class="ma-search">
            <div class="ma-filter-pills">
                <a href="{{ route('admin.media.archive-index') }}"
                   class="ma-pill {{ !request('status') ? 'ma-pill--active' : '' }}">All Statuses</a>
                <a href="{{ route('admin.media.archive-index', ['status' => 'archived']) }}"
                   class="ma-pill {{ request('status') === 'archived' ? 'ma-pill--active' : '' }}">Archived</a>
                <a href="{{ route('admin.media.archive-index', ['status' => 'pending_archive']) }}"
                   class="ma-pill {{ request('status') === 'pending_archive' ? 'ma-pill--active' : '' }}">Pending Archive</a>
                <a href="{{ route('admin.media.archive-index', ['status' => 'pending_restore']) }}"
                   class="ma-pill {{ request('status') === 'pending_restore' ? 'ma-pill--active' : '' }}">Pending Restore</a>
            </div>
            <button type="submit" style="padding:.4rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">Search</button>
        </form>
    </div>

    {{-- Archive Table --}}
    <div class="ma-panel">
        <div class="ma-panel__head">
            <span class="ma-panel__title">📦 Archived &amp; Pending Media Queue</span>
            <span style="font-size:.75rem;color:#475569;">{{ $archivedMedia->total() }} items</span>
        </div>

        @if($archivedMedia->isEmpty())
        <div class="ma-empty">
            <div class="ma-empty__icon">📭</div>
            <div class="ma-empty__title">No archived or pending media</div>
            <div class="ma-empty__sub">Archived media and pending governance requests will appear here.</div>
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="ma-table">
                <thead>
                    <tr>
                        <th>Media ID</th>
                        <th>File Name</th>
                        <th>Type</th>
                        <th>Uploaded By</th>
                        <th>Archived / Requested By</th>
                        <th>Request Date</th>
                        <th>Approval Status</th>
                        <th>Current Usage</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($archivedMedia as $asset)
                    @php
                        $hasPendingDel = $asset->deleteRequests->where('status', 'pending')->count() > 0;
                        $status = $asset->status;
                    @endphp
                    <tr>
                        <td><span class="ma-code">{{ Str::limit($asset->id, 16, '…') }}</span></td>
                        <td>
                            <div class="ma-name">{{ Str::limit($asset->original_name, 38) }}</div>
                            <div class="ma-meta">{{ $asset->humanSize() }}</div>
                        </td>
                        <td><span class="ma-type ma-type--{{ $asset->type }}">{{ $asset->type }}</span></td>
                        <td><div class="ma-meta">{{ $asset->uploader?->name ?? '—' }}</div></td>
                        <td><div class="ma-meta">{{ $asset->archiver?->name ?? '—' }}</div></td>
                        <td><div class="ma-meta">{{ $asset->updated_at?->format('d M Y') }}</div></td>

                        {{-- SECTION 7: Status Badges --}}
                        <td>
                            @if($hasPendingDel)
                                <span class="ma-badge ma-badge--del_pending">⏳ Delete Requested</span>
                            @elseif($status === 'pending_archive')
                                <span class="ma-badge ma-badge--pending_archive">⏳ Pending Archive</span>
                            @elseif($status === 'pending_restore')
                                <span class="ma-badge ma-badge--pending_restore">⏳ Pending Restore</span>
                            @else
                                <span class="ma-badge ma-badge--archived">📦 Archived</span>
                            @endif
                        </td>

                        {{-- Current Usage --}}
                        <td>
                            <span class="ma-free" id="usage-{{ $asset->id }}">
                                <button type="button"
                                        onclick="checkUsage('{{ $asset->id }}')"
                                        style="font-size:.68rem;color:#475569;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0;">
                                    Check usage
                                </button>
                            </span>
                        </td>

                        {{-- SECTION 4 & 5: Actions --}}
                        <td>
                            <div class="ma-acts">
                                {{-- Preview --}}
                                <a href="{{ $asset->publicUrl() }}" target="_blank" class="ma-act ma-act--view" title="Preview / Download">👁</a>

                                @if(auth()->user()->hasRole('super-admin'))
                                    {{-- Super Admin Approvals --}}
                                    @if($status === 'pending_archive')
                                    <form method="POST" action="{{ route('admin.media.approve-archive', $asset->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="ma-act ma-act--approve">✅ Approve Arch.</button>
                                    </form>
                                    @elseif($status === 'pending_restore')
                                    <form method="POST" action="{{ route('admin.media.approve-restore', $asset->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="ma-act ma-act--approve">✅ Approve Rest.</button>
                                    </form>
                                    @elseif($status === 'archived')
                                    <form method="POST" action="{{ route('admin.media.approve-restore', $asset->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="ma-act ma-act--restore">↩ Restore</button>
                                    </form>
                                    @endif

                                @elseif(auth()->user()->hasRole('admin'))
                                    {{-- Admin Requests --}}
                                    @if($status === 'archived')
                                    <form method="POST" action="{{ route('admin.media.request-restore', $asset->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="ma-act ma-act--restore">↩ Req. Restore</button>
                                    </form>
                                    @endif

                                    {{-- Permanent Delete Request --}}
                                    @if(!$hasPendingDel)
                                    <form method="POST" action="{{ route('admin.media.request-delete', $asset->id) }}"
                                          style="display:inline;"
                                          onsubmit="return confirm('Request PERMANENT deletion of \'{{ addslashes(Str::limit($asset->original_name,25)) }}\'? Super Admin approval required.');">
                                        @csrf
                                        <button type="submit" class="ma-act ma-act--delete">🗑 Req. Delete</button>
                                    </form>
                                    @else
                                    <span class="ma-act ma-act--disabled">🗑 Pending</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($archivedMedia->hasPages())
        <div style="padding:.85rem 1.25rem;border-top:1px solid #1e293b;">
            {{ $archivedMedia->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
async function checkUsage(mediaId) {
    const el = document.getElementById('usage-' + mediaId);
    el.innerHTML = '<span style="color:#475569;font-size:.7rem;">Checking…</span>';
    try {
        const res = await fetch(`/admin/media/${mediaId}/usage`);
        const json = await res.json();
        if (json.is_in_use) {
            el.innerHTML = `<span class="ma-used">⚠ ${json.question_count} question(s)</span>`;
        } else {
            el.innerHTML = `<span class="ma-free">✓ Not in use</span>`;
        }
    } catch(e) {
        el.innerHTML = '<span style="color:#475569;">Error</span>';
    }
}
</script>
@endpush
