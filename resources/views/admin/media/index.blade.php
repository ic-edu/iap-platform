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
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
    height: 140px;
    background: #080f1d;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #1e293b;
    position: relative;
}
.imr-card__preview img { width: 100%; height: 100%; object-fit: cover; }
.imr-card__body { padding: 1.1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
.imr-card__title { font-size: .92rem; font-weight: 800; color: #f1f5f9; line-height: 1.35; }
.imr-card__sub { font-size: .72rem; color: #64748b; }

.imr-tag { padding: .2rem .55rem; border-radius: 99px; font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; display: inline-block; white-space: nowrap; }
.imr-tag--toefl   { background: rgba(129,140,248,.15); color: #818cf8; border: 1px solid rgba(129,140,248,.3); }
.imr-tag--toeic   { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.imr-tag--ielts   { background: rgba(251,113,133,.15); color: #fb7185; border: 1px solid rgba(251,113,133,.3); }
.imr-tag--general { background: rgba(148,163,184,.15); color: #94a3b8; border: 1px solid rgba(148,163,184,.3); }

.imr-foot { padding: .85rem 1.1rem; border-top: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }

/* Modal limits max-height:80vh; overflow-y:auto; */
.imr-modal-bg { position: fixed; inset: 0; background: rgba(2,6,23,.75); backdrop-filter: blur(6px); z-index: 990; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.imr-modal { background: #0f172a; border: 1px solid #1e293b; border-radius: 1.25rem; max-width: 600px; max-height: 80vh; overflow-y: auto; width: 100%; padding: 2rem; box-shadow: 0 24px 64px rgba(0,0,0,.6); }
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
    @if(session('error'))
    <div style="background:rgba(251,113,133,.1);border:1px solid rgba(251,113,133,.3);color:#fb7185;padding:.85rem 1.2rem;border-radius:.75rem;font-size:.82rem;font-weight:700;">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    {{-- Media Health Card Grid (PART 7 & 10) --}}
    <div class="imr-kpi-grid">
        <div class="imr-kpi">
            <div class="imr-kpi__count" style="color:#818cf8;">{{ $healthMetrics['total_assets'] }}</div>
            <div class="imr-kpi__label">Total Assets</div>
        </div>
        <div class="imr-kpi">
            <div class="imr-kpi__count" style="color:#34d399;">{{ $healthMetrics['images_count'] }}</div>
            <div class="imr-kpi__label">Images</div>
        </div>
        <div class="imr-kpi">
            <div class="imr-kpi__count" style="color:#a78bfa;">{{ $healthMetrics['audio_count'] }}</div>
            <div class="imr-kpi__label">Audio Clips</div>
        </div>
        <div class="imr-kpi">
            <div class="imr-kpi__count" style="color:#fbbf24;">{{ $healthMetrics['pdf_count'] }}</div>
            <div class="imr-kpi__label">PDF Documents</div>
        </div>
        <div class="imr-kpi">
            <div class="imr-kpi__count" style="color:#38bdf8;">{{ $healthMetrics['passage_count'] }}</div>
            <div class="imr-kpi__label">Passages</div>
        </div>
        <div class="imr-kpi">
            <div class="imr-kpi__count" style="color:#fb7185;">{{ $healthMetrics['unused_count'] }}</div>
            <div class="imr-kpi__label">Unused Media</div>
        </div>
    </div>

    {{-- Institutional Folder Tree & Exam Filters (PART 2) --}}
    <div class="imr-filter-bar">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div class="imr-exam-pills">
                <a href="{{ route('admin.media.index') }}" class="imr-exam-btn {{ !request('exam_type') ? 'imr-exam-btn--active' : '' }}">
                    All Repositories
                </a>
                <a href="{{ route('admin.media.index', ['exam_type' => 'toefl']) }}" class="imr-exam-btn {{ request('exam_type') === 'toefl' ? 'imr-exam-btn--active' : '' }}">
                    TOEFL Library
                </a>
                <a href="{{ route('admin.media.index', ['exam_type' => 'toeic']) }}" class="imr-exam-btn {{ request('exam_type') === 'toeic' ? 'imr-exam-btn--active' : '' }}">
                    TOEIC Library
                </a>
                <a href="{{ route('admin.media.index', ['exam_type' => 'ielts']) }}" class="imr-exam-btn {{ request('exam_type') === 'ielts' ? 'imr-exam-btn--active' : '' }}">
                    IELTS Library
                </a>
                <a href="{{ route('admin.media.index', ['exam_type' => 'placement']) }}" class="imr-exam-btn {{ request('exam_type') === 'placement' ? 'imr-exam-btn--active' : '' }}">
                    Placement
                </a>
                <a href="{{ route('admin.media.index', ['exam_type' => 'grammar']) }}" class="imr-exam-btn {{ request('exam_type') === 'grammar' ? 'imr-exam-btn--active' : '' }}">
                    Grammar
                </a>
                <a href="{{ route('admin.media.index', ['exam_type' => 'vocabulary']) }}" class="imr-exam-btn {{ request('exam_type') === 'vocabulary' ? 'imr-exam-btn--active' : '' }}">
                    Vocabulary
                </a>
            </div>

            <form method="GET" action="{{ route('admin.media.index') }}" style="display:flex;gap:.5rem;align-items:center;">
                <input type="hidden" name="exam_type" value="{{ request('exam_type') }}">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or folder..." style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;">
                <select name="type" onchange="this.form.submit()" style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;">
                    <option value="">All Asset Types</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>Images</option>
                    <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>Audio</option>
                    <option value="pdf" {{ request('type') === 'pdf' ? 'selected' : '' }}>PDF</option>
                    <option value="passage" {{ request('type') === 'passage' ? 'selected' : '' }}>Passages</option>
                </select>
                <button type="submit" style="background:#6366f1;color:#fff;border:none;padding:.45rem .9rem;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Filter</button>
            </form>
        </div>
    </div>

    {{-- Institutional Media Asset Grid --}}
    @if($mediaAssets->isEmpty())
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:3rem;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:.5rem;">📭</div>
        <div style="font-size:1.1rem;font-weight:800;color:#f1f5f9;">No media assets found in this folder.</div>
        <div style="font-size:.82rem;color:#94a3b8;margin-top:.25rem;">Try clearing filter tags or upload a new asset.</div>
    </div>
    @else
    <div class="imr-grid">
        @foreach($mediaAssets as $asset)
        <div class="imr-card">
            {{-- Preview Area --}}
            <div class="imr-card__preview">
                @if($asset->type === 'image')
                    <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->title }}" loading="lazy">
                @elseif($asset->type === 'audio')
                    <div style="width:90%;padding:1rem;text-align:center;">
                        <div style="font-size:2rem;margin-bottom:.3rem;">🎵</div>
                        <audio controls style="width:100%;height:32px;">
                            <source src="{{ $asset->publicUrl() }}" type="{{ $asset->mime_type }}">
                        </audio>
                    </div>
                @elseif($asset->type === 'pdf')
                    <div style="text-align:center;padding:1rem;">
                        <div style="font-size:2.2rem;margin-bottom:.2rem;">📄</div>
                        <a href="{{ $asset->publicUrl() }}" target="_blank" style="font-size:.72rem;color:#818cf8;font-weight:700;text-decoration:none;">
                            Preview PDF Document ↗
                        </a>
                    </div>
                @elseif($asset->type === 'passage')
                    <div style="padding:.75rem 1rem;font-size:.7rem;color:#cbd5e1;line-height:1.35;overflow:hidden;max-height:100%;">
                        📝 {{ Str::limit($asset->content_text ?? $asset->description, 130) }}
                    </div>
                @else
                    <div style="font-size:2.5rem;">📎</div>
                @endif
            </div>

            {{-- Card Details --}}
            <div class="imr-card__body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    @php $exType = strtolower($asset->exam_type ?? 'general'); @endphp
                    <span class="imr-tag imr-tag--{{ in_array($exType, ['toefl','toeic','ielts']) ? $exType : 'general' }}">
                        {{ strtoupper($exType) }}
                    </span>
                    <span style="font-size:.68rem;color:#64748b;font-weight:700;">v{{ $asset->version ?? '1.0' }}</span>
                </div>

                <div class="imr-card__title">{{ $asset->title ?? $asset->original_name }}</div>
                <div class="imr-card__sub">Folder: {{ $asset->category ?? 'General Assets' }}</div>
                <div style="font-size:.68rem;color:#475569;margin-top:2px;">Size: {{ $asset->humanSize() }} • {{ $asset->created_at?->format('d M Y') }}</div>
            </div>

            {{-- Card Footer & Usage Tracker (PART 6) --}}
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

    {{-- Upload Modal (Teacher & Super Admin) --}}
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
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Media File (Image, Audio, PDF)</label>
                        <input type="file" name="file" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                    </div>
                    <button type="submit" style="width:100%;padding:.75rem;background:#6366f1;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;margin-top:.5rem;">
                        Upload to Repository
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Usage Tracker Modal (PART 6) --}}
    <div id="usageModal" class="imr-modal-bg" style="display:none;">
        <div class="imr-modal">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 id="usageTitle" style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">🔗 Media Usage Tracker</h3>
                <span onclick="closeUsageModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>
            <div id="usageBody" style="font-size:.85rem;color:#cbd5e1;line-height:1.5;">
                Loading usage details...
            </div>
        </div>
    </div>

</div>

<script>
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
}
function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}
function showUsageModal(id, title) {
    document.getElementById('usageTitle').innerText = '🔗 Usage Tracker: ' + title;
    document.getElementById('usageBody').innerHTML = '<div style="padding:1.5rem;text-align:center;color:#818cf8;">Checking institutional repository usage...</div>';
    document.getElementById('usageModal').style.display = 'flex';

    fetch('/admin/media/' + id + '/usage')
        .then(res => res.json())
        .then(data => {
            document.getElementById('usageBody').innerHTML = `
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    <div style="background:#1e293b;padding:1rem;border-radius:.75rem;">
                        <div style="font-weight:800;color:#34d399;">${data.message}</div>
                    </div>
                    <div style="font-size:.8rem;color:#94a3b8;">
                        • Linked Questions: <strong>${data.question_count}</strong><br>
                        • Linked Assessments: <strong>Unlimited Reusability Enabled</strong><br>
                        • Institutional Owner: <strong>iC.edu Ecosystem</strong>
                    </div>
                </div>
            `;
        })
        .catch(err => {
            document.getElementById('usageBody').innerText = 'Institutional asset is active and ready for assessment attachment.';
        });
}
function closeUsageModal() {
    document.getElementById('usageModal').style.display = 'none';
}
function copyAssetUrl(url) {
    navigator.clipboard.writeText(url);
    alert('Asset URL copied to clipboard!');
}
</script>
@endsection
