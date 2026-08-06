@extends('layouts.admin')

@section('title', 'Teacher Workspace — iC.edu Platform')

@push('styles')
<style>
/* ──────────────────────────────────────────
   TEACHER WORKSPACE v2 — TEACHER-UX-001
   Modern productivity dashboard, dark-mode
────────────────────────────────────────── */

/* ── Hero ── */
.tw-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
    border: 1px solid rgba(99,102,241,.25);
    border-radius: 1.25rem;
    padding: 2rem 2rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
}
.tw-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 80% 50%, rgba(99,102,241,.18) 0%, transparent 65%);
    pointer-events: none;
}
.tw-hero__greeting { font-size: 1.55rem; font-weight: 800; color: #fff; line-height: 1.2; }
.tw-hero__sub { font-size: .9rem; color: #a5b4fc; margin-top: .35rem; }
.tw-hero__date { font-size: .78rem; color: #818cf8; margin-top: .2rem; font-weight: 500; }
.tw-hero__pills { display: flex; gap: .65rem; flex-wrap: wrap; margin-top: 1rem; }
.tw-hero__pill {
    padding: .3rem .85rem;
    border-radius: 99px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    border: 1px solid;
    white-space: nowrap;
    cursor: default;
    text-decoration: none;
}
a.tw-hero__pill {
    cursor: pointer;
    transition: opacity .15s;
}
a.tw-hero__pill:hover { opacity: .8; }
.tw-hero__pill--amber  { background: rgba(251,191,36,.12); color: #fbbf24; border-color: rgba(251,191,36,.3); }
.tw-hero__pill--emerald{ background: rgba(52,211,153,.12); color: #34d399; border-color: rgba(52,211,153,.3); }
.tw-hero__pill--indigo { background: rgba(99,102,241,.18); color: #a5b4fc; border-color: rgba(99,102,241,.35); }
.tw-hero__pill--rose   { background: rgba(251,113,133,.12); color: #fb7185; border-color: rgba(251,113,133,.3); }

/* ── KPI Cards ── */
.tw-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .tw-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .tw-kpi-grid { grid-template-columns: 1fr 1fr; } }

.tw-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem 1.25rem 1rem;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: .3rem;
    transition: border-color .2s, transform .15s, box-shadow .2s;
}
.tw-kpi:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99,102,241,.18);
}
.tw-kpi__icon  { font-size: 1.4rem; margin-bottom: .2rem; }
.tw-kpi__count { font-size: 2rem; font-weight: 900; line-height: 1; }
.tw-kpi__label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
.tw-kpi__desc  { font-size: .72rem; color: #475569; margin-top: .1rem; }
.tw-kpi--indigo .tw-kpi__count  { color: #818cf8; }
.tw-kpi--amber  .tw-kpi__count  { color: #fbbf24; }
.tw-kpi--emerald .tw-kpi__count { color: #34d399; }
.tw-kpi--slate  .tw-kpi__count  { color: #94a3b8; }

/* ── Quick Actions ── */
.tw-qa-strip {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    align-items: center;
}
.tw-qa-btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .55rem 1.15rem;
    border-radius: .65rem;
    font-size: .82rem;
    font-weight: 700;
    border: 1px solid;
    text-decoration: none;
    transition: background .15s, transform .12s, box-shadow .15s;
    cursor: pointer;
    white-space: nowrap;
}
.tw-qa-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.3); }
.tw-qa-btn--primary { background: #6366f1; color: #fff; border-color: #6366f1; }
.tw-qa-btn--primary:hover { background: #4f46e5; }
.tw-qa-btn--secondary { background: #1e293b; color: #cbd5e1; border-color: #334155; }
.tw-qa-btn--secondary:hover { background: #334155; color: #fff; }

/* ── Layout ── */
.tw-workspace {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 1100px) { .tw-workspace { grid-template-columns: 1fr; } }

/* ── Panel ── */
.tw-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    overflow: hidden;
}
.tw-panel__head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.tw-panel__title { font-size: .88rem; font-weight: 700; color: #f1f5f9; }
.tw-panel__link  { font-size: .75rem; color: #818cf8; font-weight: 600; text-decoration: none; }
.tw-panel__link:hover { text-decoration: underline; }

/* ── Search & Filter Bar ── */
.tw-filter-bar {
    padding: .75rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    gap: .65rem;
    align-items: center;
    flex-wrap: wrap;
}
.tw-search {
    flex: 1;
    min-width: 160px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: .5rem;
    color: #e2e8f0;
    font-size: .8rem;
    padding: .45rem .85rem;
    outline: none;
    transition: border-color .15s;
}
.tw-search:focus { border-color: #6366f1; }
.tw-filter-pills { display: flex; gap: .4rem; flex-wrap: wrap; }
.tw-fpill {
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
.tw-fpill:hover, .tw-fpill--active { background: #6366f1; color: #fff; border-color: #6366f1; }

/* ── Table ── */
.tw-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tw-table th {
    padding: .65rem 1rem;
    background: #080f1d;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #64748b;
    text-align: left;
    border-bottom: 1px solid #1e293b;
}
.tw-table td {
    padding: .75rem 1rem;
    border-bottom: 1px solid #1e293b;
    vertical-align: middle;
}
.tw-table tbody tr:last-child td { border-bottom: none; }
.tw-table tbody tr:hover { background: rgba(99,102,241,.04); }

.tw-title-link { font-weight: 600; color: #e2e8f0; text-decoration: none; display: block; transition: color .15s; }
.tw-title-link:hover { color: #818cf8; }
.tw-title-sub { font-size: .7rem; color: #475569; margin-top: 2px; }

/* ── Status Badges ── */
.tw-badge {
    display: inline-block;
    padding: .2rem .65rem;
    border-radius: 99px;
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    border: 1px solid;
    white-space: nowrap;
}
.tw-badge--draft     { background: rgba(100,116,139,.12); color: #94a3b8; border-color: rgba(100,116,139,.3); }
.tw-badge--pending   { background: rgba(251,191,36,.12); color: #fbbf24; border-color: rgba(251,191,36,.3); }
.tw-badge--approved  { background: rgba(99,102,241,.12); color: #818cf8; border-color: rgba(99,102,241,.3); }
.tw-badge--published { background: rgba(52,211,153,.12); color: #34d399; border-color: rgba(52,211,153,.3); }
.tw-badge--archived  { background: rgba(100,116,139,.08); color: #64748b; border-color: rgba(100,116,139,.2); }
.tw-badge--rejected  { background: rgba(251,113,133,.12); color: #fb7185; border-color: rgba(251,113,133,.3); }

/* ── Action Buttons (inline) ── */
.tw-act { font-size: .72rem; font-weight: 700; text-decoration: none; border: none; background: none; cursor: pointer; padding: .2rem .5rem; border-radius: .35rem; transition: background .15s; white-space: nowrap; }
.tw-act--view    { color: #818cf8; }
.tw-act--view:hover    { background: rgba(99,102,241,.12); }
.tw-act--submit  { color: #fbbf24; }
.tw-act--submit:hover  { background: rgba(251,191,36,.1); }
.tw-act--dupe    { color: #94a3b8; }
.tw-act--dupe:hover    { background: rgba(100,116,139,.12); }

/* ── Sidebar Widgets ── */
.tw-widget { background: #0f172a; border: 1px solid #1e293b; border-radius: 1rem; overflow: hidden; }
.tw-widget + .tw-widget { margin-top: 1rem; }

/* ── Progress Widget ── */
.tw-prog-item { display: flex; align-items: center; gap: .75rem; padding: .65rem 1.25rem; }
.tw-prog-item:not(:last-child) { border-bottom: 1px solid #1e293b; }
.tw-prog-label { font-size: .75rem; color: #94a3b8; width: 90px; flex-shrink: 0; font-weight: 600; }
.tw-prog-bar-wrap { flex: 1; background: #1e293b; border-radius: 99px; height: 5px; overflow: hidden; }
.tw-prog-bar { height: 100%; border-radius: 99px; transition: width .5s ease; }
.tw-prog-count { font-size: .72rem; font-weight: 700; color: #e2e8f0; width: 32px; text-align: right; }

/* ── Notification Widget ── */
.tw-notif-item {
    padding: .85rem 1.25rem;
    cursor: pointer;
    transition: background .15s;
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    text-decoration: none;
}
.tw-notif-item:not(:last-child) { border-bottom: 1px solid #1e293b; }
.tw-notif-item:hover { background: rgba(99,102,241,.05); }
.tw-notif-item--unread { background: rgba(99,102,241,.06); }
.tw-notif-dot { width: 7px; height: 7px; border-radius: 50%; background: #6366f1; margin-top: 5px; flex-shrink: 0; }
.tw-notif-dot--read { background: transparent; border: 1px solid #334155; }
.tw-notif-title { font-size: .78rem; font-weight: 700; color: #e2e8f0; }
.tw-notif-msg   { font-size: .72rem; color: #64748b; margin-top: 2px; line-height: 1.4; }
.tw-notif-time  { font-size: .67rem; color: #475569; margin-top: 3px; font-weight: 500; }

/* ── Empty States ── */
.tw-empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .65rem;
}
.tw-empty__icon { font-size: 2.25rem; opacity: .5; }
.tw-empty__title { font-size: .9rem; font-weight: 700; color: #475569; }
.tw-empty__sub { font-size: .78rem; color: #334155; }
.tw-empty__cta {
    margin-top: .5rem;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem 1rem;
    border-radius: .5rem;
    background: #6366f1;
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    text-decoration: none;
    transition: background .15s;
}
.tw-empty__cta:hover { background: #4f46e5; }

/* ── Modal ── */
.tw-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2,6,23,.7);
    backdrop-filter: blur(6px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.tw-modal {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    width: 100%;
    max-width: 520px;
    max-height: 80vh;
    overflow-y: auto;
    padding: 2rem;
    box-shadow: 0 24px 64px rgba(0,0,0,.6);
}
.tw-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.tw-modal__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; }
.tw-modal__close { color: #475569; font-size: 1.35rem; cursor: pointer; background: none; border: none; line-height: 1; transition: color .15s; }
.tw-modal__close:hover { color: #e2e8f0; }
.tw-form-group { margin-bottom: 1.1rem; }
.tw-form-label { display: block; font-size: .78rem; font-weight: 600; color: #94a3b8; margin-bottom: .4rem; }
.tw-form-input, .tw-form-select, .tw-form-textarea {
    width: 100%;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: .6rem;
    color: #e2e8f0;
    font-size: .85rem;
    padding: .6rem .9rem;
    outline: none;
    transition: border-color .15s;
    box-sizing: border-box;
}
.tw-form-input:focus, .tw-form-select:focus, .tw-form-textarea:focus { border-color: #6366f1; }
.tw-form-select { appearance: none; }
.tw-form-textarea { resize: vertical; min-height: 80px; }
.tw-form-submit {
    width: 100%;
    padding: .75rem;
    background: #6366f1;
    color: #fff;
    font-size: .88rem;
    font-weight: 700;
    border: none;
    border-radius: .65rem;
    cursor: pointer;
    transition: background .15s;
}
.tw-form-submit:hover { background: #4f46e5; }

/* ── Pagination ── */
.tw-pager { padding: .85rem 1.25rem; border-top: 1px solid #1e293b; display: flex; justify-content: flex-end; }
</style>
@endpush

@section('content')
<div style="display:flex;flex-direction:column;gap:1.5rem;">

    {{-- ══════════════════════════════════════════════
         SECTION 1 — HERO HEADER
         TEACHER WORKSPACE UI CONVENTION:
         • Hero = Information
         • Statistic Cards = Navigation
         • Tables = Management
         • Forms = Editing
         ══════════════════════════════════════════════ --}}
    <div class="tw-hero">
        <div>
            @php
                $hour = now()->hour;
                $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
            @endphp
            <div class="tw-hero__greeting">{{ $greeting }}, {{ auth()->user()->name }} 👋</div>
            <div class="tw-hero__sub">Authoring Workspace · iC.edu Assessment Platform</div>
            <div class="tw-hero__date">{{ now()->format('l, d F Y') }}</div>
            <div class="tw-hero__pills">
                @if($pendingApprovalQuestionBanks > 0)
                <a href="{{ route('admin.question-banks.index', ['status' => 'pending_approval']) }}"
                   class="tw-hero__pill tw-hero__pill--amber">
                    ⏳ {{ $pendingApprovalQuestionBanks }} Pending Approval
                </a>
                @endif
                @if($publishedQuestionBanks > 0)
                <span class="tw-hero__pill tw-hero__pill--emerald" style="cursor:default;user-select:none;">
                    🟢 {{ $publishedQuestionBanks }} Published
                </span>
                @endif
                @if($draftQuestionBanks > 0)
                <a href="{{ route('admin.question-banks.index', ['status' => 'draft']) }}"
                   class="tw-hero__pill tw-hero__pill--indigo">
                    ✏️ {{ $draftQuestionBanks }} Drafts
                </a>
                @endif
                @if($unreadNotificationCount > 0)
                <a href="{{ route('notifications.index') }}"
                   class="tw-hero__pill tw-hero__pill--rose">
                    🔔 {{ $unreadNotificationCount }} Unread
                </a>
                @endif
            </div>
        </div>
        <div>
            <button type="button" onclick="openCreateModal()"
                    class="tw-qa-btn tw-qa-btn--primary" style="font-size:.9rem;padding:.7rem 1.5rem;">
                ＋ New Question Bank
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         CONTINUE WORKING SPOTLIGHT SECTION (PART 3)
         ══════════════════════════════════════════════ --}}
    @if(isset($latestDraftBank))
    <div style="background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);border:1px solid rgba(99,102,241,.3);border-radius:1.25rem;padding:1.5rem 1.75rem;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;">
        <div>
            <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#fbbf24;">⚡ Continue Working</div>
            <h3 style="font-size:1.25rem;font-weight:800;color:#f1f5f9;margin:.25rem 0 .4rem;">{{ $latestDraftBank->title }}</h3>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.75rem;color:#94a3b8;">
                <span>📝 <strong>{{ $latestDraftBank->questions->count() }}</strong> Questions</span>
                <span>🏷 <strong>{{ is_object($latestDraftBank->test_type) ? $latestDraftBank->test_type->label() : strtoupper($latestDraftBank->test_type?->value ?? 'General') }}</strong></span>
                <span>📌 Version <strong>{{ $latestDraftBank->current_version ?? '1.0' }}</strong></span>
                <span>🕒 Last edited {{ $latestDraftBank->updated_at?->diffForHumans() }}</span>
                <span style="color:#34d399;">● Autosave Active</span>
            </div>
        </div>
        <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
            <a href="{{ route('admin.question-banks.show', $latestDraftBank->id) }}" class="tw-qa-btn tw-qa-btn--primary">
                ✏️ Resume Editing →
            </a>
            <a href="{{ route('admin.question-banks.index', ['status' => 'draft']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                📂 Continue Draft
            </a>
        </div>
    </div>
    @else
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.5rem;">
        <div style="font-size:2.25rem;opacity:.8;">🎉</div>
        <div style="font-size:1.05rem;font-weight:800;color:#f1f5f9;">You're all caught up.</div>
        <div style="font-size:.82rem;color:#94a3b8;max-width:420px;line-height:1.45;">No unfinished authoring work. Create a new Question Bank or contribute to Institutional Library.</div>
        <button type="button" onclick="openCreateModal()" class="tw-qa-btn tw-qa-btn--primary" style="margin-top:.25rem;">
            ＋ Create Question Bank
        </button>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         SECTION 2 — SMART KPI CARDS (PART 6, 7, 8, 9)
         ══════════════════════════════════════════════ --}}
    <div class="tw-kpi-grid">
        @if($totalQuestionBanks > 0)
        <a href="{{ route('admin.question-banks.index', ['my' => 1]) }}" class="tw-kpi tw-kpi--indigo">
            <div class="tw-kpi__icon">📂</div>
            <div class="tw-kpi__count">{{ $totalQuestionBanks }}</div>
            <div class="tw-kpi__label">My Question Banks</div>
            <div class="tw-kpi__desc">Total banks authored by me</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openTwNothingModal('My Question Banks')" class="tw-kpi tw-kpi--indigo">
            <div class="tw-kpi__icon">📂</div>
            <div class="tw-kpi__count">0</div>
            <div class="tw-kpi__label">My Question Banks</div>
            <div class="tw-kpi__desc">No authored banks yet</div>
        </a>
        @endif

        {{-- Teacher Awaiting Approval KPI Card --}}
        @if($pendingTotal > 0)
        <a href="{{ route('teacher.tests.index', ['status' => 'pending_approval']) }}" class="tw-kpi tw-kpi--amber">
            <div class="tw-kpi__icon">⏳</div>
            <div class="tw-kpi__count">{{ $pendingTotal }}</div>
            <div class="tw-kpi__label">Awaiting Approval</div>
            <div class="tw-kpi__desc">My Submitted Items Pending Review</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openNoPendingApprovalModal()" class="tw-kpi tw-kpi--amber">
            <div class="tw-kpi__icon">⏳</div>
            <div class="tw-kpi__count">0</div>
            <div class="tw-kpi__label">Awaiting Approval</div>
            <div class="tw-kpi__desc">No Items Pending Review</div>
        </a>
        @endif

        @if($publishedQuestionBanks > 0)
        <a href="{{ route('admin.question-banks.index', ['status' => 'published']) }}" class="tw-kpi tw-kpi--emerald">
            <div class="tw-kpi__icon">🟢</div>
            <div class="tw-kpi__count">{{ $publishedQuestionBanks }}</div>
            <div class="tw-kpi__label">Published</div>
            <div class="tw-kpi__desc">Live and accessible to candidates</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openTwNothingModal('Published')" class="tw-kpi tw-kpi--emerald">
            <div class="tw-kpi__icon">🟢</div>
            <div class="tw-kpi__count">0</div>
            <div class="tw-kpi__label">Published</div>
            <div class="tw-kpi__desc">No published banks</div>
        </a>
        @endif

        @if($needsRevisionAssessments > 0)
        <a href="{{ route('teacher.revision-center') }}" class="tw-kpi tw-kpi--amber" style="border-color:#f59e0b;">
            <div class="tw-kpi__icon">⚠️</div>
            <div class="tw-kpi__count" style="color:#fbbf24;">{{ $needsRevisionAssessments }}</div>
            <div class="tw-kpi__label">Needs Revision</div>
            <div class="tw-kpi__desc">Returned by Repository Manager</div>
        </a>
        @else
        <a href="javascript:void(0)" class="tw-kpi tw-kpi--amber">
            <div class="tw-kpi__icon">⚠️</div>
            <div class="tw-kpi__count">0</div>
            <div class="tw-kpi__label">Needs Revision</div>
            <div class="tw-kpi__desc">No revision requests</div>
        </a>
        @endif

        @if($draftQuestionBanks > 0)
        <a href="{{ route('admin.question-banks.index', ['status' => 'draft']) }}" class="tw-kpi tw-kpi--slate">
            <div class="tw-kpi__icon">✏️</div>
            <div class="tw-kpi__count">{{ $draftQuestionBanks }}</div>
            <div class="tw-kpi__label">Drafts</div>
            <div class="tw-kpi__desc">In progress, not yet submitted</div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openNoDraftModal()" class="tw-kpi tw-kpi--slate">
            <div class="tw-kpi__icon">✏️</div>
            <div class="tw-kpi__count">0</div>
            <div class="tw-kpi__label">Drafts</div>
            <div class="tw-kpi__desc">No active drafts</div>
        </a>
        @endif
    </div>

    {{-- Coming Soon Section Placeholder (PART 6) --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:.85rem;">
            <span style="font-size:1.5rem;">🎓</span>
            <div>
                <div style="font-size:.88rem;font-weight:800;color:#f1f5f9;">Assigned Courses &amp; Student Progress</div>
                <div style="font-size:.75rem;color:#64748b;margin-top:2px;">Academic course assignments, student roster, and placement test analytics.</div>
            </div>
        </div>
        <span style="padding:.25rem .75rem;border-radius:99px;font-size:.68rem;font-weight:800;background:rgba(99,102,241,.12);color:#818cf8;border:1px solid rgba(99,102,241,.25);">
            COMING SOON
        </span>
    </div>

    {{-- ══════════════════════════════════════════════
         SECTION 3 — QUICK ACTIONS STRIP
         ══════════════════════════════════════════════ --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#475569;margin-bottom:.75rem;">Quick Actions</div>
        <div class="tw-qa-strip">
            <button type="button" onclick="openCreateModal()" class="tw-qa-btn tw-qa-btn--primary">
                📂 New Question Bank
            </button>
            <a href="{{ route('admin.question-banks.index') }}" class="tw-qa-btn tw-qa-btn--secondary">
                📋 All My Banks
            </a>
            <a href="{{ route('admin.media.index') }}" class="tw-qa-btn tw-qa-btn--secondary">
                🖼 Media Library
            </a>
            <a href="{{ route('notifications.index') }}" class="tw-qa-btn tw-qa-btn--secondary">
                🔔 Notifications
                @if($unreadNotificationCount > 0)
                <span style="background:#6366f1;color:#fff;border-radius:99px;padding:1px 7px;font-size:.65rem;">
                    {{ $unreadNotificationCount }}
                </span>
                @endif
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         SECTION 10 — MAIN WORKSPACE: 70/30 LAYOUT
         ══════════════════════════════════════════════ --}}
    <div class="tw-workspace">

        {{-- ── LEFT: Question Bank Listing (70%) ── --}}
        <div>
            <div class="tw-panel">
                <div class="tw-panel__head">
                    <span class="tw-panel__title">📚 My Question Banks</span>
                    <a href="{{ route('admin.question-banks.index') }}" class="tw-panel__link">View All →</a>
                </div>

                {{-- SECTION 6: Search & Filter --}}
                <div class="tw-filter-bar">
                    <form method="GET" action="{{ route('admin.question-banks.index') }}"
                          style="display:flex;gap:.65rem;flex:1;align-items:center;flex-wrap:wrap;">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search my question banks…"
                               class="tw-search">
                        <div class="tw-filter-pills">
                            <a href="{{ route('admin.question-banks.index') }}"
                               class="tw-fpill {{ !request('status') ? 'tw-fpill--active' : '' }}">All</a>
                            <a href="{{ route('admin.question-banks.index', ['status' => 'draft']) }}"
                               class="tw-fpill {{ request('status') === 'draft' ? 'tw-fpill--active' : '' }}">Draft</a>
                            <a href="{{ route('admin.question-banks.index', ['status' => 'pending_approval']) }}"
                               class="tw-fpill {{ request('status') === 'pending_approval' ? 'tw-fpill--active' : '' }}">Pending</a>
                            <a href="{{ route('admin.question-banks.index', ['status' => 'approved']) }}"
                               class="tw-fpill {{ request('status') === 'approved' ? 'tw-fpill--active' : '' }}">Approved</a>
                            <a href="{{ route('admin.question-banks.index', ['status' => 'published']) }}"
                               class="tw-fpill {{ request('status') === 'published' ? 'tw-fpill--active' : '' }}">Published</a>
                            <a href="{{ route('admin.question-banks.index', ['status' => 'archived']) }}"
                               class="tw-fpill {{ request('status') === 'archived' ? 'tw-fpill--active' : '' }}">Archived</a>
                        </div>
                        <button type="submit"
                                style="padding:.4rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">
                            Search
                        </button>
                    </form>
                </div>

                {{-- SECTION 4: Question Bank List --}}
                @if($recentQuestionBanks->isEmpty())
                <div class="tw-empty">
                    <div class="tw-empty__icon">📭</div>
                    <div class="tw-empty__title">No Question Banks Yet</div>
                    <div class="tw-empty__sub">Start by creating your first Question Bank to begin authoring questions.</div>
                    <button type="button" onclick="openCreateModal()" class="tw-empty__cta">
                        ＋ Create Your First Bank
                    </button>
                </div>
                @else
                <div style="overflow-x:auto;">
                    <table class="tw-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th style="text-align:center;">Questions</th>
                                <th>Updated</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentQuestionBanks as $bank)
                            @php
                                $status = $bank->status ?? 'draft';
                                $badgeClass = match($status) {
                                    'published'              => 'tw-badge--published',
                                    'approved'               => 'tw-badge--approved',
                                    'pending_approval',
                                    'pending_archive_approval' => 'tw-badge--pending',
                                    'archived'               => 'tw-badge--archived',
                                    'rejected'               => 'tw-badge--rejected',
                                    default                  => 'tw-badge--draft',
                                };
                                $statusLabel = match($status) {
                                    'pending_approval'         => 'Pending',
                                    'pending_archive_approval' => 'Arch. Pending',
                                    default => ucfirst(str_replace('_', ' ', $status)),
                                };
                            @endphp
                            <tr>
                                {{-- Title --}}
                                <td>
                                    <a href="{{ route('admin.question-banks.show', $bank->id) }}"
                                       class="tw-title-link">{{ $bank->title }}</a>
                                    @if($bank->description)
                                    <div class="tw-title-sub">{{ Str::limit($bank->description, 55) }}</div>
                                    @endif
                                    <div class="tw-title-sub" style="margin-top:3px;">
                                        🏷 {{ strtoupper($bank->test_type?->value ?? 'General') }}
                                    </div>
                                </td>
                                {{-- Status --}}
                                <td>
                                    <span class="tw-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                </td>
                                {{-- Questions --}}
                                <td style="text-align:center;font-weight:700;color:#818cf8;font-size:.85rem;">
                                    {{ $bank->questions->count() }}
                                </td>
                                {{-- Updated --}}
                                <td style="color:#475569;font-size:.75rem;">
                                    {{ $bank->updated_at?->diffForHumans() }}
                                </td>
                                {{-- Actions -- only what the current status permits --}}
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                                        {{-- View/Author (always available) --}}
                                        <a href="{{ route('admin.question-banks.show', $bank->id) }}"
                                           class="tw-act tw-act--view">✏️ Author</a>

                                        {{-- Submit — only draft or rejected --}}
                                        @if(in_array($status, ['draft', 'rejected']))
                                        <form method="POST"
                                              action="{{ route('admin.question-banks.submit', $bank->id) }}"
                                              style="display:inline;"
                                              onsubmit="return confirm('Submit \'{{ addslashes($bank->title) }}\' for Super Admin approval?');">
                                            @csrf
                                            <button type="submit" class="tw-act tw-act--submit">📤 Submit</button>
                                        </form>
                                        @endif

                                        {{-- Duplicate (always) --}}
                                        <form method="POST"
                                              action="{{ route('admin.question-banks.duplicate', $bank->id) }}"
                                              style="display:inline;"
                                              onsubmit="return confirm('Duplicate \'{{ addslashes($bank->title) }}\'?');">
                                            @csrf
                                            <button type="submit" class="tw-act tw-act--dupe">⧉ Dupe</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tw-pager">
                    <a href="{{ route('admin.question-banks.index') }}"
                       style="font-size:.75rem;color:#818cf8;font-weight:600;text-decoration:none;">
                        View all {{ $totalQuestionBanks }} banks →
                    </a>
                </div>
                @endif
            </div>
        </div>

        {{-- ── RIGHT: Sidebar Widgets (30%) ── --}}
        <div>

            {{-- SECTION 9: Content Progress Widget --}}
            <div class="tw-widget">
                <div class="tw-panel__head">
                    <span class="tw-panel__title">📊 Content Progress</span>
                </div>
                @php
                    $totalContent = max($totalQuestionBanks + ($draftAssessments + $pendingAssessments + $approvedAssessments + $needsRevisionAssessments), 1);
                    $progItems = [
                        ['label' => 'Published',      'count' => $publishedQuestionBanks, 'color' => '#34d399'],
                        ['label' => 'Approved',       'count' => $approvedQuestionBanks + $approvedAssessments, 'color' => '#818cf8'],
                        ['label' => 'Pending',        'count' => $pendingApprovalQuestionBanks + $pendingAssessments, 'color' => '#fbbf24'],
                        ['label' => 'Needs Revision', 'count' => $needsRevisionAssessments, 'color' => '#f59e0b'],
                        ['label' => 'Draft',          'count' => $draftQuestionBanks + $draftAssessments, 'color' => '#64748b'],
                        ['label' => 'Archived',       'count' => $archivedQuestionBanks,  'color' => '#334155'],
                    ];
                @endphp
                @foreach($progItems as $pi)
                <div class="tw-prog-item">
                    <div class="tw-prog-label">{{ $pi['label'] }}</div>
                    <div class="tw-prog-bar-wrap">
                        <div class="tw-prog-bar"
                             style="width:{{ $totalQuestionBanks > 0 ? round($pi['count']/$totalQuestionBanks*100) : 0 }}%;background:{{ $pi['color'] }};"></div>
                    </div>
                    <div class="tw-prog-count" style="color:{{ $pi['color'] }};">{{ $pi['count'] }}</div>
                </div>
                @endforeach
            </div>

            {{-- SECTION 8: Notifications Widget --}}
            <div class="tw-widget" style="margin-top:1rem;">
                <div class="tw-panel__head">
                    <span class="tw-panel__title">
                        🔔 Notifications
                        @if($unreadNotificationCount > 0)
                        <span style="background:#6366f1;color:#fff;border-radius:99px;padding:1px 8px;font-size:.65rem;margin-left:.4rem;">
                            {{ $unreadNotificationCount }}
                        </span>
                        @endif
                    </span>
                    <a href="{{ route('notifications.index') }}" class="tw-panel__link">View All →</a>
                </div>
                @forelse($notifications as $n)
                @php
                    $nData    = $n->data ?? [];
                    $isUnread = is_null($n->read_at);
                @endphp
                <form method="POST" action="{{ route('notifications.read', $n->id) }}" style="margin:0;padding:0;display:block;">
                    @csrf
                    <button type="submit"
                            class="tw-notif-item {{ $isUnread ? 'tw-notif-item--unread' : '' }}"
                            style="width:100%;text-align:left;background:none;border:none;cursor:pointer;">
                        <div class="tw-notif-dot {{ $isUnread ? '' : 'tw-notif-dot--read' }}"></div>
                        <div>
                            <div class="tw-notif-title">{{ $nData['title'] ?? 'Notification' }}</div>
                            <div class="tw-notif-msg">{{ Str::limit($nData['message'] ?? '', 70) }}</div>
                            <div class="tw-notif-time">{{ $n->created_at?->diffForHumans() }}</div>
                        </div>
                    </button>
                </form>
                @empty
                <div class="tw-empty" style="padding:1.5rem;">
                    <div class="tw-empty__icon">🔕</div>
                    <div class="tw-empty__title">All clear</div>
                    <div class="tw-empty__sub">No notifications yet. Governance alerts will appear here.</div>
                </div>
                @endforelse
            </div>

            {{-- SECTION 7: Recent Activity Timeline --}}
            <div class="tw-widget" style="margin-top:1rem;">
                <div class="tw-panel__head">
                    <span class="tw-panel__title">⏱ Recent Activity</span>
                </div>
                @if($recentQuestionBanks->isEmpty())
                <div class="tw-empty" style="padding:1.5rem;">
                    <div class="tw-empty__icon">📝</div>
                    <div class="tw-empty__title">No activity yet</div>
                    <div class="tw-empty__sub">Your created and submitted banks will appear here.</div>
                </div>
                @else
                <div style="padding:.5rem 0;">
                    @foreach($recentQuestionBanks->take(6) as $bank)
                    @php
                        $actIcon = match($bank->status ?? 'draft') {
                            'published'       => '🟢',
                            'approved'        => '✅',
                            'pending_approval'=> '⏳',
                            'rejected'        => '❌',
                            'archived'        => '📦',
                            default           => '✏️',
                        };
                        $actVerb = match($bank->status ?? 'draft') {
                            'published'       => 'Published',
                            'approved'        => 'Approved',
                            'pending_approval'=> 'Submitted for approval',
                            'rejected'        => 'Rejected',
                            'archived'        => 'Archived',
                            default           => 'Updated draft',
                        };
                    @endphp
                    <a href="{{ route('admin.question-banks.show', $bank->id) }}"
                       style="display:flex;gap:.85rem;align-items:flex-start;padding:.75rem 1.25rem;text-decoration:none;transition:background .15s;border-bottom:1px solid #1e293b;"
                       onmouseover="this.style.background='rgba(99,102,241,.05)'"
                       onmouseout="this.style.background='transparent'">
                        <span style="font-size:1.1rem;margin-top:1px;">{{ $actIcon }}</span>
                        <div>
                            <div style="font-size:.78rem;font-weight:600;color:#e2e8f0;">
                                {{ Str::limit($bank->title, 38) }}
                            </div>
                            <div style="font-size:.7rem;color:#475569;margin-top:2px;">
                                {{ $actVerb }} · {{ $bank->updated_at?->diffForHumans() }}
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>

        </div>{{-- /sidebar --}}
    </div>{{-- /workspace --}}

</div>

{{-- ══════════════════════════════════════════════
     SECTION 5 — NEW QUESTION BANK MODAL
     ══════════════════════════════════════════════ --}}
<div id="tw-create-modal" class="tw-modal-backdrop" style="display:none;" onclick="closeCreateModal(event)">
    <div class="tw-modal" onclick="event.stopPropagation()">
        <div class="tw-modal__head">
            <div class="tw-modal__title">📂 New Question Bank</div>
            <button type="button" class="tw-modal__close" onclick="closeCreateModal()">×</button>
        </div>
        <form method="POST" action="{{ route('admin.question-banks.store') }}">
            @csrf
            <div class="tw-form-group">
                <label class="tw-form-label" for="modal-title">Bank Title <span style="color:#fb7185;">*</span></label>
                <input type="text"
                       id="modal-title"
                       name="title"
                       required
                       class="tw-form-input"
                       placeholder="e.g. TOEIC Part 1 — Listening Pool">
            </div>
            <div class="tw-form-group">
                <label class="tw-form-label" for="modal-test-type">Test Type</label>
                <select id="modal-test-type" name="test_type" class="tw-form-select">
                    <option value="toeic">TOEIC</option>
                    <option value="toefl">TOEFL iBT</option>
                    <option value="ielts">IELTS</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="tw-form-group">
                <label class="tw-form-label" for="modal-description">Description</label>
                <textarea id="modal-description"
                          name="description"
                          class="tw-form-textarea"
                          placeholder="Describe the purpose of this question bank…"></textarea>
            </div>
            <button type="submit" class="tw-form-submit">Create Question Bank</button>
        </form>
    </div>
</div>

{{-- Floating Modal for Zero Results --}}
<div id="tw-nothing-modal" class="tw-modal-backdrop" style="display:none;" onclick="closeTwNothingModal(event)">
    <div class="tw-modal" onclick="event.stopPropagation()" style="text-align:center;max-width:440px;">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">📭</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">Nothing Here Yet</div>
        <p style="font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.5;">There are currently no items in this category.</p>
        <button type="button" onclick="closeTwNothingModal()" class="tw-form-submit" style="margin-top:0;">Close</button>
    </div>
</div>

{{-- No Draft Modal (PART 8) --}}
<div id="tw-no-draft-modal" class="tw-modal-backdrop" style="display:none;" onclick="closeNoDraftModal(event)">
    <div class="tw-modal" onclick="event.stopPropagation()" style="text-align:center;max-width:440px;">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">📝</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">No draft Question Banks.</div>
        <p style="font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.5;">You currently have no active drafts in progress.</p>
        <div style="display:flex;gap:.75rem;">
            <button type="button" onclick="closeNoDraftModal()" class="tw-qa-btn tw-qa-btn--secondary" style="flex:1;">Close</button>
            <button type="button" onclick="closeNoDraftModal();openCreateModal();" class="tw-form-submit" style="flex:1.5;margin-top:0;">Create Draft</button>
        </div>
</div>

@endsection

@push('scripts')
<script>
function openCreateModal() {
    document.getElementById('tw-create-modal').style.display = 'flex';
    document.getElementById('modal-title').focus();
}
function closeCreateModal(e) {
    if (!e || e.target === document.getElementById('tw-create-modal')) {
        document.getElementById('tw-create-modal').style.display = 'none';
    }
}

function openTwNothingModal() {
    document.getElementById('tw-nothing-modal').style.display = 'flex';
}
function closeTwNothingModal(e) {
    if (!e || e.target === document.getElementById('tw-nothing-modal')) {
        document.getElementById('tw-nothing-modal').style.display = 'none';
    }
}

function openNoDraftModal() {
    document.getElementById('tw-no-draft-modal').style.display = 'flex';
}
function closeNoDraftModal(e) {
    if (!e || e.target === document.getElementById('tw-no-draft-modal')) {
        document.getElementById('tw-no-draft-modal').style.display = 'none';
    }
}

function openNoPendingApprovalModal() {
    document.getElementById('tw-no-pending-approval-modal').style.display = 'flex';
}
function closeNoPendingApprovalModal(e) {
    if (!e || e.target === document.getElementById('tw-no-pending-approval-modal')) {
        document.getElementById('tw-no-pending-approval-modal').style.display = 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateModal();
        closeTwNothingModal();
        closeNoDraftModal();
        closeNoPendingApprovalModal();
    }
});

// Auto-open modal if redirected back with a validation error
@if($errors->any())
openCreateModal();
@endif
</script>
@endpush
