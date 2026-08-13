@extends('layouts.admin')

@section('content')
    @php
        $typeLabels = [
            'single_choice' => 'Single Choice',
            'multiple_choice' => 'Multiple Choice',
            'true_false' => 'True / False',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
            'listening' => 'Listening Prompt',
            'reading' => 'Reading Passage',
        ];
    @endphp

    @php
        $from = request('from');
        $testId = request('test_id');

        if ($from === 'dashboard') {
            $backUrl = route('teacher.dashboard');
            $backLabel = '← Back to Teacher Dashboard';
        } elseif ($from === 'test_builder' && $testId) {
            $backUrl = route('teacher.tests.show', $testId);
            $backLabel = '← Back to Assessment Test Builder';
        } elseif ($from === 'revision_center') {
            $backUrl = route('teacher.revision-center');
            $backLabel = '← Back to Revision Center';
        } elseif (Auth::user()?->hasRole('teacher')) {
            $backUrl = route('teacher.question-banks.index');
            $backLabel = '← Back to Question Banks';
        } elseif (Auth::user()?->hasRole('repository-manager')) {
            $backUrl = route('admin.repository-manager.questions-approval');
            $backLabel = '← Back to Governance Queue';
        } else {
            $backUrl = route('admin.question-banks.index');
            $backLabel = '← Back to Question Banks';
        }
    @endphp

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <a href="{{ $backUrl }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold transition-colors">{{ $backLabel }}</a>
                @if(Auth::user()?->hasRole('teacher'))
                    <a href="{{ route('teacher.dashboard') }}" class="text-xs text-slate-400 hover:text-white font-semibold transition-colors">🏠 Dashboard</a>
                @endif
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold text-white">{{ $questionBank->title }}</h1>
                @php
                    $statusBadge = match($questionBank->status) {
                        'published' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'approved' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                        'pending_approval' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                        default => 'bg-slate-800 text-slate-400 border-slate-700',
                    };
                @endphp
                <span class="px-2.5 py-0.5 text-xs font-bold rounded border uppercase {{ $statusBadge }}">
                    {{ $questionBank->status ?? 'draft' }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Test Type: <span class="uppercase font-bold text-indigo-400">{{ $questionBank->test_type }}</span> | Total Questions: <span class="font-bold text-white">{{ $questionBank->questions->count() }}</span></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Auth::user()?->hasRole('teacher'))
                <!-- Teacher Content Creator Actions -->
                <button onclick="document.getElementById('edit-bank-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                    ✏ Edit Bank Details
                </button>

                @if (in_array($questionBank->status, ['draft', 'rejected', null]))
                    <form action="{{ route('admin.question-banks.submit', $questionBank->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                            🚀 Submit for Approval
                        </button>
                    </form>
                @endif

                <button onclick="document.getElementById('import-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                    📥 Bulk Import
                </button>
                <button onclick="openCreateQuestionModal()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                    + Add Question
                </button>
            @endif

            @if (Auth::user()?->hasRole('admin') && !Auth::user()?->hasRole('super-admin'))
                <!-- Operational Admin Content Operator Actions -->
                @if ($questionBank->status === 'approved')
                    <form action="{{ route('admin.question-banks.publish', $questionBank->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                            🌐 Publish Live
                        </button>
                    </form>
                @elseif ($questionBank->status === 'published')
                    <form action="{{ route('admin.question-banks.unpublish', $questionBank->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 text-xs font-semibold rounded-lg border border-rose-500/30 transition-colors">
                            🔒 Unpublish
                        </button>
                    </form>
                @endif

                @if (!in_array($questionBank->status, ['archived', 'pending_archive_approval']))
                    <button onclick="document.getElementById('archive-bank-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-amber-600/20 hover:bg-amber-600/30 text-amber-400 text-xs font-semibold rounded-lg border border-amber-500/30 transition-colors">
                        📦 Request Archive
                    </button>
                @endif
            @endif
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
                    @php
                        $qTypeRaw = is_object($q->question_type) ? $q->question_type->value : (string)$q->question_type;
                        $qTypeLabel = $typeLabels[$qTypeRaw] ?? ucwords(str_replace('_', ' ', $qTypeRaw));
                    @endphp
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
                        <td class="p-4 text-xs font-semibold text-indigo-400">
                            <span class="px-2.5 py-1 rounded bg-indigo-500/10 border border-indigo-500/20">
                                {{ $qTypeLabel }}
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-slate-950 text-slate-300 border border-slate-800 uppercase">
                                {{ is_object($q->difficulty) ? $q->difficulty->value : $q->difficulty }}
                            </span>
                        </td>
                        <td class="p-4 font-bold text-white">{{ $q->points }} pts</td>
                        <td class="p-4 text-right space-x-2">
                            <button type="button" onclick='openViewQuestionModal({{ json_encode($q) }})' class="text-xs text-slate-300 hover:text-white font-semibold">
                                👁 View
                            </button>
                            @if (Auth::user()?->hasRole('teacher'))
                                <button type="button" onclick='openEditQuestionModal({{ json_encode($q) }})' class="text-xs text-indigo-400 hover:underline font-semibold">
                                    ✏ Edit
                                </button>
                                <form action="{{ route('admin.question-banks.duplicate-question', $q->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-amber-400 hover:underline font-semibold">📄 Duplicate</button>
                                </form>
                            @endif
                            @if (!Auth::user()?->hasRole('teacher'))
                                <form action="{{ route('admin.question-banks.destroy-question', $q->id) }}" method="POST" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: 'Delete Question?', message: 'Are you sure you want to delete this question? This action cannot be undone.', confirmText: 'Delete Question', variant: 'danger', form: this });">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-400 hover:underline font-semibold">🗑 Delete</button>
                                </form>
                            @endif
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

    <!-- View Question Read-Only Preview Modal -->
    <div id="view-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-xl w-full shadow-2xl my-8">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>👁 Question Preview</span>
                    <span id="v_type_badge" class="px-2 py-0.5 text-xs font-semibold rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30"></span>
                </h2>
                <button type="button" onclick="closeViewQuestionModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>

            <div class="space-y-4 text-xs text-slate-300">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Question Prompt</span>
                    <div id="v_prompt" class="text-sm font-semibold text-white mt-1 bg-slate-950 p-3 rounded-lg border border-slate-800"></div>
                </div>

                <div id="v_media_section" class="hidden">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Media Attachment</span>
                    <div id="v_media_content" class="mt-1 bg-slate-950 p-3 rounded-lg border border-slate-800 text-indigo-300"></div>
                </div>

                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-500 block mb-1">Answer Options &amp; Correct Answer</span>
                    <div id="v_choices_container" class="space-y-2"></div>
                </div>

                <div id="v_explanation_section" class="hidden">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Explanation / Rationale</span>
                    <div id="v_explanation" class="mt-1 text-slate-300 bg-slate-950 p-3 rounded-lg border border-slate-800"></div>
                </div>

                <div class="flex items-center justify-between pt-2 text-[11px] text-slate-400 border-t border-slate-800">
                    <div>Difficulty: <span id="v_difficulty" class="font-bold text-white uppercase"></span></div>
                    <div>Points: <span id="v_points" class="font-bold text-white"></span> pts</div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-slate-800 mt-4">
                <button type="button" onclick="closeViewQuestionModal()" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Close Preview</button>
            </div>
        </div>
    </div>

    <!-- Create Question Modal -->
    <div id="create-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-2xl w-full shadow-2xl my-8">
            <h2 class="text-lg font-bold text-white mb-4">Author New Question</h2>
            <form action="{{ route('admin.question-banks.store-question', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" id="q_audio_url" name="audio_url" value="">
                <input type="hidden" id="q_passage_text" name="passage_text" value="">

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Question Prompt *</label>
                    <textarea name="prompt" rows="3" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:border-indigo-500 focus:outline-none" required placeholder="Enter question text..."></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Question Type *</label>
                        <select id="q_question_type" name="question_type" onchange="updateAnswerOptionsUI('create')" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
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
                            <button type="button" onclick="openMediaSelectorModal('create')" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-semibold rounded-lg transition-colors">
                                Choose Existing Media
                            </button>
                            <label class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                                Upload New File
                                <input type="file" class="hidden" accept="audio/*,image/*,.pdf" onchange="handleDirectFileUpload(event, 'create')">
                            </label>
                        </div>
                    </div>

                    <div id="media_attached_preview" class="hidden p-3 bg-indigo-950/60 border border-indigo-500/30 rounded-lg flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 text-indigo-200">
                            <span id="attached_icon">🎵</span>
                            <span id="attached_label_text" class="font-semibold truncate"></span>
                        </div>
                        <button type="button" onclick="removeAttachedMedia('create')" class="text-rose-400 hover:underline text-xs">Remove</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Explanation / Rationale</label>
                    <input type="text" name="explanation" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Provide answer rationale...">
                </div>

                <div id="dynamic_answer_container" class="space-y-3 border-t border-slate-800 pt-4"></div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="closeCreateQuestionModal()" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div id="edit-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-2xl w-full shadow-2xl my-8">
            <h2 class="text-lg font-bold text-white mb-4">Edit Question</h2>
            <form id="edit-question-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" id="eq_audio_url" name="audio_url" value="">
                <input type="hidden" id="eq_passage_text" name="passage_text" value="">

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Question Prompt *</label>
                    <textarea id="eq_prompt" name="prompt" rows="3" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-sm focus:border-indigo-500 focus:outline-none" required></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Question Type *</label>
                        <select id="eq_question_type" name="question_type" onchange="updateAnswerOptionsUI('edit')" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="single_choice">Single Choice</option>
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
                        <select id="eq_difficulty" name="difficulty" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Points</label>
                        <input type="number" id="eq_points" name="points" value="5" min="1" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- Media Attachment Section for Edit -->
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-white flex items-center gap-2">
                            <span>📎</span> Attached Media
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openMediaSelectorModal('edit')" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-semibold rounded-lg transition-colors">
                                Replace / Select Media
                            </button>
                        </div>
                    </div>

                    <div id="eq_media_attached_preview" class="p-3 bg-indigo-950/60 border border-indigo-500/30 rounded-lg flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 text-indigo-200">
                            <span id="eq_attached_icon">🎵</span>
                            <span id="eq_attached_label_text" class="font-semibold truncate">No media attached</span>
                        </div>
                        <button type="button" onclick="removeAttachedMedia('edit')" class="text-rose-400 hover:underline text-xs">Remove</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Explanation / Rationale</label>
                    <input type="text" id="eq_explanation" name="explanation" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>

                <div id="eq_dynamic_answer_container" class="space-y-3 border-t border-slate-800 pt-4"></div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="closeEditQuestionModal()" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Update Question</button>
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
            <div id="media-items-container" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto p-1"></div>

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

    <!-- JavaScript Handlers -->
    <script>
        let allMediaItems = [];
        let currentMediaTargetMode = 'create';

        const typeLabelsMap = {
            'single_choice': 'Single Choice',
            'multiple_choice': 'Multiple Choice',
            'true_false': 'True / False',
            'short_answer': 'Short Answer',
            'essay': 'Essay',
            'listening': 'Listening Prompt',
            'reading': 'Reading Passage'
        };

        document.addEventListener('DOMContentLoaded', function() {
            updateAnswerOptionsUI('create');
        });

        function updateAnswerOptionsUI(mode = 'create') {
            const prefix = (mode === 'edit') ? 'eq_' : 'q_';
            const containerId = (mode === 'edit') ? 'eq_dynamic_answer_container' : 'dynamic_answer_container';
            const type = document.getElementById(prefix + 'question_type').value;
            const container = document.getElementById(containerId);

            if (['single_choice', 'listening', 'reading'].includes(type)) {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-2">Single Choice Answer Options (Select 1 Correct Answer)</label>
                    ${['A', 'B', 'C', 'D'].map((lbl, i) => `
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_choice" value="${i}" ${i === 0 ? 'checked' : ''} title="Select as correct answer">
                            <span class="font-bold text-xs text-indigo-400 w-4">${lbl}</span>
                            <input type="hidden" name="choices[${i}][label]" value="${lbl}">
                            <input type="text" id="${prefix}choice_${i}" name="choices[${i}][content]" class="flex-1 p-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Option ${lbl} content" required>
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
                            <input type="text" id="${prefix}choice_${i}" name="choices[${i}][content]" class="flex-1 p-2 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Option ${lbl} content" required>
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
                    <input type="text" id="${prefix}short_answer_text" name="short_answer_text" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Enter expected exact string answer...">
                `;
            } else if (type === 'essay') {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-white mb-1">Reference Answer &amp; Grading Guidelines (Optional)</label>
                    <textarea id="${prefix}reference_answer_text" name="reference_answer_text" rows="3" class="w-full p-3 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Enter model reference answer or grading rubric..."></textarea>
                `;
            }
        }

        // View Question Modal
        function openViewQuestionModal(q) {
            const typeRaw = (typeof q.question_type === 'object') ? q.question_type.value : q.question_type;
            const diffRaw = (typeof q.difficulty === 'object') ? q.difficulty.value : q.difficulty;

            document.getElementById('v_type_badge').textContent = typeLabelsMap[typeRaw] || typeRaw;
            document.getElementById('v_prompt').textContent = q.prompt;
            document.getElementById('v_difficulty').textContent = diffRaw;
            document.getElementById('v_points').textContent = q.points;

            // Media
            const mediaSec = document.getElementById('v_media_section');
            const mediaContent = document.getElementById('v_media_content');
            if (q.audio_url || q.passage_text) {
                mediaSec.classList.remove('hidden');
                let html = '';
                if (q.audio_url) html += `<div>🎵 <strong>Audio:</strong> ${q.audio_url}</div>`;
                if (q.passage_text) html += `<div class="mt-1">📖 <strong>Passage:</strong> ${q.passage_text}</div>`;
                mediaContent.innerHTML = html;
            } else {
                mediaSec.classList.add('hidden');
            }

            // Choices
            const container = document.getElementById('v_choices_container');
            container.innerHTML = '';
            if (q.choices && q.choices.length > 0) {
                q.choices.forEach(c => {
                    const div = document.createElement('div');
                    div.className = `p-2.5 rounded-lg border flex items-center justify-between ${c.is_correct ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-200' : 'bg-slate-950 border-slate-800 text-slate-300'}`;
                    div.innerHTML = `
                        <span><strong>(${c.label})</strong> ${c.content}</span>
                        ${c.is_correct ? '<span class="text-[10px] font-bold px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded">CORRECT ANSWER</span>' : ''}
                    `;
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<div class="text-slate-500 italic">No choice options (Essay / Short Answer question).</div>';
            }

            // Explanation
            const expSec = document.getElementById('v_explanation_section');
            if (q.explanation) {
                expSec.classList.remove('hidden');
                document.getElementById('v_explanation').textContent = q.explanation;
            } else {
                expSec.classList.add('hidden');
            }

            document.getElementById('view-question-modal').classList.remove('hidden');
        }

        function closeViewQuestionModal() {
            document.getElementById('view-question-modal').classList.add('hidden');
        }

        // Edit Question Modal
        function openEditQuestionModal(q) {
            const form = document.getElementById('edit-question-form');
            form.action = `/admin/question-banks/questions/${q.id}`;

            const typeRaw = (typeof q.question_type === 'object') ? q.question_type.value : q.question_type;
            const diffRaw = (typeof q.difficulty === 'object') ? q.difficulty.value : q.difficulty;

            document.getElementById('eq_prompt').value = q.prompt;
            document.getElementById('eq_question_type').value = typeRaw;
            document.getElementById('eq_difficulty').value = diffRaw;
            document.getElementById('eq_points').value = q.points;
            document.getElementById('eq_explanation').value = q.explanation || '';
            document.getElementById('eq_audio_url').value = q.audio_url || '';
            document.getElementById('eq_passage_text').value = q.passage_text || '';

            // Update Media Badge
            if (q.audio_url || q.passage_text) {
                showAttachedBadge(q.audio_url ? '🎵 Audio Attached: ' + q.audio_url : '📖 Passage Attached', 'edit');
            } else {
                removeAttachedMedia('edit');
            }

            updateAnswerOptionsUI('edit');

            // Pre-fill choices
            if (q.choices && q.choices.length > 0) {
                q.choices.forEach((c, idx) => {
                    const input = document.getElementById(`eq_choice_${idx}`);
                    if (input) input.value = c.content;
                });
            }

            document.getElementById('edit-question-modal').classList.remove('hidden');
        }

        function closeEditQuestionModal() {
            document.getElementById('edit-question-modal').classList.add('hidden');
        }

        function openCreateQuestionModal() {
            document.getElementById('create-question-modal').classList.remove('hidden');
            updateAnswerOptionsUI('create');
        }

        function closeCreateQuestionModal() {
            document.getElementById('create-question-modal').classList.add('hidden');
        }

        // Media Selector Modal Handlers
        function openMediaSelectorModal(mode = 'create') {
            currentMediaTargetMode = mode;
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
                });
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
                renderMediaGrid(allMediaItems.filter(i => i.type === type));
            }
        }

        function renderMediaGrid(items) {
            const container = document.getElementById('media-items-container');
            container.innerHTML = '';
            if (items.length === 0) {
                container.innerHTML = '<div class="col-span-2 text-center text-slate-500 text-xs py-8">No media items found.</div>';
                return;
            }

            items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'p-3 bg-slate-950 border border-slate-800 rounded-xl flex flex-col justify-between';
                let icon = item.type === 'audio' ? '🎵' : (item.type === 'passage' ? '📖' : '📁');
                
                card.innerHTML = `
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">${icon} ${item.type}</span>
                            <span class="text-[10px] font-mono text-slate-500">${item.size || ''}</span>
                        </div>
                        <div class="font-semibold text-white text-xs truncate">${item.name}</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-800 flex justify-between items-center">
                        <span class="text-[10px] text-slate-500 font-mono">${item.id}</span>
                        <button type="button" onclick="selectMediaItem(${JSON.stringify(item).replace(/"/g, '&quot;')})" 
                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">
                            Use This Media
                        </button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function selectMediaItem(item) {
            const prefix = (currentMediaTargetMode === 'edit') ? 'eq_' : 'q_';
            const mediaIdInput = document.getElementById(prefix + 'media_asset_id');
            if (mediaIdInput) mediaIdInput.value = item.id;

            if (item.type === 'audio') {
                document.getElementById(prefix + 'audio_url').value = item.url || item.name;
                document.getElementById(prefix + 'question_type').value = 'listening';
                updateAnswerOptionsUI(currentMediaTargetMode);
                showAttachedBadge('🎵 Audio Attached: ' + (item.title || item.name), currentMediaTargetMode);
            } else if (item.type === 'passage') {
                document.getElementById(prefix + 'passage_text').value = item.content_text || item.text || item.name;
                document.getElementById(prefix + 'question_type').value = 'reading';
                updateAnswerOptionsUI(currentMediaTargetMode);
                showAttachedBadge('📖 Passage Attached: ' + (item.title || item.name), currentMediaTargetMode);
            } else if (item.type === 'image') {
                document.getElementById(prefix + 'audio_url').value = item.url || item.name;
                showAttachedBadge('🖼 Image Attached: ' + (item.title || item.name), currentMediaTargetMode);
            } else {
                document.getElementById(prefix + 'audio_url').value = item.url || item.name;
                showAttachedBadge('📎 Attached: ' + (item.title || item.name), currentMediaTargetMode);
            }
            closeMediaSelectorModal();
        }

        function handleDirectFileUpload(event, mode = 'create') {
            const file = event.target.files[0];
            if (!file) return;
            const prefix = (mode === 'edit') ? 'eq_' : 'q_';

            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('/admin/media', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (res.type === 'audio') {
                            document.getElementById(prefix + 'audio_url').value = res.url;
                            document.getElementById(prefix + 'question_type').value = 'listening';
                            updateAnswerOptionsUI(mode);
                        }
                        showAttachedBadge('✓ Uploaded: ' + res.filename, mode);
                    }
                });
        }

        function showAttachedBadge(label, mode = 'create') {
            const badgeId = (mode === 'edit') ? 'eq_media_attached_preview' : 'media_attached_preview';
            const labelId = (mode === 'edit') ? 'eq_attached_label_text' : 'attached_label_text';

            document.getElementById(labelId).textContent = label;
            document.getElementById(badgeId).classList.remove('hidden');
        }

        function removeAttachedMedia(mode = 'create') {
            const prefix = (mode === 'edit') ? 'eq_' : 'q_';
            const badgeId = (mode === 'edit') ? 'eq_media_attached_preview' : 'media_attached_preview';

            document.getElementById(prefix + 'audio_url').value = '';
            document.getElementById(prefix + 'passage_text').value = '';
            document.getElementById(badgeId).classList.add('hidden');
        }
    </script>
    <!-- Edit Question Bank Modal (QB-001 Issue 1 Fix) -->
    <div id="edit-bank-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-lg w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white">Edit Question Bank Details</h2>
                <button type="button" onclick="document.getElementById('edit-bank-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="{{ route('admin.question-banks.update', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Bank Title *</label>
                    <input type="text" name="title" value="{{ old('title', $questionBank->title) }}" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Test Type *</label>
                    @php $typeVal = is_object($questionBank->test_type) ? $questionBank->test_type->value : (string)$questionBank->test_type; @endphp
                    <select name="test_type" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                        <option value="toeic" {{ $typeVal === 'toeic' ? 'selected' : '' }}>TOEIC</option>
                        <option value="toefl" {{ $typeVal === 'toefl' ? 'selected' : '' }}>TOEFL iBT</option>
                        <option value="ielts" {{ $typeVal === 'ielts' ? 'selected' : '' }}>IELTS</option>
                        <option value="general" {{ $typeVal === 'general' ? 'selected' : '' }}>General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">{{ old('description', $questionBank->description) }}</textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('edit-bank-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Archive Modal (QB-002) -->
    <div id="archive-bank-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2"><span>📦</span> Request Question Bank Archival</h2>
                <button type="button" onclick="document.getElementById('archive-bank-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="{{ route('admin.question-banks.request-archive', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Reason / Justification for Archiving *</label>
                    <textarea name="reason" required rows="4" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-amber-500 focus:outline-none" placeholder="Provide justification for archiving this item pool..."></textarea>
                </div>
                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg text-[11px] text-amber-300">
                    💡 <strong>Governance Notice:</strong> Operational Admins cannot archive directly. Submitting this request sends it to the Super Admin Approval Center.
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('archive-bank-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs rounded-lg shadow">Submit Archive Request</button>
                </div>
            </form>
        </div>
    </div>
@endsection
