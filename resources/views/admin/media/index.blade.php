<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>📁</span> Question Media Library
            </h1>
            <p class="text-xs text-slate-400 mt-1">Upload, preview, and reuse audio listening tracks, image diagrams, passages, and PDF attachments across question banks.</p>
        </div>
        <div>
            <label class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors cursor-pointer inline-flex items-center gap-2">
                <span>+ Upload New Media</span>
                <input type="file" class="hidden" accept="image/*,audio/*,.pdf" onchange="alert('Media file upload processor active!')">
            </label>
        </div>
    </div>

    <!-- Media Library Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($mediaItems as $item)
            <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                            {{ $item['type'] }}
                        </span>
                        <span class="text-xs font-mono text-slate-500">{{ $item['size'] }}</span>
                    </div>
                    <div class="font-semibold text-white text-sm truncate">{{ $item['name'] }}</div>
                    <div class="text-xs text-slate-500 mt-1">Uploaded: {{ $item['uploaded_at'] }}</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-800/80 flex justify-between items-center text-xs">
                    <span class="text-indigo-400 hover:underline cursor-pointer font-medium">Select for Question</span>
                    <span class="text-slate-500 font-mono">{{ $item['id'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</x-admin-layout>
