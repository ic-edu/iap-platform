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
    background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
    border-color: #e2e8f0;
}
html[data-theme="light"] .tm-hero__title,
.light .tm-hero__title { color: #0f172a; }
html[data-theme="light"] .tm-hero__sub,
.light .tm-hero__sub { color: #64748b; }

html[data-theme="light"] .tm-kpi,
.light .tm-kpi {
    background: #ffffff;
    border-color: #e2e8f0;
}
html[data-theme="light"] .tm-kpi:hover,
.light .tm-kpi:hover {
    border-color: #6366f1;
    box-shadow: 0 8px 24px rgba(99,102,241,.1);
}
html[data-theme="light"] .tm-kpi--active,
.light .tm-kpi--active {
    background: #eef2ff;
    border-color: #6366f1;
}

html[data-theme="light"] .tm-filter-bar,
.light .tm-filter-bar {
    background: #ffffff;
    border-color: #e2e8f0;
}
html[data-theme="light"] .tm-pill-btn,
.light .tm-pill-btn {
    background: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}
html[data-theme="light"] .tm-pill-btn--active,
.light .tm-pill-btn--active {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
}

html[data-theme="light"] .tm-bulk-toolbar,
.light .tm-bulk-toolbar {
    background: #f8fafc;
    border-color: #cbd5e1;
}

html[data-theme="light"] .tm-card,
.light .tm-card {
    background: #ffffff;
    border-color: #e2e8f0;
}
html[data-theme="light"] .tm-card__preview,
.light .tm-card__preview {
    background: #f8fafc;
}
html[data-theme="light"] .tm-foot,
.light .tm-foot {
    background: #f8fafc;
    border-top-color: #e2e8f0;
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
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 mb-2">
                📁 Teacher Workspace
            </div>
            <h1 class="tm-hero__title">My Media &amp; Working Library</h1>
            <p class="tm-hero__sub">
                Manage personal working media, attach assets to assessments, and submit selected media to the Institutional Repository.
            </p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('admin.media.index') }}" class="px-4 py-2.5 rounded-xl font-bold text-xs bg-slate-800 text-slate-200 hover:bg-slate-700 border border-slate-700 transition-all dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:border-slate-700 inline-flex items-center gap-2">
                🏛 Browse Institutional Media →
            </a>
            <button type="button" onclick="openUploadModal()" class="px-5 py-2.5 rounded-xl font-bold text-xs bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/20 transition-all inline-flex items-center gap-2">
                + Upload Media Asset
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
            <span class="tm-kpi__count text-indigo-600 dark:text-indigo-400">{{ $totalCount }}</span>
            <span class="tm-kpi__label">Total My Media</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'working', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'working' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-slate-700 dark:text-slate-300">{{ $workingCount }}</span>
            <span class="tm-kpi__label">Working / Draft</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'in_use', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'in_use' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-sky-600 dark:text-sky-400">{{ $usedCount }}</span>
            <span class="tm-kpi__label">Used in Assessments</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'pending_review', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'pending_review' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-amber-500">{{ $pendingCount }}</span>
            <span class="tm-kpi__label">Pending RM Review</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'approved', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'approved' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-emerald-500">{{ $approvedCount }}</span>
            <span class="tm-kpi__label">Institutional Approved</span>
        </a>
        <a href="{{ route('teacher.media.index', ['status' => 'revision_requested', 'type' => $currType, 'exam_type' => $currExam]) }}" class="tm-kpi {{ $currStatus === 'revision_requested' ? 'tm-kpi--active' : '' }}">
            <span class="tm-kpi__count text-orange-500">{{ $revisionCount }}</span>
            <span class="tm-kpi__label">Needs Revision</span>
        </a>
    </div>

    {{-- Filter and Search Bar --}}
    <div class="tm-filter-bar">
        <form method="GET" action="{{ route('teacher.media.index') }}" class="flex flex-col gap-3">
            <div class="flex gap-3 flex-wrap">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search by title, filename, category, transcript..." class="flex-1 min-w-[260px] px-4 py-2.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500">
                <button type="submit" class="px-5 py-2.5 rounded-xl font-bold text-xs bg-indigo-600 text-white hover:bg-indigo-500 transition-all">
                    Search
                </button>
                @if(request()->anyFilled(['search', 'status', 'type', 'exam_type']))
                <a href="{{ route('teacher.media.index') }}" class="px-4 py-2.5 rounded-xl font-bold text-xs bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 transition-all inline-flex items-center">
                    Reset
                </a>
                @endif
            </div>

            {{-- Type Filter Pills --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider min-w-[50px]">Type:</span>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'all', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'all' ? 'tm-pill-btn--active' : '' }}">All Types</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'image', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'image' ? 'tm-pill-btn--active' : '' }}">🖼 Images</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'audio', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'audio' ? 'tm-pill-btn--active' : '' }}">🎵 Audio</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'pdf', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'pdf' ? 'tm-pill-btn--active' : '' }}">📄 PDFs</a>
                <a href="{{ route('teacher.media.index', ['status' => $currStatus, 'type' => 'passage', 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currType === 'passage' ? 'tm-pill-btn--active' : '' }}">📖 Passages</a>
            </div>

            {{-- Status Filter Pills --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider min-w-[50px]">Status:</span>
                <a href="{{ route('teacher.media.index', ['status' => 'all', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'all' ? 'tm-pill-btn--active' : '' }}">All</a>
                <a href="{{ route('teacher.media.index', ['status' => 'working', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'working' ? 'tm-pill-btn--active' : '' }}">Working / Draft</a>
                <a href="{{ route('teacher.media.index', ['status' => 'in_use', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'in_use' ? 'tm-pill-btn--active' : '' }}">📌 In Assessments</a>
                <a href="{{ route('teacher.media.index', ['status' => 'pending_review', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'pending_review' ? 'tm-pill-btn--active' : '' }}">⏳ Pending Review</a>
                <a href="{{ route('teacher.media.index', ['status' => 'approved', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'approved' ? 'tm-pill-btn--active' : '' }}">✅ Approved</a>
                <a href="{{ route('teacher.media.index', ['status' => 'revision_requested', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'revision_requested' ? 'tm-pill-btn--active' : '' }}">⚠️ Needs Revision</a>
                <a href="{{ route('teacher.media.index', ['status' => 'rejected', 'type' => $currType, 'exam_type' => $currExam, 'search' => request('search')]) }}" class="tm-pill-btn {{ $currStatus === 'rejected' ? 'tm-pill-btn--active' : '' }}">❌ Rejected</a>
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
                <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">
                    (<span id="selectedCountDisplay" class="font-bold text-indigo-600 dark:text-indigo-400">0</span> selected)
                </span>
            </div>

            <button type="button" id="bulkSubmitBtn" onclick="openConfirmationModal()" disabled class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-2 shadow-sm">
                🚀 Submit Selected to Institutional Repository
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
                            <span class="text-3xl">🎵</span>
                            <audio controls class="w-full h-8" preload="metadata" src="{{ $media->previewUrl() }}">
                                <source src="{{ $media->previewUrl() }}" type="{{ $media->mime_type ?? 'audio/mpeg' }}">
                                Your browser does not support audio playback for {{ $media->formatLabel() }}.
                            </audio>
                        </div>
                    @elseif($media->type === 'pdf')
                        <div class="p-4 flex flex-col items-center justify-center text-center gap-1 cursor-pointer" onclick="openPdfModal('{{ $media->previewUrl() }}', '{{ addslashes($media->title ?? $media->original_name) }}')">
                            <span class="text-4xl text-rose-500">📄</span>
                            <span class="text-[11px] font-bold text-slate-400">PDF Document</span>
                        </div>
                    @elseif($media->type === 'passage')
                        <div class="p-3 text-left overflow-y-auto max-h-[150px] w-full text-xs text-slate-300 font-mono">
                            <span class="text-[10px] font-bold uppercase text-indigo-400 block mb-1">Passage Content:</span>
                            {{ Str::limit($media->content_text ?? $media->description ?? 'No passage text', 150) }}
                        </div>
                    @else
                        <div class="p-4 flex flex-col items-center justify-center">
                            <span class="text-4xl text-slate-500">📎</span>
                            <span class="text-[11px] font-bold text-slate-400">{{ strtoupper($media->type) }}</span>
                        </div>
                    @endif

                    {{-- Top Right Badges --}}
                    <div class="absolute top-2.5 right-2.5 flex flex-col items-end gap-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-900/80 text-white backdrop-blur border border-white/10">
                            {{ $media->typeIcon() }} {{ strtoupper($media->type) }}
                        </span>
                        @if($isUsed)
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-sky-500/90 text-white backdrop-blur shadow-sm">
                            📌 IN ASSESSMENT
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
                        <p class="text-[11px] text-slate-400 truncate mt-0.5 font-mono">
                            {{ $media->original_name }} • {{ $media->humanSize() }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100 dark:border-slate-800/80">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 uppercase">
                            {{ strtoupper($media->exam_type ?? 'GENERAL') }}
                        </span>
                        <span class="text-[11px] text-slate-400">
                            {{ $media->created_at?->format('d M Y') }}
                        </span>
                    </div>

                    {{-- Governance / Repository Status Badge --}}
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Repository Status:</span>
                            @if($media->approval_status === 'approved')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    ✅ Institutional Asset
                                </span>
                            @elseif($media->approval_status === 'pending_review')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    ⏳ Pending RM Review
                                </span>
                            @elseif($media->approval_status === 'revision_requested')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                    ⚠️ Revision Requested
                                </span>
                            @elseif($media->approval_status === 'rejected')
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    ❌ Rejected
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                    📝 Working Media
                                </span>
                            @endif
                        </div>

                        {{-- Reviewer notes if any --}}
                        @if($reviewReq && !empty($reviewReq->review_notes) && in_array($media->approval_status, ['revision_requested', 'rejected', 'approved']))
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-200 dark:border-slate-800 italic">
                            💬 RM Note: "{{ Str::limit($reviewReq->review_notes, 80) }}"
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Action Footer --}}
                <div class="tm-foot">
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="showUsageModal('{{ $media->id }}', '{{ addslashes($media->title ?? $media->original_name) }}')" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors" title="View Assessment Usage">
                            📊 Usage
                        </button>
                        <a href="{{ route('admin.media.edit', $media->id) }}" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition-colors">
                            ✏ Edit
                        </a>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if($isEligibleForSubmission)
                        <button type="button" onclick="submitSingleMedia('{{ $media->id }}')" class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-600 text-white hover:bg-indigo-500 transition-colors">
                            🚀 Submit
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
        <div class="p-16 text-center rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-4xl mb-3">📭</div>
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">No Media Assets Found</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Upload your audio clips, diagrams, passages, or PDFs to start attaching them to questions and assessments.
            </p>
            <button type="button" onclick="openUploadModal()" class="mt-4 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-500">
                + Upload First Working Media
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
