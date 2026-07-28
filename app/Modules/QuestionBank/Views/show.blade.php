<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('admin.question-banks.index') }}" class="text-xs text-slate-400 hover:text-white transition-colors">&larr; Back to Question Banks</a>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $questionBank->title }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">Test Type: <span class="uppercase font-bold text-indigo-400">{{ $questionBank->test_type }}</span> | Total Questions: <span class="font-bold text-white">{{ $questionBank->questions->count() }}</span></p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('import-modal').classList.remove('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                📥 Bulk CSV Import
            </button>
            <button onclick="openCreateQuestionModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                + Add Question
            </button>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <!-- Questions Table / Cards -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">#</th>
                    <th class="p-4">Question Prompt &amp; Media</th>
                    <th class="p-4">Type</th>
                    <th class="p-4">Difficulty</th>
                    <th class="p-4">Points</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($questionBank->questions as $idx => $q)
                    <tr>
                        <td class="p-4 font-bold text-slate-500">{{ $idx + 1 }}</td>
                        <td class="p-4 font-semibold text-white max-w-md">
                            {{ $q->prompt }}
                            @if($q->audio_url)
                                <div class="text-[11px] text-indigo-400 mt-1.5 flex items-center gap-1.5 font-mono bg-indigo-500/10 border border-indigo-500/20 px-2 py-1 rounded">
                                    <span>🎵 Audio Attached:</span> {{ basename($q->audio_url) }}
                                </div>
                            @endif
                            @if($q->passage_text)
                                <div class="text-[11px] text-slate-300 italic mt-1.5 line-clamp-2 bg-slate-950 p-2 rounded border border-slate-800">
                                    📖 Reading Passage: {{ $q->passage_text }}
                                </div>
                            @endif
                            @if($q->choices->isNotEmpty())
                                <div class="text-xs font-normal text-slate-400 mt-2 space-x-2">
                                    @foreach($q->choices as $c)
                                        <span class="{{ $c->is_correct ? 'text-emerald-400 font-bold' : 'text-slate-500' }}">
                                            ({{ $c->label }}) {{ $c->content }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="p-4 text-xs font-medium text-indigo-400 uppercase">{{ $q->question_type?->value ?? 'multiple_choice' }}</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-slate-950 text-slate-300 border border-slate-800 uppercase">
                                {{ $q->difficulty?->value ?? 'medium' }}
                            </span>
                        </td>
                        <td class="p-4 font-bold text-white">{{ $q->points }} pts</td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.question-banks.destroy-question', $q->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete this question?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-400 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500">No questions added yet. Click "+ Add Question" or "Bulk CSV Import" to start authoring.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Author Question Modal -->
    <div id="create-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-2xl w-full shadow-2xl my-8">
            <h2 class="text-lg font-bold text-white mb-4">Author New Question</h2>
            <form action="{{ route('admin.question-banks.store-question', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <!-- Hidden Media Attachment Inputs -->
                <input type="hidden" id="q_audio_url" name="audio_url" value="">
                <input type="hidden" id="q_passage_text" name="passage_text" value="">

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Question Prompt *</label>
                    <textarea name="prompt" rows="3" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:border-indigo-500 focus:outline-none" required placeholder="Enter question text..."></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Question Type *</label>
                        <select id="q_question_type" name="question_type" onchange="updateAnswerOptionsUI()" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="single_choice" selected>Single Choice</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="listening">Listening Prompt</option>
                            <option value="reading">Reading Passage</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Difficulty</label>
                        <select name="difficulty" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Points</label>
                        <input type="number" name="points" value="5" min="1" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- Media Attachment Section -->
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-white flex items-center gap-2">
                            <span>📎</span> Media Attachment (Audio / Image / Passage / PDF)
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openMediaSelectorModal()" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-semibold rounded-lg transition-colors">
                                Choose Existing Media
                            </button>
                            <label class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                                Upload New File
                                <input type="file" id="direct_media_upload" class="hidden" accept="audio/*,image/*,.pdf" onchange="handleDirectFileUpload(event)">
                            </label>
                        </div>
                    </div>

                    <!-- Live Attached Media Display Badge -->
                    <div id="media_attached_preview" class="hidden p-3 bg-indigo-950/60 border border-indigo-500/30 rounded-lg flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 text-indigo-200">
                            <span id="attached_icon">🎵</span>
                            <span id="attached_label_text" class="font-semibold truncate"></span>
                        </div>
                        <button type="button" onclick="removeAttachedMedia()" class="text-rose-400 hover:underline text-xs">Remove</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Explanation / Rationale</label>
                    <input type="text" name="explanation" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Provide answer rationale...">
                </div>

                <!-- Dynamic Answer Options Section -->
                <div id="dynamic_answer_container" class="space-y-3 border-t border-slate-800 pt-4">
                    <!-- Populated dynamically by updateAnswerOptionsUI() -->
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="closeCreateQuestionModal()" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reusable Question Media Selector Modal -->
    <div id="media-selector-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-3xl w-full shadow-2xl my-8">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>📁</span> Media Library Selector
                    </h2>
                    <p class="text-xs text-slate-400">Select an existing audio, image, reading passage, or PDF attachment to attach to this question.</p>
                </div>
                <button type="button" onclick="closeMediaSelectorModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <!-- Filter Tabs -->
            <div class="flex gap-2 mb-4">
                <button type="button" onclick="filterMediaItems('all')" class="media-tab-btn active px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg">All Media</button>
                <button type="button" onclick="filterMediaItems('audio')" class="media-tab-btn px-3 py-1.5 bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg">🎵 Audio Tracks</button>
                <button type="button" onclick="filterMediaItems('image')" class="media-tab-btn px-3 py-1.5 bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg">🖼️ Images</button>
                <button type="button" onclick="filterMediaItems('passage')" class="media-tab-btn px-3 py-1.5 bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg">📖 Passages</button>
                <button type="button" onclick="filterMediaItems('pdf')" class="media-tab-btn px-3 py-1.5 bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg">📄 PDFs</button>
            </div>

            <!-- Media Grid -->
            <div id="media-items-container" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto p-1">
                <!-- Loaded asynchronously via JS -->
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-800 mt-4">
                <button type="button" onclick="closeMediaSelectorModal()" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Close</button>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="import-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-lg w-full shadow-2xl">
            <h2 class="text-lg font-bold text-white mb-2">Bulk CSV Question Import</h2>
            <p class="text-xs text-slate-400 mb-4">Paste CSV lines in format: <code class="bg-slate-950 text-indigo-300 p-1 rounded">Prompt, Choice A, Choice B, Choice C, Choice D, Correct Index (0-3)</code></p>
            <form action="{{ route('admin.question-banks.import', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <textarea name="csv_content" rows="6" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs font-mono" placeholder="What is 2+2?, 1, 2, 4, 5, 2"></textarea>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg">Import Batch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Dynamic UI & Media Attachment Handlers -->
    <script>
        let allMediaItems = [];

        document.addEventListener('DOMContentLoaded', function() {
            updateAnswerOptionsUI();
        });

        function updateAnswerOptionsUI() {
            const type = document.getElementById('q_question_type').value;
            const container = document.getElementById('dynamic_answer_container');

            if (['single_choice', 'listening', 'reading'].includes(type)) {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-2">Single Choice Answer Options (Select 1 Correct Answer)</label>
                    ${['A', 'B', 'C', 'D'].map((lbl, i) => `
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_choice" value="${i}" ${i === 0 ? 'checked' : ''} title="Select as correct answer">
                            <span class="font-bold text-xs text-indigo-400 w-4">${lbl}</span>
                            <input type="hidden" name="choices[${i}][label]" value="${lbl}">
                            <input type="text" name="choices[${i}][content]" class="flex-1 p-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Option ${lbl} content" required>
                        </div>
                    `).join('')}
                `;
            } else if (type === 'multiple_choice') {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-2">Multiple Choice Answer Options (Check all correct choices)</label>
                    ${['A', 'B', 'C', 'D'].map((lbl, i) => `
                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="correct_choices[]" value="${i}" ${i === 0 ? 'checked' : ''} title="Check if correct">
                            <span class="font-bold text-xs text-indigo-400 w-4">${lbl}</span>
                            <input type="hidden" name="choices[${i}][label]" value="${lbl}">
                            <input type="text" name="choices[${i}][content]" class="flex-1 p-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Option ${lbl} content" required>
                        </div>
                    `).join('')}
                `;
            } else if (type === 'true_false') {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-2">True / False Correct Answer</label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-xs font-semibold text-white cursor-pointer">
                            <input type="radio" name="tf_correct_choice" value="true" checked class="text-indigo-600 focus:ring-0"> True
                        </label>
                        <label class="flex items-center gap-2 text-xs font-semibold text-white cursor-pointer">
                            <input type="radio" name="tf_correct_choice" value="false" class="text-indigo-600 focus:ring-0"> False
                        </label>
                    </div>
                `;
            } else if (type === 'short_answer') {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-1">Exact Correct Answer String *</label>
                    <input type="text" name="short_answer_text" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Enter expected exact string answer...">
                `;
            } else if (type === 'essay') {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-1">Reference Answer &amp; Grading Guidelines (Optional)</label>
                    <textarea name="reference_answer_text" rows="3" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Enter model reference answer or grading rubric..."></textarea>
                `;
            }
        }

        function openCreateQuestionModal() {
            document.getElementById('create-question-modal').classList.remove('hidden');
            updateAnswerOptionsUI();
        }

        function closeCreateQuestionModal() {
            document.getElementById('create-question-modal').classList.add('hidden');
        }

        function openMediaSelectorModal() {
            document.getElementById('media-selector-modal').classList.remove('hidden');
            fetchMediaItems();
        }

        function closeMediaSelectorModal() {
            document.getElementById('media-selector-modal').classList.add('hidden');
        }

        function fetchMediaItems() {
            fetch('/admin/media/list')
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        allMediaItems = res.data;
                        renderMediaGrid(allMediaItems);
                    }
                })
                .catch(e => console.error('Failed to load media items', e));
        }

        function filterMediaItems(type) {
            const tabs = document.querySelectorAll('.media-tab-btn');
            tabs.forEach(t => {
                t.classList.remove('bg-indigo-600', 'text-white');
                t.classList.add('bg-slate-800', 'text-slate-300');
            });
            event.target.classList.remove('bg-slate-800', 'text-slate-300');
            event.target.classList.add('bg-indigo-600', 'text-white');

            if (type === 'all') {
                renderMediaGrid(allMediaItems);
            } else {
                const filtered = allMediaItems.filter(item => item.type === type);
                renderMediaGrid(filtered);
            }
        }

        function renderMediaGrid(items) {
            const container = document.getElementById('media-items-container');
            container.innerHTML = '';

            if (items.length === 0) {
                container.innerHTML = '<div class="col-span-2 text-center text-slate-500 text-xs py-8">No media items found for this filter.</div>';
                return;
            }

            items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'p-3 bg-slate-950 border border-slate-800 rounded-xl flex flex-col justify-between';
                
                let icon = '📁';
                if (item.type === 'audio') icon = '🎵';
                if (item.type === 'image') icon = '🖼️';
                if (item.type === 'passage') icon = '📖';
                if (item.type === 'pdf') icon = '📄';

                let previewHtml = '';
                if (item.type === 'passage') {
                    previewHtml = `<div class="text-[10px] text-slate-400 italic mt-1 line-clamp-2">${item.text}</div>`;
                }

                card.innerHTML = `
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                                ${icon} ${item.type}
                            </span>
                            <span class="text-[10px] font-mono text-slate-500">${item.size || ''}</span>
                        </div>
                        <div class="font-semibold text-white text-xs truncate">${item.name}</div>
                        ${previewHtml}
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-800 flex justify-between items-center">
                        <span class="text-[10px] text-slate-500 font-mono">${item.id}</span>
                        <button type="button" onclick="selectMediaItem(${JSON.stringify(item).replace(/"/g, '&quot;')})" 
                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg transition-colors">
                            Use This Media
                        </button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function selectMediaItem(item) {
            if (item.type === 'audio') {
                document.getElementById('q_audio_url').value = item.url || item.name;
                document.getElementById('q_question_type').value = 'listening';
                updateAnswerOptionsUI();
                showAttachedBadge('🎵 Audio Attached: ' + item.name);
            } else if (item.type === 'passage') {
                document.getElementById('q_passage_text').value = item.text || item.name;
                document.getElementById('q_question_type').value = 'reading';
                updateAnswerOptionsUI();
                showAttachedBadge('📖 Reading Passage Attached: ' + item.name);
            } else {
                document.getElementById('q_audio_url').value = item.url || item.name;
                showAttachedBadge('📎 Attached: ' + item.name);
            }

            closeMediaSelectorModal();
        }

        function handleDirectFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('/admin/media', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (res.type === 'audio') {
                        document.getElementById('q_audio_url').value = res.url;
                        document.getElementById('q_question_type').value = 'listening';
                        updateAnswerOptionsUI();
                    }
                    showAttachedBadge('✓ File Uploaded & Attached: ' + res.filename);
                }
            })
            .catch(e => console.error('Upload failed', e));
        }

        function showAttachedBadge(label) {
            const badge = document.getElementById('media_attached_preview');
            const labelText = document.getElementById('attached_label_text');
            labelText.textContent = label;
            badge.classList.remove('hidden');
        }

        function removeAttachedMedia() {
            document.getElementById('q_audio_url').value = '';
            document.getElementById('q_passage_text').value = '';
            document.getElementById('media_attached_preview').classList.add('hidden');
        }
    </script>
</x-admin-layout>
