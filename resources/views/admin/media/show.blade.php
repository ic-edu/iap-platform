@extends('layouts.admin')

@section('title', ($media->title ?? $media->original_name) . ' — Media Detail')

@push('styles')
<style>
.imd-page { display:flex; flex-direction:column; gap:1.75rem; }

.imd-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .imd-card, html[data-theme="dark"] .imd-card {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}

.imd-card-title {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 1rem;
}
html.dark .imd-card-title, html[data-theme="dark"] .imd-card-title {
    color: #ffffff;
}

.imd-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.5rem;
}
@media (max-width: 1024px) { .imd-grid { grid-template-columns: 1fr; } }

.imd-preview-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    position: relative;
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .imd-preview-box, html[data-theme="dark"] .imd-preview-box {
    background: #080f1d;
    border-color: #1e293b;
}

.imd-meta-table { width: 100%; text-align: left; font-size: .82rem; border-collapse: collapse; }
.imd-meta-table th { padding: .6rem 0; color: #64748b; font-weight: 700; width: 40%; }
html.dark .imd-meta-table th, html[data-theme="dark"] .imd-meta-table th { color: #94a3b8; }
.imd-meta-table td { padding: .6rem 0; color: #0f172a; font-weight: 600; }
html.dark .imd-meta-table td, html[data-theme="dark"] .imd-meta-table td { color: #f1f5f9; }
.imd-meta-table tr { border-bottom: 1px solid #e2e8f0; }
html.dark .imd-meta-table tr, html[data-theme="dark"] .imd-meta-table tr { border-bottom-color: #1e293b; }
.imd-meta-table tr:last-child { border-bottom: none; }

/* Modals */
.imd-lightbox-bg {
    position: fixed; inset: 0; background: rgba(15,23,42,.75); backdrop-filter: blur(6px);
    z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem;
}
.imd-lightbox-img { max-width: 90vw; max-height: 80vh; object-fit: contain; border-radius: .75rem; transition: transform .2s ease-out; cursor: grab; }
.imd-lightbox-img:active { cursor: grabbing; }

.imd-modal-box {
    background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem;
    max-width: 650px; width: 100%; max-height: 85vh; overflow-y: auto; padding: 1.75rem;
    box-shadow: 0 24px 64px rgba(0,0,0,.15);
}
html.dark .imd-modal-box, html[data-theme="dark"] .imd-modal-box {
    background: #0f172a; border-color: #334155;
    box-shadow: 0 24px 64px rgba(0,0,0,.8);
}

.imd-passage-reader {
    background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem;
    max-width: 760px; width: 100%; max-height: 80vh; overflow-y: auto; padding: 2rem;
    box-shadow: 0 24px 64px rgba(0,0,0,.15);
}
html.dark .imd-passage-reader, html[data-theme="dark"] .imd-passage-reader {
    background: #0f172a; border-color: #334155;
    box-shadow: 0 24px 64px rgba(0,0,0,.8);
}

.imd-paragraph-box {
    background: #f8fafc; border-left: 3px solid #6366f1; border-radius: .5rem;
    padding: 1rem 1.25rem; margin-bottom: 1rem; font-size: .88rem; color: #1e293b; line-height: 1.6;
}
html.dark .imd-paragraph-box, html[data-theme="dark"] .imd-paragraph-box {
    background: #1e293b; color: #e2e8f0;
}
</style>
@endpush

@section('content')
<div class="imd-page">

    {{-- Top Navigation & Action Header --}}
    <div>
        <a href="{{ route('admin.media.index') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="color:#4f46e5;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:.5rem;flex-wrap:wrap;">
            <div>
                <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem;" class="text-slate-900 dark:text-white">
                    {{ $media->title ?? $media->original_name }}
                </h1>
                <p style="font-size:.82rem;margin:0;" class="text-slate-600 dark:text-slate-400">
                    Media ID: <code style="color:#6366f1;font-family:monospace;">{{ $media->id }}</code> • Repository: <strong class="text-slate-800 dark:text-slate-200 uppercase">{{ $media->exam_type ?? 'GENERAL' }}</strong>
                </p>
            </div>

            {{-- Action Buttons (TASK 4 & TASK 6: Single Interaction Blueprint, NO duplicate buttons) --}}
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">

                {{-- Edit Asset Button --}}
                <a href="{{ route('admin.media.edit', $media->id) }}" style="padding:.6rem 1.1rem;background:#4f46e5;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                    ✏️ Edit Asset
                </a>

                {{-- Copy Protected Preview URL Button (TASK 5) --}}
                <button type="button" onclick="copyAssetUrl('{{ route('media.preview', $media->id) }}')" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700" style="padding:.6rem 1.1rem;border-radius:.6rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                    📋 Copy URL
                </button>

                {{-- ROLE GOVERNANCE MATRIX --}}
                @if(Auth::user()?->hasRole('super-admin'))
                <a href="{{ route('admin.media.download', $media->id) }}" style="padding:.6rem 1.1rem;background:#059669;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
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
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#e11d48;padding:1rem 1.25rem;border-radius:.85rem;font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:.75rem;">
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
                <h3 class="imd-card-title">Asset Interactive Preview</h3>

                <div class="imd-preview-box">
                    <x-media-preview :media="$media" />
                </div>
            </div>

            {{-- Overview Card --}}
            <div class="imd-card">
                <h3 class="imd-card-title">Asset Overview &amp; Pedagogical Purpose</h3>
                <p class="text-slate-700 dark:text-slate-300" style="font-size:.85rem;line-height:1.5;margin:0 0 1.25rem;">
                    {{ $media->description ?? 'Official institutional asset configured for academic evaluation and question authoring.' }}
                </p>

                @if(!empty($media->tags))
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
                    <span style="font-size:.72rem;font-weight:700;" class="text-slate-500 dark:text-slate-400">Tags:</span>
                    @foreach((array)$media->tags as $tag)
                    <span class="bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300" style="padding:.2rem .6rem;border-radius:99px;font-size:.7rem;font-weight:700;">#{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

        </div>

        {{-- Right Column: Metadata & Usage Tracker --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="imd-card">
                <h3 class="imd-card-title">Asset Metadata</h3>
                <table class="imd-meta-table">
                    <tr>
                        <th>Media Type</th>
                        <td><span style="text-transform:uppercase;color:#4f46e5;font-weight:700;">{{ $media->type }}</span></td>
                    </tr>
                    <tr>
                        <th>Exam Repository</th>
                        <td><span style="text-transform:uppercase;color:#059669;font-weight:700;">{{ $media->exam_type ?? 'GENERAL' }}</span></td>
                    </tr>
                    <tr>
                        <th>Folder Category</th>
                        <td>{{ $media->category ?? 'General Assets' }}</td>
                    </tr>
                    <tr>
                        <th>Difficulty</th>
                        <td><span style="text-transform:capitalize;color:#d97706;font-weight:700;">{{ $media->difficulty ?? 'Medium' }}</span></td>
                    </tr>
                    <tr>
                        <th>Version</th>
                        <td>v{{ $media->version ?? '1.0' }}</td>
                    </tr>
                    <tr>
                        <th>Approval Status</th>
                        <td><span style="color:#059669;text-transform:uppercase;font-weight:700;">{{ $media->approval_status ?? 'APPROVED' }}</span></td>
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
                <h3 class="imd-card-title">🔗 Usage Tracker &amp; Explorer</h3>

                @if($usageInfo['question_count'] > 0)
                    <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.2);padding:1rem;border-radius:.75rem;margin-bottom:1rem;">
                        <div style="font-size:.82rem;font-weight:800;color:#059669;margin-bottom:.25rem;">Active Institutional Asset</div>
                        <div style="font-size:.78rem;" class="text-slate-600 dark:text-slate-400">
                            Referenced <strong>{{ $usageInfo['question_count'] }}</strong> time(s) across <strong>{{ $usageInfo['question_banks']->count() }}</strong> Question Bank(s).
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:.6rem;">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;" class="text-slate-500 dark:text-slate-400">Question Banks using this asset</div>
                        @foreach($usageInfo['question_banks'] as $bank)
                        <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/80 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200" style="display:flex;justify-content:space-between;align-items:center;padding:.65rem .85rem;border-radius:.5rem;font-size:.8rem;font-weight:600;text-decoration:none;">
                            <span>📁 {{ $bank->title }}</span>
                            <span style="font-size:.7rem;color:#4f46e5;font-weight:700;">View Bank →</span>
                        </a>
                        @endforeach
                    </div>
                @else
                    <div style="background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.15);padding:1.5rem;border-radius:.85rem;text-align:center;">
                        <div style="font-size:2rem;margin-bottom:.4rem;">📭</div>
                        <div style="font-size:.88rem;font-weight:800;margin-bottom:.4rem;" class="text-slate-900 dark:text-white">
                            No Question Bank references yet.
                        </div>
                        <div style="font-size:.78rem;margin-bottom:1.25rem;line-height:1.4;" class="text-slate-500 dark:text-slate-400">
                            Attach this asset to new assessment questions using the Media Picker Modal.
                        </div>
                        <a href="{{ route('admin.question-banks.index') }}" style="display:inline-block;padding:.55rem 1.1rem;background:#4f46e5;color:#fff;border-radius:.5rem;font-size:.8rem;font-weight:800;text-decoration:none;">
                            + Create Question
                        </a>
                    </div>
                @endif
            </div>

        </div>

    </div>

    {{-- ITEM 4: TEACHER EDIT ASSET MODAL --}}
    <div id="teacherEditModal" class="imd-lightbox-bg" style="display:none;">
        <div class="imd-modal-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid #e2e8f0;padding-bottom:.75rem;">
                <h3 style="font-size:1.1rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">✏️ Edit Asset &amp; Submit Revision</h3>
                <span onclick="closeTeacherEditModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>

            <form action="{{ route('admin.media.metadata', $media->id) }}" method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                @csrf
                @method('PATCH')

                <div>
                    <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem;">Asset Title:</label>
                    <input type="text" name="title" value="{{ $media->title }}" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                </div>

                <div>
                    <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem;">Exam Repository:</label>
                    <select name="exam_type" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                        <option value="toefl" {{ $media->exam_type === 'toefl' ? 'selected' : '' }}>TOEFL</option>
                        <option value="toeic" {{ $media->exam_type === 'toeic' ? 'selected' : '' }}>TOEIC</option>
                        <option value="ielts" {{ $media->exam_type === 'ielts' ? 'selected' : '' }}>IELTS</option>
                        <option value="placement" {{ $media->exam_type === 'placement' ? 'selected' : '' }}>Placement</option>
                        <option value="grammar" {{ $media->exam_type === 'grammar' ? 'selected' : '' }}>Grammar</option>
                        <option value="vocabulary" {{ $media->exam_type === 'vocabulary' ? 'selected' : '' }}>Vocabulary</option>
                    </select>
                </div>

                <div>
                    <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem;">Folder Category:</label>
                    <input type="text" name="category" value="{{ $media->category }}" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">
                </div>

                <div>
                    <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem;">Description:</label>
                    <textarea name="description" rows="3" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">{{ $media->description }}</textarea>
                </div>

                <div>
                    <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem;">Spoken Transcript / Content Text:</label>
                    <textarea name="content_text" rows="4" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full">{{ $media->content_text }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:.5rem;">
                    <button type="button" onclick="closeTeacherEditModal()" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700" style="padding:.5rem 1.25rem;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:.5rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:800;cursor:pointer;">
                        Submit Revision to QA Queue
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Passage Reader Modal --}}
    <div id="passageReaderModal" class="imd-lightbox-bg" style="display:none;">
        <div class="imd-passage-reader">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid #e2e8f0;padding-bottom:.75rem;">
                <h3 style="font-size:1.1rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">📖 {{ $media->title ?? 'Full Academic Passage' }}</h3>
                <span onclick="closePassageReaderModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>

            @php
                $text = $media->content_text ?? $media->description ?? 'No passage text available.';
                $paragraphs = explode("\n\n", $text);
            @endphp

            @foreach($paragraphs as $idx => $p)
            <div class="imd-paragraph-box">
                <div style="font-size:.72rem;font-weight:800;color:#4f46e5;margin-bottom:.3rem;text-transform:uppercase;">
                    Paragraph {{ chr(65 + $idx) }}
                </div>
                {{ $p }}
            </div>
            @endforeach

            <div style="text-align:right;margin-top:1.5rem;">
                <button type="button" onclick="closePassageReaderModal()" style="padding:.5rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">
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
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-[900px] w-full h-[85vh] p-6 flex flex-col shadow-2xl">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;">
                <h3 style="font-size:1.05rem;font-weight:800;margin:0;" class="text-slate-900 dark:text-white">📄 PDF Viewer — {{ $media->title ?? 'Document' }}</h3>
                <span onclick="closePdfModal()" style="color:#64748b;font-size:1.5rem;cursor:pointer;">&times;</span>
            </div>
            <iframe src="{{ $media->publicUrl() }}" style="flex:1;width:100%;border:none;border-radius:.75rem;background:#fff;"></iframe>
            <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closePdfModal()" style="padding:.4rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.78rem;font-weight:700;cursor:pointer;">
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
        <div class="imd-modal-box" style="max-width:550px;padding:2rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid #e2e8f0;padding-bottom:.75rem;">
                <h3 style="font-size:1.1rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;" class="text-slate-900 dark:text-white">
                    📥 Request Download Approval
                </h3>
                <span onclick="closeDownloadRequestModal()" style="color:#64748b;font-size:1.4rem;cursor:pointer;">&times;</span>
            </div>

            <form action="{{ route('admin.media.request-download', $media->id) }}" method="POST">
                @csrf
                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                    <div>
                        <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.4rem;">Purpose *</label>
                        <select name="purpose" id="downloadPurposeSelect" onchange="toggleDownloadReasonField()" class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full" required>
                            <option value="Academic Audit">Academic Audit</option>
                            <option value="Legal">Legal</option>
                            <option value="Accreditation">Accreditation</option>
                            <option value="Migration">Migration</option>
                            <option value="Backup">Backup</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div id="downloadReasonDiv" style="display:none;">
                        <label class="text-slate-700 dark:text-slate-300" style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.4rem;">Specify Reason (Required for Other) *</label>
                        <textarea name="reason" rows="3" placeholder="Provide detailed governance justification for download request..." class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-xs w-full"></textarea>
                    </div>

                    <button type="submit" style="width:100%;padding:.75rem;background:#4f46e5;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.88rem;">
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
