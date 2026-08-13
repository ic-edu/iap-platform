@extends('layouts.admin')

@section('title', 'Assessment Publication Queue')

@section('content')
<div class="pub-queue-page">

    <div class="page-header">
        <div class="page-header__inner">
            <div>
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">← Dashboard</a>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Assessment Publication Queue</span>
                </div>
                <h1 class="page-title">Assessment Publication Queue</h1>
                <p class="page-subtitle">Approved assessments ready for publication. Publish or unpublish only. No authoring.</p>
            </div>
            <div class="page-header__actions">
                <a href="{{ route('admin.publications.question-banks') }}" class="btn btn--ghost">Question Bank Queue</a>
                <a href="{{ route('admin.publications.published') }}" class="btn btn--ghost">Published Registry</a>
            </div>
        </div>
    </div>

    @if(session('status'))
    <div class="alert alert--success">
        <span>✅ {{ session('status') }}</span>
    </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.publications.assessments') }}" class="filter-bar">
        <div class="filter-bar__inputs">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search assessments…" class="filter-input">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved (Ready to Publish)</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
                <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
            </select>
        </div>
        <button type="submit" class="btn btn--primary">Filter</button>
    </form>

    @if($assessments->isEmpty())
    <div class="empty-state">
        <div class="empty-state__icon">📋</div>
        <h3>No Assessments Found</h3>
        <p>No assessments match the current filter. When Super Admin approves an Assessment, it will appear here ready for publication.</p>
    </div>
    @else
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Assessment Name</th>
                    <th>Author</th>
                    <th>Sections</th>
                    <th>Questions</th>
                    <th>Approval Status</th>
                    <th>Publication Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assessments as $test)
                @php
                    // Question count via real hierarchy: Test -> sections() -> testQuestions()
                    // Both relations are eager-loaded — zero N+1 queries
                    $sectionCount  = $test->sections->count();
                    $questionCount = $test->sections->sum(fn ($s) => $s->testQuestions->count());
                @endphp
                <tr class="{{ request('highlight') == $test->id ? 'row-highlighted' : '' }}"
                    id="assessment-{{ $test->id }}">
                    <td class="td-title">
                        {{ $test->title }}
                        @if($test->duration_minutes)
                        <span class="td-duration">⏱ {{ $test->duration_minutes }} min</span>
                        @endif
                    </td>
                    <td class="td-meta">{{ $test->creator?->name ?? '—' }}</td>
                    <td class="td-meta text-center">{{ $sectionCount }}</td>
                    <td class="td-meta text-center">{{ $questionCount }}</td>
                    {{-- Approval Status --}}
                    <td>
                        @if($test->status === 'approved')
                        <span class="status-badge status-badge--approved">✅ Approved</span>
                        @elseif(in_array($test->status, ['pending', 'pending_approval']))
                        <span class="status-badge status-badge--pending">⏳ Pending Approval</span>
                        @elseif(in_array($test->status, ['needs_revision', 'revision_requested']))
                        <span class="status-badge status-badge--warning" style="background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);padding:.2rem .5rem;border-radius:.3rem;font-size:.75rem;font-weight:700;">⚠️ Needs Revision</span>
                        @elseif($test->status === 'archived')
                        <span class="status-badge status-badge--archived">📦 Archived</span>
                        @elseif($test->status === 'draft')
                        <span class="status-badge status-badge--neutral">📝 Draft</span>
                        @else
                        <span class="status-badge status-badge--neutral">{{ ucfirst($test->status ?? 'Draft') }}</span>
                        @endif
                    </td>
                    {{-- Publication Status --}}
                    <td>
                        @if($test->is_published)
                        <span class="status-badge status-badge--published">🟢 Published</span>
                        @else
                        <span class="status-badge status-badge--unpublished">⚪ Unpublished</span>
                        @endif
                    </td>
                    <td class="td-meta">{{ $test->created_at?->diffForHumans() }}</td>
                    <td class="td-actions">
                        @if($test->status === 'approved' && !$test->is_published)
                        <form action="{{ route('admin.publications.assessments.publish', $test->id) }}" method="POST"
                              onsubmit="event.preventDefault(); iapConfirm({ title: 'Publish Assessment Live?', message: 'Publish \'{{ addslashes($test->title) }}\' live? Candidates will be able to access this assessment.', confirmText: 'Publish Live', variant: 'success', form: this });">
                            @csrf
                            <button type="submit" class="btn btn--sm btn--green">Publish</button>
                        </form>
                        @elseif($test->is_published)
                        <form action="{{ route('admin.publications.assessments.unpublish', $test->id) }}" method="POST"
                              onsubmit="event.preventDefault(); iapConfirm({ title: 'Unpublish Assessment?', message: 'Unpublish \'{{ addslashes($test->title) }}\'? Candidates will lose access.', confirmText: 'Unpublish Assessment', variant: 'warning', form: this });">
                            @csrf
                            <button type="submit" class="btn btn--sm btn--outline-orange">Unpublish</button>
                        </form>
                        @else
                        <span class="td-meta">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="table-footer">
            {{ $assessments->withQueryString()->links() }}
        </div>
    </div>
    @endif

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
.td-duration { display: block; font-size: .7rem; color: #94a3b8; font-weight: 500; margin-top: 2px; }
.td-actions { display: flex; gap: .5rem; }
.text-center { text-align: center; }
.status-badge { font-size: .7rem; font-weight: 700; letter-spacing: .05em; padding: 3px 9px; border-radius: 5px; white-space: nowrap; }
.status-badge--approved { background: #dcfce7; color: #15803d; }
.status-badge--pending { background: #fef3c7; color: #92400e; }
.status-badge--published { background: #d1fae5; color: #065f46; }
.status-badge--unpublished { background: #f1f5f9; color: #94a3b8; }
.status-badge--archived { background: #e2e8f0; color: #475569; }
.status-badge--neutral { background: #f1f5f9; color: #64748b; }
.table-footer { padding: 1rem; border-top: 1px solid #f1f5f9; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; background: white; border-radius: 14px; border: 1.5px dashed #e2e8f0; text-align: center; }
.empty-state__icon { font-size: 2.5rem; margin-bottom: .75rem; }
.empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 .4rem; }
.empty-state p { color: #94a3b8; font-size: .88rem; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.25rem; border-radius: 8px; font-size: .875rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background .2s, box-shadow .2s; }
.btn--primary { background: #3b82f6; color: white; }
.btn--ghost { background: #f1f5f9; color: #475569; }
.btn--sm { padding: .35rem .75rem; font-size: .78rem; }
.btn--green { background: #22c55e; color: white; }
.btn--outline-orange { background: white; color: #f97316; border: 1px solid #f97316; }
</style>
@endsection
