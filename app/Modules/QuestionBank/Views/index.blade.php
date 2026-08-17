@extends('layouts.admin')

@section('title', 'Academic Content Library (ACL) — iC.edu Platform')

@push('styles')
<style>
/* ──────────────────────────────────────────
   ACADEMIC CONTENT LIBRARY (ACL) FOUNDATION
────────────────────────────────────────── */
.acl-workspace { display: flex; flex-direction: column; gap: 1.5rem; }

/* ── Hero Header ── */
.acl-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
}
.acl-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 90% 50%, rgba(99,102,241,.15) 0%, transparent 60%);
    pointer-events: none;
}
.acl-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.acl-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

/* ── Health Score & KPI Grid ── */
.acl-top-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1.25rem;
}
@media (max-width: 1100px) { .acl-top-grid { grid-template-columns: 1fr; } }

/* ── Content Health Score Card ── */
.acl-health-card {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    position: relative;
}
.acl-health-head { display: flex; justify-content: space-between; align-items: center; }
.acl-health-label { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #818cf8; }
.acl-health-badge { padding: .2rem .65rem; border-radius: 99px; font-size: .7rem; font-weight: 900; background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }

.acl-health-body { display: flex; align-items: baseline; gap: .6rem; }
.acl-health-num  { font-size: 2.75rem; font-weight: 900; color: #f1f5f9; line-height: 1; }
.acl-health-max  { font-size: .9rem; color: #475569; font-weight: 700; }

.acl-health-metrics { display: flex; flex-direction: column; gap: .45rem; margin-top: .5rem; }
.acl-hm-item { display: flex; justify-content: space-between; font-size: .72rem; color: #64748b; }
.acl-hm-val  { font-weight: 700; color: #cbd5e1; }

/* ── KPI Cards ── */
.acl-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 900px) { .acl-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.acl-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.15rem 1.25rem;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: .25rem;
    transition: border-color .2s, transform .15s;
}
.acl-kpi:hover { border-color: #6366f1; transform: translateY(-2px); }
.acl-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.acl-kpi__label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
.acl-kpi__desc  { font-size: .7rem; color: #475569; }
.acl-kpi--indigo  .acl-kpi__count { color: #818cf8; }
.acl-kpi--amber   .acl-kpi__count { color: #fbbf24; }
.acl-kpi--emerald .acl-kpi__count { color: #34d399; }
.acl-kpi--slate   .acl-kpi__count { color: #94a3b8; }

/* ── Coverage Indicators Grid ── */
.acl-coverage-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
}
.acl-cov-head { font-size: .88rem; font-weight: 800; color: #f1f5f9; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
.acl-cov-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: .85rem;
}
.acl-cov-card {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: .85rem;
    padding: .85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.acl-cov-card__head { display: flex; justify-content: space-between; align-items: center; }
.acl-cov-card__title { font-size: .78rem; font-weight: 800; color: #e2e8f0; }
.acl-cov-card__pct   { font-size: .82rem; font-weight: 900; color: #818cf8; }
.acl-cov-card__bar-bg { width: 100%; height: 6px; background: #1e293b; border-radius: 99px; overflow: hidden; }
.acl-cov-card__bar-fill { height: 100%; background: linear-gradient(90deg, #6366f1 0%, #34d399 100%); border-radius: 99px; transition: width .4s; }
.acl-cov-card__sub { font-size: .67rem; color: #475569; display: flex; justify-content: space-between; }

/* ── Spotlight Card ── */
.acl-spotlight {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-left: 4px solid #6366f1;
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.acl-spotlight__label { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #818cf8; margin-bottom: .25rem; }
.acl-spotlight__title { font-size: 1.1rem; font-weight: 800; color: #f1f5f9; margin: 0 0 .2rem; }
.acl-spotlight__meta  { font-size: .78rem; color: #64748b; display: flex; gap: 1rem; flex-wrap: wrap; }

/* ── Action Buttons ── */
.acl-btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .5rem 1rem;
    border-radius: .6rem;
    font-size: .8rem;
    font-weight: 700;
    border: 1px solid;
    text-decoration: none;
    transition: background .15s, transform .12s;
    cursor: pointer;
    white-space: nowrap;
}
.acl-btn:hover { transform: translateY(-1px); }
.acl-btn--primary   { background: #6366f1; color: #fff; border-color: #6366f1; }
.acl-btn--primary:hover { background: #4f46e5; }
.acl-btn--secondary { background: #1e293b; color: #cbd5e1; border-color: #334155; }
.acl-btn--secondary:hover { background: #334155; color: #fff; }

/* ── Table & Filter Panel ── */
.acl-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    overflow: hidden;
}
.acl-panel__head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.acl-panel__title { font-size: .9rem; font-weight: 700; color: #f1f5f9; }

.acl-filter-bar {
    padding: .85rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    gap: .75rem;
    align-items: center;
    flex-wrap: wrap;
}
.acl-search {
    flex: 1;
    min-width: 200px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: .5rem;
    color: #e2e8f0;
    font-size: .82rem;
    padding: .5rem .9rem;
    outline: none;
    transition: border-color .15s;
}
.acl-search:focus { border-color: #6366f1; }
.acl-select {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: .5rem;
    color: #cbd5e1;
    font-size: .78rem;
    padding: .48rem .8rem;
    outline: none;
    cursor: pointer;
}
.acl-filter-pills { display: flex; gap: .4rem; flex-wrap: wrap; }
.acl-fpill {
    padding: .28rem .75rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    border: 1px solid #334155;
    color: #94a3b8;
    background: #1e293b;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s, color .15s;
    white-space: nowrap;
}
.acl-fpill:hover, .acl-fpill--active { background: #6366f1; color: #fff; border-color: #6366f1; }

/* ── Table Styling ── */
.acl-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.acl-table th {
    padding: .7rem 1rem;
    background: #080f1d;
    font-size: .67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #64748b;
    text-align: left;
    border-bottom: 1px solid #1e293b;
    white-space: nowrap;
}
.acl-table td {
    padding: .85rem 1rem;
    border-bottom: 1px solid #1e293b;
    vertical-align: middle;
}
.acl-table tbody tr:last-child td { border-bottom: none; }
.acl-table tbody tr:hover { background: rgba(99,102,241,.04); }

.acl-title-link { font-weight: 700; color: #818cf8; text-decoration: none; display: block; font-size: .88rem; transition: color .15s; }
.acl-title-link:hover { color: #a5b4fc; }

.acl-type-tag {
    display: inline-block;
    padding: .15rem .55rem;
    border-radius: 99px;
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
    background: rgba(99,102,241,.12);
    color: #818cf8;
    border: 1px solid rgba(99,102,241,.25);
}

.acl-version-tag {
    display: inline-block;
    padding: .1rem .45rem;
    border-radius: .4rem;
    font-size: .65rem;
    font-weight: 800;
    background: rgba(100,116,139,.15);
    color: #94a3b8;
    border: 1px solid rgba(100,116,139,.3);
}

/* ── Workflow Badges ── */
.acl-badge {
    display: inline-block;
    padding: .2rem .65rem;
    border-radius: 99px;
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
    border: 1px solid;
    white-space: nowrap;
}
.acl-badge--draft     { background: rgba(100,116,139,.12); color: #94a3b8; border-color: rgba(100,116,139,.3); }
.acl-badge--pending   { background: rgba(251,191,36,.12); color: #fbbf24; border-color: rgba(251,191,36,.3); }
.acl-badge--approved  { background: rgba(99,102,241,.12); color: #818cf8; border-color: rgba(99,102,241,.3); }
.acl-badge--published { background: rgba(52,211,153,.12); color: #34d399; border-color: rgba(52,211,153,.3); }
.acl-badge--rejected  { background: rgba(251,113,133,.12); color: #fb7185; border-color: rgba(251,113,133,.3); }
.acl-badge--archived  { background: rgba(148,163,184,.12); color: #cbd5e1; border-color: rgba(148,163,184,.3); }

/* ── Action buttons ── */
.acl-act { font-size: .72rem; font-weight: 700; text-decoration: none; border: none; background: none; cursor: pointer; padding: .2rem .55rem; border-radius: .35rem; transition: background .15s; white-space: nowrap; }
.acl-act--author  { color: #818cf8; }
.acl-act--author:hover  { background: rgba(99,102,241,.12); }
.acl-act--view    { color: #38bdf8; }
.acl-act--view:hover    { background: rgba(56,189,248,.12); }
.acl-act--duplicate { color: #f59e0b; }
.acl-act--duplicate:hover { background: rgba(245,158,11,.12); }
.acl-act--submit  { color: #fbbf24; }
.acl-act--submit:hover  { background: rgba(251,191,36,.1); }
.acl-act--publish { color: #34d399; }
.acl-act--publish:hover { background: rgba(52,211,153,.1); }
.acl-act--delete  { color: #fb7185; }
.acl-act--delete:hover  { background: rgba(251,113,133,.1); }

/* ── Modal ── */
.acl-modal-bg {
    position: fixed; inset: 0;
    background: rgba(2,6,23,.75);
    backdrop-filter: blur(6px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.acl-modal {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    max-width: 520px;
    max-height: 80vh;
    overflow-y: auto;
    width: 100%;
    padding: 2rem;
    box-shadow: 0 24px 64px rgba(0,0,0,.6);
}
.acl-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.acl-modal__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; }
.acl-modal__close { color: #475569; font-size: 1.35rem; cursor: pointer; background: none; border: none; line-height: 1; transition: color .15s; }
.acl-modal__close:hover { color: #e2e8f0; }

.acl-form-group { margin-bottom: 1.1rem; }
.acl-form-label { display: block; font-size: .78rem; font-weight: 600; color: #94a3b8; margin-bottom: .4rem; }
.acl-form-input, .acl-form-select, .acl-form-textarea {
    width: 100%; background: #1e293b; border: 1px solid #334155;
    border-radius: .6rem; color: #e2e8f0; font-size: .85rem;
    padding: .6rem .9rem; outline: none; transition: border-color .15s; box-sizing: border-box;
}
.acl-form-input:focus, .acl-form-select:focus, .acl-form-textarea:focus { border-color: #6366f1; }
.acl-form-submit {
    width: 100%; padding: .75rem; background: #6366f1; color: #fff;
    font-size: .88rem; font-weight: 700; border: none; border-radius: .65rem;
    cursor: pointer; transition: background .15s; margin-top: .5rem;
}
.acl-form-submit:hover { background: #4f46e5; }
</style>
@endpush

@section('content')
<div class="acl-workspace">

    {{-- Status Flash Alert --}}
    @if(session('status'))
    <div style="padding:.85rem 1.1rem;border-radius:.75rem;background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.2);color:#34d399;font-size:.82rem;font-weight:600;">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(Auth::user()?->hasRole('teacher'))
    <div style="display:flex;gap:1.25rem;align-items:center;margin-bottom:1rem;">
        <a href="{{ route('teacher.dashboard') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Teacher Dashboard
        </a>
        <a href="{{ route('teacher.dashboard') }}" style="color:#cbd5e1;font-size:.82rem;font-weight:700;text-decoration:none;">
            🏠 Dashboard
        </a>
    </div>
    @endif

    {{-- Hero Header --}}
    <div class="acl-hero">
        <div>
            <h1 class="acl-hero__title">✏️ Question Bank Authoring Workspace</h1>
            <p class="acl-hero__sub">Author, edit, duplicate, manage questions, and submit repositories for institutional approval.</p>
        </div>
        @if(Auth::user()?->hasRole('teacher'))
        <div>
            <button type="button" onclick="openCreateQbModal()" class="acl-btn acl-btn--primary" style="font-size:.88rem;padding:.65rem 1.35rem;">
                ＋ New Question Bank
            </button>
        </div>
        @endif
    </div>

    {{-- Main Question Banks Table Panel (PART 5) --}}
    <div class="acl-panel">
        <div class="acl-panel__head">
            <div>
                <span class="acl-panel__title">📂 Institutional Question Bank Repository</span>
                <p style="font-size:.75rem;color:#64748b;margin:.2rem 0 0;">
                    Official institutional repositories approved by Super Admin. These repositories belong to iC.edu and serve as reusable academic assets for assessments. Teachers may contribute through the institutional workflow.
                </p>
            </div>
            <span style="font-size:.75rem;color:#475569;">{{ $banks->total() }} total</span>
        </div>

        {{-- Search, Status & Sort Bar --}}
        <div class="acl-filter-bar">
            <form method="GET" action="{{ route('admin.question-banks.index') }}" style="display:flex;gap:.65rem;flex:1;flex-wrap:wrap;align-items:center;">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search question bank title, keyword, or description…" class="acl-search">

                <select name="sort" onchange="this.form.submit()" class="acl-select">
                    <option value="updated" {{ request('sort') === 'updated' ? 'selected' : '' }}>Sort: Last Updated</option>
                    <option value="created" {{ request('sort') === 'created' ? 'selected' : '' }}>Sort: Recently Created</option>
                    <option value="questions" {{ request('sort') === 'questions' ? 'selected' : '' }}>Sort: Question Count</option>
                    <option value="alphabetical" {{ request('sort') === 'alphabetical' ? 'selected' : '' }}>Sort: Alphabetical</option>
                </select>

                <div class="acl-filter-pills">
                    <a href="{{ route('admin.question-banks.index') }}"
                       class="acl-fpill {{ !request('status') ? 'acl-fpill--active' : '' }}">All</a>
                    <a href="{{ route('admin.question-banks.index', ['status' => 'draft']) }}"
                       class="acl-fpill {{ request('status') === 'draft' ? 'acl-fpill--active' : '' }}">Draft</a>
                    <a href="{{ route('admin.question-banks.index', ['status' => 'pending_approval']) }}"
                       class="acl-fpill {{ request('status') === 'pending_approval' ? 'acl-fpill--active' : '' }}">Pending</a>
                    <a href="{{ route('admin.question-banks.index', ['status' => 'rejected']) }}"
                       class="acl-fpill {{ request('status') === 'rejected' ? 'acl-fpill--active' : '' }}">Needs Revision</a>
                    <a href="{{ route('admin.question-banks.index', ['status' => 'approved']) }}"
                       class="acl-fpill {{ request('status') === 'approved' ? 'acl-fpill--active' : '' }}">Approved</a>
                    <a href="{{ route('admin.question-banks.index', ['status' => 'published']) }}"
                       class="acl-fpill {{ request('status') === 'published' ? 'acl-fpill--active' : '' }}">Published</a>
                </div>

                <button type="submit" style="padding:.4rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">Search</button>
            </form>
        </div>

        {{-- Table Listing --}}
        @if($banks->isEmpty())
        <div style="padding:3.5rem 1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.65rem;">
            <div style="font-size:2.75rem;opacity:.45;">📂</div>
            <div style="font-size:1rem;font-weight:700;color:#475569;">You have not created any Question Banks yet.</div>
            <div style="font-size:.82rem;color:#334155;max-width:360px;">Start by creating your first academic question bank.</div>
            @if(Auth::user()?->hasRole('teacher'))
            <button type="button" onclick="openCreateQbModal()" class="acl-btn acl-btn--primary" style="margin-top:.5rem;">
                ＋ New Question Bank
            </button>
            @endif
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="acl-table">
                <thead>
                    <tr>
                        <th>Question Bank Name</th>
                        <th>Type</th>
                        <th style="text-align:center;">Question Count</th>
                        <th>Version</th>
                        <th>Last Modified</th>
                        <th>Workflow Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($banks as $bank)
                    @php
                        $status = $bank->status ?? 'draft';
                        $badgeClass = match($status) {
                            'published' => 'acl-badge--published',
                            'approved'  => 'acl-badge--approved',
                            'pending_approval', 'submitted', 'pending_archive_approval', 'pending_restore_approval' => 'acl-badge--pending',
                            'rejected', 'revision_requested' => 'acl-badge--rejected',
                            'archived'  => 'acl-badge--archived',
                            default     => 'acl-badge--draft',
                        };
                        $statusLabel = match($status) {
                            'published' => 'Published',
                            'approved'  => 'Approved',
                            'pending_approval', 'submitted' => 'Pending Approval',
                            'pending_archive_approval' => 'Pending Archive',
                            'pending_restore_approval' => 'Pending Restore',
                            'rejected', 'revision_requested' => 'Needs Revision',
                            'archived'  => 'Archived',
                            default     => 'Draft',
                        };
                    @endphp
                    <tr>
                        {{-- Question Bank Name --}}
                        <td>
                            <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="acl-title-link">
                                {{ $bank->title }}
                            </a>
                            <div style="font-size:.73rem;color:#475569;margin-top:.15rem;">
                                {{ Str::limit($bank->description ?? 'No description provided', 60) }}
                            </div>
                        </td>

                        {{-- Type --}}
                        <td>
                            <span class="acl-type-tag">
                                {{ is_object($bank->test_type) ? $bank->test_type->label() : strtoupper($bank->test_type?->value ?? $bank->test_type ?? 'General') }}
                            </span>
                        </td>

                        {{-- Question Count --}}
                        <td style="text-align:center;font-weight:700;color:#818cf8;font-size:.85rem;">
                            {{ $bank->questions->count() }} Questions
                        </td>

                        {{-- Version --}}
                        <td>
                            <span class="acl-version-tag">v{{ $bank->current_version ?? '1.0' }}</span>
                        </td>

                        {{-- Last Modified --}}
                        <td style="color:#475569;font-size:.75rem;">
                            {{ $bank->updated_at?->diffForHumans() }}
                        </td>

                        {{-- Workflow Status --}}
                        <td>
                            <span class="acl-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </td>

                        {{-- Actions --}}
                        <td style="text-align:right;">
                            <div style="display:flex;gap:.25rem;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                                @php
                                    $isEditableState = in_array($status, ['draft', 'rejected', 'revision_requested', 'needs_revision', null], true);
                                    $isDuplicableState = in_array($status, ['draft', 'rejected', 'revision_requested', 'needs_revision', 'published', null], true);
                                @endphp

                                {{-- Author / View Action --}}
                                @if(Auth::user()?->hasRole('teacher'))
                                    @if($isEditableState)
                                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="acl-act acl-act--author">
                                        ✏️ Author
                                    </a>
                                    @else
                                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="acl-act acl-act--view">
                                        👁 View
                                    </a>
                                    @endif
                                @else
                                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="acl-act acl-act--view">
                                        👁 View
                                    </a>
                                @endif

                                {{-- Duplicate Repository Action --}}
                                @if($isDuplicableState)
                                <form method="POST" action="{{ route('admin.question-banks.duplicate', $bank->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="acl-act acl-act--duplicate" title="Duplicate repository into a new draft">⧉ Dupe</button>
                                </form>
                                @endif

                                {{-- Request Restore Action for Archived repositories (Owner Teacher or Super Admin ONLY) --}}
                                @if($status === 'archived' && (Auth::user()?->hasRole('super-admin') || (Auth::user()?->hasRole('teacher') && $bank->created_by === Auth::user()->id)))
                                <form method="POST" action="{{ route('admin.question-banks.request-restore', $bank->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="button" id="restore-trigger-btn-idx-{{ $bank->id }}" onclick="document.getElementById('inline-restore-panel-idx-{{ $bank->id }}').classList.remove('hidden'); this.classList.add('hidden');" class="acl-act acl-act--restore" style="color:#818cf8;border:1px solid rgba(129,140,248,.3);" title="Request restoration for Super Admin approval">↻ Request Restore</button>

                                    <div id="inline-restore-panel-idx-{{ $bank->id }}" class="hidden inline-flex items-center gap-2 p-1.5 bg-slate-900 border border-indigo-500/50 rounded-xl shadow-lg">
                                        <span class="text-xs text-slate-200 font-medium">
                                            Request restoration of '{{ $bank->title }}' for Super Admin approval?
                                        </span>
                                        <input type="text" name="reason" placeholder="Restoration reason..." class="px-2 py-0.5 bg-slate-950 border border-slate-700 text-xs text-white rounded-lg focus:outline-none focus:border-indigo-500" style="width:160px;">
                                        <button type="button" onclick="document.getElementById('inline-restore-panel-idx-{{ $bank->id }}').classList.add('hidden'); document.getElementById('restore-trigger-btn-idx-{{ $bank->id }}').classList.remove('hidden');" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit" class="px-2.5 py-0.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-colors">
                                            Request Restore
                                        </button>
                                    </div>
                                </form>
                                @endif

                                {{-- Super Admin Approve / Reject Restore Actions --}}
                                @if($status === 'pending_restore_approval' && Auth::user()?->hasRole('super-admin'))
                                <form method="POST" action="{{ route('admin.question-banks.approve-restore', $bank->id) }}" style="display:inline;"
                                      onsubmit="event.preventDefault(); iapConfirm({ title: 'Approve Restoration Request?', message: 'Approve restoration of Question Bank \'{{ addslashes($bank->title) }}\' to active Approved status?', confirmText: 'Approve Restoration', variant: 'success', form: this });">
                                    @csrf
                                    <button type="submit" class="acl-act acl-act--approve" style="color:#34d399;border:1px solid rgba(52,211,153,.3);" title="Approve restoration request">✓ Approve Restore</button>
                                </form>
                                <form method="POST" action="{{ route('admin.question-banks.reject-restore', $bank->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="button" id="reject-restore-trigger-btn-idx-{{ $bank->id }}" onclick="document.getElementById('inline-reject-restore-panel-idx-{{ $bank->id }}').classList.remove('hidden'); this.classList.add('hidden');" class="acl-act acl-act--reject" style="color:#fb7185;border:1px solid rgba(251,113,133,.3);" title="Reject restoration request">✗ Reject Restore</button>

                                    <div id="inline-reject-restore-panel-idx-{{ $bank->id }}" class="hidden inline-flex items-center gap-2 p-1.5 bg-slate-900 border border-rose-500/50 rounded-xl shadow-lg">
                                        <span class="text-xs text-slate-200 font-medium">Reject restoration of '{{ $bank->title }}'?</span>
                                        <input type="text" name="reason" placeholder="Rejection reason..." class="px-2 py-0.5 bg-slate-950 border border-slate-700 text-xs text-white rounded-lg focus:outline-none focus:border-rose-500" style="width:160px;" required>
                                        <button type="button" onclick="document.getElementById('inline-reject-restore-panel-idx-{{ $bank->id }}').classList.add('hidden'); document.getElementById('reject-restore-trigger-btn-idx-{{ $bank->id }}').classList.remove('hidden');" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">Cancel</button>
                                        <button type="submit" class="px-2.5 py-0.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-lg shadow transition-colors">Reject Restore</button>
                                    </div>
                                </form>
                                @endif

                                {{-- Submit for Approval --}}
                                @if(Auth::user()?->hasRole('teacher') && $isEditableState)
                                <form method="POST" action="{{ route('admin.question-banks.submit', $bank->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="button" id="submit-trigger-btn-idx-{{ $bank->id }}" onclick="document.getElementById('inline-submit-panel-idx-{{ $bank->id }}').classList.remove('hidden'); this.classList.add('hidden');" class="acl-act acl-act--submit">📤 Submit</button>

                                    <div id="inline-submit-panel-idx-{{ $bank->id }}" class="hidden inline-flex items-center gap-2 p-1.5 bg-slate-900 border border-amber-500/50 rounded-xl shadow-lg">
                                        <span class="text-xs text-slate-200 font-medium">
                                            Submit '{{ $bank->title }}' for Super Admin approval?
                                        </span>
                                        <button type="button" onclick="document.getElementById('inline-submit-panel-idx-{{ $bank->id }}').classList.add('hidden'); document.getElementById('submit-trigger-btn-idx-{{ $bank->id }}').classList.remove('hidden');" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit" class="px-2.5 py-0.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-colors">
                                            Submit for Approval
                                        </button>
                                    </div>
                                </form>
                                @endif

                                {{-- Admin Publish / Unpublish --}}
                                @if(Auth::user()?->hasRole('admin') && !Auth::user()?->hasRole('super-admin'))
                                    @if($status === 'approved')
                                    <form method="POST" action="{{ route('admin.question-banks.publish', $bank->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="acl-act acl-act--publish">🟢 Publish</button>
                                    </form>
                                    @endif
                                @endif

                                {{-- Request Deletion — Non-teachers only --}}
                                @if(!Auth::user()?->hasRole('teacher'))
                                <button type="button" onclick="openRequestDeletionModal('{{ route('admin.question-banks.destroy', $bank->id) }}', '{{ addslashes($bank->title) }}')" class="acl-act acl-act--delete">🗑 Request Deletion</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($banks->hasPages())
        <div style="padding:.85rem 1.25rem;border-top:1px solid #1e293b;">
            {{ $banks->links() }}
        </div>
        @endif
        @endif
    </div>

</div>

{{-- New Question Bank Modal --}}
@if(Auth::user()?->hasRole('teacher'))
<div id="create-qb-modal" class="acl-modal-bg" style="display:none;" onclick="closeCreateQbModal(event)">
    <div class="acl-modal" onclick="event.stopPropagation()">
        <div class="acl-modal__head">
            <div class="acl-modal__title">📂 New Academic Question Bank</div>
            <button type="button" class="acl-modal__close" onclick="closeCreateQbModal()">×</button>
        </div>
        <form method="POST" action="{{ route('admin.question-banks.store') }}">
            @csrf
            <div class="acl-form-group">
                <label class="acl-form-label" for="modal-qb-title">Bank Title <span style="color:#fb7185;">*</span></label>
                <input type="text"
                       id="modal-qb-title"
                       name="title"
                       required
                       class="acl-form-input"
                       placeholder="e.g. TOEFL Listening Lecture Pool 01">
            </div>
            <div class="acl-form-group">
                <label class="acl-form-label" for="modal-acl-cat">ACL Taxonomy Category</label>
                <select id="modal-acl-cat" name="acl_category_id" class="acl-form-select">
                    @foreach($aclCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->icon }} {{ $cat->name }} ({{ strtoupper($cat->test_type) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="acl-form-group">
                <label class="acl-form-label" for="modal-qb-type">Test Type</label>
                <select id="modal-qb-type" name="test_type" class="acl-form-select">
                    <option value="toeic">TOEIC</option>
                    <option value="toefl">TOEFL iBT</option>
                    <option value="ielts">IELTS</option>
                    <option value="general">General Assessment</option>
                </select>
            </div>
            <div class="acl-form-group">
                <label class="acl-form-label" for="modal-qb-desc">Description</label>
                <textarea id="modal-qb-desc" name="description" rows="3" class="acl-form-textarea" placeholder="Institutional details, authoring scope, or passage reference…"></textarea>
            </div>
            <button type="submit" class="acl-form-submit">Create Question Bank</button>
        </form>
    </div>
</div>
@endif

{{-- No Pending Approval Modal --}}
<div id="no-pending-modal" class="acl-modal-bg" style="display:none;" onclick="closeNoPendingModal(event)">
    <div class="acl-modal" onclick="event.stopPropagation()" style="text-align:center;max-width:440px;">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">🎉</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">No Question Banks Awaiting Approval</div>
        <p style="font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.5;">Everything you submitted has already been reviewed.</p>
        <button type="button" onclick="closeNoPendingModal()" class="acl-form-submit" style="margin-top:0;">Close</button>
    </div>
</div>

{{-- Coverage Empty State Modal (PART 4) --}}
<div id="cov-empty-modal" class="acl-modal-bg" style="display:none;" onclick="closeCovEmptyModal(event)">
    <div class="acl-modal" onclick="event.stopPropagation()" style="text-align:center;max-width:460px;">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">📭</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">No Question Banks Found</div>
        <p style="font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.5;">
            There are no published question banks inside this academic library (<strong id="cov-empty-category-name" style="color:#818cf8;">Category</strong>) yet.
        </p>
        <div style="display:flex;gap:.75rem;">
            <button type="button" onclick="closeCovEmptyModal()" class="acl-btn acl-btn--secondary" style="flex:1;padding:.75rem;">Close</button>
            <button type="button" onclick="createFromCovEmptyModal()" class="acl-btn acl-btn--primary" style="flex:1.5;padding:.75rem;">＋ Create Question Bank</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let pendingCatIdForCreate = null;

function openCreateQbModal() {
    const modal = document.getElementById('create-qb-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('modal-qb-title')?.focus();
    }
}
function closeCreateQbModal(e) {
    if (!e || e.target === document.getElementById('create-qb-modal')) {
        const modal = document.getElementById('create-qb-modal');
        if (modal) modal.style.display = 'none';
    }
}

function openNoPendingModal() {
    const modal = document.getElementById('no-pending-modal');
    if (modal) modal.style.display = 'flex';
}
function closeNoPendingModal(e) {
    if (!e || e.target === document.getElementById('no-pending-modal')) {
        const modal = document.getElementById('no-pending-modal');
        if (modal) modal.style.display = 'none';
    }
}

function openCovEmptyModal(slug, name, catId) {
    pendingCatIdForCreate = catId;
    const nameSpan = document.getElementById('cov-empty-category-name');
    if (nameSpan) nameSpan.innerText = name;
    const modal = document.getElementById('cov-empty-modal');
    if (modal) modal.style.display = 'flex';
}
function closeCovEmptyModal(e) {
    if (!e || e.target === document.getElementById('cov-empty-modal')) {
        const modal = document.getElementById('cov-empty-modal');
        if (modal) modal.style.display = 'none';
    }
}
function createFromCovEmptyModal() {
    closeCovEmptyModal();
    if (pendingCatIdForCreate) {
        const select = document.getElementById('modal-acl-cat');
        if (select) select.value = pendingCatIdForCreate;
    }
    openCreateQbModal();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateQbModal();
        closeNoPendingModal();
        closeCovEmptyModal();
        closeRequestDeletionModal();
    }
});
@if($errors->any())
openCreateQbModal();
@endif

function openRequestDeletionModal(actionUrl, assetTitle) {
    const modal = document.getElementById('request-deletion-modal');
    const form = document.getElementById('request-deletion-form');
    if (modal && form) {
        form.action = actionUrl;
        modal.classList.remove('hidden');
    }
}
function closeRequestDeletionModal() {
    const modal = document.getElementById('request-deletion-modal');
    if (modal) modal.classList.add('hidden');
}
</script>

<!-- Request Deletion Governance Modal (TASK 3) -->
<div id="request-deletion-modal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-white">🗑 Request Deletion</h3>
            <button type="button" onclick="closeRequestDeletionModal()" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form id="request-deletion-form" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="bg-slate-950/60 border border-slate-800 rounded-lg p-3 mb-4">
                <p class="text-xs text-slate-300 font-semibold mb-1">
                    This action will <strong>NOT</strong> permanently delete this repository.
                </p>
                <p class="text-[11px] text-slate-400">
                    A deletion request will be submitted to Super Admin for approval.
                </p>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Reason (required)</label>
                <textarea name="notes" required rows="3" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none" placeholder="Provide justification for deletion request..."></textarea>
            </div>
            <div class="flex justify-end gap-2 text-xs font-bold">
                <button type="button" onclick="closeRequestDeletionModal()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-lg hover:bg-slate-700">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-500 shadow-lg">Submit Request</button>
            </div>
        </form>
    </div>
</div>
@endpush
