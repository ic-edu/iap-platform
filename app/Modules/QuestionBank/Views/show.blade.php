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
            <button onclick="document.getElementById('create-question-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
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
                                <div class="text-[10px] text-indigo-400 mt-1 flex items-center gap-1 font-mono">
                                    <span>🎵 Audio:</span> {{ $q->audio_url }}
                                </div>
                            @endif
                            @if($q->passage_text)
                                <div class="text-[10px] text-slate-400 italic mt-1 line-clamp-1">
                                    📖 Passage: {{ $q->passage_text }}
                                </div>
                            @endif
                            @if($q->choices->isNotEmpty())
                                <div class="text-xs font-normal text-slate-400 mt-1 space-x-2">
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
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Question Prompt *</label>
                    <textarea name="prompt" rows="3" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm" required placeholder="Enter question text..."></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Question Type</label>
                        <select name="question_type" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="single_choice">Single Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="listening">Listening Prompt</option>
                            <option value="reading">Reading Passage</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Difficulty</label>
                        <select name="difficulty" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Points</label>
                        <input type="number" name="points" value="5" min="1" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Attach Audio Media URL</label>
                        <input type="text" name="audio_url" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs" placeholder="e.g. question-media/toeic_listening_part1.mp3">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Reading Passage Text</label>
                        <input type="text" name="passage_text" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs" placeholder="Optional reading passage...">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Explanation / Rationale</label>
                    <input type="text" name="explanation" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs" placeholder="Provide answer rationale...">
                </div>

                <!-- Answer Choices -->
                <div class="space-y-2 border-t border-slate-800 pt-4">
                    <label class="block text-xs font-bold text-white">Answer Choices &amp; Correct Answer</label>
                    @foreach(['A', 'B', 'C', 'D'] as $i => $lbl)
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_choice" value="{{ $i }}" {{ $i === 0 ? 'checked' : '' }} title="Select as correct answer">
                            <span class="font-bold text-xs text-indigo-400 w-4">{{ $lbl }}</span>
                            <input type="hidden" name="choices[{{ $i }}][label]" value="{{ $lbl }}">
                            <input type="text" name="choices[{{ $i }}][content]" class="flex-1 p-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs" placeholder="Choice {{ $lbl }} content">
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-question-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Save Question</button>
                </div>
            </form>
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
</x-admin-layout>
