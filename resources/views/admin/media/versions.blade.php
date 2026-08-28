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
    border: 1px solid #cbd5e1;
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
.tmv-table th { padding: .75rem 1rem; background: #f1f5f9; color: #1e293b; font-weight: 800; text-transform: uppercase; font-size: .72rem; border-bottom: 1px solid #cbd5e1; }
html.dark .tmv-table th, html[data-theme="dark"] .tmv-table th { background: #1e293b; color: #94a3b8; border-bottom-color: #1e293b; }

.tmv-table td { padding: .85rem 1rem; border-bottom: 1px solid #e2e8f0; color: #0f172a; }
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
                <a href="{{ route('admin.media.edit', $media->id) }}" class="text-indigo-700 dark:text-indigo-400 text-xs font-bold no-underline inline-flex items-center gap-1 hover:underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Return to Media Editor
                </a>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Version History &amp; Immutable Audit Log
            </h1>
            <div style="font-size:.82rem;margin-top:.2rem;" class="text-slate-700 dark:text-slate-300 font-medium">
                Asset: <strong class="text-slate-900 dark:text-white">{{ $media->title ?? $media->original_name }}</strong> • Published Version: <strong style="color:#0284c7;">v{{ $media->version ?? '1.0' }}</strong>
            </div>
        </div>
    </div>

    {{-- Version Control History Table --}}
    <div class="tmv-card">
        <h3 style="font-size:1rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            Version History Releases
        </h3>

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
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold {{ $ver->is_current ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700' : 'bg-slate-100 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' }}">
                                {{ $ver->is_current ? 'CURRENT PUBLISHED' : 'ARCHIVED VERSION' }}
                            </span>
                        </td>
                        <td class="text-slate-600 dark:text-slate-400 font-medium">{{ $ver->created_at?->format('d M Y, H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;font-style:italic;padding:2rem;" class="text-slate-600 dark:text-slate-400">
                            No previous versions archived. Currently running Published Version <strong>v{{ $media->version ?? '1.0' }}</strong>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Immutable Audit Trail Log Table --}}
    <div class="tmv-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Append-Only Immutable Audit Trail
            </h3>
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700">
                READ-ONLY &amp; UNALTERABLE
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
                        <td class="text-slate-600 dark:text-slate-400 font-medium">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td><strong class="text-slate-900 dark:text-white">{{ $log->actor?->name ?? 'System' }}</strong></td>
                        <td class="text-slate-700 dark:text-slate-300 font-medium">{{ $log->actor?->getRoleNames()->first() ?? 'User' }}</td>
                        <td>
                            <span class="bg-slate-100 dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 border border-slate-300 dark:border-slate-700 px-2 py-0.5 rounded text-[11px] font-extrabold">
                                {{ strtoupper($log->action) }}
                            </span>
                        </td>
                        <td style="color:#0284c7;font-weight:800;">v{{ $log->version ?? '1.0' }}</td>
                        <td class="text-slate-800 dark:text-slate-200 font-medium">{{ $log->reason ?? 'Governance action logged' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;font-style:italic;padding:2rem;" class="text-slate-600 dark:text-slate-400">
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
