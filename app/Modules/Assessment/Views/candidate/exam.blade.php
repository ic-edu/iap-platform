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
    <!-- Top CBT Status Header Bar -->
    <header class="bg-slate-900 border-b border-slate-800 px-6 py-3 sticky top-0 z-50 flex items-center justify-between">
        <div>
            <span class="text-xs text-indigo-400 font-semibold uppercase tracking-wider">CBT Examination Session</span>
            <h1 class="text-base font-bold text-white leading-snug">{{ $attempt->test?->title }}</h1>
        </div>

        <!-- Live Server-Time Timer Countdown -->
        <div class="flex items-center gap-3">
            <div class="bg-slate-950 border border-slate-800 px-4 py-1.5 rounded-lg text-center shadow-inner">
                <span class="text-[10px] text-slate-400 block uppercase font-medium">Time Remaining</span>
                <span id="countdown-timer" class="text-lg font-mono font-bold text-emerald-400">--:--:--</span>
            </div>

            <form method="POST" action="{{ route('candidate.exam.submit', $attempt) }}" onsubmit="return confirm('Are you sure you want to finalize and submit your test answers?');">
                @csrf
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition-colors shadow-md shadow-emerald-600/30">
                    Final Submit &rarr;
                </button>
            </form>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid gap-6 lg:grid-cols-4">
        <!-- Question Workspace (3 Cols) -->
        <div class="lg:col-span-3 space-y-6">
            @forelse ($shuffledQuestions as $index => $question)
                <div id="question-card-{{ $index }}" class="question-card bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm {{ $index === 0 ? '' : 'hidden' }}">
                    <!-- Header with Part & Flag -->
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                            Question {{ $index + 1 }} of {{ $shuffledQuestions->count() }}
                        </span>
                        <button type="button" onclick="toggleFlag('{{ $question->id }}', this)"
                                class="text-xs font-semibold px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors">
                            🚩 Flag Question
                        </button>
                    </div>

                    <!-- Passage Text if Available -->
                    @if ($question->passage)
                        <div class="mb-5 p-4 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 text-sm max-h-48 overflow-y-auto leading-relaxed">
                            <h4 class="font-bold text-indigo-400 mb-2">{{ $question->passage->title }}</h4>
                            {!! nl2br(e($question->passage->content)) !!}
                        </div>
                    @endif

                    <!-- Prompt -->
                    <div class="text-base font-semibold text-white mb-6 leading-snug">
                        {!! e($question->prompt) !!}
                    </div>

                    <!-- Choices Options -->
                    <div class="space-y-3">
                        @foreach ($question->choices as $choice)
                            <label class="flex items-center p-3.5 rounded-lg bg-slate-950 border border-slate-800 hover:border-indigo-500/50 cursor-pointer transition-colors">
                                <input type="radio" name="q_{{ $question->id }}" value="{{ $choice->id }}"
                                       onchange="autoSaveAnswer('{{ $question->id }}', '{{ $choice->id }}')"
                                       class="w-4 h-4 text-indigo-600 bg-slate-900 border-slate-700 focus:ring-0" />
                                <span class="ml-3 text-sm text-slate-200 font-medium"><strong class="text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}</span>
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
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm h-fit">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Question Palette</h3>
            <div class="grid grid-cols-5 gap-2 text-xs font-bold text-center">
                @foreach ($shuffledQuestions as $idx => $q)
                    <button id="palette-btn-{{ $idx }}" onclick="navigateQuestion({{ $idx }})"
                            class="p-2 rounded bg-slate-950 border border-slate-800 text-slate-300 hover:border-indigo-500">
                        {{ $idx + 1 }}
                    </button>
                @endforeach
            </div>
            <div class="mt-6 pt-4 border-t border-slate-800 text-[11px] space-y-2 text-slate-400">
                <p>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-200">N</kbd> for Next</p>
                <p>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-200">P</kbd> for Previous</p>
                <p>Press <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-200">F</kbd> to Flag</p>
            </div>
        </div>
    </div>

    <!-- Anti-Cheating & Timer JavaScript Engine -->
    <script>
        let remainingSeconds = {{ $remainingSeconds }};
        let currentQuestionIdx = 0;
        const totalQuestions = {{ $shuffledQuestions->count() }};
        const attemptId = "{{ $attempt->id }}";

        // Timer Countdown Engine
        function updateTimerDisplay() {
            if (remainingSeconds <= 0) {
                document.getElementById('countdown-timer').innerText = "00:00:00";
                alert("Time has expired! Submitting test session automatically.");
                window.location.href = "{{ route('candidate.review', $attempt) }}";
                return;
            }
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            document.getElementById('countdown-timer').innerText =
                `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            remainingSeconds--;
        }
        setInterval(updateTimerDisplay, 1000);
        updateTimerDisplay();

        // Question Navigation
        function navigateQuestion(index) {
            if (index < 0 || index >= totalQuestions) return;
            document.querySelectorAll('.question-card').forEach(card => card.classList.add('hidden'));
            document.getElementById(`question-card-${index}`).classList.remove('hidden');
            currentQuestionIdx = index;
        }

        // Auto Save Answer AJAX
        function autoSaveAnswer(questionId, choiceId) {
            fetch("{{ route('candidate.exam.autosave', $attempt) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ question_id: questionId, selected_choice: choiceId })
            });
        }

        // Toggle Flag AJAX
        function toggleFlag(questionId, btn) {
            btn.classList.toggle('bg-amber-500/20');
            btn.classList.toggle('text-amber-400');
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
            if (e.key === 'n' || e.key === 'N') navigateQuestion(currentQuestionIdx + 1);
            if (e.key === 'p' || e.key === 'P') navigateQuestion(currentQuestionIdx - 1);
        });
    </script>
</body>
</html>
