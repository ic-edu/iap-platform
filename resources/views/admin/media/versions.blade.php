@extends('layouts.admin')

@section('title', 'Version History & Audit Log — ' . ($media->title ?? $media->original_name))

@push('styles')
<style>
.tmv-page { display:flex; flex-direction:column; gap:1.5rem; }

.tmv-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.tmv-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.tmv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
    text-align: left;
}
.tmv-table th { padding: .75rem 1rem; background: #1e293b; color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: .7rem; }
.tmv-table td { padding: .85rem 1rem; border-bottom: 1px solid #1e293b; color: #f1f5f9; }
.tmv-table tr:last-child td { border-bottom: none; }
</style>
@endpush

@section('content')
<div class="tmv-page">

    {{-- Top Header --}}
    <div class="tmv-header">
        <div>
            <div style="font-size:.78rem;color:#818cf8;font-weight:700;margin-bottom:.2rem;">
                <a href="{{ route('admin.media.edit', $media->id) }}" style="color:#818cf8;text-decoration:none;">← Return to Media Editor</a>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                📄 Version History & Immutable Audit Log
            </h1>
            <div style="font-size:.82rem;color:#94a3b8;margin-top:.2rem;">
                Asset: <strong style="color:#fff;">{{ $media->title ?? $media->original_name }}</strong> • Published Version: <strong style="color:#38bdf8;">v{{ $media->version ?? '1.0' }}</strong>
            </div>
        </div>
    </div>

    {{-- TASK 7: Version Control History Table --}}
    <div class="tmv-card">
        <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">📦 Version History Releases</h3>

        <table class="tmv-table">
            <thead>
                <tr>
                    <th>Version Number</th>
                    <th>Title Snapshot</th>
                    <th>Created By</th>
                    <th>Change Reason</th>
                    <th>Status</th>
                    <th>Created Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($versions as $ver)
                <tr>
                    <td><strong style="color:#38bdf8;">v{{ $ver->version_number }}</strong></td>
                    <td>{{ $ver->title }}</td>
                    <td>{{ $ver->creator?->name ?? 'System' }}</td>
                    <td>{{ $ver->change_reason ?? 'Initial publication' }}</td>
                    <td>
                        <span style="padding:.2rem .6rem;border-radius:99px;font-size:.7rem;font-weight:800;background:{{ $ver->is_current ? 'rgba(16,185,129,.15)' : 'rgba(148,163,184,.15)' }};color:{{ $ver->is_current ? '#34d399' : '#cbd5e1' }};">
                            {{ $ver->is_current ? 'CURRENT PUBLISHED' : 'ARCHIVED VERSION' }}
                        </span>
                    </td>
                    <td>{{ $ver->created_at?->format('d M Y, H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#64748b;font-style:italic;padding:2rem;">
                        No previous versions archived. Currently running Published Version <strong>v{{ $media->version ?? '1.0' }}</strong>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- TASK 8: Immutable Audit Trail Log Table --}}
    <div class="tmv-card" style="border-color:#334155;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                🛡 Append-Only Immutable Audit Trail
            </h3>
            <span style="font-size:.72rem;color:#34d399;font-weight:700;padding:.2rem .6rem;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:99px;">
                🔒 READ-ONLY & UNALTERABLE
            </span>
        </div>

        <table class="tmv-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Version</th>
                    <th>Reason / Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                <tr>
                    <td style="color:#94a3b8;">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td><strong style="color:#fff;">{{ $log->actor?->name ?? 'System' }}</strong></td>
                    <td style="color:#cbd5e1;">{{ $log->actor?->getRoleNames()->first() ?? 'User' }}</td>
                    <td>
                        <span style="padding:.2rem .6rem;border-radius:.4rem;font-size:.7rem;font-weight:800;background:#1e293b;color:#a5b4fc;border:1px solid #334155;">
                            {{ strtoupper($log->action) }}
                        </span>
                    </td>
                    <td style="color:#38bdf8;">v{{ $log->version ?? '1.0' }}</td>
                    <td style="color:#cbd5e1;">{{ $log->reason ?? 'Governance action logged' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#64748b;font-style:italic;padding:2rem;">
                        No audit trail entries recorded yet for this asset.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
