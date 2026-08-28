@extends('layouts.admin')

@section('title', 'Institutional Media Repository — iC.edu Platform')

@push('styles')
<style>
.imr-page { display:flex; flex-direction:column; gap:1.75rem; }

.imr-hero {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 50%, #eef2ff 100%);
    border: 1px solid #dbe0fc;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    box-shadow: 0 4px 16px -2px rgba(99,102,241,0.06);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .imr-hero, html[data-theme="dark"] .imr-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border-color: #1e293b;
    box-shadow: 0 4px 16px -2px rgba(0,0,0,0.4);
}
.imr-hero__title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0 0 .3rem; }
html.dark .imr-hero__title, html[data-theme="dark"] .imr-hero__title { color: #fff; }
.imr-hero__sub   { font-size: .85rem; color: #334155; font-weight: 500; margin: 0; }
html.dark .imr-hero__sub, html[data-theme="dark"] .imr-hero__sub { color: #94a3b8; }

/* PART 1: CLICKABLE SUMMARY CARDS */
.imr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
}
@media (max-width: 1200px) { .imr-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .imr-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.imr-kpi {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
    text-decoration: none;
    transition: transform .15s, border-color .15s, box-shadow .15s, background .15s;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(15,23,42,0.04);
}
html.dark .imr-kpi, html[data-theme="dark"] .imr-kpi {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: none;
}
.imr-kpi:hover {
    transform: translateY(-2px);
    border-color: #4f46e5;
    box-shadow: 0 8px 24px rgba(99,102,241,.12);
}
.imr-kpi--active {
    border-color: #4f46e5;
    background: #f8faff;
}
html.dark .imr-kpi--active, html[data-theme="dark"] .imr-kpi--active {
    border-color: #6366f1;
    background: linear-gradient(180deg, #1e1b4b 0%, #0f172a 100%);
}
.imr-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.imr-kpi__label { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #334155; }
html.dark .imr-kpi__label, html[data-theme="dark"] .imr-kpi__label { color: #94a3b8; }

.imr-filter-bar {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.04);
}
html.dark .imr-filter-bar, html[data-theme="dark"] .imr-filter-bar {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: none;
}
.imr-exam-pills { display: flex; gap: .5rem; flex-wrap: wrap; }
.imr-exam-btn {
    padding: .45rem .95rem;
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 700;
    text-decoration: none;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    transition: all .15s;
}
html.dark .imr-exam-btn, html[data-theme="dark"] .imr-exam-btn {
    background: #1e293b;
    color: #94a3b8;
    border-color: #334155;
}
.imr-exam-btn--active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
html.dark .imr-exam-btn--active, html[data-theme="dark"] .imr-exam-btn--active { background: #6366f1; color: #fff; border-color: #6366f1; }

.imr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
}

.imr-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 1.1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color .2s, transform .15s, box-shadow .2s;
    box-shadow: 0 2px 6px rgba(15,23,42,0.04);
}
html.dark .imr-card, html[data-theme="dark"] .imr-card {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: none;
}
.imr-card:hover { border-color: #4f46e5; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
html.dark .imr-card:hover, html[data-theme="dark"] .imr-card:hover { border-color: #6366f1; }

.imr-card__preview {
    height: 160px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}
html.dark .imr-card__preview, html[data-theme="dark"] .imr-card__preview {
    background: #080f1d;
    border-bottom-color: #1e293b;
}
.imr-card__preview img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform .2s; }
.imr-card__preview img:hover { transform: scale(1.04); }

.imr-card__body { padding: 1.1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
.imr-card__title { font-size: .92rem; font-weight: 800; color: #0f172a; line-height: 1.35; text-decoration: none; }
html.dark .imr-card__title, html[data-theme="dark"] .imr-card__title { color: #f1f5f9; }
.imr-card__title:hover { color: #4f46e5; }
html.dark .imr-card__title:hover, html[data-theme="dark"] .imr-card__title:hover { color: #818cf8; }
.imr-card__sub { font-size: .75rem; color: #334155; font-weight: 600; }
html.dark .imr-card__sub, html[data-theme="dark"] .imr-card__sub { color: #94a3b8; }

.imr-tag { padding: .22rem .6rem; border-radius: 99px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; display: inline-block; white-space: nowrap; }
.imr-tag--toefl   { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
html.dark .imr-tag--toefl, html[data-theme="dark"] .imr-tag--toefl { background: rgba(129,140,248,.15); color: #818cf8; border-color: rgba(129,140,248,.3); }
.imr-tag--toeic   { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
html.dark .imr-tag--toeic, html[data-theme="dark"] .imr-tag--toeic { background: rgba(52,211,153,.15); color: #34d399; border-color: rgba(52,211,153,.3); }
.imr-tag--ielts   { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
html.dark .imr-tag--ielts, html[data-theme="dark"] .imr-tag--ielts { background: rgba(251,113,133,.15); color: #fb7185; border-color: rgba(251,113,133,.3); }
.imr-tag--general { background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; }
html.dark .imr-tag--general, html[data-theme="dark"] .imr-tag--general { background: rgba(148,163,184,.15); color: #94a3b8; border-color: rgba(148,163,184,.3); }

.imr-foot { padding: .75rem 1rem; background: #f8fafc; border-top: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; gap: .35rem; flex-wrap: wrap; }
html.dark .imr-foot, html[data-theme="dark"] .imr-foot { background: #0b1329; border-top-color: #1e293b; }
.imr-btn-neutral { padding: .35rem .6rem; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b; border-radius: .45rem; font-size: .72rem; font-weight: 700; text-decoration: none; transition: all .15s ease; display: inline-flex; align-items: center; gap: .3rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
html.dark .imr-btn-neutral, html[data-theme="dark"] .imr-btn-neutral { background: #1e293b; border-color: #334155; color: #cbd5e1; box-shadow: none; }
.imr-btn-neutral:hover { background: #f1f5f9; border-color: #94a3b8; color: #0f172a; }
html.dark .imr-btn-neutral:hover, html[data-theme="dark"] .imr-btn-neutral:hover { background: #334155; color: #fff; }

.imr-btn-edit { padding: .35rem .6rem; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; border-radius: .45rem; font-size: .72rem; font-weight: 800; text-decoration: none; transition: all .15s ease; display: inline-flex; align-items: center; gap: .3rem; box-shadow: 0 1px 2px rgba(99,102,241,0.05); }
html.dark .imr-btn-edit, html[data-theme="dark"] .imr-btn-edit { background: #1e1b4b; border-color: #4338ca; color: #a5b4fc; box-shadow: none; }
.imr-btn-edit:hover { background: #e0e7ff; color: #312e81; border-color: #818cf8; }
html.dark .imr-btn-edit:hover, html[data-theme="dark"] .imr-btn-edit:hover { background: #312e81; color: #fff; }

.imr-btn-tracker { background: none; border: none; color: #4338ca; font-size: .72rem; font-weight: 800; cursor: pointer; padding: .2rem .4rem; border-radius: .35rem; transition: color .15s ease; display: inline-flex; align-items: center; gap: .3rem; }
html.dark .imr-btn-tracker, html[data-theme="dark"] .imr-btn-tracker { color: #818cf8; }
.imr-btn-tracker:hover { color: #312e81; text-decoration: underline; }
html.dark .imr-btn-tracker:hover, html[data-theme="dark"] .imr-btn-tracker:hover { color: #a5b4fc; }

.imr-modal-bg { position: fixed; inset: 0; background: rgba(15,23,42,.75); backdrop-filter: blur(6px); z-index: 990; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
.imr-modal { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 1.25rem; max-width: 650px; max-height: 85vh; overflow-y: auto; width: 100%; padding: 2rem; box-shadow: 0 24px 64px rgba(0,0,0,.15); }
html.dark .imr-modal, html[data-theme="dark"] .imr-modal { background: #0f172a; border-color: #1e293b; box-shadow: 0 24px 64px rgba(0,0,0,.7); }
</style>
@endpush

@section('content')
<div class="imr-page">

    {{-- Hero Header --}}
    <div class="imr-hero">
        <div>
            <h1 class="imr-hero__title">Institutional Media Repository</h1>
            <p class="imr-hero__sub">Institutional single source of truth for Question Media Library and reusable audio, images, PDF documents, and passage texts across iC.edu.</p>
        </div>
        <div>
            @if(auth()->user()->hasRole('teacher') || auth()->user()->hasRole('super-admin'))
            <button type="button" onclick="openUploadModal()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Upload Institutional Asset
            </button>
            @endif
        </div>
    </div>

    {{-- Status Alerts --}}
    @if(session('status'))
    <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:.85rem 1.2rem;border-radius:.75rem;font-size:.82rem;font-weight:700;">
        ✅ {{ session('status') }}
    </div>
    @endif

    {{-- PART 1: CLICKABLE SUMMARY CARDS --}}
    <div class="imr-kpi-grid">
        <a href="{{ route('admin.media.index') }}" class="imr-kpi {{ !request('type') && !request('filter') ? 'imr-kpi--active' : '' }}">
            <div class="imr-kpi__count" style="color:#818cf8;">{{ $healthMetrics['total_assets'] }}</div>
            <div class="imr-kpi__label">Total Assets</div>
        </a>
        <a href="{{ route('admin.media.index', array_merge(request()->except('page'), ['type' => 'image', 'filter' => null])) }}" class="imr-kpi {{ request('type') === 'image' ? 'imr-kpi--active' : '' }}">
            <div class="imr-kpi__count" style="color:#34d399;">{{ $healthMetrics['images_count'] }}</div>
            <div class="imr-kpi__label">Images</div>
        </a>
        <a href="{{ route('admin.media.index', array_merge(request()->except('page'), ['type' => 'audio', 'filter' => null])) }}" class="imr-kpi {{ request('type') === 'audio' ? 'imr-kpi--active' : '' }}">
            <div class="imr-kpi__count" style="color:#a78bfa;">{{ $healthMetrics['audio_count'] }}</div>
            <div class="imr-kpi__label">Audio Clips</div>
        </a>
        <a href="{{ route('admin.media.index', array_merge(request()->except('page'), ['type' => 'pdf', 'filter' => null])) }}" class="imr-kpi {{ request('type') === 'pdf' ? 'imr-kpi--active' : '' }}">
            <div class="imr-kpi__count" style="color:#fbbf24;">{{ $healthMetrics['pdf_count'] }}</div>
            <div class="imr-kpi__label">PDF Documents</div>
        </a>
        <a href="{{ route('admin.media.index', array_merge(request()->except('page'), ['type' => 'passage', 'filter' => null])) }}" class="imr-kpi {{ request('type') === 'passage' ? 'imr-kpi--active' : '' }}">
            <div class="imr-kpi__count" style="color:#38bdf8;">{{ $healthMetrics['passage_count'] }}</div>
            <div class="imr-kpi__label">Passages</div>
        </a>
        <a href="{{ route('admin.media.index', array_merge(request()->except('page'), ['filter' => 'unused', 'type' => null])) }}" class="imr-kpi {{ request('filter') === 'unused' ? 'imr-kpi--active' : '' }}">
            <div class="imr-kpi__count" style="color:#fb7185;">{{ $healthMetrics['unused_count'] }}</div>
            <div class="imr-kpi__label">Unused Media</div>
        </a>
    </div>

    {{-- PART 7 & 8: COMBINED SEARCH & FILTERS --}}
    <div class="imr-filter-bar">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div class="imr-exam-pills">
                @php
                    $currentExam = request('exam_type');
                @endphp
                <a href="{{ route('admin.media.index', request()->except('exam_type')) }}" class="imr-exam-btn {{ !$currentExam ? 'imr-exam-btn--active' : '' }}">
                    All Repositories
                </a>
                <a href="{{ route('admin.media.index', array_merge(request()->all(), ['exam_type' => 'toefl'])) }}" class="imr-exam-btn {{ $currentExam === 'toefl' ? 'imr-exam-btn--active' : '' }}">
                    TOEFL Library
                </a>
                <a href="{{ route('admin.media.index', array_merge(request()->all(), ['exam_type' => 'toeic'])) }}" class="imr-exam-btn {{ $currentExam === 'toeic' ? 'imr-exam-btn--active' : '' }}">
                    TOEIC Library
                </a>
                <a href="{{ route('admin.media.index', array_merge(request()->all(), ['exam_type' => 'ielts'])) }}" class="imr-exam-btn {{ $currentExam === 'ielts' ? 'imr-exam-btn--active' : '' }}">
                    IELTS Library
                </a>
                <a href="{{ route('admin.media.index', array_merge(request()->all(), ['exam_type' => 'placement'])) }}" class="imr-exam-btn {{ $currentExam === 'placement' ? 'imr-exam-btn--active' : '' }}">
                    Placement
                </a>
                <a href="{{ route('admin.media.index', array_merge(request()->all(), ['exam_type' => 'grammar'])) }}" class="imr-exam-btn {{ $currentExam === 'grammar' ? 'imr-exam-btn--active' : '' }}">
                    Grammar
                </a>
                <a href="{{ route('admin.media.index', array_merge(request()->all(), ['exam_type' => 'vocabulary'])) }}" class="imr-exam-btn {{ $currentExam === 'vocabulary' ? 'imr-exam-btn--active' : '' }}">
                    Vocabulary
                </a>
            </div>

            <form method="GET" action="{{ route('admin.media.index') }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                @if(request('exam_type'))
                <input type="hidden" name="exam_type" value="{{ request('exam_type') }}">
                @endif
                @if(request('filter'))
                <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif

                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title, ID, tag, transcript..." class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg px-3 py-2 text-xs min-w-[200px]">

                <select name="type" onchange="this.form.submit()" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg px-3 py-2 text-xs">
                    <option value="">All Asset Types</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>Images</option>
                    <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>Audio</option>
                    <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>Video</option>
                    <option value="pdf" {{ request('type') === 'pdf' ? 'selected' : '' }}>PDF</option>
                    <option value="passage" {{ request('type') === 'passage' ? 'selected' : '' }}>Passages</option>
                </select>

                <button type="submit" style="padding:.45rem .9rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Filter</button>
            </form>
        </div>
    </div>

    {{-- PART 9: INFORMATIVE EMPTY STATES --}}
    @if($mediaAssets->isEmpty())
    <div class="bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-12 text-center flex flex-col items-center gap-3">
        <div style="font-size:3.5rem;">🔍</div>
        <h3 style="font-size:1.15rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">
            No {{ strtoupper(request('exam_type', '')) }} {{ ucfirst(request('type', '')) }} Assets Found
        </h3>
        <p style="font-size:.85rem;max-width:420px;line-height:1.5;margin:0;" class="text-slate-600 dark:text-slate-400">
            No matching institutional assets exist for the selected filter combination. Upload a new asset or clear your search filters to explore the full repository.
        </p>
        <a href="{{ route('admin.media.index') }}" style="padding:.6rem 1.25rem;background:#4f46e5;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;">
            Clear All Filters
        </a>
    </div>
    @else

    {{-- PART 2: REAL MEDIA PREVIEW GRID --}}
    <div class="imr-grid">
        @foreach($mediaAssets as $asset)
        <div class="imr-card">

            {{-- Card Preview --}}
            <div class="imr-card__preview">
                @if($asset->type === 'image')
                    <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->title }}" onclick="openImageModal('{{ $asset->publicUrl() }}', '{{ addslashes($asset->title ?? $asset->original_name) }}', '{{ $asset->id }}')" loading="lazy">

                @elseif($asset->type === 'audio')
                    <div style="width:90%;padding:.75rem;text-align:center;">
                        <div class="text-indigo-600 dark:text-indigo-400 mb-1 flex justify-center">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                        </div>
                        <audio controls controlsList="nodownload noplaybackrate" style="width:100%;height:32px;" preload="metadata" src="{{ $asset->publicUrl() }}">
                            <source src="{{ $asset->publicUrl() }}" type="{{ $asset->mime_type ?? 'audio/mpeg' }}">
                            Your browser does not support audio playback for {{ $asset->formatLabel() }}.
                        </audio>
                        @if($asset->content_text)
                        <button type="button" onclick="toggleTranscript('trans-{{ $asset->id }}')" class="inline-flex items-center gap-1 mt-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-400 hover:text-emerald-800 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Toggle Transcript
                        </button>
                        <div id="trans-{{ $asset->id }}" class="bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300" style="display:none;font-size:.68rem;text-align:left;padding:.5rem;border-radius:.4rem;margin-top:.3rem;max-height:60px;overflow-y:auto;">
                            {{ $asset->content_text }}
                        </div>
                        @endif
                    </div>

                @elseif($asset->type === 'video')
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#000;">
                        <video controls controlsList="nodownload noplaybackrate" style="width:100%;height:100%;object-fit:cover;">
                            <source src="{{ $asset->publicUrl() }}" type="video/mp4">
                        </video>
                    </div>

                @elseif($asset->type === 'pdf')
                    <div style="text-align:center;padding:1rem;width:100%;">
                        <div class="text-rose-500 mb-1 flex justify-center">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </div>
                        <button type="button" onclick="openPdfModal('{{ $asset->publicUrl() }}', '{{ addslashes($asset->title ?? $asset->original_name) }}')" class="inline-flex items-center gap-1 text-xs font-bold text-amber-700 dark:text-amber-400 hover:text-amber-800 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            Embedded PDF Preview
                        </button>
                    </div>

                @elseif($asset->type === 'passage')
                    <div style="padding:.75rem 1rem;font-size:.72rem;line-height:1.4;overflow:hidden;max-height:100%;" class="text-slate-800 dark:text-slate-200">
                        {{ Str::limit($asset->content_text ?? $asset->description, 130) }}
                    </div>
                @else
                    <div class="text-slate-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                    </div>
                @endif
            </div>

            {{-- Card Body --}}
            <div class="imr-card__body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    @php $exType = strtolower($asset->exam_type ?? 'general'); @endphp
                    <span class="imr-tag imr-tag--{{ in_array($exType, ['toefl','toeic','ielts']) ? $exType : 'general' }}">
                        {{ strtoupper($exType) }}
                    </span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">v{{ $asset->version ?? '1.0' }}</span>
                </div>

                <div class="imr-card__title" style="margin-top:.35rem;margin-bottom:.15rem;font-size:.92rem;line-height:1.35;">
                    {{ $asset->title ?? $asset->original_name }}
                </div>
                <div class="imr-card__sub">Folder: {{ $asset->category ?? 'General Assets' }}</div>
                <div class="text-xs font-medium text-slate-600 dark:text-slate-400" style="margin-top:2px;">Size: {{ $asset->humanSize() }} • {{ $asset->created_at?->format('d M Y') }}</div>
            </div>

            {{-- Action Column / Action Buttons (Preview, Edit, Version History, Tracker) --}}
            <div class="imr-foot">
                <a href="{{ route('admin.media.show', $asset->id) }}" class="imr-btn-neutral">
                    <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Preview
                </a>
                <a href="{{ route('admin.media.edit', $asset->id) }}" class="imr-btn-edit">
                    <svg class="w-3.5 h-3.5 text-indigo-700 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit
                </a>
                <a href="{{ route('admin.media.versions', $asset->id) }}" class="imr-btn-neutral">
                    <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Version History
                </a>
                <button type="button" onclick="showUsageModal('{{ $asset->id }}', '{{ addslashes($asset->title ?? $asset->original_name) }}')" class="imr-btn-tracker">
                    <svg class="w-3.5 h-3.5 text-indigo-700 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    Tracker
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <div style="margin-top:1rem;">
        {{ $mediaAssets->links() }}
    </div>
    @endif

    {{-- PART 2: Image Preview Modal --}}
    <div id="imageModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal" style="max-width:800px;text-align:center;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;">
                <h3 id="imageModalTitle" style="font-size:1.05rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">🖼 Image Preview</h3>
                <span onclick="closeImageModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.85rem;padding:1rem;overflow:hidden;margin-bottom:1rem;" class="dark:bg-slate-950 dark:border-slate-800">
                <img id="imageModalSrc" src="" style="max-width:100%;max-height:500px;object-fit:contain;transition:transform .3s;cursor:zoom-in;" onclick="toggleModalZoom()">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <button type="button" onclick="toggleModalZoom()" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700" style="padding:.4rem 1rem;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    🔍 Toggle Zoom
                </button>
                <button type="button" onclick="closeImageModal()" style="padding:.4rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- PART 2: Embedded PDF Viewer Modal --}}
    <div id="pdfModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal" style="max-width:850px;height:85vh;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;">
                <h3 id="pdfModalTitle" style="font-size:1.05rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">📄 Embedded PDF Viewer</h3>
                <span onclick="closePdfModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div style="flex:1;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;overflow:hidden;margin-bottom:1rem;">
                <iframe id="pdfModalSrc" src="" style="width:100%;height:100%;border:none;"></iframe>
            </div>
            <div style="text-align:right;">
                <button type="button" onclick="closePdfModal()" style="padding:.4rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    Close Viewer
                </button>
            </div>
        </div>
    </div>

    {{-- PART 5: Usage Tracker Modal --}}
    <div id="usageModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;">
                <h3 id="usageTitle" style="font-size:1.05rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">🔗 Usage Explorer</h3>
                <span onclick="closeUsageModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div id="usageBody" style="font-size:.85rem;line-height:1.5;" class="text-slate-700 dark:text-slate-300">
                Loading usage details...
            </div>
        </div>
    </div>

    {{-- Upload Wizard Modal (TASK 3 & TASK 6) --}}
    <div id="uploadModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal" style="max-width:700px;width:100%;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;">
                <h3 style="font-size:1.1rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                    🚀 Multi-Step Asset Upload Wizard
                </h3>
                <span onclick="closeUploadModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>

            <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data" id="wizardForm">
                @csrf
                <input type="hidden" name="type" id="wizardAssetType" value="image">

                {{-- STEP 1: Select Asset Type --}}
                <div id="wizardStep1">
                    <label style="font-size:.78rem;font-weight:800;display:block;margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em;" class="text-slate-600 dark:text-slate-400">
                        Step 1: Choose Institutional Asset Type *
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(5, 1fr);gap:.65rem;margin-bottom:1.5rem;">
                        <button type="button" onclick="selectWizardType('image')" id="typeBtn_image" class="bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-white border-2 border-indigo-600" style="padding:.85rem .5rem;border-radius:.75rem;font-size:.8rem;font-weight:800;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.35rem;">
                            <span style="font-size:1.5rem;">🖼</span> Image
                        </button>
                        <button type="button" onclick="selectWizardType('audio')" id="typeBtn_audio" class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700" style="padding:.85rem .5rem;border-radius:.75rem;font-size:.8rem;font-weight:800;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.35rem;">
                            <span style="font-size:1.5rem;">🎵</span> Audio
                        </button>
                        <button type="button" onclick="selectWizardType('video')" id="typeBtn_video" class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700" style="padding:.85rem .5rem;border-radius:.75rem;font-size:.8rem;font-weight:800;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.35rem;">
                            <span style="font-size:1.5rem;">🎬</span> Video
                        </button>
                        <button type="button" onclick="selectWizardType('pdf')" id="typeBtn_pdf" class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700" style="padding:.85rem .5rem;border-radius:.75rem;font-size:.8rem;font-weight:800;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.35rem;">
                            <span style="font-size:1.5rem;">📄</span> PDF
                        </button>
                        <button type="button" onclick="selectWizardType('passage')" id="typeBtn_passage" class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700" style="padding:.85rem .5rem;border-radius:.75rem;font-size:.8rem;font-weight:800;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.35rem;">
                            <span style="font-size:1.5rem;">📝</span> Passage
                        </button>
                    </div>
                </div>

                {{-- STEP 2: Dynamic Form Fields per Asset Type --}}
                <div id="wizardStep2" style="display:flex;flex-direction:column;gap:1rem;">

                    {{-- Title & Exam Fields (Common) --}}
                    <div>
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Asset Title *</label>
                        <input type="text" name="title" required placeholder="e.g. TOEFL Reading Passage Vol 1 / Part 1 Photograph" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;">
                        <div>
                            <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Exam Type *</label>
                            <select name="exam_type" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                                <option value="toefl">TOEFL iBT</option>
                                <option value="toeic">TOEIC Institutional</option>
                                <option value="ielts">IELTS Academic</option>
                                <option value="placement">Placement</option>
                                <option value="grammar">Grammar</option>
                                <option value="vocabulary">Vocabulary</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Category / Folder</label>
                            <input type="text" name="category" placeholder="e.g. Reading Passages" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Sub Category</label>
                            <input type="text" name="sub_category" placeholder="e.g. Academic Lecture" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                        </div>
                    </div>

                    {{-- Dynamic Section for IMAGE --}}
                    <div id="fields_image">
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Upload Image File (JPG, PNG, WebP) *</label>
                        <input type="file" name="file" accept="image/*" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                    </div>

                    {{-- Dynamic Section for AUDIO --}}
                    <div id="fields_audio" style="display:none;">
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Upload Audio Track (MP3, WAV, M4A) *</label>
                        <input type="file" name="audio_file" accept="audio/*" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full mb-3">
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Audio Transcript Content</label>
                        <textarea name="content_text" rows="3" placeholder="Associated transcript text..." class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full"></textarea>
                    </div>

                    {{-- Dynamic Section for VIDEO --}}
                    <div id="fields_video" style="display:none;">
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Upload Video File (MP4, WEBM) *</label>
                        <input type="file" name="video_file" accept="video/*" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full mb-3">
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Duration / Description</label>
                        <input type="text" name="description" placeholder="e.g. 05:30 min - Listening Section Explanation" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                    </div>

                    {{-- Dynamic Section for PDF --}}
                    <div id="fields_pdf" style="display:none;">
                        <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Upload PDF Document *</label>
                        <input type="file" name="pdf_file" accept="application/pdf" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                    </div>

                    {{-- Dynamic Section for PASSAGE (Text Only, Image Only, Hybrid) --}}
                    <div id="fields_passage" style="display:none;border-radius:.75rem;padding:1rem;" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <label style="font-size:.75rem;font-weight:800;color:#4f46e5;display:block;margin-bottom:.5rem;">
                            Passage Format Selector *
                        </label>
                        <div style="display:flex;gap:.5rem;margin-bottom:.85rem;">
                            <input type="hidden" name="passage_type" id="wizardPassageFormat" value="HYBRID">
                            <button type="button" onclick="setWizardPassageMode('TEXT')" id="wPillTEXT" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700" style="padding:.4rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:700;cursor:pointer;">Text Only</button>
                            <button type="button" onclick="setWizardPassageMode('IMAGE')" id="wPillIMAGE" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700" style="padding:.4rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:700;cursor:pointer;">Image Only</button>
                            <button type="button" onclick="setWizardPassageMode('HYBRID')" id="wPillHYBRID" style="padding:.4rem .75rem;border-radius:.5rem;background:#4f46e5;color:#fff;border:1px solid #4f46e5;font-size:.75rem;font-weight:700;cursor:pointer;">Hybrid (Text + Image)</button>
                        </div>

                        <div id="wPassageTextDiv">
                            <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Rich Text Passage Content</label>
                            <textarea name="passage_content_text" rows="4" placeholder="Enter passage paragraphs..." class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full font-mono"></textarea>
                        </div>

                        <div id="wPassageImgDiv" style="margin-top:.75rem;">
                            <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:.3rem;" class="text-slate-700 dark:text-slate-300">Passage Diagram / Image Scan</label>
                            <input type="file" name="passage_image_file" accept="image/*" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                        </div>
                    </div>

                    {{-- TASK 6: Button Changes -> "Submit for Repository Review" --}}
                    <button type="submit" style="width:100%;padding:.75rem;background:#4f46e5;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;margin-top:.5rem;font-size:.88rem;">
                        🚀 Submit for Repository Review
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }

function selectWizardType(type) {
    document.getElementById('wizardAssetType').value = type;
    ['image','audio','video','pdf','passage'].forEach(t => {
        const btn = document.getElementById('typeBtn_' + t);
        const fld = document.getElementById('fields_' + t);
        if (btn) {
            btn.style.border = (t === type) ? '2px solid #6366f1' : '1px solid #334155';
            btn.style.color  = (t === type) ? '#fff' : '#94a3b8';
        }
        if (fld) {
            fld.style.display = (t === type) ? 'block' : 'none';
        }
    });
}

function setWizardPassageMode(mode) {
    document.getElementById('wizardPassageFormat').value = mode;
    ['TEXT','IMAGE','HYBRID'].forEach(m => {
        const btn = document.getElementById('wPill' + m);
        if (btn) {
            btn.style.background = (m === mode) ? '#4338ca' : '#1e293b';
            btn.style.color      = (m === mode) ? '#fff' : '#cbd5e1';
            btn.style.borderColor= (m === mode) ? '#6366f1' : '#334155';
        }
    });

    const txt = document.getElementById('wPassageTextDiv');
    const img = document.getElementById('wPassageImgDiv');
    if (mode === 'TEXT') {
        if(txt) txt.style.display = 'block';
        if(img) img.style.display = 'none';
    } else if (mode === 'IMAGE') {
        if(txt) txt.style.display = 'none';
        if(img) img.style.display = 'block';
    } else {
        if(txt) txt.style.display = 'block';
        if(img) img.style.display = 'block';
    }
}

function toggleTranscript(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

// PART 2: Image Preview Modal
let isModalZoom = false;
function openImageModal(src, title, id) {
    document.getElementById('imageModalTitle').innerText = '🖼 ' + title;
    document.getElementById('imageModalSrc').src = src;
    isModalZoom = false;
    document.getElementById('imageModalSrc').style.transform = 'scale(1)';
    document.getElementById('imageModal').style.display = 'flex';
}
function closeImageModal() { document.getElementById('imageModal').style.display = 'none'; }
function toggleModalZoom() {
    const img = document.getElementById('imageModalSrc');
    isModalZoom = !isModalZoom;
    img.style.transform = isModalZoom ? 'scale(1.5)' : 'scale(1)';
}

// PART 2: Embedded PDF Viewer Modal
function openPdfModal(url, title) {
    document.getElementById('pdfModalTitle').innerText = '📄 PDF Viewer: ' + title;
    document.getElementById('pdfModalSrc').src = url;
    document.getElementById('pdfModal').style.display = 'flex';
}
function closePdfModal() { document.getElementById('pdfModal').style.display = 'none'; }

// PART 5: Usage Explorer Modal
function showUsageModal(id, title) {
    document.getElementById('usageTitle').innerText = '🔗 Usage Explorer: ' + title;
    document.getElementById('usageBody').innerHTML = '<div style="padding:1.5rem;text-align:center;color:#818cf8;">Loading usage breakdown...</div>';
    document.getElementById('usageModal').style.display = 'flex';

    fetch('/admin/media/' + id + '/usage')
        .then(res => res.json())
        .then(data => {
            if (!data.is_used) {
                document.getElementById('usageBody').innerHTML = `
                    <div style="background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);padding:1.5rem;border-radius:.75rem;text-align:center;">
                        <div style="font-size:2rem;margin-bottom:.4rem;">📭</div>
                        <div style="font-size:.85rem;font-weight:700;color:#f1f5f9;margin-bottom:.3rem;">
                            This asset has not yet been referenced by any Question Bank or Assessment.
                        </div>
                        <div style="font-size:.75rem;color:#94a3b8;">
                            Teachers can attach this asset using the Media Picker Modal when authoring questions.
                        </div>
                    </div>
                `;
            } else {
                let banksHtml = '';
                if (data.question_banks && data.question_banks.length > 0) {
                    data.question_banks.forEach(b => {
                        banksHtml += `<div style="padding:.4rem .75rem;background:#1e293b;border-radius:.4rem;font-size:.78rem;color:#e2e8f0;margin-top:.3rem;">📁 <strong>${b.title}</strong> (${b.type})</div>`;
                    });
                }

                document.getElementById('usageBody').innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:.85rem;">
                        <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);padding:.85rem;border-radius:.65rem;color:#34d399;font-weight:700;font-size:.82rem;">
                            ${data.message}
                        </div>
                        <div style="font-size:.8rem;color:#cbd5e1;line-height:1.6;">
                            • Question Banks Using: <strong>${data.assessments_count}</strong><br>
                            • Questions Linked: <strong>${data.question_count}</strong><br>
                            • Courses Using: <strong>${data.courses_count}</strong><br>
                            • Student Attempts: <strong>${data.student_attempts}</strong><br>
                            • Uploader: <strong>${data.uploader}</strong>
                        </div>
                        ${banksHtml ? '<div style="margin-top:.4rem;"><div style="font-size:.75rem;font-weight:700;color:#64748b;">REFERENCING BANKS:</div>' + banksHtml + '</div>' : ''}
                    </div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('usageBody').innerText = 'This asset has not yet been referenced by any Question Bank or Assessment.';
        });
}
function closeUsageModal() { document.getElementById('usageModal').style.display = 'none'; }

// PART 6: COPY URL
function copyAssetUrl(url) {
    navigator.clipboard.writeText(url);
    iapAlert({ title: 'URL Copied', message: '✅ Internal Asset URL Copied to Clipboard!\n\n' + url, variant: 'success' });
}
</script>
@endsection
