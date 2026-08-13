@extends('layouts.admin')

@section('title', 'Published Content Registry')

@section('content')
<div class="pub-queue-page">

    <div class="page-header">
        <div class="page-header__inner">
            <div>
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">← Dashboard</a>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Published Content Registry</span>
                </div>
                <h1 class="page-title">Published Content Registry</h1>
                <p class="page-subtitle">All live content — Question Banks and Assessments currently visible to Candidates.</p>
            </div>
            <div class="page-header__actions">
                <a href="{{ route('admin.publications.question-banks') }}" class="btn btn--ghost">Question Bank Queue</a>
                <a href="{{ route('admin.publications.assessments') }}" class="btn btn--ghost">Assessment Queue</a>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="stats-row">
        <div class="mini-stat mini-stat--green">
            <span class="mini-stat__icon">📂</span>
            <div>
                <p class="mini-stat__value">{{ $publishedBanks->count() }}</p>
                <p class="mini-stat__label">Published Question Banks</p>
            </div>
        </div>
        <div class="mini-stat mini-stat--blue">
            <span class="mini-stat__icon">📋</span>
            <div>
                <p class="mini-stat__value">{{ $publishedAssessments->count() }}</p>
                <p class="mini-stat__label">Published Assessments</p>
            </div>
        </div>
        <div class="mini-stat mini-stat--teal">
            <span class="mini-stat__icon">🌐</span>
            <div>
                <p class="mini-stat__value">{{ $publishedBanks->count() + $publishedAssessments->count() }}</p>
                <p class="mini-stat__label">Total Live Content</p>
            </div>
        </div>
    </div>

    {{-- Published Question Banks --}}
    <section>
        <h2 class="section-label">Published Question Banks</h2>
        @if($publishedBanks->isEmpty())
        <div class="empty-state">
            <div class="empty-state__icon">📂</div>
            <p>No Question Banks are currently published.</p>
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
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($publishedBanks as $bank)
                    <tr>
                        <td class="td-title">{{ $bank->title }}</td>
                        <td class="td-meta">{{ $bank->category?->name ?? '—' }}</td>
                        <td class="td-meta">{{ $bank->creator?->name ?? '—' }}</td>
                        <td class="td-meta text-center">{{ $bank->questions?->count() ?? 0 }}</td>
                        <td class="td-meta">{{ $bank->updated_at?->diffForHumans() }}</td>
                        <td class="td-actions">
                            <button type="button" class="btn btn--sm btn--outline-orange"
                                @click="$dispatch('open-archive-modal', { id: {{ $bank->id }}, title: '{{ addslashes($bank->title) }}' })">
                                Request Archive
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    {{-- Published Assessments --}}
    <section>
        <h2 class="section-label">Published Assessments</h2>
        @if($publishedAssessments->isEmpty())
        <div class="empty-state">
            <div class="empty-state__icon">📋</div>
            <p>No Assessments are currently published.</p>
        </div>
        @else
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Questions</th>
                        <th>Duration</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($publishedAssessments as $test)
                    @php
                        // Real hierarchy: Test → sections() → testQuestions()
                        $testQCount = $test->sections->sum(fn ($s) => $s->testQuestions->count());
                    @endphp
                    <tr>
                        <td class="td-title">{{ $test->title }}</td>
                        <td class="td-meta">{{ $test->creator?->name ?? '—' }}</td>
                        <td class="td-meta text-center">{{ $testQCount }}</td>
                        <td class="td-meta">{{ $test->duration_minutes ? $test->duration_minutes . ' min' : '—' }}</td>
                        <td class="td-meta">{{ $test->updated_at?->diffForHumans() }}</td>
                        <td class="td-actions">
                            <form action="{{ route('admin.publications.assessments.unpublish', $test->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Unpublish Assessment?', message: 'Unpublish \'{{ addslashes($test->title) }}\'? Candidates will lose access.', confirmText: 'Unpublish Assessment', variant: 'warning', form: this });">
                                @csrf
                                <button type="submit" class="btn btn--sm btn--outline-orange">Unpublish</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    {{-- Archive Request Modal (shared) --}}
    <div x-data="archiveModal()" x-show="open" x-cloak class="modal-overlay" @open-archive-modal.window="show($event.detail)">
        <div class="modal" @click.outside="close()">
            <div class="modal__header">
                <h3 class="modal__title">Request Archive</h3>
                <button class="modal__close" @click="close()">×</button>
            </div>
            <div class="modal__body">
                <p>Requesting archive for: <strong x-text="bankTitle"></strong></p>
                <p class="modal__info">This will submit an archive request to Super Admin for approval. The Question Bank will remain published until Super Admin approves.</p>
                <form :action="`/admin/question-banks/${bankId}/request-archive`" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Reason for Archive (optional)</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="Enter reason…"></textarea>
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
.section-label { font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin: 0 0 .75rem; }
.stats-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.mini-stat { background: white; border-radius: 12px; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; border: 1px solid #e2e8f0; flex: 1; min-width: 200px; }
.mini-stat__icon { font-size: 1.75rem; }
.mini-stat__value { font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1; margin: 0; }
.mini-stat__label { font-size: .78rem; color: #64748b; margin: 0; }
.mini-stat--green { border-top: 3px solid #22c55e; }
.mini-stat--blue { border-top: 3px solid #3b82f6; }
.mini-stat--teal { border-top: 3px solid #14b8a6; }
.table-card { background: white; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.data-table thead tr { background: #f8fafc; }
.data-table th { padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: .875rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fafc; }
.td-title { font-weight: 600; color: #0f172a; }
.td-meta { color: #64748b; }
.td-actions { display: flex; gap: .5rem; }
.text-center { text-align: center; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2.5rem; background: white; border-radius: 14px; border: 1.5px dashed #e2e8f0; text-align: center; gap: .5rem; }
.empty-state__icon { font-size: 2rem; }
.empty-state p { color: #94a3b8; font-size: .88rem; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.25rem; border-radius: 8px; font-size: .875rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background .2s; }
.btn--ghost { background: #f1f5f9; color: #475569; }
.btn--sm { padding: .35rem .75rem; font-size: .78rem; }
.btn--orange { background: #f97316; color: white; }
.btn--outline-orange { background: white; color: #f97316; border: 1px solid #f97316; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 9000; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 25px 60px rgba(0,0,0,.2); }
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
