@extends('layouts.admin')

@section('title', 'Archive Requests Queue')

@section('content')
<div class="pub-queue-page">

    <div class="page-header">
        <div class="page-header__inner">
            <div>
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">← Dashboard</a>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Archive Requests</span>
                </div>
                <h1 class="page-title">Archive Requests Queue</h1>
                <p class="page-subtitle">Track archive requests you submitted to Super Admin. Approved requests will archive the content automatically.</p>
            </div>
            <div class="page-header__actions">
                <a href="{{ route('admin.publications.published') }}" class="btn btn--ghost">← Published Content</a>
            </div>
        </div>
    </div>

    @if(session('status'))
    <div class="alert alert--success" x-data="{ show: true }" x-show="show" x-transition>
        <span>✅ {{ session('status') }}</span>
        <button @click="show = false" class="alert__close">×</button>
    </div>
    @endif

    @if($archiveRequests->isEmpty())
    <div class="empty-state">
        <div class="empty-state__icon">📦</div>
        <h3>No Archive Requests</h3>
        <p>You haven't submitted any archive requests. To request an archive, go to the Published Content Registry and click "Request Archive" on a published Question Bank.</p>
    </div>
    @else
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Question Bank</th>
                    <th>Requested By</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th>Submitted</th>
                    <th>Resolved</th>
                </tr>
            </thead>
            <tbody>
                @foreach($archiveRequests as $req)
                <tr>
                    <td class="td-title">{{ $req->questionBank?->title ?? '—' }}</td>
                    <td class="td-meta">{{ $req->requester?->name ?? '—' }}</td>
                    <td class="td-meta">{{ $req->reason ?? '—' }}</td>
                    <td>
                        @if($req->status === 'pending')
                        <span class="status-badge status-badge--pending">⏳ Pending SA Approval</span>
                        @elseif($req->status === 'approved')
                        <span class="status-badge status-badge--approved">✅ Approved — Archived</span>
                        @elseif($req->status === 'rejected')
                        <span class="status-badge status-badge--rejected">❌ Rejected</span>
                        @else
                        <span class="status-badge status-badge--neutral">{{ $req->status }}</span>
                        @endif
                    </td>
                    <td class="td-meta">{{ $req->reviewer?->name ?? '—' }}</td>
                    <td class="td-meta">{{ $req->created_at?->diffForHumans() }}</td>
                    <td class="td-meta">{{ $req->actioned_at ? \Carbon\Carbon::parse($req->actioned_at)->diffForHumans() : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="table-footer">
            {{ $archiveRequests->links() }}
        </div>
    </div>
    @endif

</div>

<style>
.pub-queue-page { padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; }
.page-title { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 .2rem; }
.page-subtitle { color: #64748b; font-size: .9rem; margin: 0; }
.page-header__inner { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
.page-header__actions { display: flex; gap: .5rem; }
.breadcrumb-link { color: #64748b; text-decoration: none; font-size: .85rem; }
.breadcrumb-link:hover { color: #3b82f6; }
.breadcrumb-sep { color: #cbd5e1; font-size: .85rem; }
.breadcrumb-current { font-size: .85rem; color: #0f172a; font-weight: 600; }
.alert { display: flex; align-items: center; justify-content: space-between; padding: .875rem 1.25rem; border-radius: 10px; font-size: .9rem; }
.alert--success { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; }
.table-card { background: white; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.data-table thead tr { background: #f8fafc; }
.data-table th { padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: .875rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fafc; }
.td-title { font-weight: 600; color: #0f172a; }
.td-meta { color: #64748b; }
.table-footer { padding: 1rem; border-top: 1px solid #f1f5f9; }
.status-badge { font-size: .7rem; font-weight: 700; letter-spacing: .05em; padding: 3px 9px; border-radius: 5px; white-space: nowrap; }
.status-badge--approved { background: #dcfce7; color: #15803d; }
.status-badge--pending { background: #fef3c7; color: #92400e; }
.status-badge--rejected { background: #fee2e2; color: #dc2626; }
.status-badge--neutral { background: #f1f5f9; color: #64748b; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; background: white; border-radius: 14px; border: 1.5px dashed #e2e8f0; text-align: center; }
.empty-state__icon { font-size: 2.5rem; margin-bottom: .75rem; }
.empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 .4rem; }
.empty-state p { color: #94a3b8; font-size: .88rem; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.25rem; border-radius: 8px; font-size: .875rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background .2s; }
.btn--ghost { background: #f1f5f9; color: #475569; }
</style>
@endsection
