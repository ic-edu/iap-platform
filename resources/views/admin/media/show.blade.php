@extends('layouts.admin')

@section('title', ($media->title ?? $media->original_name) . ' — Media Detail')

@push('styles')
<style>
.imd-page { display:flex; flex-direction:column; gap:1.75rem; }

.imd-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem;
}
.imd-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.5rem;
}
@media (max-width: 1024px) { .imd-grid { grid-template-columns: 1fr; } }

.imd-preview-box {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    position: relative;
}

.imd-meta-table { width: 100%; text-align: left; font-size: .82rem; border-collapse: collapse; }
.imd-meta-table th { padding: .6rem 0; color: #64748b; font-weight: 700; width: 40%; }
.imd-meta-table td { padding: .6rem 0; color: #f1f5f9; font-weight: 600; }
.imd-meta-table tr { border-bottom: 1px solid #1e293b; }
.imd-meta-table tr:last-child { border-bottom: none; }

/* Modals */
.imd-lightbox-bg {
    position: fixed; inset: 0; background: rgba(2,6,23,.92); backdrop-filter: blur(10px);
    z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem;
}
.imd-lightbox-img { max-width: 90vw; max-height: 80vh; object-fit: contain; border-radius: .75rem; transition: transform .2s ease-out; cursor: grab; }
.imd-lightbox-img:active { cursor: grabbing; }

.imd-passage-reader {
    background: #0f172a; border: 1px solid #334155; border-radius: 1.25rem;
    max-width: 760px; width: 100%; max-height: 80vh; overflow-y: auto; padding: 2rem;
    box-shadow: 0 24px 64px rgba(0,0,0,.8);
}
.imd-paragraph-box {
    background: #1e293b; border-left: 3px solid #6366f1; border-radius: .5rem;
    padding: 1rem 1.25rem; margin-bottom: 1rem; font-size: .88rem; color: #e2e8f0; line-height: 1.6;
}
</style>
@endpush

@section('content')
<div class="imd-page">

    {{-- Top Navigation & Action Header --}}
    <div>
        <a href="{{ route('admin.media.index') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:.5rem;flex-wrap:wrap;">
            <div>
                <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">
                    {{ $media->title ?? $media->original_name }}
                </h1>
                <p style="font-size:.82rem;color:#64748b;margin:0;">
                    Media ID: <code style="color:#a78bfa;font-family:monospace;">{{ $media->id }}</code> • Repository: <strong style="color:#e2e8f0;text-transform:uppercase;">{{ $media->exam_type ?? 'GENERAL' }}</strong>
                </p>
            </div>

            {{-- Action Buttons (TASK 4 & TASK 6: Single Interaction Blueprint, NO duplicate buttons) --}}
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">

                {{-- Edit Asset Button --}}
                <a href="{{ route('admin.media.edit', $media->id) }}" style="padding:.6rem 1.1rem;background:#3b82f6;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                    ✏️ Edit Asset
                </a>

                {{-- Copy Protected Preview URL Button (TASK 5) --}}
                <button type="button" onclick="copyAssetUrl('{{ route('media.preview', $media->id) }}')" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                    📋 Copy URL
                </button>

                {{-- ROLE GOVERNANCE MATRIX --}}
                @if(Auth::user()?->hasRole('super-admin'))
                <a href="{{ route('admin.media.download', $media->id) }}" style="padding:.6rem 1.1rem;background:#10b981;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                    ⬇ Download Asset (Super Admin)
                </a>
                @elseif(Auth::user()?->hasRole('repository-manager'))
                <button type="button" onclick="openDownloadRequestModal()" style="padding:.6rem 1.1rem;background:#312e81;border:1px solid #4338ca;color:#a5b4fc;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                    📥 Request Download
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Missing File Alert --}}
    @php
        $physicalExists = Storage::disk('public')->exists($media->path) || file_exists(public_path($media->path)) || $media->type === 'passage' || !empty($media->content_text);
    @endphp

    @if(!$physicalExists)
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:1rem 1.25rem;border-radius:.85rem;font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:.75rem;">
        <span style="font-size:1.5rem;">⚠️</span>
        <div>
            Asset file unavailable on local storage. Metadata preview is active below.
        </div>
    </div>
    @endif

    <div class="imd-grid">

        {{-- Left Column: Centralized Media Preview (TASK 1, 3, 4, 9) --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">Asset Interactive Preview</h3>

                <div class="imd-preview-box">
                    <x-media-preview :media="$media" />
                </div>
            </div>

            {{-- Overview Card --}}
            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 .75rem;">Asset Overview & Pedagogical Purpose</h3>
                <p style="font-size:.85rem;color:#cbd5e1;line-height:1.5;margin:0 0 1.25rem;">
                    {{ $media->description ?? 'Official institutional asset configured for academic evaluation and question authoring.' }}
                </p>

                @if(!empty($media->tags))
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
                    <span style="font-size:.72rem;font-weight:700;color:#64748b;">Tags:</span>
                    @foreach((array)$media->tags as $tag)
                    <span style="padding:.2rem .6rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:99px;font-size:.7rem;font-weight:700;">#{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

        </div>

        {{-- Right Column: Metadata & Usage Tracker --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">Asset Metadata</h3>
                <table class="imd-meta-table">
                    <tr>
                        <th>Media Type</th>
                        <td><span style="text-transform:uppercase;color:#818cf8;">{{ $media->type }}</span></td>
                    </tr>
                    <tr>
                        <th>Exam Repository</th>
                        <td><span style="text-transform:uppercase;color:#34d399;">{{ $media->exam_type ?? 'GENERAL' }}</span></td>
                    </tr>
                    <tr>
                        <th>Folder Category</th>
                        <td>{{ $media->category ?? 'General Assets' }}</td>
                    </tr>
                    <tr>
                        <th>Difficulty</th>
                        <td><span style="text-transform:capitalize;color:#fbbf24;">{{ $media->difficulty ?? 'Medium' }}</span></td>
                    </tr>
                    <tr>
                        <th>Version</th>
                        <td>v{{ $media->version ?? '1.0' }}</td>
                    </tr>
                    <tr>
                        <th>Approval Status</th>
                        <td><span style="color:#34d399;text-transform:uppercase;">{{ $media->approval_status ?? 'APPROVED' }}</span></td>
                    </tr>
                    <tr>
                        <th>Uploader</th>
                        <td>{{ $usageInfo['uploader_name'] }}</td>
                    </tr>
                    <tr>
                        <th>Upload Date</th>
                        <td>{{ $media->created_at?->format('d M Y, H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Storage Size</th>
                        <td>{{ $media->humanSize() }}</td>
                    </tr>
                </table>
            </div>

            {{-- Usage Tracker --}}
            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">🔗 Usage Tracker & Explorer</h3>

                @if($usageInfo['question_count'] > 0)
                    <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.2);padding:1rem;border-radius:.75rem;margin-bottom:1rem;">
                        <div style="font-size:.82rem;font-weight:800;color:#34d399;margin-bottom:.25rem;">Active Institutional Asset</div>
                        <div style="font-size:.78rem;color:#94a3b8;">
                            Referenced <strong>{{ $usageInfo['question_count'] }}</strong> time(s) across <strong>{{ $usageInfo['question_banks']->count() }}</strong> Question Bank(s).
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:.6rem;">
                        <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;">Question Banks using this asset</div>
                        @foreach($usageInfo['question_banks'] as $bank)
                        <a href="{{ route('admin.question-banks.show', $bank->id) }}" style="display:flex;justify-content:space-between;align-items:center;padding:.65rem .85rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#e2e8f0;font-size:.8rem;font-weight:600;text-decoration:none;">
                            <span>📁 {{ $bank->title }}</span>
                            <span style="font-size:.7rem;color:#818cf8;font-weight:700;">View Bank →</span>
                        </a>
                        @endforeach
                    </div>
                @else
                    <div style="background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.15);padding:1.5rem;border-radius:.85rem;text-align:center;">
                        <div style="font-size:2rem;margin-bottom:.4rem;">📭</div>
                        <div style="font-size:.88rem;font-weight:800;color:#f1f5f9;margin-bottom:.4rem;">
                            No Question Bank references yet.
                        </div>
                        <div style="font-size:.78rem;color:#94a3b8;margin-bottom:1.25rem;line-height:1.4;">
                            Attach this asset to new assessment questions using the Media Picker Modal.
                        </div>
                        <a href="{{ route('admin.question-banks.index') }}" style="display:inline-block;padding:.55rem 1.1rem;background:#6366f1;color:#fff;border-radius:.5rem;font-size:.8rem;font-weight:800;text-decoration:none;">
                            + Create Question
                        </a>
                    </div>
                @endif
            </div>

        </div>

    </div>

    {{-- ITEM 4: TEACHER EDIT ASSET MODAL --}}
    <div id="teacherEditModal" class="imd-lightbox-bg" style="display:none;">
        <div style="background:#0f172a;border:1px solid #334155;border-radius:1.25rem;max-width:650px;width:100%;max-height:85vh;overflow-y:auto;padding:1.75rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;">✏️ Edit Asset & Submit Revision</h3>
                <span onclick="closeTeacherEditModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>

            <form action="{{ route('admin.media.metadata', $media->id) }}" method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                @csrf
                @method('PATCH')

                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Asset Title:</label>
                    <input type="text" name="title" value="{{ $media->title }}" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.6rem;color:#fff;font-size:.85rem;">
                </div>

                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Exam Repository:</label>
                    <select name="exam_type" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.6rem;color:#fff;font-size:.85rem;">
                        <option value="toefl" {{ $media->exam_type === 'toefl' ? 'selected' : '' }}>TOEFL</option>
                        <option value="toeic" {{ $media->exam_type === 'toeic' ? 'selected' : '' }}>TOEIC</option>
                        <option value="ielts" {{ $media->exam_type === 'ielts' ? 'selected' : '' }}>IELTS</option>
                        <option value="placement" {{ $media->exam_type === 'placement' ? 'selected' : '' }}>Placement</option>
                        <option value="grammar" {{ $media->exam_type === 'grammar' ? 'selected' : '' }}>Grammar</option>
                        <option value="vocabulary" {{ $media->exam_type === 'vocabulary' ? 'selected' : '' }}>Vocabulary</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Folder Category:</label>
                    <input type="text" name="category" value="{{ $media->category }}" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.6rem;color:#fff;font-size:.85rem;">
                </div>

                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Description:</label>
                    <textarea name="description" rows="3" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.6rem;color:#fff;font-size:.85rem;">{{ $media->description }}</textarea>
                </div>

                <div>
                    <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Spoken Transcript / Content Text:</label>
                    <textarea name="content_text" rows="4" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.6rem;color:#fff;font-size:.85rem;">{{ $media->content_text }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:.5rem;">
                    <button type="button" onclick="closeTeacherEditModal()" style="padding:.5rem 1.25rem;background:#1e293b;color:#94a3b8;border:1px solid #334155;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:.5rem 1.25rem;background:#3b82f6;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:800;cursor:pointer;">
                        Submit Revision to QA Queue
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Passage Reader Modal --}}
    <div id="passageReaderModal" class="imd-lightbox-bg" style="display:none;">
        <div class="imd-passage-reader">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;">📖 {{ $media->title ?? 'Full Academic Passage' }}</h3>
                <span onclick="closePassageReaderModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>

            @php
                $text = $media->content_text ?? $media->description ?? 'No passage text available.';
                $paragraphs = explode("\n\n", $text);
            @endphp

            @foreach($paragraphs as $idx => $p)
            <div class="imd-paragraph-box">
                <div style="font-size:.72rem;font-weight:800;color:#818cf8;margin-bottom:.3rem;text-transform:uppercase;">
                    Paragraph {{ chr(65 + $idx) }}
                </div>
                {{ $p }}
            </div>
            @endforeach

            <div style="text-align:right;margin-top:1.5rem;">
                <button type="button" onclick="closePassageReaderModal()" style="padding:.5rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">
                    Close Reader
                </button>
            </div>
        </div>
    </div>

    {{-- Lightbox Image Viewer --}}
    <div id="lightboxModal" class="imd-lightbox-bg" style="display:none;" onclick="closeLightboxModal()">
        <div style="position:absolute;top:1.5rem;right:2rem;color:#fff;font-size:1.8rem;cursor:pointer;font-weight:800;" onclick="closeLightboxModal()">&times;</div>
        <img id="lightboxImg" src="{{ $media->publicUrl() }}" class="imd-lightbox-img" onclick="event.stopPropagation()">
    </div>

    {{-- PDF Modal --}}
    <div id="pdfViewerModal" class="imd-lightbox-bg" style="display:none;">
        <div style="background:#0f172a;border:1px solid #334155;border-radius:1.25rem;max-width:900px;width:100%;height:85vh;padding:1.5rem;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">📄 PDF Viewer — {{ $media->title ?? 'Document' }}</h3>
                <span onclick="closePdfModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>
            <iframe src="{{ $media->publicUrl() }}" style="flex:1;width:100%;border:none;border-radius:.75rem;background:#fff;"></iframe>
            <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closePdfModal()" style="padding:.4rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    Close Viewer
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// Copy URL
function copyAssetUrl(url) {
    navigator.clipboard.writeText(url);
    iapAlert({ title: 'URL Copied', message: '✅ Internal Asset URL Copied to Clipboard!\n\n' + url, variant: 'success' });
}

// Teacher Edit Modal
function openTeacherEditModal() { document.getElementById('teacherEditModal').style.display = 'flex'; }
function closeTeacherEditModal() { document.getElementById('teacherEditModal').style.display = 'none'; }

// Passage Reader Modal
function openPassageReaderModal() { document.getElementById('passageReaderModal').style.display = 'flex'; }
function closePassageReaderModal() { document.getElementById('passageReaderModal').style.display = 'none'; }

// Lightbox Image Viewer
let currentZoom = 1;
function openLightboxModal() {
    currentZoom = 1;
    const img = document.getElementById('lightboxImg');
    if (img) img.style.transform = 'scale(1)';
    document.getElementById('lightboxModal').style.display = 'flex';
}
function closeLightboxModal() { document.getElementById('lightboxModal').style.display = 'none'; }

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightboxModal();
        closePassageReaderModal();
        closePdfModal();
        closeTeacherEditModal();
    }
});

    {{-- TASK 8: Download Request Modal (Rendered ONLY for Repository Manager) --}}
    @if(Auth::user()?->hasRole('repository-manager'))
    <div id="downloadRequestModal" class="imd-lightbox-bg" style="display:none;">
        <div style="background:#0f172a;border:1px solid #334155;border-radius:1.25rem;max-width:550px;width:100%;padding:2rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                    📥 Request Download Approval
                </h3>
                <span onclick="closeDownloadRequestModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>

            <form action="{{ route('admin.media.request-download', $media->id) }}" method="POST">
                @csrf
                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                    <div>
                        <label style="font-size:.78rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.4rem;">Purpose *</label>
                        <select name="purpose" id="downloadPurposeSelect" onchange="toggleDownloadReasonField()" style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.65rem;border-radius:.6rem;font-size:.85rem;" required>
                            <option value="Academic Audit">Academic Audit</option>
                            <option value="Legal">Legal</option>
                            <option value="Accreditation">Accreditation</option>
                            <option value="Migration">Migration</option>
                            <option value="Backup">Backup</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div id="downloadReasonDiv" style="display:none;">
                        <label style="font-size:.78rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.4rem;">Specify Reason (Required for Other) *</label>
                        <textarea name="reason" rows="3" placeholder="Provide detailed governance justification for download request..." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.65rem;border-radius:.6rem;font-size:.85rem;"></textarea>
                    </div>

                    <button type="submit" style="width:100%;padding:.75rem;background:#4338ca;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.88rem;">
                        Submit Request to Super Admin Queue
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

<script>
function openDownloadRequestModal() { document.getElementById('downloadRequestModal').style.display = 'flex'; }
function closeDownloadRequestModal() { document.getElementById('downloadRequestModal').style.display = 'none'; }
function toggleDownloadReasonField() {
    const val = document.getElementById('downloadPurposeSelect').value;
    document.getElementById('downloadReasonDiv').style.display = (val === 'Other') ? 'block' : 'none';
}

// PDF Modal
function openPdfModal() { document.getElementById('pdfViewerModal').style.display = 'flex'; }
function closePdfModal() { document.getElementById('pdfViewerModal').style.display = 'none'; }

// ITEM 3: Custom Audio Player Controller
const audioEl = document.getElementById('customAudioElement');
const playBtn = document.getElementById('customAudioPlayBtn');
const seekSlider = document.getElementById('customAudioSeek');
const curTimeEl = document.getElementById('customAudioCurrent');
const durTimeEl = document.getElementById('customAudioDuration');

if (audioEl) {
    audioEl.addEventListener('loadedmetadata', function() {
        if (seekSlider) seekSlider.max = audioEl.duration;
        if (durTimeEl) durTimeEl.textContent = formatTime(audioEl.duration);
    });
    audioEl.addEventListener('timeupdate', function() {
        if (seekSlider) seekSlider.value = audioEl.currentTime;
        if (curTimeEl) curTimeEl.textContent = formatTime(audioEl.currentTime);
    });
    audioEl.addEventListener('ended', function() {
        if (playBtn) playBtn.textContent = '▶';
    });
}

function toggleCustomAudio() {
    if (!audioEl) return;
    if (audioEl.paused) {
        audioEl.play();
        if (playBtn) playBtn.textContent = '⏸';
    } else {
        audioEl.pause();
        if (playBtn) playBtn.textContent = '▶';
    }
}
function seekCustomAudio(val) {
    if (audioEl) audioEl.currentTime = val;
}
function setCustomAudioVolume(val) {
    if (audioEl) audioEl.volume = val;
}
function formatTime(sec) {
    if (isNaN(sec)) return '00:00';
    let m = Math.floor(sec / 60);
    let s = Math.floor(sec % 60);
    return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
}

function triggerVideoPlay() {
    const video = document.getElementById('html5Video');
    if (video) { video.scrollIntoView({ behavior: 'smooth' }); video.play(); }
}
</script>
@endsection
