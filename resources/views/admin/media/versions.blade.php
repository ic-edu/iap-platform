@extends('layouts.admin')

@section('title', 'Version History & Audit Log — ' . ($media->title ?? $media->original_name))

@push('styles')
<style>
.tmv-page { display:flex; flex-direction:column; gap:1.5rem; }

.tmv-header {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 50%, #eef2ff 100%);
    border: 1px solid #dbe0fc;
    border-radius: 1.25rem;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 4px 16px -2px rgba(99,102,241,0.06);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tmv-header, html[data-theme="dark"] .tmv-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border-color: #1e293b;
    box-shadow: 0 4px 16px -2px rgba(0,0,0,0.4);
}

.tmv-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tmv-card, html[data-theme="dark"] .tmv-card {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tmv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
    text-align: left;
}
.tmv-table th { padding: .75rem 1rem; background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: .7rem; border-bottom: 1px solid #e2e8f0; }
html.dark .tmv-table th, html[data-theme="dark"] .tmv-table th { background: #1e293b; color: #94a3b8; border-bottom-color: #1e293b; }

.tmv-table td { padding: .85rem 1rem; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
html.dark .tmv-table td, html[data-theme="dark"] .tmv-table td { border-bottom-color: #1e293b; color: #f1f5f9; }
.tmv-table tr:last-child td { border-bottom: none; }
</style>
@endpush

@section('content')
<div class="tmv-page">

    {{-- Top Header --}}
    <div class="tmv-header">
        <div>
            <div style="font-size:.78rem;font-weight:700;margin-bottom:.2rem;">
                <a href="{{ route('admin.media.edit', $media->id) }}" style="color:#4f46e5;text-decoration:none;">← Return to Media Editor</a>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                📄 Version History &amp; Immutable Audit Log
            </h1>
            <div style="font-size:.82rem;margin-top:.2rem;" class="text-slate-600 dark:text-slate-400">
                Asset: <strong class="text-slate-900 dark:text-white">{{ $media->title ?? $media->original_name }}</strong> • Published Version: <strong style="color:#0284c7;">v{{ $media->version ?? '1.0' }}</strong>
            </div>
        </div>
    </div>

    {{-- TASK 7: Version Control History Table --}}
    <div class="tmv-card">
        <h3 style="font-size:1rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">📦 Version History Releases</h3>

        <div style="overflow-x:auto;">
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
                        <td><strong style="color:#0284c7;">v{{ $ver->version_number }}</strong></td>
                        <td class="font-semibold">{{ $ver->title }}</td>
                        <td>{{ $ver->creator?->name ?? 'System' }}</td>
                        <td>{{ $ver->change_reason ?? 'Initial publication' }}</td>
                        <td>
                            <span style="padding:.2rem .6rem;border-radius:99px;font-size:.7rem;font-weight:800;background:{{ $ver->is_current ? 'rgba(16,185,129,.15)' : 'rgba(148,163,184,.15)' }};color:{{ $ver->is_current ? '#059669' : '#475569' }};">
                                {{ $ver->is_current ? 'CURRENT PUBLISHED' : 'ARCHIVED VERSION' }}
                            </span>
                        </td>
                        <td class="text-slate-500 dark:text-slate-400">{{ $ver->created_at?->format('d M Y, H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;font-style:italic;padding:2rem;" class="text-slate-500 dark:text-slate-400">
                            No previous versions archived. Currently running Published Version <strong>v{{ $media->version ?? '1.0' }}</strong>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TASK 8: Immutable Audit Trail Log Table --}}
    <div class="tmv-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                🛡 Append-Only Immutable Audit Trail
            </h3>
            <span style="font-size:.72rem;color:#059669;font-weight:700;padding:.2rem .6rem;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:99px;">
                🔒 READ-ONLY &amp; UNALTERABLE
            </span>
        </div>

        <div style="overflow-x:auto;">
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
                        <td class="text-slate-500 dark:text-slate-400">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td><strong class="text-slate-900 dark:text-white">{{ $log->actor?->name ?? 'System' }}</strong></td>
                        <td class="text-slate-700 dark:text-slate-300">{{ $log->actor?->getRoleNames()->first() ?? 'User' }}</td>
                        <td>
                            <span class="bg-slate-100 dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 border border-slate-200 dark:border-slate-700" style="padding:.2rem .6rem;border-radius:.4rem;font-size:.7rem;font-weight:800;">
                                {{ strtoupper($log->action) }}
                            </span>
                        </td>
                        <td style="color:#0284c7;font-weight:700;">v{{ $log->version ?? '1.0' }}</td>
                        <td class="text-slate-700 dark:text-slate-300">{{ $log->reason ?? 'Governance action logged' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;font-style:italic;padding:2rem;" class="text-slate-500 dark:text-slate-400">
                            No audit trail entries recorded yet for this asset.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
