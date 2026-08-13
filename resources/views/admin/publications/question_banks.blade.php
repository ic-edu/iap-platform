@extends('layouts.admin')

@section('title', 'Question Bank Publication Queue')

@section('content')
<div class="pub-queue-page">

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header__inner">
            <div>
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">← Dashboard</a>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Question Bank Publication Queue</span>
                </div>
                <h1 class="page-title">Question Bank Publication Queue</h1>
                <p class="page-subtitle">Approved content ready for publication. Publish or request archive only.</p>
            </div>
            <div class="page-header__actions">
                <a href="{{ route('admin.publications.assessments') }}" class="btn btn--ghost">Assessment Queue</a>
                <a href="{{ route('admin.publications.published') }}" class="btn btn--ghost">Published</a>
            </div>
        </div>
    </div>

    @if(session('status'))
    <div class="alert alert--success" x-data="{ show: true }" x-show="show" x-transition>
        <span>✅ {{ session('status') }}</span>
        <button @click="show = false" class="alert__close">×</button>
    </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.publications.question-banks') }}" class="filter-bar">
        <div class="filter-bar__inputs">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search question banks…" class="filter-input">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved (Ready to Publish)</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
                <option value="pending_archive_approval" {{ request('status') === 'pending_archive_approval' ? 'selected' : '' }}>Pending Archive Approval</option>
                <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
            </select>
        </div>
        <button type="submit" class="btn btn--primary">Filter</button>
    </form>

    {{-- Queue Table --}}
    @if($questionBanks->isEmpty())
    <div class="empty-state">
        <div class="empty-state__icon">📂</div>
        <h3>No Question Banks Found</h3>
        <p>No question banks match the current filter. When a Teacher submits a Question Bank and Super Admin approves it, it will appear here.</p>
    </div>
    @else
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($questionBanks as $bank)
                <tr class="{{ request('highlight') == $bank->id ? 'row-highlighted' : '' }}" id="bank-{{ $bank->id }}">
                    <td class="td-title">{{ $bank->title }}</td>
                    <td class="td-meta">{{ $bank->category?->name ?? '—' }}</td>
                    <td class="td-meta">{{ $bank->creator?->name ?? '—' }}</td>
                    <td class="td-meta text-center">{{ $bank->questions?->count() ?? 0 }}</td>
                    <td>
                        @php $s = $bank->status; @endphp
                        @if($s === 'approved')
                        <span class="status-badge status-badge--approved">✅ Approved</span>
                        @elseif($s === 'published')
                        <span class="status-badge status-badge--published">🟢 Published</span>
                        @elseif($s === 'pending_archive_approval')
                        <span class="status-badge status-badge--pending">⏳ Archive Pending</span>
                        @elseif($s === 'archived')
                        <span class="status-badge status-badge--archived">📦 Archived</span>
                        @else
                        <span class="status-badge status-badge--neutral">{{ $s }}</span>
                        @endif
                    </td>
                    <td class="td-meta">{{ $bank->updated_at?->diffForHumans() }}</td>
                    <td class="td-actions">
                        {{-- ADMIN-OPS-001: Admin can only PUBLISH or REQUEST ARCHIVE. No authoring. --}}
                        @if($bank->status === 'approved')
                        <form action="{{ route('admin.publications.question-banks.publish', $bank->id) }}" method="POST" onsubmit="event.preventDefault(); iapConfirm({ title: 'Publish Question Bank Live?', message: 'Publish \'{{ addslashes($bank->title) }}\' live? Candidates will be able to access this repository.', confirmText: 'Publish Live', variant: 'success', form: this });">
                            @csrf
                            <button type="submit" class="btn btn--sm btn--green">Publish</button>
                        </form>
                        @elseif($bank->status === 'published')
                        <button type="button" class="btn btn--sm btn--outline-orange"
                            @click="$dispatch('open-archive-modal', { id: {{ $bank->id }}, title: '{{ addslashes($bank->title) }}' })">
                            Request Archive
                        </button>
                        @elseif($bank->status === 'archived')
                        <span class="td-meta">Archived</span>
                        @elseif($bank->status === 'pending_archive_approval')
                        <span class="td-meta text-warning">Pending SA Approval</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="table-footer">
            {{ $questionBanks->withQueryString()->links() }}
        </div>
    </div>
    @endif

    {{-- Archive Request Modal --}}
    <div x-data="archiveModal()" x-show="open" x-cloak class="modal-overlay" @open-archive-modal.window="show($event.detail)">
        <div class="modal" @click.outside="close()">
            <div class="modal__header">
                <h3 class="modal__title">Request Archive</h3>
                <button class="modal__close" @click="close()">×</button>
            </div>
            <div class="modal__body">
                <p>Requesting archive for: <strong x-text="bankTitle"></strong></p>
                <p class="modal__info">This will submit an archive request to Super Admin for approval. The Question Bank will remain published until approved.</p>
                <form :action="`/admin/question-banks/${bankId}/request-archive`" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Reason for Archive (optional)</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="Enter reason for archiving this Question Bank…"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn--ghost" @click="close()">Cancel</button>
                        <button type="submit" class="btn btn--orange">Submit Archive Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<style>
.pub-queue-page { padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; }
.page-title { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 .2rem; }
.page-subtitle { color: #64748b; font-size: .9rem; margin: 0; }
.page-header__inner { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
.page-header__actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.breadcrumb-link { color: #64748b; text-decoration: none; font-size: .85rem; }
.breadcrumb-link:hover { color: #3b82f6; }
.breadcrumb-sep { color: #cbd5e1; font-size: .85rem; }
.breadcrumb-current { font-size: .85rem; color: #0f172a; font-weight: 600; }
.alert { display: flex; align-items: center; justify-content: space-between; padding: .875rem 1.25rem; border-radius: 10px; font-size: .9rem; }
.alert--success { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; }
.filter-bar { display: flex; gap: .75rem; align-items: center; background: white; padding: 1rem 1.25rem; border-radius: 10px; border: 1px solid #e2e8f0; flex-wrap: wrap; }
.filter-bar__inputs { display: flex; gap: .5rem; flex: 1; flex-wrap: wrap; }
.filter-input, .filter-select { padding: .5rem .875rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: .875rem; outline: none; }
.filter-input { flex: 1; min-width: 180px; }
.filter-input:focus, .filter-select:focus { border-color: #3b82f6; }
.table-card { background: white; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.data-table thead tr { background: #f8fafc; }
.data-table th { padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: .875rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fafc; }
.row-highlighted { background: #fefce8 !important; }
.td-title { font-weight: 600; color: #0f172a; }
.td-meta { color: #64748b; }
.td-actions { display: flex; gap: .5rem; }
.text-center { text-align: center; }
.text-warning { color: #92400e; font-size: .8rem; }
.status-badge { font-size: .7rem; font-weight: 700; letter-spacing: .05em; padding: 3px 9px; border-radius: 5px; white-space: nowrap; }
.status-badge--approved { background: #dcfce7; color: #15803d; }
.status-badge--published { background: #d1fae5; color: #065f46; }
.status-badge--pending { background: #fef3c7; color: #92400e; }
.status-badge--archived { background: #f1f5f9; color: #475569; }
.status-badge--neutral { background: #f1f5f9; color: #64748b; }
.table-footer { padding: 1rem; border-top: 1px solid #f1f5f9; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; background: white; border-radius: 14px; border: 1.5px dashed #e2e8f0; text-align: center; }
.empty-state__icon { font-size: 2.5rem; margin-bottom: .75rem; }
.empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 .4rem; }
.empty-state p { color: #94a3b8; font-size: .88rem; margin: 0; }
/* Buttons */
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.25rem; border-radius: 8px; font-size: .875rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background .2s, box-shadow .2s; }
.btn--primary { background: #3b82f6; color: white; }
.btn--ghost { background: #f1f5f9; color: #475569; }
.btn--sm { padding: .35rem .75rem; font-size: .78rem; }
.btn--green { background: #22c55e; color: white; }
.btn--orange { background: #f97316; color: white; }
.btn--outline-orange { background: white; color: #f97316; border: 1px solid #f97316; }
/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 9000; }
.modal { background: white; border-radius: 16px; padding: 0; width: 100%; max-width: 480px; box-shadow: 0 25px 60px rgba(0,0,0,.2); }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.modal__title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0; }
.modal__close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal__body { padding: 1.5rem; }
.modal__info { font-size: .84rem; color: #64748b; background: #f8fafc; padding: .75rem 1rem; border-radius: 8px; margin: .5rem 0 1rem; }
.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-size: .85rem; font-weight: 600; color: #374151; margin-bottom: .35rem; }
.form-control { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: .6rem .875rem; font-size: .875rem; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #3b82f6; }
.form-actions { display: flex; justify-content: flex-end; gap: .5rem; }
</style>

@push('scripts')
<script>
function archiveModal() {
    return {
        open: false,
        bankId: null,
        bankTitle: '',
        show({ id, title }) { this.open = true; this.bankId = id; this.bankTitle = title; },
        close() { this.open = false; }
    };
}
</script>
@endpush
@endsection
