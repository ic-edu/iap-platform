@extends('layouts.admin')

@section('title', 'Edit Media Asset — ' . ($media->title ?? $media->original_name))

@push('styles')
<style>
.tme-page { display:flex; flex-direction:column; gap:1.5rem; }

.tme-header {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 50%, #eef2ff 100%);
    border: 1px solid #dbe0fc;
    border-radius: 1.25rem;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 4px 16px -2px rgba(99,102,241,0.06);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tme-header, html[data-theme="dark"] .tme-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border-color: #1e293b;
    box-shadow: 0 4px 16px -2px rgba(0,0,0,0.4);
}

.tme-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.5rem;
}
@media (max-width: 1024px) { .tme-grid { grid-template-columns: 1fr; } }

.tme-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .tme-card, html[data-theme="dark"] .tme-card {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.tme-section-title {
    font-size: .95rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: .5rem;
    margin: 0;
    padding-bottom: .65rem;
    border-bottom: 1px solid #e2e8f0;
}
html.dark .tme-section-title, html[data-theme="dark"] .tme-section-title {
    color: #f8fafc;
    border-bottom-color: #1e293b;
}

.tme-form-group {
    display: flex;
    flex-direction: column;
    gap: .4rem;
}

.tme-label {
    font-size: .75rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .05em;
}
html.dark .tme-label, html[data-theme="dark"] .tme-label {
    color: #94a3b8;
}

.tme-input, .tme-select, .tme-textarea {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: .6rem;
    color: #0f172a;
    padding: .6rem .85rem;
    font-size: .85rem;
    width: 100%;
    transition: border-color .15s, background .15s, color .15s;
}
html.dark .tme-input, html.dark .tme-select, html.dark .tme-textarea,
html[data-theme="dark"] .tme-input, html[data-theme="dark"] .tme-select, html[data-theme="dark"] .tme-textarea {
    background: #1e293b;
    border-color: #334155;
    color: #fff;
}
.tme-input:focus, .tme-select:focus, .tme-textarea:focus {
    border-color: #6366f1;
    outline: none;
}

.tme-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .75rem;
    border-radius: 99px;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
}
.tme-badge--approved { background: rgba(16,185,129,.15); color: #059669; border: 1px solid rgba(16,185,129,.3); }
html.dark .tme-badge--approved, html[data-theme="dark"] .tme-badge--approved { color: #34d399; }
.tme-badge--pending  { background: rgba(245,158,11,.15); color: #d97706; border: 1px solid rgba(245,158,11,.3); }
html.dark .tme-badge--pending, html[data-theme="dark"] .tme-badge--pending { color: #fbbf24; }
.tme-badge--draft    { background: rgba(148,163,184,.15); color: #475569; border: 1px solid rgba(148,163,184,.3); }
html.dark .tme-badge--draft, html[data-theme="dark"] .tme-badge--draft { color: #cbd5e1; }
.tme-badge--revision { background: rgba(239,68,68,.15); color: #e11d48; border: 1px solid rgba(239,68,68,.3); }
html.dark .tme-badge--revision, html[data-theme="dark"] .tme-badge--revision { color: #f87171; }

.tme-passage-pill {
    padding: .5rem 1rem;
    border-radius: .6rem;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-size: .8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .15s;
}
html.dark .tme-passage-pill, html[data-theme="dark"] .tme-passage-pill {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}
.tme-passage-pill--active {
    background: #4f46e5;
    color: #fff;
    border-color: #4f46e5;
}
html.dark .tme-passage-pill--active, html[data-theme="dark"] .tme-passage-pill--active {
    background: #6366f1;
    color: #fff;
    border-color: #6366f1;
}
</style>
@endpush

@section('content')
<div class="tme-page">

    {{-- Top Header & Navigation --}}
    <div class="tme-header">
        <div>
            <div style="font-size:.78rem;font-weight:700;margin-bottom:.2rem;">
                <a href="{{ route('admin.media.index') }}" class="text-indigo-700 dark:text-indigo-400 text-xs font-bold no-underline inline-flex items-center gap-1 hover:underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Institutional Media Repository
                </a>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit Media Asset: {{ $media->title ?? $media->original_name }}
            </h1>
        </div>

        <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;">
            <a href="{{ route('admin.media.versions', $media->id) }}" class="bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 px-3 py-2 rounded-xl text-xs font-bold transition-all inline-flex items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Version History (v{{ $media->version ?? '1.0' }})
            </a>
            <button type="submit" form="editMediaForm" class="bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 px-3 py-2 rounded-xl text-xs font-bold transition-all inline-flex items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Save Draft (Working Copy)
            </button>
            <form action="{{ route('admin.media.submit-review', $media->id) }}" method="POST" style="display:inline;" onsubmit="event.preventDefault(); iapConfirm({ title: 'Submit for Review?', message: 'Submit this working copy candidate to Repository Manager for Quality Assurance Review?', confirmText: 'Submit for Review', variant: 'primary', form: this });">
                @csrf
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    Submit for Review
                </button>
            </form>
        </div>
    </div>

    @if(session('status'))
    <div style="padding:1rem 1.25rem;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#065f46;border-radius:.75rem;font-size:.85rem;font-weight:700;">
        {{ session('status') }}
    </div>
    @endif

    <form id="editMediaForm" action="{{ route('admin.media.update', $media->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="tme-grid">

            {{-- Left Column: Main Editor Sections --}}
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                {{-- SECTION 1: General Information --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        General Information
                    </h3>

                    <div class="tme-form-group">
                        <label class="tme-label">Asset Title *</label>
                        <input type="text" name="title" value="{{ old('title', $media->title ?? $media->original_name) }}" class="tme-input" required placeholder="e.g. TOEFL Listening Audio Track 1: Academic Lecture">
                    </div>

                    <div class="tme-form-group">
                        <label class="tme-label">Description</label>
                        <textarea name="description" rows="3" class="tme-textarea" placeholder="Detailed educational description of this asset...">{{ old('description', $media->description) }}</textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                        <div class="tme-form-group">
                            <label class="tme-label">Folder / Category</label>
                            <input type="text" name="category" value="{{ old('category', $media->category ?? 'General Assets') }}" class="tme-input">
                        </div>
                        <div class="tme-form-group">
                            <label class="tme-label">Exam Type</label>
                            <select name="exam_type" class="tme-select">
                                <option value="toefl" {{ (old('exam_type', $media->exam_type) === 'toefl') ? 'selected' : '' }}>TOEFL iBT</option>
                                <option value="toeic" {{ (old('exam_type', $media->exam_type) === 'toeic') ? 'selected' : '' }}>TOEIC Institutional</option>
                                <option value="ielts" {{ (old('exam_type', $media->exam_type) === 'ielts') ? 'selected' : '' }}>IELTS Academic</option>
                                <option value="placement" {{ (old('exam_type', $media->exam_type) === 'placement') ? 'selected' : '' }}>Placement</option>
                                <option value="grammar" {{ (old('exam_type', $media->exam_type) === 'grammar') ? 'selected' : '' }}>Grammar</option>
                                <option value="vocabulary" {{ (old('exam_type', $media->exam_type) === 'vocabulary') ? 'selected' : '' }}>Vocabulary</option>
                                <option value="general" {{ (old('exam_type', $media->exam_type) === 'general') ? 'selected' : '' }}>General</option>
                            </select>
                        </div>
                        <div class="tme-form-group">
                            <label class="tme-label">Difficulty</label>
                            <select name="difficulty" class="tme-select">
                                <option value="easy" {{ (old('difficulty', $media->difficulty) === 'easy') ? 'selected' : '' }}>Easy</option>
                                <option value="medium" {{ (old('difficulty', $media->difficulty) === 'medium' || !$media->difficulty) ? 'selected' : '' }}>Medium</option>
                                <option value="hard" {{ (old('difficulty', $media->difficulty) === 'hard') ? 'selected' : '' }}>Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="tme-form-group">
                        <label class="tme-label">Tags (Comma Separated)</label>
                        <input type="text" name="tags" value="{{ old('tags', is_array($media->tags) ? implode(', ', $media->tags) : $media->tags) }}" class="tme-input" placeholder="institutional, reading, starter">
                    </div>
                </div>

                {{-- Passage Format Editor (Text / Image / Hybrid) --}}
                @if($media->type === 'passage' || request('type') === 'passage')
                <div class="tme-card" style="border-color:#4f46e5;">
                    <h3 class="tme-section-title" style="color:#4f46e5;">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Passage Editor (Format Selector)
                    </h3>

                    <div style="display:flex;gap:.75rem;align-items:center;">
                        <span class="tme-label" style="margin:0;">Passage Format:</span>
                        <input type="hidden" name="passage_type" id="passageTypeInput" value="HYBRID">
                        <button type="button" onclick="setPassageFormat('TEXT')" id="pillTEXT" class="tme-passage-pill">Rich Text Only</button>
                        <button type="button" onclick="setPassageFormat('IMAGE')" id="pillIMAGE" class="tme-passage-pill">Image Only</button>
                        <button type="button" onclick="setPassageFormat('HYBRID')" id="pillHYBRID" class="tme-passage-pill tme-passage-pill--active">Hybrid (Text + Image)</button>
                    </div>

                    <div id="passageTextGroup" class="tme-form-group">
                        <label class="tme-label">Passage Content (Rich Text / Plain Text)</label>
                        <textarea name="content_text" rows="8" class="tme-textarea" style="font-family:monospace;line-height:1.5;" placeholder="Enter passage paragraph text...">{{ old('content_text', $media->content_text ?? $media->description) }}</textarea>
                    </div>

                    <div id="passageImageGroup" class="tme-form-group">
                        <label class="tme-label">Passage Visual Diagram / Scan Image</label>
                        <input type="file" name="passage_image" accept="image/*" class="tme-input">
                        @if($media->type === 'image' || ($media->path && str_contains($media->mime_type, 'image')))
                        <div style="margin-top:.5rem;padding:.5rem;border-radius:.5rem;display:inline-block;" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-800">
                            <img src="{{ $media->publicUrl() }}" style="max-height:150px;border-radius:.4rem;" alt="Passage Image">
                        </div>
                        @endif
                    </div>
                </div>
                @else
                <div class="tme-card">
                    <h3 class="tme-section-title">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Asset Content / Transcript
                    </h3>
                    <div class="tme-form-group">
                        <label class="tme-label">Transcript / Content Text</label>
                        <textarea name="content_text" rows="6" class="tme-textarea" placeholder="Associated text transcript or document content...">{{ old('content_text', $media->content_text) }}</textarea>
                    </div>
                </div>
                @endif

                {{-- Live Interactive Preview Section --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        Asset Live Preview
                    </h3>
                    <div class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800" style="border-radius:.85rem;padding:1.5rem;display:flex;align-items:center;justify-content:center;min-height:200px;">
                        @if($media->type === 'image')
                            <img src="{{ $media->publicUrl() }}" style="max-width:100%;max-height:350px;border-radius:.5rem;" alt="{{ $media->title }}">
                        @elseif($media->type === 'audio')
                            <div style="width:100%;text-align:center;">
                                <div class="text-indigo-600 dark:text-indigo-400 mb-2 flex justify-center">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                                </div>
                                <audio controls style="width:100%;max-width:500px;">
                                    <source src="{{ $media->publicUrl() }}" type="{{ $media->mime_type }}">
                                </audio>
                            </div>
                        @elseif($media->type === 'video')
                            <video controls style="max-width:100%;max-height:350px;border-radius:.5rem;">
                                <source src="{{ $media->publicUrl() }}" type="video/mp4">
                            </video>
                        @elseif($media->type === 'pdf')
                            <div style="width:100%;height:350px;">
                                <iframe src="{{ $media->publicUrl() }}" style="width:100%;height:100%;border:none;border-radius:.5rem;"></iframe>
                            </div>
                        @else
                            <div style="font-size:.85rem;line-height:1.6;white-space:pre-wrap;" class="text-slate-800 dark:text-slate-200">
                                {{ $media->content_text ?? $media->description ?? 'Reading passage content text preview.' }}
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Right Column: Governance & Versioning Sidebar --}}
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                {{-- Governance Panel --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                        Governance Information
                    </h3>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="tme-label">Approval Status</span>
                        @php $st = strtolower($media->approval_status ?? 'approved'); @endphp
                        <span class="tme-badge tme-badge--{{ $st === 'approved' ? 'approved' : ($st === 'pending_review' ? 'pending' : ($st === 'revision_requested' ? 'revision' : 'draft')) }}">
                            {{ strtoupper($st) }}
                        </span>
                    </div>

                    <div style="font-size:.82rem;line-height:1.5;margin-top:.5rem;" class="text-slate-700 dark:text-slate-300 space-y-1">
                        <div>Author: <strong class="text-slate-900 dark:text-white">{{ $media->uploader?->name ?? 'Institutional System' }}</strong></div>
                        <div>Asset Type: <strong class="text-slate-900 dark:text-white">{{ strtoupper($media->type) }}</strong></div>
                        <div>Original File: <strong class="text-indigo-700 dark:text-indigo-400">{{ $media->original_name }}</strong></div>
                    </div>
                </div>

                {{-- Version Information Panel --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Version Control
                    </h3>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="tme-label">Published Version</span>
                        <span style="font-size:1.1rem;font-weight:900;color:#0284c7;">v{{ $media->version ?? '1.0' }}</span>
                    </div>

                    <div style="font-size:.8rem;line-height:1.4;" class="text-slate-700 dark:text-slate-300">
                        Working copy updates create version candidate <strong>v{{ ((float)($media->version ?? 1.0) + 0.1) }}-candidate</strong>. Published version is never modified directly.
                    </div>

                    <a href="{{ route('admin.media.versions', $media->id) }}" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700" style="padding:.5rem;border-radius:.5rem;font-size:.78rem;font-weight:700;text-align:center;text-decoration:none;">
                        View All {{ $versions->count() }} Versions &amp; Log
                    </a>
                </div>

                {{-- Reusable Slot Mapping --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        Reusable Slot Mapping
                    </h3>

                    <div style="font-size:.8rem;" class="text-slate-700 dark:text-slate-300">
                        Used in <strong class="text-slate-900 dark:text-white">{{ $linkedQuestions->count() }}</strong> Question Slot(s):
                    </div>

                    @if($linkedQuestions->isNotEmpty())
                        <div style="display:flex;flex-direction:column;gap:.5rem;max-height:200px;overflow-y:auto;">
                            @foreach($linkedQuestions as $q)
                            <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-200" style="border-radius:.5rem;padding:.5rem .75rem;font-size:.75rem;">
                                <div style="color:#0284c7;font-weight:800;">{{ $q->questionBank?->title ?? 'Question Bank' }}</div>
                                <div class="text-slate-700 dark:text-slate-300 font-medium">Q: {{ Str::limit($q->prompt, 50) }}</div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div style="font-size:.78rem;font-style:italic;" class="text-slate-600 dark:text-slate-400">
                            Not linked to any active Question Slot yet.
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </form>

</div>

@push('scripts')
<script>
function setPassageFormat(mode) {
    document.getElementById('passageTypeInput').value = mode;
    document.querySelectorAll('.tme-passage-pill').forEach(p => p.classList.remove('tme-passage-pill--active'));
    document.getElementById('pill' + mode).classList.add('tme-passage-pill--active');

    const textGrp = document.getElementById('passageTextGroup');
    const imgGrp  = document.getElementById('passageImageGroup');

    if (mode === 'TEXT') {
        if(textGrp) textGrp.style.display = 'flex';
        if(imgGrp) imgGrp.style.display = 'none';
    } else if (mode === 'IMAGE') {
        if(textGrp) textGrp.style.display = 'none';
        if(imgGrp) imgGrp.style.display = 'flex';
    } else {
        if(textGrp) textGrp.style.display = 'flex';
        if(imgGrp) imgGrp.style.display = 'flex';
    }
}
</script>
@endpush
@endsection
