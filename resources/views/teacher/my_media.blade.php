@extends('layouts.admin')

@section('title', 'My Media — Working Media Workspace')

@push('styles')
<style>
.tm-page { display:flex; flex-direction:column; gap:1.75rem; }

.tm-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.tm-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.tm-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.tm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
}
@media (max-width: 1200px) { .tm-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .tm-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.tm-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
    text-decoration: none;
    transition: transform .15s, border-color .15s, box-shadow .15s;
    cursor: pointer;
}
.tm-kpi:hover {
    transform: translateY(-2px);
    border-color: #6366f1;
    box-shadow: 0 8px 24px rgba(99,102,241,.15);
}
.tm-kpi--active {
    border-color: #6366f1;
    background: linear-gradient(180deg, #1e1b4b 0%, #0f172a 100%);
}
.tm-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.tm-kpi__label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }

.tm-filter-bar {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.tm-pills { display: flex; gap: .5rem; flex-wrap: wrap; }
.tm-pill-btn {
    padding: .45rem .95rem;
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 700;
    text-decoration: none;
    background: #1e293b;
    color: #94a3b8;
    border: 1px solid #334155;
    transition: all .15s;
}
.tm-pill-btn--active { background: #6366f1; color: #fff; border-color: #6366f1; }

.tm-bulk-toolbar {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: .85rem;
    padding: .75rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.tm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}

.tm-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color .2s, transform .15s;
    position: relative;
}
.tm-card:hover { border-color: #6366f1; transform: translateY(-2px); }

.tm-card__preview {
    height: 160px;
    background: #080f1d;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.tm-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
    width: 20px;
    height: 20px;
    accent-color: #6366f1;
    cursor: pointer;
}

.tm-foot {
    background: #080f1d;
    border-top: 1px solid #1e293b;
    padding: .75rem 1rem;
    display: flex;
    gap: .5rem;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

/* Light Theme Overrides */
html[data-theme="light"] .tm-hero,
.light .tm-hero {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 50%, #eef2ff 100%);
    border-color: #cbd5e1;
    box-shadow: 0 4px 16px -2px rgba(99,102,241,0.06);
}
html[data-theme="light"] .tm-hero__title,
.light .tm-hero__title { color: #0f172a; }
html[data-theme="light"] .tm-hero__sub,
.light .tm-hero__sub { color: #334155; font-weight: 500; }

html[data-theme="light"] .tm-kpi,
.light .tm-kpi {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(15,23,42,0.04);
}
html[data-theme="light"] .tm-kpi:hover,
.light .tm-kpi:hover {
    border-color: #4f46e5;
    box-shadow: 0 8px 24px rgba(99,102,241,.12);
}
html[data-theme="light"] .tm-kpi--active,
.light .tm-kpi--active {
    background: #eef2ff;
    border-color: #4f46e5;
}
html[data-theme="light"] .tm-kpi__label,
.light .tm-kpi__label {
    color: #334155;
    font-weight: 800;
}

html[data-theme="light"] .tm-filter-bar,
.light .tm-filter-bar {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(15,23,42,0.04);
}
html[data-theme="light"] .tm-pill-btn,
.light .tm-pill-btn {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
    font-weight: 700;
}
html[data-theme="light"] .tm-pill-btn:hover,
.light .tm-pill-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
html[data-theme="light"] .tm-pill-btn--active,
.light .tm-pill-btn--active {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
}

html[data-theme="light"] .tm-bulk-toolbar,
.light .tm-bulk-toolbar {
    background: #f8fafc;
    border-color: #cbd5e1;
}

html[data-theme="light"] .tm-card,
.light .tm-card {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(15,23,42,0.04);
}
html[data-theme="light"] .tm-card:hover,
.light .tm-card:hover {
    border-color: #4f46e5;
    box-shadow: 0 8px 24px rgba(99,102,241,.1);
}
html[data-theme="light"] .tm-card__preview,
.light .tm-card__preview {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
html[data-theme="light"] .tm-foot,
.light .tm-foot {
    background: #f8fafc;
    border-top-color: #cbd5e1;
}

.tm-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.75);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1.5rem;
}
.tm-modal-card {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 1.25rem;
    max-width: 600px;
    width: 100%;
    overflow: hidden;
}
html[data-theme="light"] .tm-modal-card,
.light .tm-modal-card {
    background: #ffffff;
    border-color: #cbd5e1;
}
</style>
@endpush

@section('content')
<div class="tm-page">

    {{-- Flash Notifications --}}
    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-sm font-semibold flex items-center justify-between">
        <span>✅ {{ session('status') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-sm font-semibold flex items-center justify-between">
        <span>⚠️ {{ session('error') }}</span>
    </div>
    @endif

    {{-- Hero Section --}}
    <div class="tm-hero">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-extrabold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 mb-2">
                Teacher Workspace
            </div>
            <h1 class="tm-hero__title">My Media &amp; Working Library</h1>
            <p class="tm-hero__sub">
                Manage personal working media, attach assets to assessments, and submit selected media to the Institutional Repository.
            </p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('admin.media.index') }}" class="px-4 py-2.5 rounded-xl font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 transition-all dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:border-slate-700 inline-flex items-center gap-2">
                Browse Institutional Media →
            </a>
            <button type="button" onclick="openUploadModal()" class="px-5 py-2.5 rounded-xl font-bold text-xs bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/20 transition-all inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Upload Media Asset
            </button>
        </div>
    </div>

    {{-- KPI Metric Cards --}}
    @php
        $currStatus = request('status', 'all');
        $currType   = request('type', 'all');
        $currExam   = request('exam_type', 'all');
    @endphp
    <div class="tm-kpi-grid">
        <a href="{{ route('teacher.media.index') }}" class="tm-kpi {{ $currStatus === 'all' && $currType === 'all' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-indigo-700 dark:text-indigo-400">{{ $totalCount }}</span>
            <span class="tm-kpi__label">Total My Media</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'working', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'working' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-slate-800 dark:text-slate-300">{{ $workingCount }}</span>
            <span class="tm-kpi__label">Working / Draft</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'in_use', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'in_use' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-sky-700 dark:text-sky-400">{{ $usedCount }}</span>
            <span class="tm-kpi__label">Used in Assessments</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'pending_review', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'pending_review' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-amber-700 dark:text-amber-400">{{ $pendingCount }}</span>
            <span class="tm-kpi__label">Pending RM Review</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'approved', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'approved' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-emerald-700 dark:text-emerald-400">{{ $approvedCount }}</span>
            <span class="tm-kpi__label">Institutional Approved</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'revision_requested', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'revision_requested' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-orange-700 dark:text-orange-400">{{ $revisionCount }}</span>
            <span class="tm-kpi__label">Needs Revision</span>
        </a>
    </div>

    {{-- Filter and Search Bar --}}
    <div class="tm-filter-bar">
        <form method="GET" action="{{ route('teacher.media.index') }}" class="flex flex-col gap-3">
            <div class="flex gap-3 flex-wrap">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title, filename, category, transcript..." class="flex-1 min-w-[260px] px-4 py-2.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500">
                <button type="submit" class="px-5 py-2.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 transition-all">
                    Search
                </button>
                @if(request()->anyFilled(['search', 'status', 'type', 'exam_type']))
                <a href="{{ route('teacher.media.index') }}" class="px-4 py-2.5 rounded-xl font-bold text-xs bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-700 transition-all inline-flex items-center">
                    Reset
                </a>
                @endif
            </div>

            {{-- Type Filter Pills --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider min-w-[50px]">Type:</span>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'all', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'all' ? 'tm-pill-btn--active' : '' }}">All Types</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'image', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'image' ? 'tm-pill-btn--active' : '' }}">Images</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'audio', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'audio' ? 'tm-pill-btn--active' : '' }}">Audio</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'pdf', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'pdf' ? 'tm-pill-btn--active' : '' }}">PDFs</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'passage', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'passage' ? 'tm-pill-btn--active' : '' }}">Passages</a>
            </div>

            {{-- Status Filter Pills --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider min-w-[50px]">Status:</span>
                <a href="{{ route('teacher.media.index', ['status' => 'all', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'all' ? 'tm-pill-btn--active' : '' }}">All</a>
                <a href="{{ route('teacher.media.index', ['status' => 'working', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'working' ? 'tm-pill-btn--active' : '' }}">Working / Draft</a>
                <a href="{{ route('teacher.media.index', ['status' => 'in_use', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'in_use' ? 'tm-pill-btn--active' : '' }}">In Assessments</a>
                <a href="{{ route('teacher.media.index', ['status' => 'pending_review', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'pending_review' ? 'tm-pill-btn--active' : '' }}">Pending Review</a>
                <a href="{{ route('teacher.media.index', ['status' => 'approved', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'approved' ? 'tm-pill-btn--active' : '' }}">Approved</a>
                <a href="{{ route('teacher.media.index', ['status' => 'revision_requested', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'revision_requested' ? 'tm-pill-btn--active' : '' }}">Needs Revision</a>
                <a href="{{ route('teacher.media.index', ['status' => 'rejected', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'rejected' ? 'tm-pill-btn--active' : '' }}">Rejected</a>
            </div>
        </form>
    </div>

    {{-- Bulk Action Submission Form & Toolbar --}}
    <form id="bulkSubmitForm" method="POST" action="{{ route('teacher.media.submit-selected') }}">
        @csrf
        
        <div class="tm-bulk-toolbar mb-4">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-800 dark:text-slate-200">
                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Select All Eligible for Submission</span>
                </label>
                <span class="text-xs text-slate-600 dark:text-slate-400 font-mono">
                    (<span id="selectedCountDisplay" class="font-bold text-indigo-700 dark:text-indigo-400">0</span> selected)
                </span>
            </div>

            <button type="button" id="bulkSubmitBtn" onclick="openConfirmationModal()" disabled class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-2 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                Submit Selected to Institutional Repository
            </button>
        </div>

        {{-- Media Grid --}}
        @if($mediaAssets->count() > 0)
        <div class="tm-grid">
            @foreach($mediaAssets as $media)
            @php
                $isEligibleForSubmission = in_array($media->approval_status, ['draft', 'working', 'revision_requested', 'rejected', null]);
                $isUsed = in_array($media->id, $usedMediaIds);
                $reviewReq = $media->latestReviewRequest;
            @endphp
            <div class="tm-card">
                {{-- Preview Header --}}
                <div class="tm-card__preview">
                    @if($isEligibleForSubmission)
                    <input type="checkbox" name="selected_media[]" value="{{ $media->id }}" onchange="updateSelectedCount()" class="tm-checkbox media-select-checkbox">
                    @endif

                    @if($media->type === 'image')
                        <img src="{{ $media->previewUrl() }}" alt="{{ $media->title }}" class="w-full h-full object-cover cursor-pointer" onclick="openImageModal('{{ $media->previewUrl() }}', '{{ addslashes($media->title ?? $media->original_name) }}')">
                    @elseif($media->type === 'audio')
                        <div class="p-4 w-full flex flex-col items-center justify-center gap-2">
                            <div class="text-indigo-600 dark:text-indigo-400 flex justify-center">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                            </div>
                            <audio controls class="w-full h-8" preload="metadata" src="{{ $media->previewUrl() }}">
                                <source src="{{ $media->previewUrl() }}" type="{{ $media->mime_type ?? 'audio/mpeg' }}">
                                Your browser does not support audio playback for {{ $media->formatLabel() }}.
                            </audio>
                        </div>
                    @elseif($media->type === 'pdf')
                        <div class="p-4 flex flex-col items-center justify-center text-center gap-1 cursor-pointer" onclick="openPdfModal('{{ $media->previewUrl() }}', '{{ addslashes($media->title ?? $media->original_name) }}')">
                            <div class="text-rose-500 flex justify-center">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">PDF Document</span>
                        </div>
                    @elseif($media->type === 'passage')
                        <div class="p-3 text-left overflow-y-auto max-h-[150px] w-full text-xs text-slate-800 dark:text-slate-200 font-mono">
                            <span class="text-[10px] font-bold uppercase text-indigo-700 dark:text-indigo-400 block mb-1">Passage Content:</span>
                            {{ Str::limit($media->content_text ?? $media->description ?? 'No passage text', 150) }}
                        </div>
                    @else
                        <div class="p-4 flex flex-col items-center justify-center">
                            <div class="text-slate-400">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ strtoupper($media->type) }}</span>
                        </div>
                    @endif

                    {{-- Top Right Badges --}}
                    <div class="absolute top-2.5 right-2.5 flex flex-col items-end gap-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-900/90 text-white backdrop-blur border border-white/10">
                            {{ $media->typeIcon() }} {{ strtoupper($media->type) }}
                        </span>
                        @if($isUsed)
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-sky-600 text-white backdrop-blur shadow-sm">
                            IN ASSESSMENT
                        </span>
                        @endif
                    </div>
                </div>

                {{-- Card Content --}}
                <div class="p-4 space-y-3">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white truncate" title="{{ $media->title ?? $media->original_name }}">
                            {{ $media->title ?? $media->original_name }}
                        </h3>
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-400 truncate mt-0.5 font-mono">
                            {{ $media->original_name }} • {{ $media->humanSize() }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200 dark:border-slate-800">
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 uppercase border border-slate-300 dark:border-slate-700">
                            {{ strtoupper($media->exam_type ?? 'GENERAL') }}
                        </span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $media->created_at?->format('d M Y') }}
                        </span>
                    </div>

                    {{-- Governance / Repository Status Badge --}}
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider text-[10px]">Repository Status:</span>
                            @if($media->approval_status === 'approved')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                    Institutional Asset
                                </span>
                            @elseif($media->approval_status === 'pending_review')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                    Pending RM Review
                                </span>
                            @elseif($media->approval_status === 'revision_requested')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-orange-100 text-orange-900 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-300 dark:border-orange-700">
                                    Revision Requested
                                </span>
                            @elseif($media->approval_status === 'rejected')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700">
                                    Rejected
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">
                                    Working Media
                                </span>
                            @endif
                        </div>

                        {{-- Reviewer notes if any --}}
                        @if($reviewReq && !empty($reviewReq->review_notes) && in_array($media->approval_status, ['revision_requested', 'rejected', 'approved']))
                        <div class="text-xs text-slate-700 dark:text-slate-300 pt-1 border-t border-slate-200 dark:border-slate-800 italic">
                            RM Note: "{{ Str::limit($reviewReq->review_notes, 80) }}"
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Action Footer --}}
                <div class="tm-foot">
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="showUsageModal('{{ $media->id }}', '{{ addslashes($media->title ?? $media->original_name) }}')" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-1" title="View Assessment Usage">
                            <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            Usage
                        </button>
                        <a href="{{ route('admin.media.edit', $media->id) }}" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition-colors inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-indigo-700 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Edit
                        </a>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if($isEligibleForSubmission)
                        <button type="button" onclick="submitSingleMedia('{{ $media->id }}')" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-600 text-white hover:bg-indigo-500 transition-colors inline-flex items-center gap-1 shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            Submit
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $mediaAssets->links() }}
        </div>
        @else
        <div class="p-16 text-center rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-800">
            <div class="text-slate-400 mb-3 flex justify-center">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">No Media Assets Found</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 max-w-md mx-auto">
                Upload your audio clips, diagrams, passages, or PDFs to start attaching them to questions and assessments.
            </p>
            <button type="button" onclick="openUploadModal()" class="mt-4 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500 inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Upload First Working Media
            </button>
        </div>
        @endif
    </form>

    {{-- Confirmation Modal for Repository Submission --}}
    <div id="confirmModal" class="tm-modal-overlay">
        <div class="tm-modal-card p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-500/10 text-indigo-600 flex items-center justify-center text-xl font-bold">
                    🚀
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Submit Media to Institutional Repository?</h3>
                    <p class="text-xs text-slate-500">Institutional Quality Assurance &amp; Governance Review</p>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200 leading-relaxed">
                Selected media will be submitted for Repository Manager review. They will remain usable in your assessments while awaiting review.
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeConfirmationModal()" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300">
                    Cancel
                </button>
                <button type="button" onclick="confirmSubmitBatch()" class="px-5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md">
                    Submit for Review
                </button>
            </div>
        </div>
    </div>

    {{-- Quick Upload Modal --}}
    <div id="uploadModal" class="tm-modal-overlay">
        <div class="tm-modal-card p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Upload Working Media Asset</h3>
                <button type="button" onclick="closeUploadModal()" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
            </div>

            <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Media File *</label>
                    <input type="file" name="file" required accept="image/*,audio/*,.pdf" class="w-full text-xs p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    <span class="text-[10px] text-slate-400">Supported: JPEG, PNG, WEBP, MP3, WAV, M4A, PDF (Max: 10MB)</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Asset Title</label>
                    <input type="text" name="title" placeholder="Descriptive title for authoring reference" class="w-full text-xs p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Exam Type</label>
                        <select name="exam_type" class="w-full text-xs p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                            <option value="general">General</option>
                            <option value="toefl">TOEFL</option>
                            <option value="toeic">TOEIC</option>
                            <option value="ielts">IELTS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Category</label>
                        <input type="text" name="category" value="General Assets" placeholder="e.g. Listening Part 1" class="w-full text-xs p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Description / Notes</label>
                    <textarea name="description" rows="2" placeholder="Optional description..." class="w-full text-xs p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500 shadow-md">
                        Upload to My Media
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Usage Modal --}}
    <div id="usageModal" class="tm-modal-overlay">
        <div class="tm-modal-card p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 id="usageTitle" class="text-base font-bold text-slate-900 dark:text-white">Assessment Usage Explorer</h3>
                <button type="button" onclick="closeUsageModal()" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
            </div>
            <div id="usageBody" class="text-xs text-slate-300">
                Loading...
            </div>
        </div>
    </div>

    {{-- Image Lightbox Modal --}}
    <div id="imageModal" class="tm-modal-overlay" onclick="closeImageModal()">
        <div class="max-w-3xl w-full p-4" onclick="event.stopPropagation()">
            <img id="imageModalSrc" src="" class="max-h-[80vh] w-auto mx-auto rounded-xl shadow-2xl object-contain">
        </div>
    </div>

    {{-- PDF Modal --}}
    <div id="pdfModal" class="tm-modal-overlay">
        <div class="tm-modal-card max-w-4xl h-[85vh] p-4 flex flex-col">
            <div class="flex justify-between items-center pb-2">
                <h3 id="pdfModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">PDF Viewer</h3>
                <button type="button" onclick="closePdfModal()" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <iframe id="pdfModalSrc" src="" class="flex-1 w-full rounded-lg border border-slate-700"></iframe>
        </div>
    </div>

</div>

<script>
function updateSelectedCount() {
    const checkedBoxes = document.querySelectorAll('.media-select-checkbox:checked');
    const count = checkedBoxes.length;
    document.getElementById('selectedCountDisplay').innerText = count;
    document.getElementById('bulkSubmitBtn').disabled = (count === 0);
}

function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.media-select-checkbox');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
    updateSelectedCount();
}

function openConfirmationModal() {
    const count = document.querySelectorAll('.media-select-checkbox:checked').length;
    if (count === 0) return;
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirmationModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

function confirmSubmitBatch() {
    document.getElementById('bulkSubmitForm').submit();
}

function submitSingleMedia(id) {
    // Uncheck all, check only this id, then open confirmation modal
    const checkboxes = document.querySelectorAll('.media-select-checkbox');
    checkboxes.forEach(cb => cb.checked = (cb.value === id));
    updateSelectedCount();
    openConfirmationModal();
}

function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }

function openImageModal(src, title) {
    document.getElementById('imageModalSrc').src = src;
    document.getElementById('imageModal').style.display = 'flex';
}
function closeImageModal() { document.getElementById('imageModal').style.display = 'none'; }

function openPdfModal(url, title) {
    document.getElementById('pdfModalTitle').innerText = title;
    document.getElementById('pdfModalSrc').src = url;
    document.getElementById('pdfModal').style.display = 'flex';
}
function closePdfModal() { document.getElementById('pdfModal').style.display = 'none'; }

function showUsageModal(id, title) {
    document.getElementById('usageTitle').innerText = 'Assessment Usage: ' + title;
    document.getElementById('usageBody').innerHTML = '<div class="py-4 text-center text-indigo-400">Loading usage statistics...</div>';
    document.getElementById('usageModal').style.display = 'flex';

    fetch('/admin/media/' + id + '/usage')
        .then(res => res.json())
        .then(data => {
            if (!data.is_used) {
                document.getElementById('usageBody').innerHTML = `
                    <div class="p-4 text-center rounded-xl bg-slate-800/40 border border-slate-700/50">
                        <div class="text-2xl mb-1">📭</div>
                        <div class="font-bold text-slate-200">Not currently attached to any assessments</div>
                        <div class="text-slate-400 text-[11px] mt-1">You can attach this working media inside Test Builder.</div>
                    </div>
                `;
            } else {
                document.getElementById('usageBody').innerHTML = `
                    <div class="space-y-3">
                        <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-bold">
                            ${data.message}
                        </div>
                        <div class="text-slate-300 space-y-1 text-xs">
                            <div>• Linked Questions: <strong>${data.question_count}</strong></div>
                            <div>• Referencing Question Banks: <strong>${data.assessments_count}</strong></div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('usageBody').innerHTML = '<div class="p-3 text-slate-400">Unable to load usage information.</div>';
        });
}
function closeUsageModal() { document.getElementById('usageModal').style.display = 'none'; }
</script>
@endsection
