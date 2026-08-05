@extends('layouts.admin')

@section('title', 'Institutional Media Repository — iC.edu Platform')

@push('styles')
<style>
.imr-page { display:flex; flex-direction:column; gap:1.75rem; }

.imr-hero {
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
.imr-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.imr-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

/* PART 1: CLICKABLE SUMMARY CARDS */
.imr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
}
@media (max-width: 1200px) { .imr-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .imr-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.imr-kpi {
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
.imr-kpi:hover {
    transform: translateY(-2px);
    border-color: #6366f1;
    box-shadow: 0 8px 24px rgba(99,102,241,.15);
}
.imr-kpi--active {
    border-color: #6366f1;
    background: linear-gradient(180deg, #1e1b4b 0%, #0f172a 100%);
}
.imr-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.imr-kpi__label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }

.imr-filter-bar {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.imr-exam-pills { display: flex; gap: .5rem; flex-wrap: wrap; }
.imr-exam-btn {
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
.imr-exam-btn--active { background: #6366f1; color: #fff; border-color: #6366f1; }

.imr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
}

.imr-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color .2s, transform .15s;
}
.imr-card:hover { border-color: #6366f1; transform: translateY(-2px); }

.imr-card__preview {
    height: 160px;
    background: #080f1d;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #1e293b;
    position: relative;
    overflow: hidden;
}
.imr-card__preview img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform .2s; }
.imr-card__preview img:hover { transform: scale(1.04); }

.imr-card__body { padding: 1.1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
.imr-card__title { font-size: .92rem; font-weight: 800; color: #f1f5f9; line-height: 1.35; text-decoration: none; }
.imr-card__title:hover { color: #818cf8; }
.imr-card__sub { font-size: .72rem; color: #64748b; }

.imr-tag { padding: .2rem .55rem; border-radius: 99px; font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; display: inline-block; white-space: nowrap; }
.imr-tag--toefl   { background: rgba(129,140,248,.15); color: #818cf8; border: 1px solid rgba(129,140,248,.3); }
.imr-tag--toeic   { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.imr-tag--ielts   { background: rgba(251,113,133,.15); color: #fb7185; border: 1px solid rgba(251,113,133,.3); }
.imr-tag--general { background: rgba(148,163,184,.15); color: #94a3b8; border: 1px solid rgba(148,163,184,.3); }

.imr-foot { padding: .85rem 1.1rem; border-top: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }

.imr-modal-bg { position: fixed; inset: 0; background: rgba(2,6,23,.82); backdrop-filter: blur(8px); z-index: 990; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
.imr-modal { background: #0f172a; border: 1px solid #1e293b; border-radius: 1.25rem; max-width: 650px; max-height: 85vh; overflow-y: auto; width: 100%; padding: 2rem; box-shadow: 0 24px 64px rgba(0,0,0,.7); }
</style>
@endpush

@section('content')
<div class="imr-page">

    {{-- Hero Header --}}
    <div class="imr-hero">
        <div>
            <h1 class="imr-hero__title">🏛 Institutional Media Repository</h1>
            <p class="imr-hero__sub">Official single source of truth for Question Media Library and reusable audio, images, PDF documents, and passage texts across iC.edu.</p>
        </div>
        <div>
            @if(auth()->user()->hasRole('teacher') || auth()->user()->hasRole('super-admin'))
            <button type="button" onclick="openUploadModal()" style="padding:.65rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.85rem;font-weight:700;cursor:pointer;">
                ⬆ Upload Institutional Asset
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

                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title, ID, tag, transcript..." style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;min-width:200px;">

                <select name="type" onchange="this.form.submit()" style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;">
                    <option value="">All Asset Types</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>Images</option>
                    <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>Audio</option>
                    <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>Video</option>
                    <option value="pdf" {{ request('type') === 'pdf' ? 'selected' : '' }}>PDF</option>
                    <option value="passage" {{ request('type') === 'passage' ? 'selected' : '' }}>Passages</option>
                </select>

                <button type="submit" style="background:#6366f1;color:#fff;border:none;padding:.45rem .9rem;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Filter</button>
            </form>
        </div>
    </div>

    {{-- PART 9: INFORMATIVE EMPTY STATES --}}
    @if($mediaAssets->isEmpty())
    <div style="background:#0f172a;border:1px dashed #334155;border-radius:1.25rem;padding:4rem 2rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:1rem;">
        <div style="font-size:3.5rem;">🔍</div>
        <h3 style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin:0;">
            No {{ strtoupper(request('exam_type', '')) }} {{ ucfirst(request('type', '')) }} Assets Found
        </h3>
        <p style="font-size:.85rem;color:#94a3b8;max-width:420px;line-height:1.5;margin:0;">
            No matching institutional assets exist for the selected filter combination. Upload a new asset or clear your search filters to explore the full repository.
        </p>
        <a href="{{ route('admin.media.index') }}" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;">
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
                        <div style="font-size:1.6rem;margin-bottom:.2rem;">🎵</div>
                        <audio controls controlsList="nodownload noplaybackrate" style="width:100%;height:32px;">
                            <source src="{{ $asset->publicUrl() }}" type="{{ $asset->mime_type }}">
                        </audio>
                        @if($asset->content_text)
                        <button type="button" onclick="toggleTranscript('trans-{{ $asset->id }}')" style="font-size:.65rem;color:#34d399;font-weight:700;background:none;border:none;cursor:pointer;margin-top:.3rem;">
                            📝 Toggle Transcript
                        </button>
                        <div id="trans-{{ $asset->id }}" style="display:none;font-size:.68rem;color:#94a3b8;text-align:left;background:#0f172a;padding:.5rem;border-radius:.4rem;margin-top:.3rem;max-height:60px;overflow-y:auto;">
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
                        <div style="font-size:2.2rem;margin-bottom:.2rem;">📄</div>
                        <button type="button" onclick="openPdfModal('{{ $asset->publicUrl() }}', '{{ addslashes($asset->title ?? $asset->original_name) }}')" style="font-size:.72rem;color:#fbbf24;font-weight:700;background:none;border:none;cursor:pointer;">
                            🔍 Embedded PDF Preview ↗
                        </button>
                    </div>

                @elseif($asset->type === 'passage')
                    <div style="padding:.75rem 1rem;font-size:.7rem;color:#cbd5e1;line-height:1.35;overflow:hidden;max-height:100%;">
                        📝 {{ Str::limit($asset->content_text ?? $asset->description, 130) }}
                    </div>
                @else
                    <div style="font-size:2.5rem;">📎</div>
                @endif
            </div>

            {{-- Card Body --}}
            <div class="imr-card__body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    @php $exType = strtolower($asset->exam_type ?? 'general'); @endphp
                    <span class="imr-tag imr-tag--{{ in_array($exType, ['toefl','toeic','ielts']) ? $exType : 'general' }}">
                        {{ strtoupper($exType) }}
                    </span>
                    <span style="font-size:.68rem;color:#64748b;font-weight:700;">v{{ $asset->version ?? '1.0' }}</span>
                </div>

                {{-- PART 4: Media Detail Link --}}
                <a href="{{ route('admin.media.show', $asset->id) }}" class="imr-card__title">
                    {{ $asset->title ?? $asset->original_name }}
                </a>
                <div class="imr-card__sub">Folder: {{ $asset->category ?? 'General Assets' }}</div>
                <div style="font-size:.68rem;color:#475569;margin-top:2px;">Size: {{ $asset->humanSize() }} • {{ $asset->created_at?->format('d M Y') }}</div>
            </div>

            {{-- Card Footer & Usage Tracker (PART 5 & 6) --}}
            <div class="imr-foot">
                <button type="button" onclick="showUsageModal('{{ $asset->id }}', '{{ addslashes($asset->title ?? $asset->original_name) }}')" style="background:none;border:none;color:#818cf8;font-size:.75rem;font-weight:700;cursor:pointer;padding:0;">
                    🔗 Usage Tracker
                </button>
                <button type="button" onclick="copyAssetUrl('{{ $asset->publicUrl() }}')" style="padding:.3rem .75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;">
                    Copy URL
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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 id="imageModalTitle" style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">🖼 Image Preview</h3>
                <span onclick="closeImageModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div style="background:#080f1d;border:1px solid #1e293b;border-radius:.85rem;padding:1rem;overflow:hidden;margin-bottom:1rem;">
                <img id="imageModalSrc" src="" style="max-width:100%;max-height:500px;object-fit:contain;transition:transform .3s;cursor:zoom-in;" onclick="toggleModalZoom()">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <button type="button" onclick="toggleModalZoom()" style="padding:.4rem 1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    🔍 Toggle Zoom
                </button>
                <button type="button" onclick="closeImageModal()" style="padding:.4rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- PART 2: Embedded PDF Viewer Modal --}}
    <div id="pdfModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal" style="max-width:850px;height:85vh;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 id="pdfModalTitle" style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">📄 Embedded PDF Viewer</h3>
                <span onclick="closePdfModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div style="flex:1;background:#fff;border-radius:.75rem;overflow:hidden;margin-bottom:1rem;">
                <iframe id="pdfModalSrc" src="" style="width:100%;height:100%;border:none;"></iframe>
            </div>
            <div style="text-align:right;">
                <button type="button" onclick="closePdfModal()" style="padding:.4rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    Close Viewer
                </button>
            </div>
        </div>
    </div>

    {{-- PART 5: Usage Tracker Modal --}}
    <div id="usageModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 id="usageTitle" style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">🔗 Usage Explorer</h3>
                <span onclick="closeUsageModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div id="usageBody" style="font-size:.85rem;color:#cbd5e1;line-height:1.5;">
                Loading usage details...
            </div>
        </div>
    </div>

    {{-- Upload Modal --}}
    <div id="uploadModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;">⬆ Upload Institutional Media Asset</h3>
                <span onclick="closeUploadModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    <div>
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Asset Title</label>
                        <input type="text" name="title" required placeholder="e.g. TOEFL Listening Audio Transcript 01" style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Exam Type</label>
                            <select name="exam_type" style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                                <option value="toefl">TOEFL</option>
                                <option value="toeic">TOEIC</option>
                                <option value="ielts">IELTS</option>
                                <option value="placement">Placement</option>
                                <option value="grammar">Grammar</option>
                                <option value="vocabulary">Vocabulary</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Folder Category</label>
                            <input type="text" name="category" placeholder="e.g. Listening Audio" style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Media File (Image, Audio, Video, PDF)</label>
                        <input type="file" name="file" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                    </div>
                    <button type="submit" style="width:100%;padding:.75rem;background:#6366f1;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;margin-top:.5rem;">
                        Upload to Repository
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }

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
    alert('✅ Internal Asset URL Copied to Clipboard!\n\n' + url);
}
</script>
@endsection
