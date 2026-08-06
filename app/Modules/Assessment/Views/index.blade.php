@extends('layouts.admin')

@section('title', 'Test Builder Workspace — iC.edu Platform')

@push('styles')
<style>
/* ──────────────────────────────────────────
   TEACHER TEST BUILDER WORKSPACE — TEACHER-002
────────────────────────────────────────── */
.tb-workspace { display: flex; flex-direction: column; gap: 1.5rem; }

/* ── Hero Header ── */
.tb-hero {
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
.tb-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 90% 50%, rgba(99,102,241,.15) 0%, transparent 60%);
    pointer-events: none;
}
.tb-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.tb-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

/* ── KPI Cards (SECTION 2 & 7) ── */
.tb-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .tb-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.tb-kpi {
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
.tb-kpi:hover { border-color: #6366f1; transform: translateY(-2px); }
.tb-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.tb-kpi__label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
.tb-kpi__desc  { font-size: .7rem; color: #475569; }
.tb-kpi--indigo .tb-kpi__count { color: #818cf8; }
.tb-kpi--amber  .tb-kpi__count { color: #fbbf24; }
.tb-kpi--emerald .tb-kpi__count { color: #34d399; }
.tb-kpi--slate  .tb-kpi__count { color: #94a3b8; }

/* ── Quick Actions Strip (SECTION 8) ── */
.tb-qa-strip {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: .85rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.tb-qa-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #475569; width: 100%; margin-bottom: .25rem; }
.tb-qa-btn {
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
.tb-qa-btn:hover { transform: translateY(-1px); }
.tb-qa-btn--primary   { background: #6366f1; color: #fff; border-color: #6366f1; }
.tb-qa-btn--primary:hover { background: #4f46e5; }
.tb-qa-btn--secondary { background: #1e293b; color: #cbd5e1; border-color: #334155; }
.tb-qa-btn--secondary:hover { background: #334155; color: #fff; }

/* ── Table Panel (SECTION 11) ── */
.tb-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    overflow: hidden;
}
.tb-panel__head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.tb-panel__title { font-size: .9rem; font-weight: 700; color: #f1f5f9; }

/* ── Search & Filter Bar (SECTION 9 & 10) ── */
.tb-filter-bar {
    padding: .75rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    gap: .65rem;
    align-items: center;
    flex-wrap: wrap;
}
.tb-search {
    flex: 1;
    min-width: 180px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: .5rem;
    color: #e2e8f0;
    font-size: .8rem;
    padding: .45rem .85rem;
    outline: none;
    transition: border-color .15s;
}
.tb-search:focus { border-color: #6366f1; }
.tb-filter-pills { display: flex; gap: .4rem; flex-wrap: wrap; }
.tb-fpill {
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
.tb-fpill:hover, .tb-fpill--active { background: #6366f1; color: #fff; border-color: #6366f1; }

/* ── Table Styling (SECTION 11) ── */
.tb-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tb-table th {
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
.tb-table td {
    padding: .85rem 1rem;
    border-bottom: 1px solid #1e293b;
    vertical-align: middle;
}
.tb-table tbody tr:last-child td { border-bottom: none; }
.tb-table tbody tr:hover { background: rgba(99,102,241,.04); }

.tb-title-link { font-weight: 700; color: #f1f5f9; text-decoration: none; display: block; transition: color .15s; }
.tb-title-link:hover { color: #818cf8; }

.tb-type-tag {
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

/* ── Workflow Status Badges (SECTION 5 & 6) ── */
.tb-badge {
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
.tb-badge--draft     { background: rgba(100,116,139,.12); color: #94a3b8; border-color: rgba(100,116,139,.3); }
.tb-badge--pending   { background: rgba(251,191,36,.12); color: #fbbf24; border-color: rgba(251,191,36,.3); }
.tb-badge--approved  { background: rgba(99,102,241,.12); color: #818cf8; border-color: rgba(99,102,241,.3); }
.tb-badge--published { background: rgba(52,211,153,.12); color: #34d399; border-color: rgba(52,211,153,.3); }
.tb-badge--rejected  { background: rgba(251,113,133,.12); color: #fb7185; border-color: rgba(251,113,133,.3); }

/* ── Actions (SECTION 4 & 11) ── */
.tb-act { font-size: .72rem; font-weight: 700; text-decoration: none; border: none; background: none; cursor: pointer; padding: .2rem .55rem; border-radius: .35rem; transition: background .15s; white-space: nowrap; }
.tb-act--author  { color: #818cf8; }
.tb-act--author:hover  { background: rgba(99,102,241,.12); }
.tb-act--submit  { color: #fbbf24; }
.tb-act--submit:hover  { background: rgba(251,191,36,.1); }
.tb-act--publish { color: #34d399; }
.tb-act--publish:hover { background: rgba(52,211,153,.1); }
.tb-act--dupe    { color: #94a3b8; }
.tb-act--dupe:hover    { background: rgba(100,116,139,.12); }
.tb-act--delete  { color: #fb7185; }
.tb-act--delete:hover  { background: rgba(251,113,133,.1); }

/* ── Empty State (SECTION 13) ── */
.tb-empty {
    padding: 3.5rem 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .65rem;
}
.tb-empty__icon  { font-size: 2.75rem; opacity: .45; }
.tb-empty__title { font-size: 1rem; font-weight: 700; color: #475569; }
.tb-empty__sub   { font-size: .82rem; color: #334155; max-width: 360px; line-height: 1.5; }

/* ── Modal (SECTION 3) ── */
.tb-modal-bg {
    position: fixed; inset: 0;
    background: rgba(2,6,23,.75);
    backdrop-filter: blur(6px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.tb-modal {
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
.tb-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.tb-modal__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; }
.tb-modal__close { color: #475569; font-size: 1.35rem; cursor: pointer; background: none; border: none; line-height: 1; transition: color .15s; }
.tb-modal__close:hover { color: #e2e8f0; }

.tb-form-group { margin-bottom: 1.1rem; }
.tb-form-label { display: block; font-size: .78rem; font-weight: 600; color: #94a3b8; margin-bottom: .4rem; }
.tb-form-input, .tb-form-select {
    width: 100%; background: #1e293b; border: 1px solid #334155;
    border-radius: .6rem; color: #e2e8f0; font-size: .85rem;
    padding: .6rem .9rem; outline: none; transition: border-color .15s; box-sizing: border-box;
}
.tb-form-input:focus, .tb-form-select:focus { border-color: #6366f1; }
.tb-form-submit {
    width: 100%; padding: .75rem; background: #6366f1; color: #fff;
    font-size: .88rem; font-weight: 700; border: none; border-radius: .65rem;
    cursor: pointer; transition: background .15s; margin-top: .5rem;
}
.tb-form-submit:hover { background: #4f46e5; }
</style>
@endpush

@section('content')
<div class="tb-workspace">

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

    {{-- SECTION 2: Hero Header & Primary Workspace CTA --}}
    <div class="tb-hero">
        <div>
            <h1 class="tb-hero__title">📋 Assessment Test Builder</h1>
            <p class="tb-hero__sub">Author, structure sections, configure duration, and manage assessment tests.</p>
        </div>
        @if(!Auth::user()?->hasRole('super-admin'))
    {{-- SECTION 2 & 7: Smart KPI Cards (PART 2) --}}
    <div class="tb-kpi-grid">
        @if(($totalTests ?? 0) > 0)
        <a href="{{ route('admin.tests.index') }}" class="tb-kpi tb-kpi--indigo">
            <div class="tb-kpi__count">{{ $totalTests ?? $tests->total() }}</div>
            <div class="tb-kpi__label">My Assessments</div>
            <div class="tb-kpi__desc">Total tests registered</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openTbNothingModal()" class="tb-kpi tb-kpi--indigo">
            <div class="tb-kpi__count">0</div>
            <div class="tb-kpi__label">My Assessments</div>
            <div class="tb-kpi__desc">No tests created yet</div>
        </a>
        @endif

        @if(($draftTests ?? 0) > 0)
        <a href="{{ route('admin.tests.index', ['status' => 'draft']) }}" class="tb-kpi tb-kpi--slate">
            <div class="tb-kpi__count">{{ $draftTests }}</div>
            <div class="tb-kpi__label">Drafts</div>
            <div class="tb-kpi__desc">Work in progress</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openTbNothingModal()" class="tb-kpi tb-kpi--slate">
            <div class="tb-kpi__count">0</div>
            <div class="tb-kpi__label">Drafts</div>
            <div class="tb-kpi__desc">No active drafts</div>
        </a>
        @endif

        @if(($pendingApprovalTests ?? 0) > 0)
        <a href="{{ route('admin.tests.index', ['status' => 'pending_approval']) }}" class="tb-kpi tb-kpi--amber">
            <div class="tb-kpi__count">{{ $pendingApprovalTests }}</div>
            <div class="tb-kpi__label">Pending Approval</div>
            <div class="tb-kpi__desc">Awaiting Super Admin review</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openTbNothingModal()" class="tb-kpi tb-kpi--amber">
            <div class="tb-kpi__count">0</div>
            <div class="tb-kpi__label">Pending Approval</div>
            <div class="tb-kpi__desc">All reviewed</div>
        </a>
        @endif

        @if(($publishedTests ?? 0) > 0)
        <a href="{{ route('admin.tests.index', ['status' => 'published']) }}" class="tb-kpi tb-kpi--emerald">
            <div class="tb-kpi__count">{{ $publishedTests }}</div>
            <div class="tb-kpi__label">Approved &amp; Live</div>
            <div class="tb-kpi__desc">Active for candidates</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openTbNothingModal()" class="tb-kpi tb-kpi--emerald">
            <div class="tb-kpi__count">0</div>
            <div class="tb-kpi__label">Approved &amp; Live</div>
            <div class="tb-kpi__desc">No live assessments</div>
        </a>
        @endif
    </div>
        <div>
            <button type="button" onclick="openCreateTestModal()" class="tb-qa-btn tb-qa-btn--primary" style="font-size:.88rem;padding:.65rem 1.35rem;">
                ＋ New Assessment
            </button>
        </div>
        @endif
    </div>

    {{-- SECTION 8: Quick Actions Strip --}}
    <div class="tb-qa-strip">
        <div class="tb-qa-label">Quick Actions</div>
        @if(!Auth::user()?->hasRole('super-admin'))
        <button type="button" onclick="openCreateTestModal()" class="tb-qa-btn tb-qa-btn--primary">
            ＋ New Assessment
        </button>
        @endif
        @if(isset($latestDraft))
        <a href="{{ route('admin.tests.index', ['search' => $latestDraft->title]) }}" class="tb-qa-btn tb-qa-btn--secondary">
            ✏️ Continue Draft: {{ Str::limit($latestDraft->title, 22) }}
        </a>
        @endif
        <a href="{{ route('admin.question-banks.index') }}" class="tb-qa-btn tb-qa-btn--secondary">
            📂 Question Banks
        </a>
        <a href="{{ route('admin.media.index') }}" class="tb-qa-btn tb-qa-btn--secondary">
            🖼 Media Library
        </a>
    </div>

    {{-- SECTION 11: Main Assessment Table Panel --}}
    <div class="tb-panel">
        <div class="tb-panel__head">
            <span class="tb-panel__title">📋 Assessment Tests</span>
            <span style="font-size:.75rem;color:#475569;">{{ $tests->total() }} total</span>
        </div>

        {{-- SECTION 9 & 10: Search & Filter Bar --}}
        <div class="tb-filter-bar">
            <form method="GET" action="{{ route('admin.tests.index') }}" style="display:flex;gap:.65rem;flex:1;flex-wrap:wrap;align-items:center;">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search assessment name or type…" class="tb-search">
                <div class="tb-filter-pills">
                    <a href="{{ route('admin.tests.index') }}"
                       class="tb-fpill {{ !request('status') ? 'tb-fpill--active' : '' }}">All</a>
                    <a href="{{ route('admin.tests.index', ['status' => 'draft']) }}"
                       class="tb-fpill {{ request('status') === 'draft' ? 'tb-fpill--active' : '' }}">Draft</a>
                    <a href="{{ route('admin.tests.index', ['status' => 'pending_approval']) }}"
                       class="tb-fpill {{ request('status') === 'pending_approval' ? 'tb-fpill--active' : '' }}">Pending</a>
                    <a href="{{ route('admin.tests.index', ['status' => 'rejected']) }}"
                       class="tb-fpill {{ request('status') === 'rejected' ? 'tb-fpill--active' : '' }}">Needs Revision</a>
                    <a href="{{ route('admin.tests.index', ['status' => 'approved']) }}"
                       class="tb-fpill {{ request('status') === 'approved' ? 'tb-fpill--active' : '' }}">Approved</a>
                    <a href="{{ route('admin.tests.index', ['status' => 'published']) }}"
                       class="tb-fpill {{ request('status') === 'published' ? 'tb-fpill--active' : '' }}">Published</a>
                </div>
                <button type="submit" style="padding:.4rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">Search</button>
            </form>
        </div>

        {{-- SECTION 4, 5, 11, 12: Separated Table Columns --}}
        @if($tests->isEmpty())
        {{-- SECTION 13: Empty State --}}
        <div class="tb-empty">
            <div class="tb-empty__icon">📝</div>
            <div class="tb-empty__title">No assessments yet</div>
            <div class="tb-empty__sub">Start by creating your first assessment test for your students.</div>
            @if(!Auth::user()?->hasRole('super-admin'))
            <button type="button" onclick="openCreateTestModal()" class="tb-qa-btn tb-qa-btn--primary" style="margin-top:.5rem;">
                ＋ New Assessment
            </button>
            @endif
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="tb-table">
                <thead>
                    <tr>
                        <th>Assessment Name</th>
                        <th>Type</th>
                        <th style="text-align:center;">Questions</th>
                        <th style="text-align:center;">Sections</th>
                        <th>Duration</th>
                        <th>Pass Score</th>
                        <th>Last Modified</th>
                        <th>Workflow Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tests as $test)
                    @php
                        $qCount = $test->sections->sum(fn($s) => $s->testQuestions->count());
                        $status = $test->status ?? ($test->is_published ? 'published' : 'draft');
                        $badgeClass = match($status) {
                            'published'                                        => 'tb-badge--published',
                            'approved'                                         => 'tb-badge--approved',
                            'pending', 'pending_approval'                        => 'tb-badge--pending',
                            'needs_revision', 'revision_requested', 'rejected' => 'tb-badge--rejected',
                            default                                            => 'tb-badge--draft',
                        };
                        $statusLabel = match($status) {
                            'published'                                        => 'Published',
                            'approved'                                         => 'Approved',
                            'pending', 'pending_approval'                        => 'Pending Approval',
                            'needs_revision', 'revision_requested', 'rejected' => 'Needs Revision',
                            default                                            => 'Draft',
                        };
                    @endphp
                    <tr>
                        {{-- Assessment Name --}}
                        <td>
                            <span class="tb-title-link">{{ $test->title }}</span>
                        </td>

                        {{-- Type --}}
                        <td>
                            <span class="tb-type-tag">{{ is_object($test->test_type) ? $test->test_type->label() : strtoupper($test->test_type ?? 'GENERAL') }}</span>
                        </td>

                        {{-- Questions --}}
                        <td style="text-align:center;font-weight:700;color:#818cf8;">
                            {{ $qCount }}
                        </td>

                        {{-- Sections --}}
                        <td style="text-align:center;font-weight:600;color:#94a3b8;">
                            {{ $test->sections->count() }}
                        </td>

                        {{-- Duration --}}
                        <td style="color:#cbd5e1;font-weight:600;">
                            {{ $test->duration_minutes }} mins
                        </td>

                        {{-- Pass Score --}}
                        <td style="color:#cbd5e1;font-weight:600;">
                            {{ $test->pass_score }} pts
                        </td>

                        {{-- Last Modified (SECTION 12) --}}
                        <td style="color:#475569;font-size:.75rem;">
                            {{ $test->updated_at?->diffForHumans() }}
                        </td>

                        {{-- Workflow Status (SECTION 5 & 6) --}}
                        <td>
                            <span class="tb-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </td>

                        {{-- Actions (TASK 3 Workflow Action Buttons) --}}
                        <td style="text-align:right;">
                            <div style="display:flex;gap:.25rem;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                                @if(in_array($status, ['needs_revision', 'revision_requested']))
                                <a href="{{ route('teacher.tests.show', $test->id) }}" class="tb-act" style="background:#f59e0b;color:#fff;text-decoration:none;font-weight:700;padding:.25rem .6rem;border-radius:.35rem;font-size:.75rem;">✏️ Continue Revision</a>
                                @elseif(in_array($status, ['pending', 'pending_approval']))
                                <a href="{{ route('teacher.tests.show', $test->id) }}" class="tb-act" style="background:#334155;color:#e2e8f0;text-decoration:none;font-weight:700;padding:.25rem .6rem;border-radius:.35rem;font-size:.75rem;">👁 View</a>
                                @elseif($status === 'draft')
                                <a href="{{ route('teacher.tests.show', $test->id) }}" class="tb-act" style="background:#4338ca;color:#fff;text-decoration:none;font-weight:700;padding:.25rem .6rem;border-radius:.35rem;font-size:.75rem;">✏️ Edit</a>
                                @else
                                <a href="{{ route('teacher.tests.show', $test->id) }}" class="tb-act" style="background:#1e293b;color:#94a3b8;text-decoration:none;font-weight:700;padding:.25rem .6rem;border-radius:.35rem;font-size:.75rem;">👁 Preview</a>
                                @endif

                                {{-- Duplicate --}}
                                @if(!Auth::user()?->hasRole('super-admin'))
                                <form method="POST" action="{{ route('admin.tests.duplicate', $test->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="tb-act tb-act--dupe">⧉ Dupe</button>
                                </form>
                                @endif

                                {{-- Request Deletion — Non-teachers or non-published --}}
                                @if(!Auth::user()?->hasRole('teacher') && (!$test->is_published || Auth::user()?->hasRole('super-admin')))
                                <button type="button" onclick="openRequestDeletionModal('{{ route('admin.tests.destroy', $test->id) }}', '{{ addslashes($test->title) }}')" class="tb-act tb-act--delete">🗑 Request Deletion</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($tests->hasPages())
        <div style="padding:.85rem 1.25rem;border-top:1px solid #1e293b;">
            {{ $tests->links() }}
        </div>
        @endif
        @endif
    </div>

</div>

{{-- SECTION 3: New Assessment Modal --}}
@if(!Auth::user()?->hasRole('super-admin'))
<div id="create-test-modal" class="tb-modal-bg" style="display:none;" onclick="closeCreateTestModal(event)">
    <div class="tb-modal" onclick="event.stopPropagation()">
        <div class="tb-modal__head">
            <div class="tb-modal__title">📋 New Assessment Test</div>
            <button type="button" class="tb-modal__close" onclick="closeCreateTestModal()">×</button>
        </div>
        <form method="POST" action="{{ route('admin.tests.store') }}">
            @csrf
            <div class="tb-form-group">
                <label class="tb-form-label" for="modal-test-title">Test Title <span style="color:#fb7185;">*</span></label>
                <input type="text"
                       id="modal-test-title"
                       name="title"
                       required
                       class="tb-form-input"
                       placeholder="e.g. TOEIC Listening &amp; Reading Simulation 01">
            </div>
            <div class="tb-form-group">
                <label class="tb-form-label" for="modal-test-type">Test Type</label>
                <select id="modal-test-type" name="test_type" class="tb-form-select">
                    <option value="toeic">TOEIC</option>
                    <option value="toefl">TOEFL iBT</option>
                    <option value="ielts">IELTS</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                <div class="tb-form-group">
                    <label class="tb-form-label" for="modal-duration">Duration (Minutes)</label>
                    <input type="number" id="modal-duration" name="duration_minutes" value="120" min="1" required class="tb-form-input">
                </div>
                <div class="tb-form-group">
                    <label class="tb-form-label" for="modal-pass-score">Pass Threshold (Score)</label>
                    <input type="number" id="modal-pass-score" name="pass_score" value="700" min="0" required class="tb-form-input">
                </div>
            </div>
            <div style="padding-top:.5rem;border-top:1px solid #1e293b;display:flex;flex-direction:column;gap:.4rem;margin-bottom:1rem;">
                <label style="font-size:.78rem;color:#cbd5e1;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                    <input type="checkbox" name="shuffle_questions" value="1"> Shuffle Question Order
                </label>
                <label style="font-size:.78rem;color:#cbd5e1;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                    <input type="checkbox" name="shuffle_choices" value="1"> Shuffle Multiple Choice Options
                </label>
            </div>
            <button type="submit" class="tb-form-submit">Create Draft Test</button>
        </form>
    </div>
</div>
@endif

{{-- Floating Modal for Zero Results (PART 2) --}}
<div id="tb-nothing-modal" class="tb-modal-bg" style="display:none;" onclick="closeTbNothingModal(event)">
    <div class="tb-modal" onclick="event.stopPropagation()" style="text-align:center;max-width:440px;">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">📭</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">Nothing Here Yet</div>
        <p style="font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.5;">There are currently no items in this category.</p>
        <button type="button" onclick="closeTbNothingModal()" class="tb-form-submit" style="margin-top:0;">Close</button>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openCreateTestModal() {
    const modal = document.getElementById('create-test-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('modal-test-title')?.focus();
    }
}
function closeCreateTestModal(e) {
    if (!e || e.target === document.getElementById('create-test-modal')) {
        const modal = document.getElementById('create-test-modal');
        if (modal) modal.style.display = 'none';
    }
}

function openTbNothingModal() {
    const modal = document.getElementById('tb-nothing-modal');
    if (modal) modal.style.display = 'flex';
}
function closeTbNothingModal(e) {
    if (!e || e.target === document.getElementById('tb-nothing-modal')) {
        const modal = document.getElementById('tb-nothing-modal');
        if (modal) modal.style.display = 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateTestModal();
        closeTbNothingModal();
        closeRequestDeletionModal();
    }
});
@if($errors->any())
openCreateTestModal();
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
                    This action will <strong>NOT</strong> permanently delete this assessment test.
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
