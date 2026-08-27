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
                <a href="{{ route('admin.media.index') }}" style="color:#4f46e5;text-decoration:none;">← Institutional Media Repository</a>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                ✏️ Edit Media Asset: {{ $media->title ?? $media->original_name }}
            </h1>
        </div>

        <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;">
            <a href="{{ route('admin.media.versions', $media->id) }}" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700" style="padding:.55rem 1.1rem;border-radius:.6rem;font-size:.8rem;font-weight:700;text-decoration:none;">
                📄 Version History (v{{ $media->version ?? '1.0' }})
            </a>
            <button type="submit" form="editMediaForm" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700" style="padding:.55rem 1.1rem;border-radius:.6rem;font-size:.8rem;font-weight:800;cursor:pointer;">
                💾 Save Draft (Working Copy)
            </button>
            <form action="{{ route('admin.media.submit-review', $media->id) }}" method="POST" style="display:inline;" onsubmit="event.preventDefault(); iapConfirm({ title: 'Submit for Review?', message: 'Submit this working copy candidate to Repository Manager for Quality Assurance Review?', confirmText: 'Submit for Review', variant: 'primary', form: this });">
                @csrf
                <button type="submit" style="padding:.55rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.6rem;font-size:.8rem;font-weight:800;cursor:pointer;">
                    🚀 Submit for Review
                </button>
            </form>
        </div>
    </div>

    @if(session('status'))
    <div style="padding:1rem 1.25rem;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#34d399;border-radius:.75rem;font-size:.85rem;font-weight:700;">
        ✔ {{ session('status') }}
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
                    <h3 class="tme-section-title">📌 General Information</h3>

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
                                <option value="toeic" {{ (old('exam_type', $media->exam_type) === 'toeic') ? 'selected' : '' }}>TOEIC Official</option>
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

                {{-- TASK 3: Passage Format Editor (Text / Image / Hybrid) --}}
                @if($media->type === 'passage' || request('type') === 'passage')
                <div class="tme-card" style="border-color:#4f46e5;">
                    <h3 class="tme-section-title" style="color:#4f46e5;">📖 Passage Editor (Format Selector)</h3>

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
                        <div style="margin-top:.5rem;padding:.5rem;border-radius:.5rem;display:inline-block;" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <img src="{{ $media->publicUrl() }}" style="max-height:150px;border-radius:.4rem;" alt="Passage Image">
                        </div>
                        @endif
                    </div>
                </div>
                @else
                <div class="tme-card">
                    <h3 class="tme-section-title">📝 Asset Content / Transcript</h3>
                    <div class="tme-form-group">
                        <label class="tme-label">Transcript / Content Text</label>
                        <textarea name="content_text" rows="6" class="tme-textarea" placeholder="Associated text transcript or document content...">{{ old('content_text', $media->content_text) }}</textarea>
                    </div>
                </div>
                @endif

                {{-- Live Interactive Preview Section --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">👁 Asset Live Preview</h3>
                    <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800" style="border-radius:.85rem;padding:1.5rem;display:flex;align-items:center;justify-content:center;min-height:200px;">
                        @if($media->type === 'image')
                            <img src="{{ $media->publicUrl() }}" style="max-width:100%;max-height:350px;border-radius:.5rem;" alt="{{ $media->title }}">
                        @elseif($media->type === 'audio')
                            <div style="width:100%;text-align:center;">
                                <div style="font-size:2.5rem;margin-bottom:.5rem;">🎵</div>
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
                    <h3 class="tme-section-title">⚖ Governance Information</h3>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="tme-label">Approval Status</span>
                        @php $st = strtolower($media->approval_status ?? 'approved'); @endphp
                        <span class="tme-badge tme-badge--{{ $st === 'approved' ? 'approved' : ($st === 'pending_review' ? 'pending' : ($st === 'revision_requested' ? 'revision' : 'draft')) }}">
                            {{ strtoupper($st) }}
                        </span>
                    </div>

                    <div style="font-size:.8rem;line-height:1.5;margin-top:.5rem;" class="text-slate-600 dark:text-slate-400 space-y-1">
                        <div>Author: <strong class="text-slate-900 dark:text-white">{{ $media->uploader?->name ?? 'Institutional System' }}</strong></div>
                        <div>Asset Type: <strong class="text-slate-900 dark:text-white">{{ strtoupper($media->type) }}</strong></div>
                        <div>Original File: <strong class="text-indigo-600 dark:text-indigo-400">{{ $media->original_name }}</strong></div>
                    </div>
                </div>

                {{-- Version Information Panel --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">📄 Version Control</h3>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="tme-label">Published Version</span>
                        <span style="font-size:1.1rem;font-weight:900;color:#0284c7;">v{{ $media->version ?? '1.0' }}</span>
                    </div>

                    <div style="font-size:.78rem;line-height:1.4;" class="text-slate-600 dark:text-slate-400">
                        Working copy updates create version candidate <strong>v{{ ((float)($media->version ?? 1.0) + 0.1) }}-candidate</strong>. Published version is never modified directly.
                    </div>

                    <a href="{{ route('admin.media.versions', $media->id) }}" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700" style="padding:.5rem;border-radius:.5rem;font-size:.78rem;font-weight:700;text-align:center;text-decoration:none;">
                        View All {{ $versions->count() }} Versions &amp; Log
                    </a>
                </div>

                {{-- TASK 2: Reusable Slot Mapping --}}
                <div class="tme-card">
                    <h3 class="tme-section-title">🔗 Reusable Slot Mapping</h3>

                    <div style="font-size:.78rem;" class="text-slate-600 dark:text-slate-400">
                        Used in <strong class="text-slate-900 dark:text-white">{{ $linkedQuestions->count() }}</strong> Question Slot(s):
                    </div>

                    @if($linkedQuestions->isNotEmpty())
                        <div style="display:flex;flex-direction:column;gap:.5rem;max-height:200px;overflow-y:auto;">
                            @foreach($linkedQuestions as $q)
                            <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200" style="border-radius:.5rem;padding:.5rem .75rem;font-size:.75rem;">
                                <div style="color:#0284c7;font-weight:700;">{{ $q->questionBank?->title ?? 'Question Bank' }}</div>
                                <div class="text-slate-600 dark:text-slate-300">Q: {{ Str::limit($q->prompt, 50) }}</div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div style="font-size:.75rem;font-style:italic;" class="text-slate-500 dark:text-slate-400">
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
