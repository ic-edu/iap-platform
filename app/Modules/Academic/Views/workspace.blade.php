@extends('layouts.admin')

@section('title', $course->title . ' — Academic Workspace')

@push('styles')
<style>
/* ──────────────────────────────────────────
   DEDICATED ACADEMIC WORKSPACE PAGE
────────────────────────────────────────── */
.ws-page { display: flex; flex-direction: column; gap: 1.5rem; }

/* ── Hero Header ── */
.ws-hero {
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
.ws-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 90% 50%, rgba(99,102,241,.15) 0%, transparent 60%);
    pointer-events: none;
}
.ws-hero__code { font-family: monospace; font-weight: 800; font-size: .8rem; color: #818cf8; background: rgba(99,102,241,.15); padding: .25rem .65rem; border-radius: .4rem; border: 1px solid rgba(99,102,241,.3); }
.ws-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: .4rem 0 .2rem; }
.ws-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

/* ── Action Strip ── */
.ws-actions-strip {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: .85rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .65rem;
    flex-wrap: wrap;
}
.ws-act-btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .5rem .95rem;
    border-radius: .6rem;
    font-size: .8rem;
    font-weight: 700;
    border: 1px solid;
    text-decoration: none;
    transition: background .15s, transform .12s;
    cursor: pointer;
    white-space: nowrap;
}
.ws-act-btn:hover { transform: translateY(-1px); }
.ws-act-btn--primary   { background: #6366f1; color: #fff; border-color: #6366f1; }
.ws-act-btn--primary:hover { background: #4f46e5; }
.ws-act-btn--secondary { background: #1e293b; color: #cbd5e1; border-color: #334155; }
.ws-act-btn--secondary:hover { background: #334155; color: #fff; }

/* ── Dashboard KPI Counters ── */
.ws-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .ws-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.ws-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.15rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
}
.ws-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.ws-kpi__label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
.ws-kpi__desc  { font-size: .7rem; color: #475569; }
.ws-kpi--indigo  .ws-kpi__count { color: #818cf8; }
.ws-kpi--amber   .ws-kpi__count { color: #fbbf24; }
.ws-kpi--emerald .ws-kpi__count { color: #34d399; }
.ws-kpi--slate   .ws-kpi__count { color: #94a3b8; }

/* ── Two Column Layout (Question Libraries & Health Score) ── */
.ws-two-col {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.25rem;
}
@media (max-width: 1100px) { .ws-two-col { grid-template-columns: 1fr; } }

/* ── Question Libraries Section ── */
.ws-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem;
}
.ws-panel__title { font-size: 1rem; font-weight: 800; color: #f1f5f9; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; }

.ws-lib-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
}

.ws-lib-card {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.15rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}
.ws-lib-card__head { display: flex; justify-content: space-between; align-items: center; }
.ws-lib-card__title { font-size: .9rem; font-weight: 800; color: #f1f5f9; }
.ws-lib-card__pct   { font-size: .85rem; font-weight: 900; color: #818cf8; }

.ws-bar-bg { width: 100%; height: 7px; background: #1e293b; border-radius: 99px; overflow: hidden; }
.ws-bar-fill { height: 100%; background: linear-gradient(90deg, #6366f1 0%, #34d399 100%); border-radius: 99px; }

/* ── Content Health Score Widget ── */
.ws-side-panel { display: flex; flex-direction: column; gap: 1.25rem; }

.ws-health-card {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.ws-health-head { display: flex; justify-content: space-between; align-items: center; }
.ws-health-label { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #818cf8; }
.ws-health-badge { padding: .2rem .65rem; border-radius: 99px; font-size: .7rem; font-weight: 900; background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }

.ws-health-score { font-size: 2.75rem; font-weight: 900; color: #f1f5f9; line-height: 1; }

.ws-progress-list { display: flex; flex-direction: column; gap: .75rem; }
.ws-prog-item { display: flex; flex-direction: column; gap: .25rem; }
.ws-prog-meta { display: flex; justify-content: space-between; font-size: .75rem; font-weight: 700; color: #cbd5e1; }

/* ── Recent Activity Widget ── */
.ws-activity-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
}
.ws-act-list { display: flex; flex-direction: column; gap: .85rem; margin-top: .85rem; }
.ws-act-item { display: flex; align-items: flex-start; gap: .65rem; font-size: .78rem; border-bottom: 1px solid #1e293b; padding-bottom: .65rem; }
.ws-act-item:last-child { border-bottom: none; padding-bottom: 0; }
.ws-act-text { font-weight: 600; color: #e2e8f0; }
.ws-act-time { font-size: .68rem; color: #64748b; margin-top: 2px; }
</style>
@endpush

@section('content')
<div class="ws-page">

    {{-- Hero Header --}}
    <div class="ws-hero">
        <div>
            <span class="ws-hero__code">{{ $course->code }}</span>
            <h1 class="ws-hero__title">{{ $course->title }}</h1>
            <p class="ws-hero__sub">Assigned by Academic Administration · Active Institutional Curriculum Program</p>
        </div>
        <a href="{{ route('admin.academic.index') }}" class="ws-act-btn ws-act-btn--secondary">
            ← Back to All Workspace Cards
        </a>
    </div>

    {{-- Action Buttons Strip (No Delete Button) --}}
    <div class="ws-actions-strip">
        <a href="{{ route('admin.question-banks.index') }}" class="ws-act-btn ws-act-btn--primary">
            ＋ New Question
        </a>
        <a href="{{ route('admin.question-banks.index') }}" class="ws-act-btn ws-act-btn--secondary">
            📥 Import Question
        </a>
        <a href="{{ route('admin.question-banks.index') }}" class="ws-act-btn ws-act-btn--secondary">
            📂 Open Library
        </a>
        <a href="{{ route('admin.media.index') }}" class="ws-act-btn ws-act-btn--secondary">
            🖼 Media
        </a>
        <a href="{{ route('admin.tests.index') }}" class="ws-act-btn ws-act-btn--secondary">
            📋 Blueprint
        </a>
        <a href="{{ route('admin.question-banks.index') }}" class="ws-act-btn ws-act-btn--secondary">
            👁 Preview
        </a>
        <a href="{{ route('admin.question-banks.index') }}" class="ws-act-btn ws-act-btn--secondary" style="color:#fbbf24;border-color:rgba(251,191,36,.3);">
            📤 Submit Revision
        </a>
    </div>

    {{-- Dashboard KPI Counters --}}
    <div class="ws-kpi-grid">
        <div class="ws-kpi ws-kpi--indigo">
            <div class="ws-kpi__count">{{ $totalQuestions }}</div>
            <div class="ws-kpi__label">Total Questions</div>
            <div class="ws-kpi__desc">Items in program repository</div>
        </div>
        <div class="ws-kpi ws-kpi--slate">
            <div class="ws-kpi__count">{{ $draftQuestions }}</div>
            <div class="ws-kpi__label">Draft Content</div>
            <div class="ws-kpi__desc">Work in progress</div>
        </div>
        <div class="ws-kpi ws-kpi--amber">
            <div class="ws-kpi__count">{{ $pendingApprovalCount }}</div>
            <div class="ws-kpi__label">Pending Approval</div>
            <div class="ws-kpi__desc">Awaiting Super Admin review</div>
        </div>
        <div class="ws-kpi ws-kpi--emerald">
            <div class="ws-kpi__count">{{ $approvedQuestions }}</div>
            <div class="ws-kpi__label">Approved &amp; Live</div>
            <div class="ws-kpi__desc">Active for tests</div>
        </div>
    </div>

    {{-- Two Column Layout: Categorized Question Libraries & Health Score --}}
    <div class="ws-two-col">

        {{-- Left: Categorized Question Libraries --}}
        <div class="ws-panel">
            <div class="ws-panel__title">
                <span>📚 Question Libraries (Categorized)</span>
                <span style="font-size:.75rem;color:#475569;font-weight:600;">{{ count($librarySections) }} Categories</span>
            </div>

            <div class="ws-lib-grid">
                @foreach($librarySections as $lib)
                <div class="ws-lib-card">
                    <div class="ws-lib-card__head">
                        <span class="ws-lib-card__title">{{ $lib['icon'] }} {{ $lib['name'] }}</span>
                        <span class="ws-lib-card__pct">{{ $lib['progress'] }}%</span>
                    </div>
                    <div class="ws-bar-bg">
                        <div class="ws-bar-fill" style="width: {{ $lib['progress'] }}%;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.2rem;">
                        <span style="font-size:.72rem;color:#64748b;">Target: {{ $lib['target'] }} items</span>
                        <a href="{{ route('admin.question-banks.index') }}" style="font-size:.75rem;font-weight:700;color:#818cf8;text-decoration:none;">
                            Author Items →
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Right: Content Health Score & Recent Activity Widgets --}}
        <div class="ws-side-panel">

            {{-- Visual Content Health Score Breakdown --}}
            <div class="ws-health-card">
                <div class="ws-health-head">
                    <span class="ws-health-label">Content Health Score</span>
                    <span class="ws-health-badge">Grade {{ $healthData['grade'] }}</span>
                </div>

                <div class="ws-health-score">{{ $healthData['score'] }}<span style="font-size:.9rem;color:#475569;">/100</span></div>

                <div class="ws-progress-list">
                    @foreach($librarySections as $lib)
                    <div class="ws-prog-item">
                        <div class="ws-prog-meta">
                            <span>{{ $lib['name'] }}</span>
                            <span style="color:#34d399;">{{ $lib['progress'] }}%</span>
                        </div>
                        <div class="ws-bar-bg">
                            <div class="ws-bar-fill" style="width: {{ $lib['progress'] }}%;"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Recent Activity Widget --}}
            <div class="ws-activity-card">
                <div style="font-size:.85rem;font-weight:800;color:#f1f5f9;">🕒 Recent Activity</div>
                <div class="ws-act-list">
                    @foreach($recentActivities as $act)
                    <div class="ws-act-item">
                        <span style="font-size:1.1rem;">{{ $act['icon'] }}</span>
                        <div>
                            <div class="ws-act-text">{{ $act['action'] }}</div>
                            <div class="ws-act-time">{{ $act['time'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
