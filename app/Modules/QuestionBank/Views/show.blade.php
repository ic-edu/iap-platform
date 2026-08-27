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
        } elseif ($from === 'revision_task' && request('revision_request_id')) {
            $backUrl = route('teacher.repository-revisions.show', request('revision_request_id'));
            $backLabel = '← Back to Revision Task';
        } elseif ($from === 'archived_repositories' || $from === 'archived') {
            $backUrl = Auth::user()?->hasRole('super-admin') ? route('admin.archived-repositories.index') : route('teacher.archived-repositories.index');
            $backLabel = '← Back to Archived Repositories';
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
                <a href="{{ $backUrl }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-semibold transition-colors">{{ $backLabel }}</a>
                @if(Auth::user()?->hasRole('teacher'))
                    <a href="{{ route('teacher.dashboard') }}" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-semibold transition-colors">Dashboard</a>
                @endif
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $questionBank->title }}</h1>
                @php
                    $statusBadge = match($questionBank->status) {
                        'published' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                        'approved' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
                        'pending_approval' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                        'rejected' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                        default => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-400 border-slate-300 dark:border-slate-700',
                    };
                @endphp
                <span class="px-2.5 py-0.5 text-xs font-bold rounded border uppercase {{ $statusBadge }}">
                    {{ $questionBank->status ?? 'draft' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Test Type: <span class="uppercase font-bold text-indigo-600 dark:text-indigo-400">{{ $questionBank->test_type }}</span> | Total Questions: <span class="font-bold text-slate-900 dark:text-white">{{ $questionBank->questions->count() }}</span></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Auth::user()?->hasRole('teacher'))
                <!-- Teacher Content Creator Actions -->
                @if (in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null]))
                    <button onclick="document.getElementById('edit-bank-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 transition-colors">
                        ✏ Edit Bank Details
                    </button>

                    <form action="{{ route('admin.question-banks.submit', $questionBank->id) }}" method="POST" class="inline">
                        @csrf
                        @if(request('from'))
                            <input type="hidden" name="from" value="{{ request('from') }}">
                        @endif
                        @if(request('revision_request_id'))
                            <input type="hidden" name="revision_request_id" value="{{ request('revision_request_id') }}">
                        @endif
                        @if(request('test_id'))
                            <input type="hidden" name="test_id" value="{{ request('test_id') }}">
                        @endif
                        <button type="button" id="submit-trigger-btn" onclick="document.getElementById('inline-submit-panel').classList.remove('hidden'); this.classList.add('hidden');" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                            🚀 Submit for Approval
                        </button>

                        <div id="inline-submit-panel" class="hidden inline-flex items-center gap-2.5 p-2 bg-white dark:bg-slate-900 border border-amber-500/50 rounded-xl shadow-lg">
                            <span class="text-xs text-slate-800 dark:text-slate-200 font-medium">
                                Submit '{{ $questionBank->title }}' for Super Admin approval?
                            </span>
                            <button type="button" onclick="document.getElementById('inline-submit-panel').classList.add('hidden'); document.getElementById('submit-trigger-btn').classList.remove('hidden');" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" id="confirm-submit-btn" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-colors">
                                Submit for Approval
                            </button>
                        </div>
                    </form>

                    <button onclick="document.getElementById('import-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 transition-colors">
                        📥 Bulk Import
                    </button>
                    <button onclick="openCreateQuestionModal()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                        + Add Question
                    </button>
                @else
                    @if($questionBank->getLockMessage())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-medium rounded-lg">
                        {{ $questionBank->getLockMessage() }}
                    </span>
                    @endif
                    @if(in_array($questionBank->status, ['approved', 'published']))
                        <button type="button" onclick="openTeacherRequestRevisionModal('{{ $questionBank->id }}', '', '{{ addslashes($questionBank->title) }}')" class="px-3 py-1.5 bg-amber-600/10 hover:bg-amber-600/20 text-amber-600 dark:text-amber-400 text-xs font-semibold rounded-lg border border-amber-500/30 transition-colors">
                            🛠 Request Repository Revision
                        </button>
                    @endif
                @endif
            @endif

            @if (Auth::user()?->hasRole('repository-manager') || Auth::user()?->hasRole('super-admin'))
                <!-- Repository Manager Quality Operator Actions -->
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
                        <button type="submit" class="px-3 py-1.5 bg-rose-600/10 hover:bg-rose-600/20 text-rose-600 dark:text-rose-400 text-xs font-semibold rounded-lg border border-rose-500/30 transition-colors">
                            🔒 Unpublish
                        </button>
                    </form>
                @endif

                @if (!in_array($questionBank->status, ['archived', 'pending_archive_approval']))
                    <button onclick="document.getElementById('archive-bank-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-amber-600/10 hover:bg-amber-600/20 text-amber-600 dark:text-amber-400 text-xs font-semibold rounded-lg border border-amber-500/30 transition-colors">
                        📦 Request Archive
                    </button>
                @endif
            @endif
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    @if ($questionBank->status === 'pending_approval')
        <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-xs flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <span class="text-base">🔒</span>
                <div>
                    <strong class="text-amber-800 dark:text-amber-200">Repository Locked Under Governance Review</strong>
                    <p class="text-slate-600 dark:text-slate-400 text-xs mt-0.5 mb-0">This repository has been submitted for approval and is locked while awaiting governance review from a Repository Manager. Authoring actions are disabled.</p>
                </div>
            </div>
            @if(request('from') === 'revision_task' && request('revision_request_id'))
                <a href="{{ route('teacher.repository-revisions.show', request('revision_request_id')) }}" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-lg whitespace-nowrap transition-colors">
                    ← Back to Revision Task
                </a>
            @endif
        </div>
    @endif

    <!-- Questions Table / Cards -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="p-4">#</th>
                    <th class="p-4">Question Prompt &amp; Media</th>
                    <th class="p-4">Type</th>
                    <th class="p-4">Difficulty</th>
                    <th class="p-4">Points</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                @forelse ($questionBank->questions as $idx => $q)
                    @php
                        $qTypeRaw = is_object($q->question_type) ? $q->question_type->value : (string)$q->question_type;
                        $qTypeLabel = $typeLabels[$qTypeRaw] ?? ucwords(str_replace('_', ' ', $qTypeRaw));
                    @endphp
                    <tr>
                        <td class="p-4 font-bold text-slate-400 dark:text-slate-500">{{ $idx + 1 }}</td>
                        <td class="p-4 font-semibold text-slate-900 dark:text-white max-w-md">
                            {{ $q->prompt }}
                            @if($q->audio_url)
                                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-1.5 flex items-center gap-1.5 font-mono bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 px-2 py-1 rounded">
                                    <span>🎵 Audio Attached:</span> {{ basename($q->audio_url) }}
                                </div>
                            @endif
                            @if($q->passage_text)
                                <div class="text-[11px] text-slate-700 dark:text-slate-300 italic mt-1.5 line-clamp-2 bg-slate-50 dark:bg-slate-950 p-2 rounded border border-slate-200 dark:border-slate-800">
                                    📖 Reading Passage: {{ $q->passage_text }}
                                </div>
                            @endif
                            @if($q->choices->isNotEmpty())
                                <div class="text-xs font-normal text-slate-500 dark:text-slate-400 mt-2 space-x-2">
                                    @foreach($q->choices as $c)
                                        <span class="{{ $c->is_correct ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                                            ({{ $c->label }}) {{ $c->content }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="p-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                            <span class="px-2.5 py-1 rounded bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20">
                                {{ $qTypeLabel }}
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-slate-100 dark:bg-slate-950 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 uppercase">
                                {{ is_object($q->difficulty) ? $q->difficulty->value : $q->difficulty }}
                            </span>
                        </td>
                        <td class="p-4 font-bold text-slate-900 dark:text-white">{{ $q->points }} pts</td>
                        <td class="p-4 text-right space-x-2">
                            <button type="button" onclick='openViewQuestionModal({{ json_encode($q) }})' class="text-xs text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-semibold">
                                👁 View
                            </button>
                            @if (Auth::user()?->hasRole('teacher') && in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null]))
                                <button type="button" onclick='openEditQuestionModal({{ json_encode($q) }})' class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                    ✏️ Edit
                                </button>
                                <form action="{{ route('admin.question-banks.duplicate-question', $q->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-amber-600 dark:text-amber-400 hover:underline font-semibold">📄 Duplicate</button>
                                </form>
                            @elseif (Auth::user()?->hasRole('teacher') && in_array($questionBank->status, ['approved', 'published']))
                                <button type="button" onclick="openTeacherRequestRevisionModal('{{ $questionBank->id }}', '{{ $q->id }}', '{{ addslashes(Str::limit($q->prompt, 60)) }}')" class="text-xs text-amber-600 dark:text-amber-400 hover:underline font-semibold">
                                    🛠 Request Revision
                                </button>
                            @endif
                            @if (!Auth::user()?->hasRole('teacher'))
                                <form action="{{ route('admin.question-banks.destroy-question', $q->id) }}" method="POST" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: 'Delete Question?', message: 'Are you sure you want to delete this question? This action cannot be undone.', confirmText: 'Delete Question', variant: 'danger', form: this });">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-semibold">🗑 Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500">
                            @if(in_array($questionBank->status, ['draft', 'rejected', 'needs_revision', null]))
                                No questions added yet. Click "+ Add Question" or "Bulk CSV Import" to start authoring.
                            @else
                                No questions in this repository.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- View Question Read-Only Preview Modal -->
    <div id="view-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-xl w-full shadow-2xl my-8">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>👁 Question Preview</span>
                    <span id="v_type_badge" class="px-2 py-0.5 text-xs font-semibold rounded bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30"></span>
                </h2>
                <button type="button" onclick="closeViewQuestionModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg">&times;</button>
            </div>

            <div class="space-y-4 text-xs text-slate-700 dark:text-slate-300">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Question Prompt</span>
                    <div id="v_prompt" class="text-sm font-semibold text-slate-900 dark:text-white mt-1 bg-slate-50 dark:bg-slate-950 p-3 rounded-lg border border-slate-200 dark:border-slate-800"></div>
                </div>

                <div id="v_media_section" class="hidden">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Media Attachment</span>
                    <div id="v_media_content" class="mt-1 bg-slate-50 dark:bg-slate-950 p-3 rounded-lg border border-slate-200 dark:border-slate-800 text-indigo-600 dark:text-indigo-300"></div>
                </div>

                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-500 block mb-1">Answer Options &amp; Correct Answer</span>
                    <div id="v_choices_container" class="space-y-2"></div>
                </div>

                <div id="v_explanation_section" class="hidden">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Explanation / Rationale</span>
                    <div id="v_explanation" class="mt-1 text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-950 p-3 rounded-lg border border-slate-200 dark:border-slate-800"></div>
                </div>

                <div class="flex items-center justify-between pt-2 text-[11px] text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800">
                    <div>Difficulty: <span id="v_difficulty" class="font-bold text-slate-900 dark:text-white uppercase"></span></div>
                    <div>Points: <span id="v_points" class="font-bold text-slate-900 dark:text-white"></span> pts</div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800 mt-4">
                <button type="button" onclick="closeViewQuestionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Close Preview</button>
            </div>
        </div>
    </div>

    <!-- Create Question Modal -->
    <div id="create-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-2xl w-full shadow-2xl my-8">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Author New Question</h2>
            <form action="{{ route('admin.question-banks.store-question', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" id="q_audio_url" name="audio_url" value="">
                <input type="hidden" id="q_passage_text" name="passage_text" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Question Prompt *</label>
                    <textarea name="prompt" rows="3" class="w-full p-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none" required placeholder="Enter question text..."></textarea>
                </div>

                @php
                    $isToeicBank = (is_object($questionBank->test_type) ? $questionBank->test_type->value : (string)$questionBank->test_type) === 'toeic';
                @endphp

                @if($isToeicBank)
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-500/30 rounded-xl space-y-2">
                    <label class="block text-xs font-bold text-indigo-800 dark:text-indigo-300">🎯 TOEIC Part Selection *</label>
                    <select id="q_part_number" name="part_number" onchange="onQbToeicPartChange('create', this.value)" class="w-full p-2.5 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs font-bold focus:border-indigo-500 focus:outline-none">
                        <option value="1">Part 1: Photographs (Listening — Image &amp; Audio Required, 4 Choices)</option>
                        <option value="2">Part 2: Question-Response (Listening — Audio Required, Exactly 3 Choices)</option>
                        <option value="3">Part 3: Conversations (Listening — Audio Required, 4 Choices)</option>
                        <option value="4">Part 4: Talks (Listening — Audio Required, 4 Choices)</option>
                        <option value="5">Part 5: Incomplete Sentences (Reading — Audio Forbidden, 4 Choices)</option>
                        <option value="6">Part 6: Text Completion (Reading — Passage Required, 4 Choices)</option>
                        <option value="7">Part 7: Reading Comprehension (Reading — Passage Required, 4 Choices)</option>
                    </select>
                    <input type="hidden" id="q_section" name="section" value="listening">
                </div>

                <div id="q_passage_box" class="hidden p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">📖 Reading Passage Text *</label>
                    <textarea name="passage_text" rows="3" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs" placeholder="Enter reading passage text..."></textarea>
                </div>
                @endif

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Question Type *</label>
                        <select id="q_question_type" name="question_type" onchange="updateAnswerOptionsUI('create')" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="single_choice" selected>Single Choice</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            @if(!$isToeicBank)
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="listening">Listening Prompt</option>
                            <option value="reading">Reading Passage</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Difficulty *</label>
                        <select name="difficulty" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <input type="hidden" name="points" value="1">
                </div>

                <!-- Media Attachment Section -->
                <div class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📎</span> Media Attachment (Audio / Image / Passage / PDF)
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openMediaSelectorModal('create')" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-600/20 hover:bg-indigo-100 dark:hover:bg-indigo-600/30 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 text-xs font-semibold rounded-lg transition-colors">
                                Choose Existing Media
                            </button>
                            <label class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                                Upload New File
                                <input type="file" class="hidden" accept="audio/*,image/*,.pdf" onchange="handleDirectFileUpload(event, 'create')">
                            </label>
                        </div>
                    </div>

                    <div id="media_attached_preview" class="hidden p-3 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-500/30 rounded-lg flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-200">
                            <span id="attached_icon">🎵</span>
                            <span id="attached_label_text" class="font-semibold truncate"></span>
                        </div>
                        <button type="button" onclick="removeAttachedMedia('create')" class="text-rose-600 dark:text-rose-400 hover:underline text-xs font-semibold">Remove</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Explanation / Rationale</label>
                    <input type="text" name="explanation" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Provide answer rationale...">
                </div>

                <div id="dynamic_answer_container" class="space-y-3 border-t border-slate-200 dark:border-slate-800 pt-4"></div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="closeCreateQuestionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div id="edit-question-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-2xl w-full shadow-2xl my-8">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Edit Question</h2>
            <form id="edit-question-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" id="eq_audio_url" name="audio_url" value="">
                <input type="hidden" id="eq_passage_text" name="passage_text" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Question Prompt *</label>
                    <textarea id="eq_prompt" name="prompt" rows="3" class="w-full p-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none" required></textarea>
                </div>

                @if($isToeicBank)
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-500/30 rounded-xl space-y-2">
                    <label class="block text-xs font-bold text-indigo-800 dark:text-indigo-300">🎯 TOEIC Part Selection *</label>
                    <select id="eq_part_number" name="part_number" onchange="onQbToeicPartChange('edit', this.value)" class="w-full p-2.5 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs font-bold focus:border-indigo-500 focus:outline-none">
                        <option value="1">Part 1: Photographs (Listening — Image &amp; Audio Required, 4 Choices)</option>
                        <option value="2">Part 2: Question-Response (Listening — Audio Required, Exactly 3 Choices)</option>
                        <option value="3">Part 3: Conversations (Listening — Audio Required, 4 Choices)</option>
                        <option value="4">Part 4: Talks (Listening — Audio Required, 4 Choices)</option>
                        <option value="5">Part 5: Incomplete Sentences (Reading — Audio Forbidden, 4 Choices)</option>
                        <option value="6">Part 6: Text Completion (Reading — Passage Required, 4 Choices)</option>
                        <option value="7">Part 7: Reading Comprehension (Reading — Passage Required, 4 Choices)</option>
                    </select>
                    <input type="hidden" id="eq_section" name="section" value="listening">
                </div>

                <div id="eq_passage_box" class="hidden p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">📖 Reading Passage Text *</label>
                    <textarea id="eq_passage_text_input" name="passage_text" rows="3" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs" placeholder="Enter reading passage text..."></textarea>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Question Type *</label>
                        <select id="eq_question_type" name="question_type" onchange="updateAnswerOptionsUI('edit')" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="single_choice">Single Choice</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            @if(!$isToeicBank)
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="listening">Listening Prompt</option>
                            <option value="reading">Reading Passage</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Difficulty *</label>
                        <select id="eq_difficulty" name="difficulty" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <input type="hidden" id="eq_points" name="points" value="1">
                </div>

                <!-- Media Attachment Section for Edit -->
                <div class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📎</span> Attached Media
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openMediaSelectorModal('edit')" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-600/20 hover:bg-indigo-100 dark:hover:bg-indigo-600/30 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 text-xs font-semibold rounded-lg transition-colors">
                                Replace / Select Media
                            </button>
                        </div>
                    </div>

                    <div id="eq_media_attached_preview" class="p-3 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-500/30 rounded-lg flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-200">
                            <span id="eq_attached_icon">🎵</span>
                            <span id="eq_attached_label_text" class="font-semibold truncate">No media attached</span>
                        </div>
                        <button type="button" onclick="removeAttachedMedia('edit')" class="text-rose-600 dark:text-rose-400 hover:underline text-xs font-semibold">Remove</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Explanation / Rationale</label>
                    <input type="text" id="eq_explanation" name="explanation" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>

                <div id="eq_dynamic_answer_container" class="space-y-3 border-t border-slate-200 dark:border-slate-800 pt-4"></div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="closeEditQuestionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">Update Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reusable Question Media Selector Modal -->
    <div id="media-selector-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-3xl w-full shadow-2xl my-8">
            <div class="flex items-center justify-between mb-4 border-b border-slate-200 dark:border-slate-800 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📁</span> Media Library Selector
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Select an existing audio, image, reading passage, or PDF attachment to attach to this question.</p>
                </div>
                <button type="button" onclick="closeMediaSelectorModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg">&times;</button>
            </div>

            <!-- Filter Tabs -->
            <div class="flex gap-2 mb-4">
                <button type="button" onclick="filterMediaItems('all')" class="media-tab-btn active px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg">All Media</button>
                <button type="button" onclick="filterMediaItems('audio')" class="media-tab-btn px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg">🎵 Audio Tracks</button>
                <button type="button" onclick="filterMediaItems('image')" class="media-tab-btn px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg">🖼️ Images</button>
                <button type="button" onclick="filterMediaItems('passage')" class="media-tab-btn px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg">📖 Passages</button>
                <button type="button" onclick="filterMediaItems('pdf')" class="media-tab-btn px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg">📄 PDFs</button>
            </div>

            <!-- Media Grid -->
            <div id="media-items-container" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto p-1"></div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800 mt-4">
                <button type="button" onclick="closeMediaSelectorModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Close</button>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="import-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-lg w-full shadow-2xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Bulk CSV Question Import</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Paste CSV lines in format: <code class="bg-slate-100 dark:bg-slate-950 text-indigo-700 dark:text-indigo-300 p-1 rounded">Prompt, Choice A, Choice B, Choice C, Choice D, Correct Index (0-3)</code></p>
            <form action="{{ route('admin.question-banks.import', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <textarea name="csv_content" rows="6" class="w-full p-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs font-mono" placeholder="What is 2+2?, 1, 2, 4, 5, 2"></textarea>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">Import Batch</button>
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

        function onQbToeicPartChange(mode, part) {
            part = parseInt(part);
            const prefix = (mode === 'edit') ? 'eq_' : 'q_';
            const secInput = document.getElementById(prefix + 'section');
            if (secInput) {
                secInput.value = (part >= 1 && part <= 4) ? 'listening' : 'reading';
            }
            const passageBox = document.getElementById(prefix + 'passage_box');
            if (passageBox) {
                passageBox.classList.toggle('hidden', !(part === 6 || part === 7));
            }
            updateAnswerOptionsUI(mode);
        }

        function updateAnswerOptionsUI(mode = 'create', existingChoices = null) {
            const prefix = (mode === 'edit') ? 'eq_' : 'q_';
            const containerId = (mode === 'edit') ? 'eq_dynamic_answer_container' : 'dynamic_answer_container';
            const type = document.getElementById(prefix + 'question_type').value;
            const container = document.getElementById(containerId);

            const isToeic = {{ $isToeicBank ? 'true' : 'false' }};
            let partNumber = 1;
            const partEl = document.getElementById(prefix + 'part_number');
            if (partEl) {
                partNumber = parseInt(partEl.value);
            }

            if (['single_choice', 'listening', 'reading', 'multiple_choice'].includes(type)) {
                const isMultiple = (type === 'multiple_choice');
                const titleText = isMultiple
                    ? 'Multiple Choice Answer Options (Check all correct choices)'
                    : (isToeic && partNumber === 2 
                        ? 'Part 2 Answer Options (Exactly 3 choices: A, B, C)'
                        : 'Single Choice Answer Options (Select 1 Correct Answer)');
                const inputType = isMultiple ? 'checkbox' : 'radio';
                const inputName = isMultiple ? 'correct_choices[]' : 'correct_choice';

                // Standard initial set: 3 choices for Part 2, 4 choices for others
                let choicesData = (isToeic && partNumber === 2) ? [
                    { label: 'A', content: '', is_correct: true },
                    { label: 'B', content: '', is_correct: false },
                    { label: 'C', content: '', is_correct: false }
                ] : [
                    { label: 'A', content: '', is_correct: true },
                    { label: 'B', content: '', is_correct: false },
                    { label: 'C', content: '', is_correct: false },
                    { label: 'D', content: '', is_correct: false }
                ];

                if (existingChoices && existingChoices.length > 0) {
                    choicesData = existingChoices.map((c, idx) => ({
                        label: c.label || String.fromCharCode(65 + idx),
                        content: c.content || '',
                        is_correct: !!c.is_correct
                    }));
                    if (isToeic && partNumber === 2 && choicesData.length > 3) {
                        choicesData = choicesData.slice(0, 3);
                    }
                }

                const hideAddBtn = isToeic && partNumber === 2;

                container.innerHTML = `
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-slate-900 dark:text-white">${titleText}</label>
                        ${hideAddBtn ? '' : `
                        <button type="button" onclick="addDynamicChoice('${prefix}', '${type}')" class="text-[11px] font-bold text-indigo-700 dark:text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-500/30 px-2.5 py-1 rounded cursor-pointer transition-colors">
                            + Add Option
                        </button>`}
                    </div>
                    <div id="${prefix}choices_list" class="space-y-2.5">
                        ${choicesData.map((c, i) => `
                            <div class="choice-item-row flex items-center gap-3 bg-slate-50 dark:bg-slate-950/40 p-2 rounded-lg border border-slate-200 dark:border-slate-900">
                                <input type="${inputType}" name="${inputName}" value="${i}" ${c.is_correct ? 'checked' : ''} title="${isMultiple ? 'Check if correct' : 'Select as correct answer'}">
                                <span class="choice-item-label font-bold text-xs text-indigo-600 dark:text-indigo-400 w-4">${c.label}</span>
                                <input type="hidden" class="choice-input-label" name="choices[${i}][label]" value="${c.label}">
                                <input type="text" id="${prefix}choice_${i}" name="choices[${i}][content]" value="${c.content.replace(/"/g, '&quot;')}" class="flex-1 p-2 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Option ${c.label} content" required>
                                <button type="button" onclick="removeDynamicChoice(this, '${prefix}')" class="choice-remove-btn text-rose-600 dark:text-rose-400 hover:text-rose-500 dark:hover:text-rose-300 text-xs px-2 py-1 cursor-pointer" title="Remove choice" style="${choicesData.length <= 2 ? 'display:none;' : ''}">✕</button>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else if (type === 'true_false') {
                let tfCorrect = 'true';
                if (existingChoices && existingChoices.length > 0) {
                    const falseChoice = existingChoices.find(c => c.content === 'False' && c.is_correct);
                    if (falseChoice) tfCorrect = 'false';
                }
                container.innerHTML = `
                    <label class="block text-xs font-bold text-slate-900 dark:text-white mb-2">True / False Correct Answer</label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-white cursor-pointer">
                            <input type="radio" name="tf_correct_choice" value="true" ${tfCorrect === 'true' ? 'checked' : ''} class="text-indigo-600 focus:ring-0"> True
                        </label>
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-white cursor-pointer">
                            <input type="radio" name="tf_correct_choice" value="false" ${tfCorrect === 'false' ? 'checked' : ''} class="text-indigo-600 focus:ring-0"> False
                        </label>
                    </div>
                `;
            } else if (type === 'short_answer') {
                let initialText = '';
                if (existingChoices && existingChoices.length > 0) {
                    initialText = existingChoices[0].content || '';
                }
                container.innerHTML = `
                    <label class="block text-xs font-bold text-slate-900 dark:text-white mb-1">Exact Correct Answer String *</label>
                    <input type="text" id="${prefix}short_answer_text" name="short_answer_text" value="${initialText.replace(/"/g, '&quot;')}" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Enter expected exact string answer...">
                `;
            } else if (type === 'essay') {
                container.innerHTML = `
                    <label class="block text-xs font-bold text-slate-900 dark:text-white mb-1">Reference Answer &amp; Grading Guidelines (Optional)</label>
                    <textarea id="${prefix}reference_answer_text" name="reference_answer_text" rows="3" class="w-full p-3 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Enter model reference answer or grading rubric..."></textarea>
                `;
            }
        }

        function addDynamicChoice(prefix, type) {
            const list = document.getElementById(prefix + 'choices_list');
            if (!list) return;
            const rows = list.querySelectorAll('.choice-item-row');
            const idx = rows.length;
            const label = String.fromCharCode(65 + idx);
            const isMultiple = (type === 'multiple_choice');
            const inputType = isMultiple ? 'checkbox' : 'radio';
            const inputName = isMultiple ? 'correct_choices[]' : 'correct_choice';

            const div = document.createElement('div');
            div.className = 'choice-item-row flex items-center gap-3 bg-slate-50 dark:bg-slate-950/40 p-2 rounded-lg border border-slate-200 dark:border-slate-900';
            div.innerHTML = `
                <input type="${inputType}" name="${inputName}" value="${idx}" title="${isMultiple ? 'Check if correct' : 'Select as correct answer'}">
                <span class="choice-item-label font-bold text-xs text-indigo-600 dark:text-indigo-400 w-4">${label}</span>
                <input type="hidden" class="choice-input-label" name="choices[${idx}][label]" value="${label}">
                <input type="text" id="${prefix}choice_${idx}" name="choices[${idx}][content]" class="flex-1 p-2 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Option ${label} content" required>
                <button type="button" onclick="removeDynamicChoice(this, '${prefix}')" class="choice-remove-btn text-rose-600 dark:text-rose-400 hover:text-rose-500 dark:hover:text-rose-300 text-xs px-2 py-1 cursor-pointer" title="Remove choice">✕</button>
            `;
            list.appendChild(div);
            reindexDynamicChoices(prefix);
        }

        function removeDynamicChoice(btn, prefix) {
            const list = document.getElementById(prefix + 'choices_list');
            if (!list) return;
            const rows = list.querySelectorAll('.choice-item-row');
            if (rows.length <= 2) return;
            btn.closest('.choice-item-row').remove();
            reindexDynamicChoices(prefix);
        }

        function reindexDynamicChoices(prefix) {
            const list = document.getElementById(prefix + 'choices_list');
            if (!list) return;
            const rows = list.querySelectorAll('.choice-item-row');
            rows.forEach((row, idx) => {
                const label = String.fromCharCode(65 + idx);
                const input = row.querySelector('input[type="radio"], input[type="checkbox"]');
                const labelSpan = row.querySelector('.choice-item-label');
                const hiddenLabel = row.querySelector('.choice-input-label');
                const textInput = row.querySelector('input[type="text"]');
                const removeBtn = row.querySelector('.choice-remove-btn');

                if (input) input.value = idx;
                if (labelSpan) labelSpan.textContent = label;
                if (hiddenLabel) {
                    hiddenLabel.name = `choices[${idx}][label]`;
                    hiddenLabel.value = label;
                }
                if (textInput) {
                    textInput.id = `${prefix}choice_${idx}`;
                    textInput.name = `choices[${idx}][content]`;
                    textInput.placeholder = `Option ${label} content`;
                }
                if (removeBtn) {
                    removeBtn.style.display = rows.length > 2 ? 'inline-block' : 'none';
                }
            });
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
                    div.className = `p-2.5 rounded-lg border flex items-center justify-between ${c.is_correct ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-500/40 text-emerald-800 dark:text-emerald-200' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300'}`;
                    div.innerHTML = `
                        <span><strong>(${c.label})</strong> ${c.content}</span>
                        ${c.is_correct ? '<span class="text-[10px] font-bold px-2 py-0.5 bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 rounded border border-emerald-500/30">CORRECT ANSWER</span>' : ''}
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

            if (document.getElementById('eq_part_number') && q.part_number) {
                document.getElementById('eq_part_number').value = q.part_number;
                onQbToeicPartChange('edit', q.part_number);
            }

            updateAnswerOptionsUI('edit', q.choices || null);

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
                t.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-300');
            });
            event.target.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-300');
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
                card.className = 'p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col justify-between';
                let icon = item.type === 'audio' ? '🎵' : (item.type === 'passage' ? '📖' : '📁');
                
                card.innerHTML = `
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">${icon} ${item.type}</span>
                            <span class="text-[10px] font-mono text-slate-500">${item.size || ''}</span>
                        </div>
                        <div class="font-semibold text-slate-900 dark:text-white text-xs truncate">${item.name}</div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <span class="text-[10px] text-slate-500 font-mono">${item.id}</span>
                        <button type="button" onclick="selectMediaItem(${JSON.stringify(item).replace(/"/g, '&quot;')})" 
                                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
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
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-lg w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Edit Question Bank Details</h2>
                <button type="button" onclick="document.getElementById('edit-bank-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">&times;</button>
            </div>
            <form action="{{ route('admin.question-banks.update', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                @if(request('from'))
                    <input type="hidden" name="from" value="{{ request('from') }}">
                @endif
                @if(request('revision_request_id'))
                    <input type="hidden" name="revision_request_id" value="{{ request('revision_request_id') }}">
                @endif

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Bank Title *</label>
                    <input type="text" name="title" value="{{ old('title', $questionBank->title) }}" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Test Type *</label>
                    @php $typeVal = is_object($questionBank->test_type) ? $questionBank->test_type->value : (string)$questionBank->test_type; @endphp
                    <select name="test_type" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                        <option value="toeic" {{ $typeVal === 'toeic' ? 'selected' : '' }}>TOEIC</option>
                        <option value="toefl" {{ $typeVal === 'toefl' ? 'selected' : '' }}>TOEFL iBT</option>
                        <option value="ielts" {{ $typeVal === 'ielts' ? 'selected' : '' }}>IELTS</option>
                        <option value="general" {{ $typeVal === 'general' ? 'selected' : '' }}>General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Academic Category (ACL)</label>
                    <select name="acl_category_id" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                        <option value="">-- Select Category --</option>
                        @foreach($aclCategories ?? [] as $cat)
                            <option value="{{ $cat->id }}" {{ (string)$questionBank->acl_category_id === (string)$cat->id ? 'selected' : '' }}>
                                {{ $cat->name }} ({{ strtoupper($cat->test_type ?? 'general') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Version</label>
                    <input type="text" name="current_version" value="{{ old('current_version', $questionBank->current_version ?? '1.0') }}" placeholder="1.0" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-indigo-500 focus:outline-none">{{ old('description', $questionBank->description) }}</textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('edit-bank-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Archive Modal (QB-002) -->
    <div id="archive-bank-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2"><span>📦</span> Request Question Bank Archival</h2>
                <button type="button" onclick="document.getElementById('archive-bank-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">&times;</button>
            </div>
            <form action="{{ route('admin.question-banks.request-archive', $questionBank->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason / Justification for Archiving *</label>
                    <textarea name="reason" required rows="4" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs focus:border-amber-500 focus:outline-none" placeholder="Provide justification for archiving this item pool..."></textarea>
                </div>
                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg text-[11px] text-amber-700 dark:text-amber-300">
                    💡 <strong>Governance Notice:</strong> Operational Admins cannot archive directly. Submitting this request sends it to the Super Admin Approval Center.
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('archive-bank-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg font-semibold transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">Submit Archive Request</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Teacher Request Repository Revision Modal --}}
    <div id="teacher-request-revision-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm" onclick="closeTeacherRequestRevisionModal(event)">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🛠</span> Request Repository Revision
                </h3>
                <button type="button" onclick="closeTeacherRequestRevisionModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg">&times;</button>
            </div>
            <form method="POST" action="{{ route('teacher.repository-revisions.request') }}">
                @csrf
                <input type="hidden" id="tr-modal-bank-id" name="question_bank_id" value="">
                <input type="hidden" id="tr-modal-question-id" name="question_id" value="">

                <div class="mb-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Repository / Question Item</label>
                    <div id="tr-modal-target-title" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-indigo-700 dark:text-indigo-300 font-semibold truncate"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Revision Rationale / Specific Feedback <span class="text-rose-600 dark:text-rose-400">*</span></label>
                    <textarea name="notes" required rows="4" placeholder="Explain the specific corrections required (e.g., prompt clarification, key correction, answer explanation update)..." class="w-full p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:border-amber-500"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeTeacherRequestRevisionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-lg shadow transition-colors">Submit Revision Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTeacherRequestRevisionModal(bankId, questionId, targetTitle) {
            document.getElementById('tr-modal-bank-id').value = bankId;
            document.getElementById('tr-modal-question-id').value = questionId || '';
            document.getElementById('tr-modal-target-title').innerText = targetTitle || 'Institutional Repository';
            document.getElementById('teacher-request-revision-modal').classList.remove('hidden');
        }

        function closeTeacherRequestRevisionModal(e) {
            if (!e || e.target === document.getElementById('teacher-request-revision-modal')) {
                document.getElementById('teacher-request-revision-modal').classList.add('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            if (action === 'edit_metadata' || action === 'edit_category' || action === 'edit') {
                const modal = document.getElementById('edit-bank-modal');
                if (modal) modal.classList.remove('hidden');
            } else if (action === 'add_question') {
                if (typeof openCreateQuestionModal === 'function') {
                    openCreateQuestionModal();
                }
            }
        });
    </script>
@endsection

