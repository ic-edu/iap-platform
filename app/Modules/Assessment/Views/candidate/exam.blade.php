<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>CBT Exam Session — {{ $attempt->test?->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen flex flex-col select-none">
    @php
        $existingAnswers = $attempt->answers->keyBy('question_id');
        $answeredQuestionIds = $existingAnswers->filter(fn($a) => !is_null($a->selected_choice_id))->keys()->values();
        $totalQuestionsCount = $shuffledQuestions->count();
        $isAllInitiallyAnswered = ($totalQuestionsCount > 0 && $answeredQuestionIds->count() === $totalQuestionsCount);
    @endphp

    <!-- Top CBT Status Header Bar -->
    <header class="bg-slate-900 border-b border-slate-800 px-4 sm:px-6 py-3 sticky top-0 z-50 flex items-center justify-between flex-wrap gap-3">
        <div>
            <span class="text-xs text-indigo-400 font-semibold uppercase tracking-wider">CBT Examination Session</span>
            <h1 class="text-sm sm:text-base font-bold text-white leading-snug">{{ $attempt->test?->title }}</h1>
        </div>

        <!-- Live Server-Time Timer Countdown & Final Submit -->
        <div class="flex items-center gap-3">
            <div class="bg-slate-950 border border-slate-800 px-3 sm:px-4 py-1.5 rounded-lg text-center shadow-inner">
                <span class="text-[10px] text-slate-400 block uppercase font-medium">Time Remaining</span>
                <span id="countdown-timer" class="text-base sm:text-lg font-mono font-bold text-emerald-400">--:--:--</span>
            </div>

            <form id="form-final-submit" method="POST" action="{{ route('candidate.exam.submit', $attempt) }}">
                @csrf
                <button type="submit" 
                        id="btn-final-submit"
                        onclick="event.preventDefault(); if (answeredQuestionIds.size === totalQuestions) { iapConfirm({ title: 'Finalize and Submit Test?', message: 'Are you sure you want to finalize and submit your test answers?', confirmText: 'Final Submit', variant: 'success', form: this.form }); }"
                        {{ $isAllInitiallyAnswered ? '' : 'disabled' }}
                        class="px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-md"
                        style="background: {{ $isAllInitiallyAnswered ? '#10b981' : '#1e293b' }}; color: {{ $isAllInitiallyAnswered ? '#ffffff' : '#64748b' }}; border: 1px solid {{ $isAllInitiallyAnswered ? '#10b981' : '#334155' }}; cursor: {{ $isAllInitiallyAnswered ? 'pointer' : 'not-allowed' }};">
                    {{ $isAllInitiallyAnswered ? 'Final Submit →' : 'Final Submit (' . $answeredQuestionIds->count() . '/' . $totalQuestionsCount . ')' }}
                </button>
            </form>
        </div>
    </header>

    @if (session('error'))
        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 pt-4">
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Workspace -->
    <div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid gap-6 lg:grid-cols-4">
        <!-- Question Workspace (3 Cols) -->
        <div class="lg:col-span-3 space-y-6">
            @forelse ($shuffledQuestions as $index => $question)
                @php
                    $section = $question->section_model ?? null;
                    if (!$section && isset($sections)) {
                        $section = $sections->first(fn($s) => $s->testQuestions->contains('question_id', $question->id));
                    }
                    $prevQuestion = $index > 0 ? $shuffledQuestions[$index - 1] : null;
                    $prevSectionId = $prevQuestion ? ($prevQuestion->section_model?->id ?? (isset($sections) ? $sections->first(fn($s) => $s->testQuestions->contains('question_id', $prevQuestion->id))?->id : null)) : null;
                    $isFirstOfSection = !$prevSectionId || ($section && $section->id !== $prevSectionId);
                    $existingAnswer = $existingAnswers->get($question->id);
                @endphp

                <div id="question-card-{{ $index }}" class="question-card bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm {{ $index === 0 ? '' : 'hidden' }}">
                    <!-- Header with Part & Flag -->
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                            Question {{ $index + 1 }} of {{ $shuffledQuestions->count() }}
                        </span>
                        <button type="button" onclick="toggleFlag('{{ $question->id }}', {{ $index }}, this)"
                                class="btn-flag text-xs font-semibold px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors">
                            🚩 Flag Question
                        </button>
                    </div>

                    <!-- Section Directions: Full Intro for First Question of Section, Streamlined Badge for Subsequent -->
                    @if($section)
                        @if($isFirstOfSection)
                            <div class="mb-5 p-4 rounded-xl bg-slate-950/80 border border-indigo-500/30 text-slate-300 shadow-sm">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2.5 py-0.5 text-xs font-extrabold rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase tracking-wide">
                                        {{ $section->title }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-semibold">
                                        • {{ is_object($section->section_type) ? $section->section_type->label() : ucfirst($section->section_type) }}
                                    </span>
                                </div>

                                @if(!empty($section->instructions))
                                    <div class="mt-2 text-xs sm:text-sm text-slate-300 leading-relaxed whitespace-pre-line border-t border-slate-800/80 pt-2">
                                        <div class="flex items-center gap-1.5 mb-1 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                            <span>📌</span> Section Directions
                                        </div>
                                        {{ $section->instructions }}
                                    </div>
                                @endif

                                @if($section->mediaAssets && $section->mediaAssets->isNotEmpty())
                                    <div class="mt-4 space-y-3">
                                        @foreach($section->mediaAssets as $sectionMedia)
                                            <div class="p-3 rounded-lg bg-slate-900 border border-slate-800 text-slate-200">
                                                @if($sectionMedia->pivot?->caption)
                                                    <div class="flex items-center gap-1.5 mb-1.5 text-xs font-bold text-indigo-300 uppercase tracking-wider">
                                                        <span>{{ $sectionMedia->typeIcon() }}</span>
                                                        <span>{{ $sectionMedia->pivot->caption }}</span>
                                                    </div>
                                                @endif
                                                <x-media-preview :media="$sectionMedia" />
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="mb-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wide">
                                    {{ $section->title }}
                                </span>
                                <span class="text-[11px] text-slate-400 font-medium">
                                    • {{ is_object($section->section_type) ? $section->section_type->label() : ucfirst($section->section_type) }}
                                </span>
                            </div>
                        @endif
                    @endif

                    <!-- Passage Text if Available -->
                    @if ($question->passage)
                        <div class="mb-5 p-4 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 text-sm max-h-48 overflow-y-auto leading-relaxed">
                            <h4 class="font-bold text-indigo-400 mb-2">{{ $question->passage->title }}</h4>
                            {!! nl2br(e($question->passage->content)) !!}
                        </div>
                    @endif

                    <!-- Question-Level Media (Image, Audio, MediaAsset) -->
                    @if (!empty($question->image_url))
                        <div class="mb-5 text-center">
                            <img src="{{ $question->image_url }}" alt="Question Attachment" class="max-h-72 max-w-full rounded-xl mx-auto border border-slate-800 shadow-md object-contain">
                        </div>
                    @endif

                    @if (!empty($question->audio_url))
                        <div class="mb-5 p-4 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-200">
                            <div class="flex items-center gap-1.5 mb-2 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                <span>🎧</span>
                                <span>Question Audio Prompt</span>
                            </div>
                            <audio controls controlsList="nodownload noplaybackrate" class="w-full" src="{{ $question->audio_url }}" preload="metadata"></audio>
                        </div>
                    @endif

                    @if (empty($question->image_url) && empty($question->audio_url) && $question->mediaAsset)
                        <div class="mb-5 p-4 rounded-xl bg-slate-950/90 border border-slate-800 text-slate-200">
                            <x-media-preview :media="$question->mediaAsset" />
                        </div>
                    @endif

                    <!-- Prompt -->
                    <div class="text-base font-semibold text-white mb-6 leading-snug">
                        {!! e($question->prompt) !!}
                    </div>

                    <!-- Choices Options -->
                    <div class="space-y-3">
                        @foreach ($question->choices as $choice)
                            @php
                                $isChecked = $existingAnswer && $existingAnswer->selected_choice_id === $choice->id;
                            @endphp
                            <label class="flex items-center p-3.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-indigo-500/50 cursor-pointer transition-colors">
                                <input type="radio" name="q_{{ $question->id }}" value="{{ $choice->id }}"
                                       {{ $isChecked ? 'checked' : '' }}
                                       onchange="autoSaveAnswer('{{ $question->id }}', '{{ $choice->id }}', {{ $index }})"
                                       class="w-4 h-4 text-indigo-600 bg-slate-900 border-slate-700 focus:ring-0" />
                                <span class="ml-3 text-sm text-slate-200 font-medium">
                                    <strong class="text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <!-- Question Navigation Controls -->
                    <div class="flex justify-between pt-6 mt-6 border-t border-slate-800">
                        <button type="button" onclick="navigateQuestion({{ $index - 1 }})" {{ $index === 0 ? 'disabled' : '' }}
                                class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-white transition-colors">
                            &larr; Previous (P)
                        </button>
                        <button type="button" onclick="navigateQuestion({{ $index + 1 }})" {{ $index === $shuffledQuestions->count() - 1 ? 'disabled' : '' }}
                                class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white transition-colors">
                            Next (N) &rarr;
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 text-center text-slate-500">
                    No questions assigned to this test.
                </div>
            @endforelse
        </div>

        <!-- Question Palette Sidebar (1 Col) -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm h-fit">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-800">
                <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Question Navigation</h3>
                <span id="answered-counter-badge" class="text-[11px] font-bold text-indigo-400">
                    {{ count($answeredQuestionIds) }}/{{ $shuffledQuestions->count() }}
                </span>
            </div>

            <div id="palette-grid" class="flex flex-wrap gap-1.5 text-xs font-bold">
                @foreach ($shuffledQuestions as $idx => $q)
                    @php
                        $isAns = $existingAnswers->has($q->id) && !is_null($existingAnswers->get($q->id)->selected_choice_id);
                    @endphp
                    <button id="palette-btn-{{ $idx }}" type="button" onclick="navigateQuestion({{ $idx }})"
                            class="palette-btn px-2.5 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center gap-1 {{ $idx === 0 ? 'ring-2 ring-indigo-400 border-indigo-500 bg-indigo-950/80 text-white shadow-sm' : ($isAns ? 'border-emerald-500/40 bg-emerald-950/30 text-emerald-300' : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700') }}">
                        <span>{{ $idx + 1 }}</span>
                        <span id="palette-icon-{{ $idx }}" class="text-[10px]">{{ $isAns ? '✓' : '—' }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-4 pt-3 border-t border-slate-800/80 text-[10px] text-slate-400 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <span class="text-emerald-400 font-bold">✓</span> Answered
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-slate-500 font-bold">—</span> Unanswered
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-amber-400 font-bold">⚑</span> Flagged
                </div>
            </div>

            <div class="mt-3 text-[10px] space-y-1 text-slate-500">
                <p><kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">N</kbd> Next • <kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">P</kbd> Previous • <kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">F</kbd> Flag</p>
            </div>
        </div>
    </div>

    <!-- Anti-Cheating & Timer JavaScript Engine -->
    <script>
        let remainingSeconds = {{ $remainingSeconds }};
        let currentQuestionIdx = 0;
        const totalQuestions = {{ $shuffledQuestions->count() }};
        const questionIds = @json($shuffledQuestions->pluck('id'));
        const answeredQuestionIds = new Set(@json($answeredQuestionIds));
        const flaggedQuestionIndices = new Set();
        let timerInterval = null;

        // Timer Countdown Engine
        function updateTimerDisplay() {
            if (remainingSeconds <= 0) {
                if (timerInterval) clearInterval(timerInterval);
                document.getElementById('countdown-timer').innerText = "00:00:00";
                
                // Trigger auto submission on expiry
                iapAlert({
                    title: 'Time Expired',
                    message: 'Time has expired! Submitting test session automatically.',
                    okText: 'Submit Now',
                    variant: 'warning',
                    onOk: () => {
                        document.getElementById('form-final-submit').submit();
                    }
                });

                setTimeout(() => {
                    document.getElementById('form-final-submit').submit();
                }, 3000);
                return;
            }

            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            document.getElementById('countdown-timer').innerText =
                `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            remainingSeconds--;
        }

        timerInterval = setInterval(updateTimerDisplay, 1000);
        updateTimerDisplay();

        // Final Submit Client Guard & UI updater
        function updateFinalSubmitButton() {
            const btn = document.getElementById('btn-final-submit');
            if (!btn) return;

            const isAllAnswered = (answeredQuestionIds.size === totalQuestions && totalQuestions > 0);
            if (isAllAnswered) {
                btn.disabled = false;
                btn.style.background = '#10b981';
                btn.style.borderColor = '#10b981';
                btn.style.color = '#ffffff';
                btn.style.cursor = 'pointer';
                btn.innerHTML = 'Final Submit &rarr;';
            } else {
                btn.disabled = true;
                btn.style.background = '#1e293b';
                btn.style.borderColor = '#334155';
                btn.style.color = '#64748b';
                btn.style.cursor = 'not-allowed';
                btn.innerHTML = `Final Submit (${answeredQuestionIds.size}/${totalQuestions})`;
            }
        }

        // Palette State & Highlighting Engine
        function updatePaletteUI() {
            document.querySelectorAll('.palette-btn').forEach((btn, idx) => {
                const icon = document.getElementById(`palette-icon-${idx}`);
                const qId = questionIds[idx];
                const isAnswered = answeredQuestionIds.has(qId);
                const isFlagged = flaggedQuestionIndices.has(idx);
                const isCurrent = (idx === currentQuestionIdx);

                if (icon) {
                    if (isFlagged && isAnswered) {
                        icon.textContent = '✓⚑';
                    } else if (isFlagged) {
                        icon.textContent = '⚑';
                    } else if (isAnswered) {
                        icon.textContent = '✓';
                    } else {
                        icon.textContent = '—';
                    }
                }

                btn.className = 'palette-btn px-2.5 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center gap-1 ';
                if (isCurrent) {
                    btn.className += 'ring-2 ring-indigo-400 border-indigo-500 bg-indigo-950/80 text-white shadow-sm';
                } else if (isFlagged) {
                    btn.className += 'border-amber-500/40 bg-amber-950/30 text-amber-300';
                } else if (isAnswered) {
                    btn.className += 'border-emerald-500/40 bg-emerald-950/30 text-emerald-300';
                } else {
                    btn.className += 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700';
                }
            });

            const counter = document.getElementById('answered-counter-badge');
            if (counter) {
                counter.textContent = `${answeredQuestionIds.size}/${totalQuestions}`;
            }

            updateFinalSubmitButton();
        }

        // Question Navigation
        function navigateQuestion(index) {
            if (index < 0 || index >= totalQuestions) return;
            document.querySelectorAll('.question-card').forEach(card => card.classList.add('hidden'));
            const targetCard = document.getElementById(`question-card-${index}`);
            if (targetCard) targetCard.classList.remove('hidden');
            currentQuestionIdx = index;
            updatePaletteUI();
        }

        // Auto Save Answer AJAX with Palette & Submit State Synchronization
        function autoSaveAnswer(questionId, choiceId, index) {
            answeredQuestionIds.add(questionId);
            updatePaletteUI();

            fetch("{{ route('candidate.exam.autosave', $attempt) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ question_id: questionId, selected_choice: choiceId })
            });
        }

        // Toggle Flag AJAX with Palette State Synchronization
        function toggleFlag(questionId, index, btn) {
            if (flaggedQuestionIndices.has(index)) {
                flaggedQuestionIndices.delete(index);
                if (btn) {
                    btn.classList.remove('bg-amber-500/20', 'text-amber-400');
                    btn.classList.add('bg-slate-800', 'text-slate-300');
                }
            } else {
                flaggedQuestionIndices.add(index);
                if (btn) {
                    btn.classList.remove('bg-slate-800', 'text-slate-300');
                    btn.classList.add('bg-amber-500/20', 'text-amber-400');
                }
            }
            updatePaletteUI();

            fetch("{{ route('candidate.exam.flag', $attempt) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ question_id: questionId })
            });
        }

        // Anti-Cheating Violation Logger
        function logViolation(type) {
            fetch("{{ route('candidate.exam.violation', $attempt) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ violation_type: type })
            });
        }

        window.addEventListener('blur', () => logViolation('window_blur'));
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
            if (e.key === 'n' || e.key === 'N') navigateQuestion(currentQuestionIdx + 1);
            if (e.key === 'p' || e.key === 'P') navigateQuestion(currentQuestionIdx - 1);
            if (e.key === 'f' || e.key === 'F') {
                const currentCard = document.getElementById(`question-card-${currentQuestionIdx}`);
                const flagBtn = currentCard ? currentCard.querySelector('.btn-flag') : null;
                toggleFlag(questionIds[currentQuestionIdx], currentQuestionIdx, flagBtn);
            }
        });

        // Initialize state on page ready
        document.addEventListener('DOMContentLoaded', () => {
            updatePaletteUI();
        });
    </script>

    <x-iap-modal />
</body>
</html>
