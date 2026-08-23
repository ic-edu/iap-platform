@extends('layouts.admin')

@section('title', 'Teacher Workspace — iC.edu Platform')

@push('styles')
<style>
/* ──────────────────────────────────────────
   TEACHER WORKSPACE — THEME ARCHITECTURE
   Unified Light & Dark Theme Design System
────────────────────────────────────────── */

/* ── Hero ── */
.tw-hero {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 50%, #eef2ff 100%);
    border: 1px solid #dbe0fc;
    border-radius: 1.25rem;
    padding: 2rem 2rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(99,102,241,0.06);
    transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
html.dark .tw-hero,
html[data-theme="dark"] .tw-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #0f172a 100%);
    border-color: rgba(99,102,241,0.3);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
}

.tw-hero__greeting { font-size: 1.55rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
html.dark .tw-hero__greeting, html[data-theme="dark"] .tw-hero__greeting { color: #f8fafc; }

.tw-hero__sub { font-size: .9rem; color: #475569; margin-top: .35rem; font-weight: 500; }
html.dark .tw-hero__sub, html[data-theme="dark"] .tw-hero__sub { color: #94a3b8; }

.tw-hero__date { font-size: .78rem; color: #4f46e5; margin-top: .2rem; font-weight: 600; }
html.dark .tw-hero__date, html[data-theme="dark"] .tw-hero__date { color: #818cf8; }

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
    transition: all 0.15s ease;
}
a.tw-hero__pill { cursor: pointer; transition: transform .12s, box-shadow .12s; }
a.tw-hero__pill:hover { transform: translateY(-1px); }

.tw-hero__pill--amber  { background: #fffbeb; color: #b45309; border-color: #fde68a; }
html.dark .tw-hero__pill--amber, html[data-theme="dark"] .tw-hero__pill--amber {
    background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3);
}

.tw-hero__pill--emerald{ background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
html.dark .tw-hero__pill--emerald, html[data-theme="dark"] .tw-hero__pill--emerald {
    background: rgba(16,185,129,0.15); color: #34d399; border-color: rgba(16,185,129,0.3);
}

.tw-hero__pill--indigo { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
html.dark .tw-hero__pill--indigo, html[data-theme="dark"] .tw-hero__pill--indigo {
    background: rgba(99,102,241,0.15); color: #818cf8; border-color: rgba(99,102,241,0.3);
}

.tw-hero__pill--rose   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
html.dark .tw-hero__pill--rose, html[data-theme="dark"] .tw-hero__pill--rose {
    background: rgba(244,63,94,0.15); color: #fb7185; border-color: rgba(244,63,94,0.3);
}

/* ── Continue Working / Spotlight CTA ── */
.tw-spotlight {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    padding: 1.5rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    box-shadow: 0 4px 16px -2px rgba(15,23,42,0.04);
    transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
html.dark .tw-spotlight,
html[data-theme="dark"] .tw-spotlight {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 16px -2px rgba(0,0,0,0.4);
}

.tw-spotlight--draft {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 50%, #eef2ff 100%);
    border: 1.5px solid #c7d2fe;
    box-shadow: 0 8px 24px -4px rgba(99,102,241,0.08);
}
html.dark .tw-spotlight--draft,
html[data-theme="dark"] .tw-spotlight--draft {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    border-color: rgba(99,102,241,0.4);
    box-shadow: 0 8px 24px -4px rgba(0,0,0,0.5);
}

.tw-spotlight--action {
    background: linear-gradient(135deg, #ffffff 0%, #fff1f2 100%);
    border: 1.5px solid #fecdd3;
    box-shadow: 0 8px 24px -4px rgba(244,63,94,0.08);
}
html.dark .tw-spotlight--action,
html[data-theme="dark"] .tw-spotlight--action {
    background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
    border-color: rgba(244,63,94,0.5);
    box-shadow: 0 8px 24px -4px rgba(244,63,94,0.2);
}

.tw-spotlight--review {
    background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
    border: 1.5px solid #bae6fd;
    box-shadow: 0 8px 24px -4px rgba(3,105,161,0.06);
}
html.dark .tw-spotlight--review,
html[data-theme="dark"] .tw-spotlight--review {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    border-color: rgba(99,102,241,0.35);
    box-shadow: 0 8px 24px -4px rgba(0,0,0,0.5);
}

.tw-spotlight--clean {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    text-align: center;
    justify-content: center;
    flex-direction: column;
    gap: .5rem;
}
html.dark .tw-spotlight--clean,
html[data-theme="dark"] .tw-spotlight--clean {
    background: #0f172a;
    border-color: #1e293b;
}

.tw-spotlight__tag {
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
.tw-spotlight__tag--amber { color: #b45309; background: #fef3c7; border: 1px solid #fde68a; padding: .25rem .65rem; border-radius: 99px; }
html.dark .tw-spotlight__tag--amber, html[data-theme="dark"] .tw-spotlight__tag--amber {
    color: #fbbf24; background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.3);
}

.tw-spotlight__tag--rose  { color: #be123c; background: #ffe4e6; border: 1px solid #fecdd3; padding: .25rem .65rem; border-radius: 99px; }
html.dark .tw-spotlight__tag--rose, html[data-theme="dark"] .tw-spotlight__tag--rose {
    color: #fb7185; background: rgba(244,63,94,0.15); border-color: rgba(244,63,94,0.3);
}

.tw-spotlight__tag--indigo{ color: #4338ca; background: #e0e7ff; border: 1px solid #c7d2fe; padding: .25rem .65rem; border-radius: 99px; }
html.dark .tw-spotlight__tag--indigo, html[data-theme="dark"] .tw-spotlight__tag--indigo {
    color: #818cf8; background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.3);
}

.tw-spotlight__title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: .4rem 0 .35rem; line-height: 1.3; }
html.dark .tw-spotlight__title, html[data-theme="dark"] .tw-spotlight__title { color: #f8fafc; }

.tw-spotlight__desc  { font-size: .84rem; color: #475569; line-height: 1.45; }
html.dark .tw-spotlight__desc, html[data-theme="dark"] .tw-spotlight__desc { color: #94a3b8; }

.tw-spotlight__meta  { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .78rem; color: #475569; align-items: center; margin-top: .35rem; }
html.dark .tw-spotlight__meta, html[data-theme="dark"] .tw-spotlight__meta { color: #94a3b8; }

.tw-spotlight__meta strong { color: #1e293b; }
html.dark .tw-spotlight__meta strong, html[data-theme="dark"] .tw-spotlight__meta strong { color: #f1f5f9; }

.tw-spotlight__status-badge { color: #059669; font-weight: 700; background: #ecfdf5; border: 1px solid #a7f3d0; padding: .15rem .5rem; border-radius: 99px; }
html.dark .tw-spotlight__status-badge, html[data-theme="dark"] .tw-spotlight__status-badge {
    color: #34d399; background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3);
}

.tw-spotlight__actions { display: flex; gap: .65rem; flex-wrap: wrap; align-items: center; }

/* ── KPI Cards ── */
.tw-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .tw-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .tw-kpi-grid { grid-template-columns: 1fr 1fr; } }

.tw-kpi {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.25rem 1.25rem 1rem;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: .3rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color .2s, transform .15s, box-shadow .2s;
}
html.dark .tw-kpi,
html[data-theme="dark"] .tw-kpi {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tw-kpi:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(99,102,241,.12);
}
html.dark .tw-kpi:hover,
html[data-theme="dark"] .tw-kpi:hover {
    border-color: #6366f1;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
}

.tw-kpi__icon  { font-size: 1.4rem; margin-bottom: .2rem; }
.tw-kpi__count { font-size: 2rem; font-weight: 900; line-height: 1; }
.tw-kpi__label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
html.dark .tw-kpi__label, html[data-theme="dark"] .tw-kpi__label { color: #94a3b8; }

.tw-kpi__desc  { font-size: .75rem; color: #64748b; margin-top: .15rem; }
html.dark .tw-kpi__desc, html[data-theme="dark"] .tw-kpi__desc { color: #64748b; }

.tw-kpi--indigo .tw-kpi__count  { color: #4f46e5; }
html.dark .tw-kpi--indigo .tw-kpi__count, html[data-theme="dark"] .tw-kpi--indigo .tw-kpi__count { color: #818cf8; }

.tw-kpi--amber  .tw-kpi__count  { color: #d97706; }
html.dark .tw-kpi--amber .tw-kpi__count, html[data-theme="dark"] .tw-kpi--amber .tw-kpi__count { color: #fbbf24; }

.tw-kpi--emerald .tw-kpi__count { color: #059669; }
html.dark .tw-kpi--emerald .tw-kpi__count, html[data-theme="dark"] .tw-kpi--emerald .tw-kpi__count { color: #34d399; }

.tw-kpi--slate  .tw-kpi__count  { color: #334155; }
html.dark .tw-kpi--slate .tw-kpi__count, html[data-theme="dark"] .tw-kpi--slate .tw-kpi__count { color: #cbd5e1; }

/* ── Assigned Courses Banner ── */
.tw-banner {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.1rem 1.35rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-banner,
html[data-theme="dark"] .tw-banner {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tw-banner__title { font-size: .88rem; font-weight: 800; color: #0f172a; }
html.dark .tw-banner__title, html[data-theme="dark"] .tw-banner__title { color: #f8fafc; }

.tw-banner__sub { font-size: .75rem; color: #64748b; margin-top: 2px; }
html.dark .tw-banner__sub, html[data-theme="dark"] .tw-banner__sub { color: #94a3b8; }

.tw-banner__badge {
    padding: .25rem .75rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 800;
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}
html.dark .tw-banner__badge, html[data-theme="dark"] .tw-banner__badge {
    background: rgba(99,102,241,0.15);
    color: #818cf8;
    border-color: rgba(99,102,241,0.3);
}

/* ── Quick Actions ── */
.tw-qa-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.1rem 1.35rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-qa-panel,
html[data-theme="dark"] .tw-qa-panel {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tw-qa-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-bottom: .75rem; }
html.dark .tw-qa-label, html[data-theme="dark"] .tw-qa-label { color: #94a3b8; }

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
    border: 1px solid transparent;
    text-decoration: none;
    transition: background .15s, transform .12s, box-shadow .15s, border-color .15s;
    cursor: pointer;
    white-space: nowrap;
}
.tw-qa-btn:hover { transform: translateY(-1px); }

.tw-qa-btn--primary {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    box-shadow: 0 4px 12px rgba(79,70,229,0.25);
}
.tw-qa-btn--primary:hover { background: #4338ca; border-color: #4338ca; }
html.dark .tw-qa-btn--primary, html[data-theme="dark"] .tw-qa-btn--primary {
    background: #6366f1;
    border-color: #6366f1;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
html.dark .tw-qa-btn--primary:hover, html[data-theme="dark"] .tw-qa-btn--primary:hover {
    background: #4f46e5;
    border-color: #4f46e5;
}

.tw-qa-btn--secondary {
    background: #f8fafc;
    color: #334155;
    border-color: #cbd5e1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.tw-qa-btn--secondary:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
html.dark .tw-qa-btn--secondary, html[data-theme="dark"] .tw-qa-btn--secondary {
    background: #1e293b;
    color: #cbd5e1;
    border-color: #334155;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
html.dark .tw-qa-btn--secondary:hover, html[data-theme="dark"] .tw-qa-btn--secondary:hover {
    background: #334155;
    color: #ffffff;
    border-color: #475569;
}

.tw-qa-btn--danger {
    background: #e11d48;
    color: #ffffff;
    border-color: #e11d48;
    box-shadow: 0 4px 12px rgba(225,29,72,0.25);
}
.tw-qa-btn--danger:hover { background: #be123c; border-color: #be123c; }
html.dark .tw-qa-btn--danger, html[data-theme="dark"] .tw-qa-btn--danger {
    background: #f43f5e;
    border-color: #fb7185;
}

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
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-panel,
html[data-theme="dark"] .tw-panel {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tw-panel__head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: #ffffff;
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-panel__head,
html[data-theme="dark"] .tw-panel__head {
    background: #0f172a;
    border-color: #1e293b;
}

.tw-panel__title { font-size: .88rem; font-weight: 800; color: #0f172a; }
html.dark .tw-panel__title, html[data-theme="dark"] .tw-panel__title { color: #f8fafc; }

.tw-panel__link  { font-size: .75rem; color: #4f46e5; font-weight: 700; text-decoration: none; }
.tw-panel__link:hover { text-decoration: underline; }
html.dark .tw-panel__link, html[data-theme="dark"] .tw-panel__link { color: #818cf8; }

/* ── Search & Filter Bar ── */
.tw-filter-bar {
    padding: .75rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    gap: .65rem;
    align-items: center;
    flex-wrap: wrap;
    background: #fafbfc;
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-filter-bar,
html[data-theme="dark"] .tw-filter-bar {
    background: #0b0f19;
    border-color: #1e293b;
}

.tw-search {
    flex: 1;
    min-width: 160px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: .5rem;
    color: #0f172a;
    font-size: .8rem;
    padding: .45rem .85rem;
    outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s, color .15s;
}
.tw-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
html.dark .tw-search,
html[data-theme="dark"] .tw-search {
    background: #020617;
    border-color: #1e293b;
    color: #f8fafc;
}

.tw-search-btn {
    padding: .4rem .9rem;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: .5rem;
    color: #475569;
    font-size: .75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.tw-search-btn:hover { background: #e2e8f0; color: #0f172a; }
html.dark .tw-search-btn,
html[data-theme="dark"] .tw-search-btn {
    background: #1e293b;
    border-color: #334155;
    color: #94a3b8;
}
html.dark .tw-search-btn:hover,
html[data-theme="dark"] .tw-search-btn:hover {
    background: #334155;
    color: #ffffff;
}

.tw-filter-pills { display: flex; gap: .4rem; flex-wrap: wrap; }
.tw-fpill {
    padding: .28rem .75rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    border: 1px solid #cbd5e1;
    color: #475569;
    background: #f1f5f9;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s, color .15s, border-color .15s;
    white-space: nowrap;
}
.tw-fpill:hover { background: #e2e8f0; color: #0f172a; }
html.dark .tw-fpill,
html[data-theme="dark"] .tw-fpill {
    background: #1e293b;
    border-color: #334155;
    color: #94a3b8;
}
html.dark .tw-fpill:hover,
html[data-theme="dark"] .tw-fpill:hover {
    background: #334155;
    color: #ffffff;
}

.tw-fpill--active { background: #4f46e5; color: #ffffff; border-color: #4f46e5; }
html.dark .tw-fpill--active,
html[data-theme="dark"] .tw-fpill--active {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
}

/* ── Table ── */
.tw-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.tw-table th {
    padding: .65rem 1rem;
    background: #f8fafc;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #475569;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
html.dark .tw-table th,
html[data-theme="dark"] .tw-table th {
    background: #0b0f19;
    color: #94a3b8;
    border-color: #1e293b;
}

.tw-table td {
    padding: .75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #0f172a;
}
html.dark .tw-table td,
html[data-theme="dark"] .tw-table td {
    border-color: #1e293b;
    color: #f8fafc;
}

.tw-table tbody tr:last-child td { border-bottom: none; }
.tw-table tbody tr:hover { background: #f8faff; }
html.dark .tw-table tbody tr:hover,
html[data-theme="dark"] .tw-table tbody tr:hover { background: #1e293b; }

.tw-title-link { font-weight: 700; color: #0f172a; text-decoration: none; display: block; transition: color .15s; }
.tw-title-link:hover { color: #4f46e5; }
html.dark .tw-title-link, html[data-theme="dark"] .tw-title-link { color: #f8fafc; }
html.dark .tw-title-link:hover, html[data-theme="dark"] .tw-title-link:hover { color: #818cf8; }

.tw-title-sub { font-size: .72rem; color: #64748b; margin-top: 2px; }
html.dark .tw-title-sub, html[data-theme="dark"] .tw-title-sub { color: #94a3b8; }

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
.tw-badge--draft     { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
html.dark .tw-badge--draft, html[data-theme="dark"] .tw-badge--draft { background: #1e293b; color: #94a3b8; border-color: #334155; }

.tw-badge--pending   { background: #fef3c7; color: #b45309; border-color: #fde68a; }
html.dark .tw-badge--pending, html[data-theme="dark"] .tw-badge--pending { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }

.tw-badge--approved  { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
html.dark .tw-badge--approved, html[data-theme="dark"] .tw-badge--approved { background: rgba(99,102,241,0.15); color: #818cf8; border-color: rgba(99,102,241,0.3); }

.tw-badge--published { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
html.dark .tw-badge--published, html[data-theme="dark"] .tw-badge--published { background: rgba(16,185,129,0.15); color: #34d399; border-color: rgba(16,185,129,0.3); }

.tw-badge--archived  { background: #f1f5f9; color: #64748b; border-color: #cbd5e1; }
html.dark .tw-badge--archived, html[data-theme="dark"] .tw-badge--archived { background: #1e293b; color: #64748b; border-color: #334155; }

.tw-badge--rejected  { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
html.dark .tw-badge--rejected, html[data-theme="dark"] .tw-badge--rejected { background: rgba(244,63,94,0.15); color: #fb7185; border-color: rgba(244,63,94,0.3); }

/* ── Action Buttons (inline) ── */
.tw-act { font-size: .72rem; font-weight: 700; text-decoration: none; border: none; background: none; cursor: pointer; padding: .25rem .55rem; border-radius: .35rem; transition: background .15s; white-space: nowrap; }
.tw-act--view    { color: #4f46e5; }
.tw-act--view:hover    { background: #eef2ff; }
html.dark .tw-act--view, html[data-theme="dark"] .tw-act--view { color: #818cf8; }
html.dark .tw-act--view:hover, html[data-theme="dark"] .tw-act--view:hover { background: rgba(99,102,241,0.15); }

.tw-act--submit  { color: #b45309; }
.tw-act--submit:hover  { background: #fef3c7; }
html.dark .tw-act--submit, html[data-theme="dark"] .tw-act--submit { color: #fbbf24; }
html.dark .tw-act--submit:hover, html[data-theme="dark"] .tw-act--submit:hover { background: rgba(245,158,11,0.15); }

.tw-act--dupe    { color: #475569; }
.tw-act--dupe:hover    { background: #f1f5f9; }
html.dark .tw-act--dupe, html[data-theme="dark"] .tw-act--dupe { color: #94a3b8; }
html.dark .tw-act--dupe:hover, html[data-theme="dark"] .tw-act--dupe:hover { background: #1e293b; }

.tw-act--restore { color: #4f46e5; border: 1px solid #c7d2fe; }
.tw-act--restore:hover { background: #eef2ff; }
html.dark .tw-act--restore, html[data-theme="dark"] .tw-act--restore { color: #818cf8; border-color: rgba(129,140,248,0.3); }
html.dark .tw-act--restore:hover, html[data-theme="dark"] .tw-act--restore:hover { background: rgba(99,102,241,0.15); }

/* ── Inline Action Popover Panel ── */
.tw-popover {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .375rem .6rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: .75rem;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}
html.dark .tw-popover,
html[data-theme="dark"] .tw-popover {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.6);
}

.tw-popover__text { font-size: .75rem; font-weight: 500; color: #334155; }
html.dark .tw-popover__text, html[data-theme="dark"] .tw-popover__text { color: #e2e8f0; }

.tw-popover__input {
    padding: .2rem .5rem;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: .5rem;
    font-size: .75rem;
    color: #0f172a;
    outline: none;
    width: 160px;
}
html.dark .tw-popover__input, html[data-theme="dark"] .tw-popover__input {
    background: #020617;
    border-color: #334155;
    color: #ffffff;
}

.tw-popover__cancel {
    padding: .2rem .5rem;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: .5rem;
    font-size: .75rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}
.tw-popover__cancel:hover { background: #e2e8f0; color: #0f172a; }
html.dark .tw-popover__cancel, html[data-theme="dark"] .tw-popover__cancel {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}
html.dark .tw-popover__cancel:hover, html[data-theme="dark"] .tw-popover__cancel:hover {
    background: #334155;
    color: #ffffff;
}

.tw-popover__submit {
    padding: .2rem .65rem;
    background: #4f46e5;
    border: none;
    border-radius: .5rem;
    font-size: .75rem;
    font-weight: 700;
    color: #ffffff;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(79,70,229,0.3);
    transition: all 0.15s ease;
}
.tw-popover__submit:hover { background: #4338ca; }
html.dark .tw-popover__submit, html[data-theme="dark"] .tw-popover__submit {
    background: #6366f1;
}
html.dark .tw-popover__submit:hover, html[data-theme="dark"] .tw-popover__submit:hover {
    background: #4f46e5;
}

/* ── Sidebar Widgets ── */
.tw-widget {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-widget,
html[data-theme="dark"] .tw-widget {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tw-widget + .tw-widget { margin-top: 1rem; }

/* ── Progress Widget ── */
.tw-prog-item { display: flex; align-items: center; gap: .75rem; padding: .65rem 1.25rem; }
.tw-prog-item:not(:last-child) { border-bottom: 1px solid #f1f5f9; }
html.dark .tw-prog-item:not(:last-child), html[data-theme="dark"] .tw-prog-item:not(:last-child) {
    border-color: #1e293b;
}

.tw-prog-label { font-size: .75rem; color: #475569; width: 90px; flex-shrink: 0; font-weight: 600; }
html.dark .tw-prog-label, html[data-theme="dark"] .tw-prog-label { color: #94a3b8; }

.tw-prog-bar-wrap { flex: 1; background: #e2e8f0; border-radius: 99px; height: 6px; overflow: hidden; }
html.dark .tw-prog-bar-wrap, html[data-theme="dark"] .tw-prog-bar-wrap { background: #1e293b; }

.tw-prog-bar { height: 100%; border-radius: 99px; transition: width .5s ease; }
.tw-prog-count { font-size: .75rem; font-weight: 800; color: #0f172a; width: 32px; text-align: right; }
html.dark .tw-prog-count, html[data-theme="dark"] .tw-prog-count { color: #f8fafc; }

/* ── Recent Activity ── */
.tw-activity-item {
    display: flex;
    gap: .85rem;
    align-items: flex-start;
    padding: .75rem 1.25rem;
    text-decoration: none;
    transition: background .15s;
    border-bottom: 1px solid #f1f5f9;
}
.tw-activity-item:hover { background: #f8faff; }
html.dark .tw-activity-item,
html[data-theme="dark"] .tw-activity-item {
    border-color: #1e293b;
}
html.dark .tw-activity-item:hover,
html[data-theme="dark"] .tw-activity-item:hover {
    background: #1e293b;
}

.tw-activity-title { font-size: .78rem; font-weight: 700; color: #0f172a; }
html.dark .tw-activity-title, html[data-theme="dark"] .tw-activity-title { color: #f8fafc; }

.tw-activity-sub { font-size: .7rem; color: #64748b; margin-top: 2px; }
html.dark .tw-activity-sub, html[data-theme="dark"] .tw-activity-sub { color: #64748b; }

/* ── Empty States ── */
.tw-empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .65rem;
}
.tw-empty__icon { font-size: 2.25rem; opacity: .7; }
.tw-empty__title { font-size: .92rem; font-weight: 800; color: #0f172a; }
html.dark .tw-empty__title, html[data-theme="dark"] .tw-empty__title { color: #f8fafc; }

.tw-empty__sub { font-size: .78rem; color: #64748b; }
html.dark .tw-empty__sub, html[data-theme="dark"] .tw-empty__sub { color: #94a3b8; }

.tw-empty__cta {
    margin-top: .5rem;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1.1rem;
    border-radius: .5rem;
    background: #4f46e5;
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    text-decoration: none;
    transition: background .15s;
}
.tw-empty__cta:hover { background: #4338ca; }
html.dark .tw-empty__cta, html[data-theme="dark"] .tw-empty__cta { background: #6366f1; }
html.dark .tw-empty__cta:hover, html[data-theme="dark"] .tw-empty__cta:hover { background: #4f46e5; }

/* ── Modal ── */
.tw-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.6);
    backdrop-filter: blur(4px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.tw-modal {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    width: 100%;
    max-width: 520px;
    max-height: 80vh;
    overflow-y: auto;
    padding: 2rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tw-modal,
html[data-theme="dark"] .tw-modal {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6);
}

.tw-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; }
html.dark .tw-modal__head, html[data-theme="dark"] .tw-modal__head { border-color: #1e293b; }

.tw-modal__title { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
html.dark .tw-modal__title, html[data-theme="dark"] .tw-modal__title { color: #f8fafc; }

.tw-modal__close { color: #64748b; font-size: 1.35rem; cursor: pointer; background: none; border: none; line-height: 1; transition: color .15s; }
.tw-modal__close:hover { color: #0f172a; }
html.dark .tw-modal__close:hover, html[data-theme="dark"] .tw-modal__close:hover { color: #ffffff; }

.tw-modal__body-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: .5rem; }
html.dark .tw-modal__body-title, html[data-theme="dark"] .tw-modal__body-title { color: #f8fafc; }

.tw-modal__body-desc { font-size: .85rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.5; }
html.dark .tw-modal__body-desc, html[data-theme="dark"] .tw-modal__body-desc { color: #94a3b8; }

.tw-form-group { margin-bottom: 1.1rem; }
.tw-form-label { display: block; font-size: .78rem; font-weight: 700; color: #334155; margin-bottom: .4rem; }
html.dark .tw-form-label, html[data-theme="dark"] .tw-form-label { color: #cbd5e1; }

.tw-form-input, .tw-form-select, .tw-form-textarea {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: .6rem;
    color: #0f172a;
    font-size: .85rem;
    padding: .6rem .9rem;
    outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s, color .15s;
    box-sizing: border-box;
}
.tw-form-input:focus, .tw-form-select:focus, .tw-form-textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.tw-form-select { appearance: none; }
.tw-form-textarea { resize: vertical; min-height: 80px; }

html.dark .tw-form-input, html[data-theme="dark"] .tw-form-input,
html.dark .tw-form-select, html[data-theme="dark"] .tw-form-select,
html.dark .tw-form-textarea, html[data-theme="dark"] .tw-form-textarea {
    background: #020617;
    border-color: #334155;
    color: #f8fafc;
}

.tw-form-submit {
    width: 100%;
    padding: .75rem;
    background: #4f46e5;
    color: #fff;
    font-size: .88rem;
    font-weight: 700;
    border: none;
    border-radius: .65rem;
    cursor: pointer;
    transition: background .15s;
    box-shadow: 0 4px 12px rgba(79,70,229,0.25);
}
.tw-form-submit:hover { background: #4338ca; }
html.dark .tw-form-submit, html[data-theme="dark"] .tw-form-submit {
    background: #6366f1;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
html.dark .tw-form-submit:hover, html[data-theme="dark"] .tw-form-submit:hover { background: #4f46e5; }

/* ── Pagination ── */
.tw-pager { padding: .85rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; background: #ffffff; }
html.dark .tw-pager, html[data-theme="dark"] .tw-pager {
    border-color: #1e293b;
    background: #0f172a;
}
.tw-pager a { font-size: .75rem; color: #4f46e5; font-weight: 700; text-decoration: none; }
html.dark .tw-pager a, html[data-theme="dark"] .tw-pager a { color: #818cf8; }
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
         CONTINUE WORKING SPOTLIGHT SECTION (HERO WORKFLOW STATE)
         ══════════════════════════════════════════════ --}}
    @php
        $pendingRepoRevCount = $pendingRepoRevCount ?? 0;
        $actionNeededCount = ($draftQuestionBanks ?? 0) + ($draftAssessments ?? 0) + ($needsRevisionAssessments ?? 0) + ($pendingRepoRevCount ?? 0);
        $inProgressReviewCount = ($pendingApprovalQuestionBanks ?? 0) + ($pendingAssessments ?? 0);
    @endphp

    @if($pendingRepoRevCount > 0)
    {{-- STATE A: ACTIONABLE REPOSITORY REVISION SPOTLIGHT --}}
    <div class="tw-spotlight tw-spotlight--action">
        <div>
            <div class="tw-spotlight__tag tw-spotlight__tag--rose">🛠 Action Required • Repository Revision Task</div>
            <h3 class="tw-spotlight__title">You have work requiring attention</h3>
            <div class="tw-spotlight__desc">You have {{ $pendingRepoRevCount }} repository revision task(s) requiring your immediate attention or revision.</div>
        </div>
        <div>
            <a href="{{ route('teacher.repository-revisions.index') }}" class="tw-qa-btn tw-qa-btn--danger">
                🛠 Open Revision Task Center →
            </a>
        </div>
    </div>
    @elseif(isset($latestDraftBank) && $latestDraftBank)
    {{-- STATE B: CONTINUE WORKING DRAFT SPOTLIGHT --}}
    <div class="tw-spotlight tw-spotlight--draft">
        <div>
            <div class="tw-spotlight__tag tw-spotlight__tag--amber">⚡ Continue Working • Question Bank Draft</div>
            <h3 class="tw-spotlight__title">{{ $latestDraftBank->title }}</h3>
            <div class="tw-spotlight__meta">
                <span>📝 <strong>{{ $latestDraftBank->questions->count() }}</strong> Questions</span>
                <span>🏷 <strong>{{ is_object($latestDraftBank->test_type) ? $latestDraftBank->test_type->label() : strtoupper($latestDraftBank->test_type?->value ?? 'General') }}</strong></span>
                <span>📌 Version <strong>{{ $latestDraftBank->current_version ?? '1.0' }}</strong></span>
                <span>🕒 Last edited {{ $latestDraftBank->updated_at?->diffForHumans() }}</span>
                <span class="tw-spotlight__status-badge">● Autosave Active</span>
            </div>
        </div>
        <div class="tw-spotlight__actions">
            <a href="{{ route('admin.question-banks.show', $latestDraftBank->id) }}" class="tw-qa-btn tw-qa-btn--primary">
                ✏️ Resume Editing →
            </a>
            <a href="{{ route('admin.question-banks.index', ['status' => 'draft']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                📂 Continue Draft
            </a>
        </div>
    </div>
    @elseif(isset($latestDraftTest) && $latestDraftTest)
    {{-- STATE B2: CONTINUE WORKING ASSESSMENT DRAFT SPOTLIGHT --}}
    <div class="tw-spotlight tw-spotlight--draft">
        <div>
            <div class="tw-spotlight__tag tw-spotlight__tag--amber">⚡ Continue Working • Assessment Draft</div>
            <h3 class="tw-spotlight__title">{{ $latestDraftTest->title }}</h3>
            <div class="tw-spotlight__meta">
                <span>📝 <strong>{{ $latestDraftTest->sections->sum(fn($s) => $s->testQuestions->count()) }}</strong> Questions</span>
                <span>🏷 <strong>{{ is_object($latestDraftTest->test_type) ? $latestDraftTest->test_type->label() : strtoupper($latestDraftTest->test_type?->value ?? 'General') }}</strong></span>
                <span>📌 Status <strong style="text-transform:uppercase;">{{ is_object($latestDraftTest->status) ? $latestDraftTest->status->value : $latestDraftTest->status }}</strong></span>
                <span>🕒 Last edited {{ $latestDraftTest->updated_at?->diffForHumans() }}</span>
                <span class="tw-spotlight__status-badge">● Assigned Workspace</span>
            </div>
        </div>
        <div class="tw-spotlight__actions">
            <a href="{{ route('teacher.tests.show', $latestDraftTest->id) }}" class="tw-qa-btn tw-qa-btn--primary">
                ✏️ Author Assessment →
            </a>
            <a href="{{ route('admin.tests.index', ['status' => 'draft']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                📂 All Assessment Drafts
            </a>
        </div>
    </div>
    @elseif($inProgressReviewCount > 0)
    {{-- STATE C: INSTITUTIONAL REVIEW IN PROGRESS (ENTITY-AWARE) --}}
    @php
        $onlyPendingAssessments = ($pendingAssessments ?? 0) > 0 && ($pendingApprovalQuestionBanks ?? 0) === 0;
        $onlyPendingBanks = ($pendingApprovalQuestionBanks ?? 0) > 0 && ($pendingAssessments ?? 0) === 0;
    @endphp
    <div class="tw-spotlight tw-spotlight--review">
        <div>
            @if($onlyPendingAssessments)
                <div class="tw-spotlight__tag tw-spotlight__tag--indigo">⏳ ASSESSMENT REVIEW IN PROGRESS</div>
                <h3 class="tw-spotlight__title">Assessment Submitted for Review</h3>
                <div class="tw-spotlight__desc">You have {{ $pendingAssessments }} assessment(s) awaiting institutional review.</div>
            @elseif($onlyPendingBanks)
                <div class="tw-spotlight__tag tw-spotlight__tag--indigo">⏳ REPOSITORY REVIEW IN PROGRESS</div>
                <h3 class="tw-spotlight__title">Repository Submitted for Review</h3>
                <div class="tw-spotlight__desc">You have {{ $pendingApprovalQuestionBanks }} repository bank(s) awaiting institutional review.</div>
            @else
                <div class="tw-spotlight__tag tw-spotlight__tag--indigo">⏳ INSTITUTIONAL REVIEW IN PROGRESS</div>
                <h3 class="tw-spotlight__title">You have work requiring attention</h3>
                <div class="tw-spotlight__desc">
                    You have {{ $pendingApprovalQuestionBanks }} repository bank(s) and {{ $pendingAssessments }} assessment(s) awaiting institutional review.
                </div>
            @endif
        </div>
        <div class="tw-spotlight__actions">
            @if($onlyPendingAssessments)
                <a href="{{ route('admin.tests.index', ['status' => 'pending_approval']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                    📋 View Submitted Assessments →
                </a>
            @elseif($onlyPendingBanks)
                <a href="{{ route('admin.question-banks.index', ['status' => 'pending_approval']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                    📋 View Submitted Repositories →
                </a>
            @else
                <a href="{{ route('admin.tests.index', ['status' => 'pending_approval']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                    📋 View Submitted Assessments →
                </a>
                <a href="{{ route('admin.question-banks.index', ['status' => 'pending_approval']) }}" class="tw-qa-btn tw-qa-btn--secondary">
                    📁 View Submitted Repositories →
                </a>
            @endif
        </div>
    </div>
    @else
    {{-- STATE D: TRUE ALL CAUGHT UP --}}
    <div class="tw-spotlight tw-spotlight--clean">
        <div style="font-size:2.25rem;">🎉</div>
        <div class="tw-spotlight__title">You're all caught up.</div>
        <div class="tw-spotlight__desc" style="max-width:420px;margin:0 auto;">No unfinished authoring work. Create a new Question Bank or contribute to Institutional Library.</div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         SECTION 2 — SMART KPI CARDS (WORKFLOW SEPARATION)
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
        @php
            $totalPendingReview = ($pendingApprovalQuestionBanks ?? 0) + ($pendingAssessments ?? 0);
        @endphp
        @if($totalPendingReview > 0)
        @php
            $pendingRoute = ($pendingAssessments > 0 && ($pendingApprovalQuestionBanks ?? 0) === 0)
                ? route('admin.tests.index', ['status' => 'pending_approval'])
                : route('admin.question-banks.index', ['status' => 'pending_approval']);
        @endphp
        <a href="{{ $pendingRoute }}" class="tw-kpi tw-kpi--amber">
            <div class="tw-kpi__icon">⏳</div>
            <div class="tw-kpi__count">{{ $totalPendingReview }}</div>
            <div class="tw-kpi__label">Awaiting Approval</div>
            <div class="tw-kpi__desc">
                @if(($pendingAssessments ?? 0) > 0 && ($pendingApprovalQuestionBanks ?? 0) > 0)
                    {{ $pendingApprovalQuestionBanks }} bank(s), {{ $pendingAssessments }} assessment(s)
                @elseif(($pendingAssessments ?? 0) > 0)
                    {{ $pendingAssessments }} assessment(s) awaiting review
                @else
                    Repositories Submitted for Review
                @endif
            </div>
        </a>
        @else
        <a href="javascript:void(0)" onclick="openNoPendingApprovalModal()" class="tw-kpi tw-kpi--amber">
            <div class="tw-kpi__icon">⏳</div>
            <div class="tw-kpi__count">0</div>
            <div class="tw-kpi__label">Awaiting Approval</div>
            <div class="tw-kpi__desc">No Repositories Awaiting Review</div>
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

        @if(($draftTotal ?? 0) > 0)
        @php
            $draftRoute = ($draftAssessments > 0 && ($draftQuestionBanks ?? 0) === 0)
                ? route('admin.tests.index', ['status' => 'draft'])
                : route('admin.question-banks.index', ['status' => 'draft']);
        @endphp
        <a href="{{ $draftRoute }}" class="tw-kpi tw-kpi--slate">
            <div class="tw-kpi__icon">✏️</div>
            <div class="tw-kpi__count">{{ $draftTotal }}</div>
            <div class="tw-kpi__label">Drafts</div>
            <div class="tw-kpi__desc">
                @if(($draftAssessments ?? 0) > 0 && ($draftQuestionBanks ?? 0) > 0)
                    {{ $draftQuestionBanks }} bank(s), {{ $draftAssessments }} assessment(s)
                @elseif(($draftAssessments ?? 0) > 0)
                    {{ $draftAssessments }} assessment draft(s) in progress
                @else
                    In progress, not yet submitted
                @endif
            </div>
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
    <div class="tw-banner">
        <div style="display:flex;align-items:center;gap:.85rem;">
            <span style="font-size:1.5rem;">🎓</span>
            <div>
                <div class="tw-banner__title">Assigned Courses &amp; Student Progress</div>
                <div class="tw-banner__sub">Academic course assignments, student roster, and placement test analytics.</div>
            </div>
        </div>
        <span class="tw-banner__badge">
            COMING SOON
        </span>
    </div>

    {{-- ══════════════════════════════════════════════
         SECTION 3 — QUICK ACTIONS STRIP
         ══════════════════════════════════════════════ --}}
    <div class="tw-qa-panel">
        <div class="tw-qa-label">Quick Actions</div>
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
                        <button type="submit" class="tw-search-btn">
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
                                    'published'                => 'tw-badge--published',
                                    'approved'                 => 'tw-badge--approved',
                                    'pending_approval',
                                    'pending_archive_approval',
                                    'pending_restore_approval' => 'tw-badge--pending',
                                    'archived'                 => 'tw-badge--archived',
                                    'rejected'                 => 'tw-badge--rejected',
                                    default                    => 'tw-badge--draft',
                                };
                                $statusLabel = match($status) {
                                    'pending_approval'         => 'Pending',
                                    'pending_archive_approval' => 'Arch. Pending',
                                    'pending_restore_approval' => 'Pending Restore',
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
                                <td style="text-align:center;font-weight:800;color:#4f46e5;font-size:.85rem;">
                                    {{ $bank->questions->count() }}
                                </td>
                                {{-- Updated --}}
                                <td style="color:#64748b;font-size:.75rem;">
                                    {{ $bank->updated_at?->diffForHumans() }}
                                </td>
                                {{-- Actions -- only what the current status permits --}}
                                <td style="text-align:right;">
                                    <div style="display:flex;gap:.25rem;justify-content:flex-end;align-items:center;flex-wrap:wrap;">
                                        @php
                                             $isEditableState = in_array($status, ['draft', 'rejected', 'revision_requested', 'needs_revision', null], true);
                                             $isDuplicableState = in_array($status, ['draft', 'rejected', 'revision_requested', 'needs_revision', 'published', null], true);
                                        @endphp

                                        {{-- View/Author --}}
                                        <a href="{{ route('admin.question-banks.show', $bank->id) }}"
                                           class="tw-act tw-act--view">{{ $isEditableState ? '✏️ Author' : '👁 View' }}</a>

                                        {{-- Request Restore Action for Archived repositories (Owner Teacher or Super Admin ONLY) --}}
                                        @if($status === 'archived' && (Auth::user()?->hasRole('super-admin') || (Auth::user()?->hasRole('teacher') && $bank->created_by === Auth::user()->id)))
                                        <form method="POST" action="{{ route('admin.question-banks.request-restore', $bank->id) }}" style="display:inline;">
                                            @csrf
                                            <button type="button" id="restore-trigger-btn-dash-{{ $bank->id }}" onclick="document.getElementById('inline-restore-panel-dash-{{ $bank->id }}').classList.remove('hidden'); this.classList.add('hidden');" class="tw-act tw-act--restore" title="Request restoration for Super Admin approval">↻ Request Restore</button>

                                            <div id="inline-restore-panel-dash-{{ $bank->id }}" class="hidden tw-popover">
                                                <span class="tw-popover__text">
                                                    Request restoration of '{{ $bank->title }}' for Super Admin approval?
                                                </span>
                                                <input type="text" name="reason" placeholder="Restoration reason..." class="tw-popover__input">
                                                <button type="button" onclick="document.getElementById('inline-restore-panel-dash-{{ $bank->id }}').classList.add('hidden'); document.getElementById('restore-trigger-btn-dash-{{ $bank->id }}').classList.remove('hidden');" class="tw-popover__cancel">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="tw-popover__submit">
                                                    Request Restore
                                                </button>
                                            </div>
                                        </form>
                                        @endif

                                        {{-- Submit — only draft or rejected --}}
                                        @if($isEditableState)
                                        <form method="POST"
                                              action="{{ route('admin.question-banks.submit', $bank->id) }}"
                                              style="display:inline;">
                                            @csrf
                                            <button type="button" id="submit-trigger-btn-dash-{{ $bank->id }}" onclick="document.getElementById('inline-submit-panel-dash-{{ $bank->id }}').classList.remove('hidden'); this.classList.add('hidden');" class="tw-act tw-act--submit">📤 Submit</button>

                                            <div id="inline-submit-panel-dash-{{ $bank->id }}" class="hidden tw-popover">
                                                <span class="tw-popover__text">
                                                    Submit '{{ $bank->title }}' for Super Admin approval?
                                                </span>
                                                <button type="button" onclick="document.getElementById('inline-submit-panel-dash-{{ $bank->id }}').classList.add('hidden'); document.getElementById('submit-trigger-btn-dash-{{ $bank->id }}').classList.remove('hidden');" class="tw-popover__cancel">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="tw-popover__submit">
                                                    Submit for Approval
                                                </button>
                                            </div>
                                        </form>
                                        @endif

                                        {{-- Duplicate --}}
                                        @if($isDuplicableState)
                                        <form method="POST"
                                              action="{{ route('admin.question-banks.duplicate', $bank->id) }}"
                                              style="display:inline;"
                                              onsubmit="event.preventDefault(); iapConfirm({ title: 'Duplicate Question Bank?', message: 'Duplicate \'{{ addslashes($bank->title) }}\'?', confirmText: 'Duplicate Repository', variant: 'info', form: this });">
                                            @csrf
                                            <button type="submit" class="tw-act tw-act--dupe">⧉ Dupe</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tw-pager">
                    <a href="{{ route('admin.question-banks.index') }}">
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
                        ['label' => 'Published',      'count' => $publishedQuestionBanks, 'color' => '#059669'],
                        ['label' => 'Approved',       'count' => $approvedQuestionBanks + $approvedAssessments, 'color' => '#4f46e5'],
                        ['label' => 'Pending',        'count' => $pendingApprovalQuestionBanks + $pendingAssessments, 'color' => '#d97706'],
                        ['label' => 'Needs Revision', 'count' => $needsRevisionAssessments, 'color' => '#f59e0b'],
                        ['label' => 'Draft',          'count' => $draftQuestionBanks + $draftAssessments, 'color' => '#64748b'],
                        ['label' => 'Archived',       'count' => $archivedQuestionBanks,  'color' => '#94a3b8'],
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
                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="tw-activity-item">
                        <span style="font-size:1.1rem;margin-top:1px;">{{ $actIcon }}</span>
                        <div>
                            <div class="tw-activity-title">
                                {{ Str::limit($bank->title, 38) }}
                            </div>
                            <div class="tw-activity-sub">
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
        <div class="tw-modal__body-title">Nothing Here Yet</div>
        <p class="tw-modal__body-desc">There are currently no items in this category.</p>
        <button type="button" onclick="closeTwNothingModal()" class="tw-form-submit" style="margin-top:0;">Close</button>
    </div>
</div>

{{-- No Draft Modal (PART 8) --}}
<div id="tw-no-draft-modal" class="tw-modal-backdrop" style="display:none;" onclick="closeNoDraftModal(event)">
    <div class="tw-modal" onclick="event.stopPropagation()" style="text-align:center;max-width:440px;">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">📝</div>
        <div class="tw-modal__body-title">No draft Question Banks.</div>
        <p class="tw-modal__body-desc">You currently have no active drafts in progress.</p>
        <div style="display:flex;gap:.75rem;">
            <button type="button" onclick="closeNoDraftModal()" class="tw-qa-btn tw-qa-btn--secondary" style="flex:1;">Close</button>
            <button type="button" onclick="closeNoDraftModal();openCreateModal();" class="tw-form-submit" style="flex:1.5;margin-top:0;">Create Draft</button>
        </div>
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
