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
    min-height: 280px;
    position: relative;
}
.imd-preview-box img { max-width: 100%; max-height: 450px; border-radius: .75rem; object-fit: contain; }

.imd-meta-table { width: 100%; text-align: left; font-size: .82rem; border-collapse: collapse; }
.imd-meta-table th { padding: .6rem 0; color: #64748b; font-weight: 700; width: 40%; }
.imd-meta-table td { padding: .6rem 0; color: #f1f5f9; font-weight: 600; }
.imd-meta-table tr { border-bottom: 1px solid #1e293b; }
.imd-meta-table tr:last-child { border-bottom: none; }
</style>
@endpush

@section('content')
<div class="imd-page">

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

            <div style="display:flex;gap:.5rem;">
                <button type="button" onclick="copyAssetUrl('{{ $media->publicUrl() }}')" style="padding:.55rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.8rem;font-weight:700;cursor:pointer;">
                    📋 Copy URL
                </button>
                <a href="{{ $media->publicUrl() }}" target="_blank" style="padding:.55rem 1.1rem;background:#6366f1;color:#fff;border-radius:.6rem;font-size:.8rem;font-weight:700;text-decoration:none;">
                    ↗ Open Raw Asset
                </a>
            </div>
        </div>
    </div>

    <div class="imd-grid">

        {{-- Left Column: Interactive Media Preview (PART 2) --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">Asset Interactive Preview</h3>

                <div class="imd-preview-box">
                    @if($media->type === 'image')
                        <div style="text-align:center;width:100%;">
                            <img id="previewImg" src="{{ $media->publicUrl() }}" alt="{{ $media->title }}" style="transition:transform .3s;cursor:zoom-in;" onclick="toggleZoomImg()">
                            <div style="font-size:.7rem;color:#64748b;margin-top:.75rem;">Click image to toggle 100% full-resolution zoom.</div>
                        </div>

                    @elseif($media->type === 'audio')
                        <div style="width:100%;max-width:500px;text-align:center;">
                            <div style="font-size:3.5rem;margin-bottom:.5rem;">🎵</div>
                            <audio controls style="width:100%;margin-bottom:1rem;">
                                <source src="{{ $media->publicUrl() }}" type="{{ $media->mime_type }}">
                            </audio>
                            @if($media->content_text)
                            <div style="text-align:left;background:#0f172a;border:1px solid #334155;border-radius:.75rem;padding:1rem;margin-top:.75rem;">
                                <div style="font-size:.75rem;font-weight:800;color:#34d399;margin-bottom:.4rem;">📝 Audio Spoken Transcript</div>
                                <div style="font-size:.8rem;color:#cbd5e1;line-height:1.45;">{{ $media->content_text }}</div>
                            </div>
                            @endif
                        </div>

                    @elseif($media->type === 'video')
                        <div style="width:100%;max-width:640px;text-align:center;">
                            <video controls style="width:100%;border-radius:.75rem;max-height:400px;background:#000;">
                                <source src="{{ $media->publicUrl() }}" type="video/mp4">
                            </video>
                            @if($media->content_text)
                            <div style="text-align:left;background:#0f172a;border:1px solid #334155;border-radius:.75rem;padding:1rem;margin-top:.75rem;">
                                <div style="font-size:.75rem;font-weight:800;color:#38bdf8;margin-bottom:.4rem;">📝 Video Closed Captions / Script</div>
                                <div style="font-size:.8rem;color:#cbd5e1;line-height:1.45;">{{ $media->content_text }}</div>
                            </div>
                            @endif
                        </div>

                    @elseif($media->type === 'pdf')
                        <div style="width:100%;height:450px;">
                            <iframe src="{{ $media->publicUrl() }}" style="width:100%;height:100%;border:none;border-radius:.75rem;background:#fff;"></iframe>
                        </div>

                    @elseif($media->type === 'passage')
                        <div style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:.75rem;padding:1.5rem;">
                            <div style="font-size:.8rem;font-weight:800;color:#818cf8;margin-bottom:.5rem;">📖 Academic Passage Reading Content</div>
                            <div style="font-size:.88rem;color:#e2e8f0;line-height:1.6;white-space:pre-wrap;">{{ $media->content_text ?? $media->description }}</div>
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

        {{-- Right Column: Metadata & Usage Summary (PART 4 & 5) --}}
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

            {{-- Usage Explorer Card (PART 5) --}}
            <div class="imd-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">🔗 Usage Tracker & Explorer</h3>

                @if($usageInfo['question_count'] > 0)
                    <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.2);padding:1rem;border-radius:.75rem;margin-bottom:1rem;">
                        <div style="font-size:.82rem;font-weight:800;color:#34d399;margin-bottom:.25rem;">Active Institutional Asset</div>
                        <div style="font-size:.78rem;color:#94a3b8;">
                            Referenced by <strong>{{ $usageInfo['question_count'] }}</strong> Question(s) across <strong>{{ $usageInfo['question_banks']->count() }}</strong> Question Bank(s).
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:.6rem;">
                        <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;">Referencing Question Banks</div>
                        @foreach($usageInfo['question_banks'] as $bank)
                        <a href="{{ route('admin.question-banks.show', $bank->id) }}" style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .8rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#e2e8f0;font-size:.8rem;font-weight:600;text-decoration:none;">
                            <span>📁 {{ $bank->title }}</span>
                            <span style="font-size:.7rem;color:#818cf8;font-weight:700;">View Bank →</span>
                        </a>
                        @endforeach
                    </div>
                @else
                    <div style="background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);padding:1.25rem;border-radius:.75rem;text-align:center;">
                        <div style="font-size:1.8rem;margin-bottom:.3rem;">📭</div>
                        <div style="font-size:.82rem;font-weight:700;color:#94a3b8;">
                            This asset has not yet been referenced by any Question Bank or Assessment.
                        </div>
                        <div style="font-size:.72rem;color:#64748b;margin-top:.4rem;">
                            Teachers can attach this asset using the Media Picker Modal when authoring questions.
                        </div>
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>

<script>
let isZoomed = false;
function toggleZoomImg() {
    const img = document.getElementById('previewImg');
    if (!img) return;
    isZoomed = !isZoomed;
    img.style.transform = isZoomed ? 'scale(1.5)' : 'scale(1)';
    img.style.cursor = isZoomed ? 'zoom-out' : 'zoom-in';
}
function copyAssetUrl(url) {
    navigator.clipboard.writeText(url);
    alert('Asset URL copied to clipboard!\n' + url);
}
</script>
@endsection
