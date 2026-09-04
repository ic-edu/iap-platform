@props(['media'])

<div class="central-media-preview" style="width:100%;">
    @if($media->type === 'image')
        {{-- IMAGE: Display preview immediately. Click image -> Lightbox. No duplicate View Image button --}}
        <div style="text-align:center;width:100%;">
            <img src="{{ route('media.preview', $media->id) }}" alt="{{ $media->title }}" style="max-width:100%;max-height:450px;border-radius:.75rem;cursor:pointer;object-fit:contain;" onclick="openCentralLightbox('{{ route('media.preview', $media->id) }}', '{{ addslashes($media->title ?? $media->original_name) }}')">
        </div>

    @elseif($media->type === 'audio')
        {{-- AUDIO: Display HTML5 player immediately. No duplicate Play Audio button --}}
        <div class="w-full max-w-[550px] bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl p-6 text-center mx-auto shadow-sm">
            <div class="text-indigo-600 dark:text-indigo-400 mb-3 flex justify-center">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
            </div>
            <audio controls controlsList="nodownload noplaybackrate" data-exam-audio="true" src="{{ route('media.preview', $media->id) }}" preload="metadata" class="w-full max-w-[480px] mx-auto">
                <source src="{{ route('media.preview', $media->id) }}" type="{{ $media->mime_type ?? 'audio/mpeg' }}">
                <p class="text-xs text-slate-500 mt-2">Your browser does not support audio playback for {{ $media->formatLabel() }} format. Please use a compatible browser.</p>
            </audio>

            @if($media->content_text)
            <div class="text-left bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl p-4 mt-4 shadow-sm">
                <div class="text-xs font-extrabold text-emerald-700 dark:text-emerald-400 mb-1.5 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Audio Spoken Transcript
                </div>
                <div class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed font-mono">{{ $media->content_text }}</div>
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
        <div class="w-full h-[520px] bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <iframe src="{{ route('media.preview', $media->id) }}#toolbar=0&navpanes=0&scrollbar=1" style="width:100%;height:100%;border:none;background:#fff;"></iframe>
        </div>

    @else
        {{-- PASSAGE: Display reader immediately. No duplicate Read Passage button --}}
        <div class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4 cursor-default shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold text-sky-800 dark:text-sky-400 uppercase tracking-wide bg-sky-50 dark:bg-sky-950/60 px-2.5 py-1 rounded-lg border border-sky-200 dark:border-sky-800 inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Reading Passage Text
                </span>
                <span class="text-xs text-slate-500 font-medium">Read-only View</span>
            </div>

            <div class="text-sm text-slate-900 dark:text-slate-100 leading-relaxed whitespace-pre-wrap font-serif">
                {{ $media->content_text ?? $media->description ?? 'No passage text available.' }}
            </div>

            @if($media->path && str_contains($media->mime_type ?? '', 'image'))
            <div class="mt-4 text-center">
                <img src="{{ route('media.preview', $media->id) }}" style="max-width:100%;max-height:300px;border-radius:.5rem;" alt="Passage Visual Diagram">
            </div>
            @endif
        </div>
    @endif
</div>

{{-- Lightbox Modal --}}
<div id="centralLightboxModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,.92);backdrop-filter:blur(10px);z-index:9999;flex-direction:column;align-items:center;justify-content:center;padding:2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;width:100%;max-width:90vw;margin-bottom:1rem;">
        <h4 id="centralLightboxTitle" style="color:#fff;font-size:1.05rem;font-weight:800;margin:0;">Image Lightbox Preview</h4>
        <span onclick="closeCentralLightbox()" style="color:#94a3b8;font-size:1.5rem;cursor:pointer;">&times;</span>
    </div>
    <img id="centralLightboxImg" src="" style="max-width:90vw;max-height:80vh;object-fit:contain;border-radius:.75rem;">
</div>

<script>
function openCentralLightbox(src, title) {
    document.getElementById('centralLightboxTitle').innerText = title;
    document.getElementById('centralLightboxImg').src = src;
    document.getElementById('centralLightboxModal').style.display = 'flex';
}
function closeCentralLightbox() {
    document.getElementById('centralLightboxModal').style.display = 'none';
}
</script>
