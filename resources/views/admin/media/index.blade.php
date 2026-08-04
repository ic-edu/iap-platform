@extends('layouts.admin')

@section('title', 'Media Management — iC.edu Platform')

@push('styles')
<style>
/* ──────────────────────────────────────
   MEDIA MANAGEMENT — MEDIA-002
────────────────────────────────────── */
.ml-page { display:flex; flex-direction:column; gap:1.5rem; }

.ml-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.ml-title  { font-size:1.45rem; font-weight:800; color:#f1f5f9; margin:0 0 .25rem; }
.ml-sub    { font-size:.82rem; color:#64748b; margin:0; }

/* Filter bar */
.ml-filter { background:#0f172a; border:1px solid #1e293b; border-radius:.85rem; padding:.85rem 1.1rem; display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; }
.ml-search { flex:1; min-width:160px; background:#1e293b; border:1px solid #334155; border-radius:.5rem; color:#e2e8f0; font-size:.82rem; padding:.45rem .85rem; outline:none; transition:border-color .15s; }
.ml-search:focus { border-color:#6366f1; }
.ml-filter-pills { display:flex; gap:.4rem; flex-wrap:wrap; }
.ml-pill { padding:.25rem .7rem; border-radius:99px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; border:1px solid #334155; color:#94a3b8; background:#1e293b; cursor:pointer; text-decoration:none; transition:background .15s; white-space:nowrap; }
.ml-pill:hover, .ml-pill--active { background:#6366f1; color:#fff; border-color:#6366f1; }

/* Media grid */
.ml-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1rem; }

/* Media card */
.ml-card { background:#0f172a; border:1px solid #1e293b; border-radius:1rem; overflow:hidden; display:flex; flex-direction:column; transition:border-color .2s, box-shadow .2s; }
.ml-card:hover { border-color:#334155; box-shadow:0 6px 20px rgba(0,0,0,.3); }

.ml-card__preview {
    height:80px; background:#080f1d; display:flex; align-items:center; justify-content:center; font-size:2rem;
    border-bottom:1px solid #1e293b;
}
.ml-card__preview--image { height:120px; overflow:hidden; }
.ml-card__preview--image img { width:100%; height:100%; object-fit:cover; }

.ml-card__body { padding:1rem; flex:1; display:flex; flex-direction:column; gap:.4rem; }
.ml-card__name { font-size:.82rem; font-weight:600; color:#e2e8f0; word-break:break-word; line-height:1.3; }
.ml-card__meta { font-size:.7rem; color:#475569; }
.ml-card__id   { font-size:.67rem; color:#334155; font-family:monospace; }

.ml-card__foot { padding:.75rem 1rem; border-top:1px solid #1e293b; display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; }
.ml-card__status { font-size:.65rem; font-weight:800; text-transform:uppercase; padding:.18rem .6rem; border-radius:99px; border:1px solid; white-space:nowrap; }
.ml-card__status--active           { background:rgba(52,211,153,.1); color:#34d399; border-color:rgba(52,211,153,.25); }
.ml-card__status--pending_archive   { background:rgba(251,191,36,.12); color:#fbbf24; border-color:rgba(251,191,36,.3); }
.ml-card__status--archived          { background:rgba(100,116,139,.1); color:#94a3b8; border-color:rgba(100,116,139,.2); }

.ml-card__actions { display:flex; gap:.3rem; flex-wrap:wrap; }

/* Type badges */
.ml-type { display:inline-block; padding:.18rem .6rem; border-radius:99px; font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; border:1px solid; }
.ml-type--audio   { background:rgba(139,92,246,.12); color:#a78bfa; border-color:rgba(139,92,246,.25); }
.ml-type--image   { background:rgba(52,211,153,.12); color:#34d399; border-color:rgba(52,211,153,.25); }
.ml-type--pdf     { background:rgba(251,191,36,.12);  color:#fbbf24; border-color:rgba(251,191,36,.25); }
.ml-type--passage { background:rgba(99,102,241,.12);  color:#818cf8; border-color:rgba(99,102,241,.25); }
.ml-type--other   { background:rgba(100,116,139,.12); color:#94a3b8; border-color:rgba(100,116,139,.2); }

/* Action buttons */
.ml-act { font-size:.7rem; font-weight:700; padding:.22rem .55rem; border-radius:.35rem; border:none; cursor:pointer; text-decoration:none; transition:background .15s; background:none; white-space:nowrap; }
.ml-act--view     { color:#818cf8; }
.ml-act--view:hover   { background:rgba(99,102,241,.12); }
.ml-act--archive  { color:#fbbf24; }
.ml-act--archive:hover { background:rgba(251,191,36,.1); }
.ml-act--approve  { color:#34d399; }
.ml-act--approve:hover { background:rgba(52,211,153,.1); }

/* Upload button (Teacher only) */
.ml-upload-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.55rem 1.15rem; border-radius:.65rem; background:#6366f1; color:#fff; font-size:.82rem; font-weight:700; border:none; cursor:pointer; text-decoration:none; transition:background .15s; white-space:nowrap; }
.ml-upload-btn:hover { background:#4f46e5; }

/* Empty state */
.ml-empty { padding:3.5rem 1.5rem; text-align:center; display:flex; flex-direction:column; align-items:center; gap:.65rem; background:#0f172a; border:1.5px dashed #1e293b; border-radius:1rem; }
.ml-empty__icon  { font-size:2.5rem; opacity:.4; }
.ml-empty__title { font-size:1rem; font-weight:700; color:#475569; }
.ml-empty__sub   { font-size:.82rem; color:#334155; max-width:380px; line-height:1.5; }

/* Alert */
.ml-alert { padding:.85rem 1.1rem; border-radius:.75rem; font-size:.82rem; font-weight:600; display:flex; justify-content:space-between; align-items:center; }
.ml-alert--success { background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.2); color:#34d399; }
.ml-alert--error   { background:rgba(251,113,133,.08); border:1px solid rgba(251,113,133,.2); color:#fb7185; }

/* Upload modal */
.ml-modal-bg { position:fixed; inset:0; background:rgba(2,6,23,.75); backdrop-filter:blur(6px); z-index:900; display:flex; align-items:center; justify-content:center; padding:1rem; }
.ml-modal { background:#0f172a; border:1px solid #1e293b; border-radius:1.25rem; max-width:460px; width:100%; padding:2rem; box-shadow:0 24px 64px rgba(0,0,0,.6); }
.ml-modal__head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; }
.ml-modal__title { font-size:1.05rem; font-weight:800; color:#f1f5f9; }
.ml-modal__close { color:#475569; font-size:1.35rem; cursor:pointer; background:none; border:none; line-height:1; transition:color .15s; }
.ml-modal__close:hover { color:#e2e8f0; }
.ml-drop { border:2px dashed #334155; border-radius:.85rem; padding:2rem; text-align:center; cursor:pointer; transition:border-color .15s; background:#1e293b; }
.ml-drop:hover { border-color:#6366f1; }
.ml-drop input[type=file] { display:none; }
.ml-drop__icon  { font-size:2rem; margin-bottom:.5rem; }
.ml-drop__label { font-size:.82rem; color:#64748b; }
.ml-drop__hint  { font-size:.7rem; color:#334155; margin-top:.3rem; }
.ml-submit-btn { width:100%; padding:.75rem; background:#6366f1; color:#fff; font-size:.88rem; font-weight:700; border:none; border-radius:.65rem; cursor:pointer; transition:background .15s; margin-top:1.25rem; }
.ml-submit-btn:hover { background:#4f46e5; }
</style>
@endpush

@section('content')
<div class="ml-page">

    {{-- SECTION 3: Header --}}
    <div class="ml-header">
        <div>
            @if(auth()->user()->hasRole('teacher'))
                <h1 class="ml-title">📁 Question Media Library</h1>
                <p class="ml-sub">Upload and manage your media assets. Archive media you no longer need.</p>
            @else
                <h1 class="ml-title">📁 Media Management</h1>
                <p class="ml-sub">Admin manages teacher uploaded media assets. Monitor, review, and request archive or restore.</p>
            @endif
        </div>
        {{-- SECTION 1: Remove Upload New Media button for Admin completely --}}
        @if(auth()->user()->hasRole('teacher'))
        <button type="button" onclick="openUploadModal()" class="ml-upload-btn">
            ⬆ Upload New Media
        </button>
        @endif
    </div>

    {{-- Status Alerts --}}
    @if(session('status'))
    <div class="ml-alert ml-alert--success">✅ {{ session('status') }}</div>
    @endif
    @if(session('error'))
    <div class="ml-alert ml-alert--error">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Admin link to archive --}}
    @if(!auth()->user()->hasRole('teacher'))
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:.85rem;padding:.85rem 1.1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div style="font-size:.8rem;color:#64748b;">
            📦 Need to review archived media?
        </div>
        <a href="{{ route('admin.media.archive-index') }}"
           style="font-size:.78rem;font-weight:700;color:#818cf8;text-decoration:none;padding:.35rem .9rem;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:.5rem;">
            Go to Media Archive →
        </a>
    </div>
    @endif

    {{-- SECTION 8: Search & Filter (Filename, Media ID, Uploader, Type, Status) --}}
    <div class="ml-filter">
        <form method="GET" action="{{ route('admin.media.index') }}" style="display:flex;gap:.65rem;flex:1;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search by filename, ID, or uploader…" class="ml-search">
            <div class="ml-filter-pills">
                <a href="{{ route('admin.media.index') }}"
                   class="ml-pill {{ !request('type') ? 'ml-pill--active' : '' }}">All Types</a>
                <a href="{{ route('admin.media.index', ['type' => 'audio']) }}"
                   class="ml-pill {{ request('type') === 'audio' ? 'ml-pill--active' : '' }}">🎵 Audio</a>
                <a href="{{ route('admin.media.index', ['type' => 'image']) }}"
                   class="ml-pill {{ request('type') === 'image' ? 'ml-pill--active' : '' }}">🖼 Image</a>
                <a href="{{ route('admin.media.index', ['type' => 'pdf']) }}"
                   class="ml-pill {{ request('type') === 'pdf' ? 'ml-pill--active' : '' }}">📄 PDF</a>
                <a href="{{ route('admin.media.index', ['type' => 'passage']) }}"
                   class="ml-pill {{ request('type') === 'passage' ? 'ml-pill--active' : '' }}">📝 Passage</a>
            </div>
            <button type="submit" style="padding:.4rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">Search</button>
        </form>
    </div>

    {{-- Media Grid --}}
    @if($mediaAssets->isEmpty())
    {{-- SECTION 2: EMPTY STATE --}}
    <div class="ml-empty">
        <div class="ml-empty__icon">📭</div>
        @if(auth()->user()->hasRole('teacher'))
            <div class="ml-empty__title">No media found</div>
            <div class="ml-empty__sub">Upload your first media asset to use in Question Banks and Assessments.</div>
            <button type="button" onclick="openUploadModal()"
                    class="ml-upload-btn" style="margin-top:.5rem;font-size:.8rem;">
                ⬆ Upload First Media
            </button>
        @else
            <div class="ml-empty__title">No active media available.</div>
            <div class="ml-empty__sub">Teacher uploaded media will appear here automatically.</div>
        @endif
    </div>
    @else
    <div class="ml-grid">
        @foreach($mediaAssets as $asset)
        <div class="ml-card" id="media-{{ $asset->id }}">
            {{-- Preview --}}
            <div class="ml-card__preview {{ $asset->type === 'image' ? 'ml-card__preview--image' : '' }}">
                @if($asset->type === 'image')
                    <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->original_name }}" loading="lazy">
                @else
                    {{ $asset->typeIcon() }}
                @endif
            </div>

            {{-- Body --}}
            <div class="ml-card__body">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.4rem;flex-wrap:wrap;">
                    <span class="ml-type ml-type--{{ $asset->type }}">{{ $asset->type }}</span>
                    <span style="font-size:.7rem;color:#475569;">{{ $asset->humanSize() }}</span>
                </div>
                <div class="ml-card__name" title="{{ $asset->original_name }}">
                    {{ Str::limit($asset->original_name, 42) }}
                </div>
                @if($asset->title)
                <div style="font-size:.72rem;color:#64748b;font-style:italic;">{{ $asset->title }}</div>
                @endif
                <div class="ml-card__meta">Uploaded {{ $asset->created_at?->diffForHumans() }}</div>
                <div class="ml-card__meta">By {{ $asset->uploader?->name ?? '—' }}</div>
                <div class="ml-card__id">{{ $asset->id }}</div>
            </div>

            {{-- SECTION 4 & 7: Footer Actions & Badges --}}
            <div class="ml-card__foot">
                @if($asset->status === 'pending_archive')
                    <span class="ml-card__status ml-card__status--pending_archive">⏳ Pending Archive</span>
                @else
                    <span class="ml-card__status ml-card__status--active">Active</span>
                @endif

                <div class="ml-card__actions">
                    {{-- Preview / Download --}}
                    <a href="{{ $asset->publicUrl() }}" target="_blank" class="ml-act ml-act--view" title="Preview / Download">👁 Preview</a>

                    {{-- Actions --}}
                    @if(auth()->user()->hasRole('teacher'))
                        {{-- Teacher: direct Archive on own media --}}
                        @if($asset->uploaded_by === auth()->id())
                        <form method="POST" action="{{ route('admin.media.archive', $asset->id) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Archive \'{{ addslashes(Str::limit($asset->original_name, 30)) }}\'?');">
                            @csrf
                            <button type="submit" class="ml-act ml-act--archive" title="Archive">📦 Archive</button>
                        </form>
                        @endif
                    @elseif(auth()->user()->hasRole('super-admin'))
                        {{-- Super Admin: approve archive or archive directly --}}
                        @if($asset->status === 'pending_archive')
                        <form method="POST" action="{{ route('admin.media.approve-archive', $asset->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="ml-act ml-act--approve">✅ Approve Archive</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('admin.media.request-archive', $asset->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="ml-act ml-act--archive">📦 Archive</button>
                        </form>
                        @endif
                    @elseif(auth()->user()->hasRole('admin'))
                        {{-- Admin: Request Archive --}}
                        @if($asset->status !== 'pending_archive')
                        <form method="POST" action="{{ route('admin.media.request-archive', $asset->id) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Submit archive request for \'{{ addslashes(Str::limit($asset->original_name, 30)) }}\'? Super Admin approval will be required.');">
                            @csrf
                            <button type="submit" class="ml-act ml-act--archive">📦 Req. Archive</button>
                        </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($mediaAssets->hasPages())
    <div style="padding:.5rem 0;">
        {{ $mediaAssets->links() }}
    </div>
    @endif
    @endif

</div>

{{-- SECTION 1: Upload Modal — rendered only for Teachers --}}
@if(auth()->user()->hasRole('teacher'))
<div id="ml-upload-modal" class="ml-modal-bg" style="display:none;" onclick="closeUploadModal(event)">
    <div class="ml-modal" onclick="event.stopPropagation()">
        <div class="ml-modal__head">
            <div class="ml-modal__title">⬆ Upload Media Asset</div>
            <button type="button" class="ml-modal__close" onclick="closeUploadModal()">×</button>
        </div>
        <div class="ml-drop" onclick="document.getElementById('ml-file-input').click()">
            <input type="file" id="ml-file-input" accept="image/*,audio/*,.pdf,.mp3,.wav,.m4a" onchange="handleFileSelect(this)">
            <div class="ml-drop__icon">📎</div>
            <div class="ml-drop__label" id="ml-drop-label">Click or drag file here</div>
            <div class="ml-drop__hint">Supported: JPG, PNG, WebP, MP3, WAV, M4A, PDF · Max 10 MB</div>
        </div>
        <div id="ml-upload-progress" style="display:none;margin-top:1rem;">
            <div style="background:#1e293b;border-radius:99px;height:5px;overflow:hidden;">
                <div id="ml-progress-bar" style="width:0%;height:100%;background:#6366f1;transition:width .3s;"></div>
            </div>
            <div id="ml-upload-status" style="font-size:.75rem;color:#64748b;margin-top:.4rem;text-align:center;"></div>
        </div>
        <button type="button" id="ml-upload-btn" onclick="uploadFile()" class="ml-submit-btn" disabled style="opacity:.5;">
            Upload
        </button>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if(auth()->user()->hasRole('teacher'))
<script>
function openUploadModal() { document.getElementById('ml-upload-modal').style.display = 'flex'; }
function closeUploadModal(e) {
    if (!e || e.target === document.getElementById('ml-upload-modal')) {
        document.getElementById('ml-upload-modal').style.display = 'none';
    }
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeUploadModal(); });

let selectedFile = null;
function handleFileSelect(input) {
    selectedFile = input.files[0];
    if (selectedFile) {
        document.getElementById('ml-drop-label').textContent = selectedFile.name;
        document.getElementById('ml-upload-btn').disabled = false;
        document.getElementById('ml-upload-btn').style.opacity = '1';
    }
}

async function uploadFile() {
    if (!selectedFile) return;
    const formData = new FormData();
    formData.append('file', selectedFile);
    formData.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');

    document.getElementById('ml-upload-progress').style.display = 'block';
    document.getElementById('ml-upload-status').textContent = 'Uploading…';
    document.getElementById('ml-progress-bar').style.width = '40%';

    try {
        const res = await fetch('{{ route('admin.media.store') }}', { method: 'POST', body: formData });
        const json = await res.json();
        document.getElementById('ml-progress-bar').style.width = '100%';
        if (json.success) {
            document.getElementById('ml-upload-status').textContent = 'Upload complete! Refreshing…';
            setTimeout(() => window.location.reload(), 800);
        } else {
            document.getElementById('ml-upload-status').textContent = json.message || 'Upload failed.';
        }
    } catch(e) {
        document.getElementById('ml-upload-status').textContent = 'Upload error: ' + e.message;
    }
}
</script>
@endif
@endpush
