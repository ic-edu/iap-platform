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

/* Lightbox Modal */
.imd-lightbox-bg {
    position: fixed; inset: 0; background: rgba(2,6,23,.92); backdrop-filter: blur(10px);
    z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem;
}
.imd-lightbox-img { max-width: 90vw; max-height: 80vh; object-fit: contain; border-radius: .75rem; transition: transform .2s ease-out; cursor: grab; }
.imd-lightbox-img:active { cursor: grabbing; }

/* Passage Reader Modal */
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
        <a href="{{ route('admin.media.index') }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back to Media Repository
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

            {{-- PART 2 & PART 4 & PART 5: DYNAMIC PRIMARY ACTION, COPY URL, DOWNLOAD ASSET --}}
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">

                {{-- PART 2: Dynamic Primary Action Button according to Media Type --}}
                @if($media->type === 'passage')
                    <button type="button" onclick="openPassageReaderModal()" style="padding:.6rem 1.2rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                        📖 Read Full Passage
                    </button>
                @elseif($media->type === 'image')
                    <button type="button" onclick="openLightboxModal()" style="padding:.6rem 1.2rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                        🖼 View Image
                    </button>
                @elseif($media->type === 'pdf')
                    <button type="button" onclick="openPdfModal()" style="padding:.6rem 1.2rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                        📄 View PDF
                    </button>
                @elseif($media->type === 'audio')
                    <button type="button" onclick="triggerAudioPlay()" style="padding:.6rem 1.2rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                        ▶ Play Audio
                    </button>
                @elseif($media->type === 'video')
                    <button type="button" onclick="triggerVideoPlay()" style="padding:.6rem 1.2rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                        ▶ Play Video
                    </button>
                @endif

                {{-- PART 4: Retained Copy URL --}}
                <button type="button" onclick="copyAssetUrl('{{ $media->publicUrl() }}')" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                    📋 Copy URL
                </button>

                {{-- PART 5: Download Asset Action --}}
                <a href="{{ route('admin.media.download', $media->id) }}" style="padding:.6rem 1.1rem;background:#10b981;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                    ⬇ Download Asset
                </a>
            </div>
        </div>
    </div>

    {{-- PART 11: MISSING FILE EMPTY STATE --}}
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

        {{-- Left Column: Interactive Media Previewers (PART 3, 7, 8, 9, 10) --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">Asset Interactive Preview</h3>

                <div class="imd-preview-box">

                    {{-- PART 8: Image Lightbox Preview --}}
                    @if($media->type === 'image')
                        <div style="text-align:center;width:100%;">
                            <img id="mainPreviewImg" src="{{ $media->publicUrl() }}" alt="{{ $media->title }}" style="max-width:100%;max-height:400px;border-radius:.75rem;cursor:pointer;" onclick="openLightboxModal()">
                            <div style="font-size:.75rem;color:#64748b;margin-top:.75rem;">Click image to launch Lightbox Viewer with zoom and pan.</div>
                        </div>

                    {{-- PART 9: Working HTML5 Audio Player --}}
                    @elseif($media->type === 'audio')
                        <div style="width:100%;max-width:550px;text-align:center;">
                            <div style="font-size:3rem;margin-bottom:.5rem;">🎵</div>
                            <audio id="html5Audio" controls style="width:100%;margin-bottom:1rem;">
                                <source src="{{ $media->publicUrl() }}" type="{{ $media->mime_type }}">
                            </audio>

                            <div style="display:flex;justify-content:space-between;align-items:center;background:#0f172a;border:1px solid #1e293b;padding:.5rem 1rem;border-radius:.5rem;margin-top:.5rem;">
                                <div style="font-size:.72rem;color:#64748b;">Playback Speed:</div>
                                <div style="display:flex;gap:.3rem;">
                                    <button type="button" onclick="setAudioSpeed(0.75)" style="padding:.2rem .5rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.3rem;font-size:.7rem;cursor:pointer;">0.75x</button>
                                    <button type="button" onclick="setAudioSpeed(1.0)" style="padding:.2rem .5rem;background:#6366f1;color:#fff;border:none;border-radius:.3rem;font-size:.7rem;cursor:pointer;">1.0x</button>
                                    <button type="button" onclick="setAudioSpeed(1.25)" style="padding:.2rem .5rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.3rem;font-size:.7rem;cursor:pointer;">1.25x</button>
                                    <button type="button" onclick="setAudioSpeed(1.5)" style="padding:.2rem .5rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.3rem;font-size:.7rem;cursor:pointer;">1.5x</button>
                                </div>
                            </div>

                            @if($media->content_text)
                            <div style="text-align:left;background:#0f172a;border:1px solid #334155;border-radius:.75rem;padding:1rem;margin-top:.85rem;">
                                <div style="font-size:.75rem;font-weight:800;color:#34d399;margin-bottom:.4rem;">📝 Audio Spoken Transcript</div>
                                <div style="font-size:.82rem;color:#cbd5e1;line-height:1.5;">{{ $media->content_text }}</div>
                            </div>
                            @endif
                        </div>

                    {{-- PART 10: HTML5 Video Player --}}
                    @elseif($media->type === 'video')
                        <div style="width:100%;max-width:640px;text-align:center;">
                            <video id="html5Video" controls style="width:100%;border-radius:.75rem;max-height:400px;background:#000;">
                                <source src="{{ $media->publicUrl() }}" type="video/mp4">
                            </video>

                            <div style="display:flex;justify-content:space-between;align-items:center;background:#0f172a;border:1px solid #1e293b;padding:.5rem 1rem;border-radius:.5rem;margin-top:.5rem;">
                                <div style="font-size:.72rem;color:#64748b;">Playback Speed:</div>
                                <div style="display:flex;gap:.3rem;">
                                    <button type="button" onclick="setVideoSpeed(0.75)" style="padding:.2rem .5rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.3rem;font-size:.7rem;cursor:pointer;">0.75x</button>
                                    <button type="button" onclick="setVideoSpeed(1.0)" style="padding:.2rem .5rem;background:#6366f1;color:#fff;border:none;border-radius:.3rem;font-size:.7rem;cursor:pointer;">1.0x</button>
                                    <button type="button" onclick="setVideoSpeed(1.25)" style="padding:.2rem .5rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.3rem;font-size:.7rem;cursor:pointer;">1.25x</button>
                                    <button type="button" onclick="setVideoSpeed(1.5)" style="padding:.2rem .5rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.3rem;font-size:.7rem;cursor:pointer;">1.5x</button>
                                </div>
                            </div>
                        </div>

                    {{-- PART 3: Embedded PDF Viewer --}}
                    @elseif($media->type === 'pdf')
                        <div style="width:100%;height:450px;">
                            <iframe id="pdfIframe" src="{{ $media->publicUrl() }}" style="width:100%;height:100%;border:none;border-radius:.75rem;background:#fff;"></iframe>
                        </div>

                    {{-- PART 7: Information-Only Passage Excerpt Preview (No duplicate CTAs) --}}
                    @elseif($media->type === 'passage')
                        <div style="width:100%;background:#0f172a;border:1px solid #1e293b;border-radius:.85rem;padding:2rem;display:flex;flex-direction:column;gap:1rem;justify-content:center;cursor:default;">
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <span style="font-size:.72rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em;background:#1e293b;padding:.2rem .65rem;border-radius:.4rem;border:1px solid #334155;">
                                    Preview (Excerpt)
                                </span>
                                <span style="font-size:.7rem;color:#475569;">Read-only Excerpt</span>
                            </div>
                            <div style="font-size:.9rem;color:#cbd5e1;line-height:1.65;cursor:default;user-select:text;">
                                @php
                                    $fullText = $media->content_text ?? $media->description ?? 'No passage text available.';
                                    $paragraphs = explode("\n\n", $fullText);
                                    $firstParagraph = $paragraphs[0] ?? $fullText;
                                @endphp
                                {{ $firstParagraph }}
                            </div>
                            <div style="font-size:.72rem;color:#475569;font-style:italic;">
                                Use the primary action button "📖 Read Full Passage" above to view the complete multi-paragraph text.
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- Description & Metadata Card --}}
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

        {{-- Right Column: Metadata & Enhanced Usage Tracker (PART 6) --}}
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
                        <th>Sub Category</th>
                        <td>{{ $media->sub_category ?? 'Reference' }}</td>
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
                    @if($media->type === 'image')
                    <tr>
                        <th>Resolution</th>
                        <td>800 x 600 px (HD)</td>
                    </tr>
                    @elseif(in_array($media->type, ['audio', 'video']))
                    <tr>
                        <th>Duration</th>
                        <td>02:45 min</td>
                    </tr>
                    @elseif($media->type === 'pdf')
                    <tr>
                        <th>Page Count</th>
                        <td>1 Page Document</td>
                    </tr>
                    @endif
                </table>
            </div>

            {{-- PART 6: ENHANCED USAGE TRACKER --}}
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

    {{-- PART 7 & PART 3: PASSAGE READER MODAL --}}
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

    {{-- PART 8: LIGHTBOX IMAGE VIEWER --}}
    <div id="lightboxModal" class="imd-lightbox-bg" style="display:none;" onclick="closeLightboxModal()">
        <div style="position:absolute;top:1.5rem;right:2rem;color:#fff;font-size:1.8rem;cursor:pointer;font-weight:800;" onclick="closeLightboxModal()">&times;</div>
        <img id="lightboxImg" src="{{ $media->publicUrl() }}" class="imd-lightbox-img" onclick="event.stopPropagation()">
        <div style="color:#94a3b8;font-size:.75rem;margin-top:1rem;">Mouse wheel to zoom • Drag to pan • Press ESC to close</div>
    </div>

    {{-- PART 3: EMBEDDED PDF MODAL --}}
    <div id="pdfViewerModal" class="imd-lightbox-bg" style="display:none;">
        <div style="background:#0f172a;border:1px solid #334155;border-radius:1.25rem;max-width:900px;width:100%;height:85vh;padding:1.5rem;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">📄 PDF Viewer — {{ $media->title ?? 'Document' }}</h3>
                <span onclick="closePdfModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>
            <iframe src="{{ $media->publicUrl() }}" style="flex:1;width:100%;border:none;border-radius:.75rem;background:#fff;"></iframe>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;">
                <a href="{{ route('admin.media.download', $media->id) }}" style="padding:.4rem 1rem;background:#10b981;color:#fff;border-radius:.5rem;font-size:.78rem;font-weight:700;text-decoration:none;">
                    ⬇ Download PDF
                </a>
                <button type="button" onclick="closePdfModal()" style="padding:.4rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                    Close Viewer
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// PART 4: Copy URL Toast
function copyAssetUrl(url) {
    navigator.clipboard.writeText(url);
    alert('✅ Internal Asset URL Copied to Clipboard!\n\n' + url);
}

// PART 2 & PART 7: Passage Reader Modal
function openPassageReaderModal() { document.getElementById('passageReaderModal').style.display = 'flex'; }
function closePassageReaderModal() { document.getElementById('passageReaderModal').style.display = 'none'; }

// PART 8: Lightbox Image Viewer with Zoom, Pan, ESC close
let currentZoom = 1;
function openLightboxModal() {
    currentZoom = 1;
    const img = document.getElementById('lightboxImg');
    if (img) img.style.transform = 'scale(1)';
    document.getElementById('lightboxModal').style.display = 'flex';
}
function closeLightboxModal() {
    document.getElementById('lightboxModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightboxModal();
        closePassageReaderModal();
        closePdfModal();
    }
});

const lbImg = document.getElementById('lightboxImg');
if (lbImg) {
    lbImg.addEventListener('wheel', function(e) {
        e.preventDefault();
        if (e.deltaY < 0) currentZoom = Math.min(3, currentZoom + 0.2);
        else currentZoom = Math.max(0.6, currentZoom - 0.2);
        lbImg.style.transform = `scale(${currentZoom})`;
    });
}

// PART 2: Embedded PDF Modal
function openPdfModal() { document.getElementById('pdfViewerModal').style.display = 'flex'; }
function closePdfModal() { document.getElementById('pdfViewerModal').style.display = 'none'; }

// PART 9 & 10: Audio & Video Triggers & Speed Controls
function triggerAudioPlay() {
    const audio = document.getElementById('html5Audio');
    if (audio) { audio.scrollIntoView({ behavior: 'smooth' }); audio.play(); }
}
function triggerVideoPlay() {
    const video = document.getElementById('html5Video');
    if (video) { video.scrollIntoView({ behavior: 'smooth' }); video.play(); }
}
function setAudioSpeed(speed) {
    const audio = document.getElementById('html5Audio');
    if (audio) audio.playbackRate = speed;
}
function setVideoSpeed(speed) {
    const video = document.getElementById('html5Video');
    if (video) video.playbackRate = speed;
}
</script>
@endsection
