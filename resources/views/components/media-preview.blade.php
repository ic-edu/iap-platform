@props(['media'])

<div class="central-media-preview" style="width:100%;">
    @if($media->type === 'image')
        {{-- IMAGE: Display preview immediately. Click image -> Lightbox. No duplicate View Image button --}}
        <div style="text-align:center;width:100%;">
            <img src="{{ route('media.preview', $media->id) }}" alt="{{ $media->title }}" style="max-width:100%;max-height:450px;border-radius:.75rem;cursor:pointer;object-fit:contain;" onclick="openCentralLightbox('{{ route('media.preview', $media->id) }}', '{{ addslashes($media->title ?? $media->original_name) }}')">
        </div>

    @elseif($media->type === 'audio')
        {{-- AUDIO: Display HTML5 player immediately. No duplicate Play Audio button --}}
        <div style="width:100%;max-width:550px;background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1.5rem;text-align:center;margin:0 auto;">
            <div style="font-size:2.8rem;margin-bottom:.5rem;">🎵</div>
            <audio controls controlsList="nodownload noplaybackrate" src="{{ route('media.preview', $media->id) }}" preload="metadata" style="width:100%;max-width:480px;accent-color:#6366f1;">
                <source src="{{ route('media.preview', $media->id) }}" type="{{ $media->mime_type ?? 'audio/mpeg' }}">
                <p style="font-size:.78rem;color:#94a3b8;margin-top:.5rem;">Your browser does not support audio playback for {{ $media->formatLabel() }} format. Please use a compatible browser.</p>
            </audio>

            @if($media->content_text)
            <div style="text-align:left;background:#1e293b;border:1px solid #334155;border-radius:.75rem;padding:1rem;margin-top:1rem;">
                <div style="font-size:.75rem;font-weight:800;color:#34d399;margin-bottom:.4rem;">📝 Audio Spoken Transcript</div>
                <div style="font-size:.82rem;color:#cbd5e1;line-height:1.5;">{{ $media->content_text }}</div>
            </div>
            @endif
        </div>

    @elseif($media->type === 'video')
        {{-- VIDEO: Display HTML5 video player immediately --}}
        <div style="width:100%;max-width:640px;text-align:center;margin:0 auto;">
            <video controls controlsList="nodownload noplaybackrate" style="width:100%;border-radius:.75rem;max-height:400px;background:#000;">
                <source src="{{ route('media.preview', $media->id) }}" type="{{ $media->mime_type ?? 'video/mp4' }}">
            </video>
        </div>

    @elseif($media->type === 'pdf')
        {{-- PDF: Display embedded viewer immediately (toolbar disabled). No duplicate View PDF button --}}
        <div style="width:100%;height:520px;background:#1e293b;border-radius:.75rem;overflow:hidden;">
            <iframe src="{{ route('media.preview', $media->id) }}#toolbar=0&navpanes=0&scrollbar=1" style="width:100%;height:100%;border:none;border-radius:.75rem;background:#fff;"></iframe>
        </div>

    @else
        {{-- PASSAGE: Display reader immediately. No duplicate Read Passage button --}}
        <div style="width:100%;background:#0f172a;border:1px solid #1e293b;border-radius:.85rem;padding:1.75rem;display:flex;flex-direction:column;gap:1rem;cursor:default;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:.72rem;font-weight:800;color:#38bdf8;text-transform:uppercase;letter-spacing:.06em;background:#1e293b;padding:.2rem .65rem;border-radius:.4rem;border:1px solid #334155;">
                    📖 Reading Passage Text
                </span>
                <span style="font-size:.7rem;color:#64748b;">Read-only View</span>
            </div>

            <div style="font-size:.9rem;color:#cbd5e1;line-height:1.65;white-space:pre-wrap;font-family:serif;">
                {{ $media->content_text ?? $media->description ?? 'No passage text available.' }}
            </div>

            @if($media->path && str_contains($media->mime_type ?? '', 'image'))
            <div style="margin-top:1rem;text-align:center;">
                <img src="{{ route('media.preview', $media->id) }}" style="max-width:100%;max-height:300px;border-radius:.5rem;" alt="Passage Visual Diagram">
            </div>
            @endif
        </div>
    @endif
</div>

{{-- Lightbox Modal --}}
<div id="centralLightboxModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,.92);backdrop-filter:blur(10px);z-index:9999;flex-direction:column;align-items:center;justify-content:center;padding:2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;width:100%;max-width:90vw;margin-bottom:1rem;">
        <h4 id="centralLightboxTitle" style="color:#fff;font-size:1.05rem;font-weight:800;margin:0;">🖼 Image Lightbox Preview</h4>
        <span onclick="closeCentralLightbox()" style="color:#94a3b8;font-size:1.5rem;cursor:pointer;">&times;</span>
    </div>
    <img id="centralLightboxImg" src="" style="max-width:90vw;max-height:80vh;object-fit:contain;border-radius:.75rem;">
</div>

<script>
function openCentralLightbox(src, title) {
    document.getElementById('centralLightboxTitle').innerText = '🖼 ' + title;
    document.getElementById('centralLightboxImg').src = src;
    document.getElementById('centralLightboxModal').style.display = 'flex';
}
function closeCentralLightbox() {
    document.getElementById('centralLightboxModal').style.display = 'none';
}
</script>
