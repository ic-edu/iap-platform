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
        $isRealTest = $isRealTest ?? ($attempt->test?->isRealTest() ?? false);
        $playedAudioSet = collect($playedAudioQuestionIds ?? []);

        // Build section mapping and first-question indices
        $sectionFirstQuestionIndex = [];
        $questionSectionMap = [];
        foreach ($shuffledQuestions as $idx => $q) {
            $s = $q->section_model ?? (isset($sections) ? $sections->first(fn($sec) => $sec->testQuestions->contains('question_id', $q->id)) : null);
            if ($s) {
                if (!isset($sectionFirstQuestionIndex[$s->id])) {
                    $sectionFirstQuestionIndex[$s->id] = $idx;
                }
                $questionSectionMap[$idx] = $s->id;
            }
        }
    @endphp

    <!-- Fullscreen Warning Overlay for Real Test Mode -->
    @if($isRealTest)
        <div id="fullscreen-warning-overlay" class="fixed inset-0 z-[100] bg-slate-950/95 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center hidden">
            <div class="max-w-md w-full p-8 rounded-2xl bg-slate-900 border border-rose-500/40 shadow-2xl space-y-4">
                <span class="text-4xl">⚠️</span>
                <h2 class="text-xl font-black text-white">Secure Fullscreen Exited</h2>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                    You have exited secure fullscreen mode. For official exam integrity, this incident has been logged. Please return to fullscreen mode immediately to continue your assessment.
                </p>
                <button type="button" onclick="enterFullscreen()" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all">
                    Return to Fullscreen &rarr;
                </button>
            </div>
        </div>
    @endif

    <!-- Top CBT Status Header Bar -->
    <header class="bg-slate-900 border-b border-slate-800 px-4 sm:px-6 py-3 sticky top-0 z-50 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2.5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-indigo-400 font-semibold uppercase tracking-wider">CBT Examination Session</span>
                    @if($isRealTest)
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/30 uppercase tracking-wide">
                            SECURE MOCK TEST
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 uppercase tracking-wide">
                            TEST SIMULATOR
                        </span>
                    @endif
                </div>
                <h1 class="text-sm sm:text-base font-bold text-white leading-snug">{{ $attempt->test?->title }}</h1>
            </div>
        </div>

        <!-- Live Server-Time Timer Countdown & Final Submit -->
        <div class="flex items-center gap-3">
            @if($isRealTest)
                <button type="button" onclick="enterFullscreen()" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition-colors">
                    <span>⛶</span> Fullscreen
                </button>
            @endif

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
        <!-- Question & Section Workspace (3 Cols) -->
        <div class="lg:col-span-3 space-y-6">

            <!-- 1. DEDICATED SECTION DIRECTIONS SCREENS (Rendered per Section) -->
            @if(isset($sections) && $sections->isNotEmpty())
                @foreach($sections as $secIndex => $sec)
                    @php
                        $firstQIdx = $sectionFirstQuestionIndex[$sec->id] ?? 0;
                        $btnLabel = 'Begin ' . $sec->title;
                    @endphp
                    <div id="section-intro-card-{{ $sec->id }}" class="section-intro-card bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-lg space-y-6 hidden">
                        <div class="border-b border-slate-800 pb-4">
                            <div class="flex items-center gap-2.5 mb-2">
                                <span class="px-3 py-1 text-xs font-extrabold rounded-lg bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase tracking-wider">
                                    {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type) }} SECTION
                                </span>
                                <span class="text-xs text-slate-400 font-medium">
                                    Section {{ $secIndex + 1 }} of {{ $sections->count() }}
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                                {{ $sec->title }}
                            </h2>
                        </div>

                        <!-- Directions Text -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider flex items-center gap-1.5">
                                <span>📌</span> Section Directions
                            </h3>
                            <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-sm leading-relaxed whitespace-pre-line">
                                {{ $sec->instructions ?: 'Please read the questions carefully and select the best answer option.' }}
                            </div>
                        </div>

                        <!-- Section Media Assets / Examples (if any) -->
                        @if($sec->mediaAssets && $sec->mediaAssets->isNotEmpty())
                            <div class="space-y-3 pt-2">
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🎧</span> Section Reference / Example Media
                                </h3>
                                <div class="space-y-4">
                                    @foreach($sec->mediaAssets as $sectionMedia)
                                        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-slate-200">
                                            @if($sectionMedia->pivot?->caption)
                                                <div class="flex items-center gap-1.5 mb-2 text-xs font-bold text-indigo-300 uppercase tracking-wider">
                                                    <span>{{ $sectionMedia->typeIcon() }}</span>
                                                    <span>{{ $sectionMedia->pivot->caption }}</span>
                                                </div>
                                            @endif
                                            <x-media-preview :media="$sectionMedia" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Action Bar to Advance into Section Questions -->
                        <div class="pt-6 border-t border-slate-800 flex items-center justify-between flex-wrap gap-3">
                            @if(!$isRealTest && $secIndex > 0 && isset($sections[$secIndex - 1]))
                                @php
                                    $prevLastQIdx = ($sectionFirstQuestionIndex[$sec->id] ?? 1) - 1;
                                @endphp
                                <button type="button" onclick="navigateQuestion({{ $prevLastQIdx }})" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-colors">
                                    &larr; Previous Section
                                </button>
                            @else
                                <div></div>
                            @endif

                            <button type="button" onclick="startSectionQuestions({{ $firstQIdx }})" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-indigo-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]">
                                <span>🚀 {{ $btnLabel }} &rarr;</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            @endif

            <!-- 2. INDIVIDUAL QUESTION CARDS -->
            @forelse ($shuffledQuestions as $index => $question)
                @php
                    $section = $question->section_model ?? null;
                    if (!$section && isset($sections)) {
                        $section = $sections->first(fn($s) => $s->testQuestions->contains('question_id', $question->id));
                    }
                    $existingAnswer = $existingAnswers->get($question->id);
                    $nextIndex = $index + 1;
                    $hasNextQuestion = $nextIndex < $totalQuestionsCount;
                    $nextQuestionSectionId = $hasNextQuestion ? ($questionSectionMap[$nextIndex] ?? null) : null;
                    $isLastQuestionOfSection = $section && $hasNextQuestion && ($nextQuestionSectionId !== $section->id);
                    $isAudioPlayed = $playedAudioSet->contains($question->id);
                @endphp

                <div id="question-card-{{ $index }}" class="question-card bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm hidden">
                    <!-- Header with Part, Breadcrumb & Flag -->
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                Question {{ $index + 1 }} of {{ $shuffledQuestions->count() }}
                            </span>
                            @if($section && !$isRealTest)
                                <span class="text-slate-600">•</span>
                                <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="text-[11px] font-bold text-indigo-400 hover:text-indigo-300 underline underline-offset-2 transition-colors">
                                    {{ $section->title }} (View Directions)
                                </button>
                            @elseif($section)
                                <span class="text-slate-600">•</span>
                                <span class="text-[11px] font-bold text-slate-400">{{ $section->title }}</span>
                            @endif
                        </div>
                        <button type="button" onclick="toggleFlag('{{ $question->id }}', {{ $index }}, this)"
                                class="btn-flag text-xs font-semibold px-3 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors">
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

                    <!-- Question-Level Media (Image, Audio, MediaAsset) -->
                    @if (!empty($question->image_url))
                        <div class="mb-5 text-center">
                            <img src="{{ $question->image_url }}" alt="Question Attachment" class="max-h-72 max-w-full rounded-xl mx-auto border border-slate-800 shadow-md object-contain">
                        </div>
                    @endif

                    <!-- Question Audio (Shared Audio Group or Individual Audio) -->
                    @php
                        $hasAudioSource = !empty($question->audio_url) || ($question->mediaAsset && $question->mediaAsset->type === 'audio') || ($question->audioGroup && (!empty($question->audioGroup->audio_url) || $question->audioGroup->media_asset_id));
                        $isAudioGroup = (bool) $question->audioGroup;
                        $audioGroupModel = $question->audioGroup;
                        $groupTypeLabel = $audioGroupModel ? ($audioGroupModel->isTalk() ? 'Talk' : 'Conversation') : 'Audio';
                    @endphp

                    @if ($hasAudioSource)
                        @if ($isRealTest)
                            <div id="audio-container-{{ $question->id }}" class="mb-5 p-4 rounded-xl bg-slate-950/90 border {{ $isAudioGroup ? 'border-indigo-500/40 bg-indigo-950/20' : 'border-slate-800' }} text-slate-200" data-audio-group-id="{{ $question->audio_group_id }}">
                                <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        <span>🎧</span>
                                        <span>{{ $isAudioGroup ? "Shared {$groupTypeLabel} Audio (Part " . ($audioGroupModel->part_number ?? 3) . " — Single Play)" : 'Question Audio Prompt (Single Play)' }}</span>
                                    </div>
                                    <span id="audio-badge-{{ $question->id }}" class="text-[11px] font-bold {{ $isAudioPlayed ? 'text-slate-500 bg-slate-900 border-slate-800' : 'text-amber-400 bg-amber-950/40 border-amber-500/30' }} px-2.5 py-0.5 rounded-md border">
                                        {{ $isAudioPlayed ? 'Audio Played (1/1)' : 'Play Available (1/1)' }}
                                    </span>
                                </div>

                                @if($isAudioGroup && $audioGroupModel->title)
                                    <div class="text-xs text-slate-300 font-medium mb-2.5 italic">
                                        📌 Questions refer to the following {{ strtolower($groupTypeLabel) }}: <strong class="text-white">{{ $audioGroupModel->title }}</strong>
                                    </div>
                                @endif

                                <div class="flex items-center gap-3">
                                    <button id="btn-play-{{ $question->id }}" type="button" 
                                            onclick="playRealTestAudio('{{ $question->id }}', '{{ route('candidate.exam.audio-stream', [$attempt, $question]) }}', '{{ $question->audio_group_id }}')"
                                            {{ $isAudioPlayed ? 'disabled' : '' }}
                                            class="px-4 py-2 rounded-xl {{ $isAudioPlayed ? 'bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/30' }} font-bold text-xs transition-all flex items-center gap-2">
                                        <span>▶</span>
                                        <span id="btn-play-label-{{ $question->id }}">{{ $isAudioPlayed ? 'Already Played' : ($isAudioGroup ? "Play {$groupTypeLabel} Audio" : 'Play Audio Prompt') }}</span>
                                    </button>
                                    <audio id="audio-elem-{{ $question->id }}" class="hidden" preload="none" onended="onAudioEnded('{{ $question->id }}', '{{ $question->audio_group_id }}')"></audio>
                                </div>
                            </div>
                        @else
                            <div class="mb-5 p-4 rounded-xl bg-slate-950/90 border {{ $isAudioGroup ? 'border-indigo-500/40 bg-indigo-950/20' : 'border-slate-800' }} text-slate-200">
                                <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        <span>🎧</span>
                                        <span>{{ $isAudioGroup ? "Shared {$groupTypeLabel} Audio (Part " . ($audioGroupModel->part_number ?? 3) . ")" : 'Question Audio Prompt' }}</span>
                                    </div>
                                    @if($isAudioGroup)
                                        <span class="text-[10px] font-bold text-indigo-300 bg-indigo-950/60 border border-indigo-500/30 px-2 py-0.5 rounded">
                                            Shared Group
                                        </span>
                                    @endif
                                </div>
                                @if($isAudioGroup && $audioGroupModel->title)
                                    <div class="text-xs text-slate-300 font-medium mb-2 italic">
                                        📌 Questions refer to the following {{ strtolower($groupTypeLabel) }}: <strong class="text-white">{{ $audioGroupModel->title }}</strong>
                                    </div>
                                @endif
                                <audio controls controlsList="nodownload noplaybackrate" class="w-full" src="{{ $question->audio_url ?: route('candidate.exam.audio-stream', [$attempt, $question]) }}" preload="metadata"></audio>
                            </div>
                        @endif
                    @endif

                    @if (empty($question->image_url) && empty($question->audio_url) && $question->mediaAsset && $question->mediaAsset->type !== 'audio')
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

                    <!-- Question Navigation Controls (Mode-Aware) -->
                    <div class="flex justify-between items-center pt-6 mt-6 border-t border-slate-800">
                        @if (!$isRealTest)
                            @if($index === 0 && $section)
                                <button type="button" onclick="showSectionIntro('{{ $section->id }}')"
                                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors">
                                    &larr; Section Directions
                                </button>
                            @else
                                <button type="button" onclick="navigateQuestion({{ $index - 1 }})" {{ $index === 0 ? 'disabled' : '' }}
                                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-white transition-colors">
                                    &larr; Previous (P)
                                </button>
                            @endif
                        @else
                            <div></div>
                        @endif

                        @if($isLastQuestionOfSection && $nextQuestionSectionId)
                            <button type="button" onclick="handleNextClick({{ $index }}, 'section', '{{ $nextQuestionSectionId }}')"
                                    class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30">
                                Next Section &rarr;
                            </button>
                        @else
                            <button type="button" onclick="handleNextClick({{ $index }}, 'question', {{ $index + 1 }})" {{ $index === $shuffledQuestions->count() - 1 && $isRealTest ? 'disabled' : '' }}
                                    class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white transition-all shadow-md shadow-indigo-600/30">
                                Next (N) &rarr;
                            </button>
                        @endif
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
                    <button id="palette-btn-{{ $idx }}" type="button" onclick="handlePaletteClick({{ $idx }})"
                            class="palette-btn px-2.5 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center gap-1 {{ $isAns ? 'border-emerald-500/40 bg-emerald-950/30 text-emerald-300' : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700' }}">
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
                @if(!$isRealTest)
                    <p><kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">N</kbd> Next • <kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">P</kbd> Previous • <kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">F</kbd> Flag</p>
                @else
                    <p><kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">N</kbd> Next • <kbd class="px-1 py-0.2 bg-slate-800 text-slate-300 rounded">F</kbd> Flag</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Anti-Cheating, Security & Navigation JavaScript Engine -->
    <script>
        const isRealTest = {{ $isRealTest ? 'true' : 'false' }};
        let remainingSeconds = {{ $remainingSeconds }};
        let currentQuestionIdx = -1; // -1 represents section intro view
        const totalQuestions = {{ $shuffledQuestions->count() }};
        const questionIds = @json($shuffledQuestions->pluck('id'));
        const answeredQuestionIds = new Set(@json($answeredQuestionIds));
        const flaggedQuestionIndices = new Set();
        const firstSectionId = "{{ isset($sections) && $sections->isNotEmpty() ? $sections->first()->id : '' }}";
        let timerInterval = null;

        // Fullscreen Mode Handler for Real Test
        function enterFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    logViolation('fullscreen_enter');
                    const overlay = document.getElementById('fullscreen-warning-overlay');
                    if (overlay) overlay.classList.add('hidden');
                }).catch(err => {
                    console.warn('Fullscreen request failed:', err);
                });
            } else {
                const overlay = document.getElementById('fullscreen-warning-overlay');
                if (overlay) overlay.classList.add('hidden');
            }
        }

        if (isRealTest) {
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) {
                    logViolation('fullscreen_exit');
                    const overlay = document.getElementById('fullscreen-warning-overlay');
                    if (overlay) overlay.classList.remove('hidden');
                } else {
                    const overlay = document.getElementById('fullscreen-warning-overlay');
                    if (overlay) overlay.classList.add('hidden');
                }
            });
        }

        // Section Questions Initiation (Enters Fullscreen on user gesture for Real Test)
        function startSectionQuestions(firstQIdx) {
            if (isRealTest) {
                enterFullscreen();
            }
            navigateQuestion(firstQIdx);
        }

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

        // Show Dedicated Section Introduction Screen
        function showSectionIntro(sectionId) {
            if (!sectionId) return;
            document.querySelectorAll('.question-card, .section-intro-card').forEach(card => card.classList.add('hidden'));
            const targetSection = document.getElementById('section-intro-card-' + sectionId);
            if (targetSection) {
                targetSection.classList.remove('hidden');
                currentQuestionIdx = -1;
                window.location.hash = 'section=' + sectionId;
                updatePaletteUI();
            }
        }

        // Navigate to Specific Question
        function navigateQuestion(index) {
            if (index < 0 || index >= totalQuestions) return;
            document.querySelectorAll('.question-card, .section-intro-card').forEach(card => card.classList.add('hidden'));
            const targetCard = document.getElementById(`question-card-${index}`);
            if (targetCard) targetCard.classList.remove('hidden');
            currentQuestionIdx = index;
            window.location.hash = 'q=' + index;
            updatePaletteUI();
        }

        // Mode-Aware Next Action Handler
        function handleNextClick(currentIndex, type, target) {
            const currentQId = questionIds[currentIndex];

            if (isRealTest) {
                // Mock Test: Mandatory answer before next
                if (!answeredQuestionIds.has(currentQId)) {
                    iapAlert({
                        title: 'Answer Required',
                        message: 'For Mock Test examination, you must select an answer before proceeding to the next question.',
                        variant: 'warning',
                        okText: 'Understood'
                    });
                    return;
                }
            }

            if (type === 'section') {
                showSectionIntro(target);
            } else {
                if (target < totalQuestions) {
                    navigateQuestion(target);
                } else if (!isRealTest && answeredQuestionIds.size < totalQuestions) {
                    // Simulator Mode: Loop back to first unanswered question
                    for (let i = 0; i < totalQuestions; i++) {
                        if (!answeredQuestionIds.has(questionIds[i])) {
                            iapAlert({
                                title: 'Review Unanswered Questions',
                                message: 'You have reached the end of the simulation. Navigating to your first unanswered question.',
                                variant: 'info',
                                okText: 'Continue',
                                onOk: () => navigateQuestion(i)
                            });
                            return;
                        }
                    }
                }
            }
        }

        // Mode-Aware Palette Click Handler
        function handlePaletteClick(targetIdx) {
            if (isRealTest) {
                // Mock Test: cannot jump forward over unanswered current question
                if (currentQuestionIdx >= 0 && targetIdx > currentQuestionIdx && !answeredQuestionIds.has(questionIds[currentQuestionIdx])) {
                    iapAlert({
                        title: 'Answer Current Question First',
                        message: 'You must answer the current question before moving forward in Mock Test mode.',
                        variant: 'warning',
                        okText: 'Understood'
                    });
                    return;
                }
            }

            navigateQuestion(targetIdx);
        }

        // Real Test Single Play Audio Engine
        function playRealTestAudio(questionId, streamUrl, audioGroupId) {
            const btn = document.getElementById(`btn-play-${questionId}`);
            const label = document.getElementById(`btn-play-label-${questionId}`);
            const badge = document.getElementById(`audio-badge-${questionId}`);
            const audio = document.getElementById(`audio-elem-${questionId}`);

            if (!audio || (btn && btn.disabled)) return;

            if (btn) {
                btn.disabled = true;
                btn.className = 'px-4 py-2 rounded-xl bg-slate-800 text-slate-500 cursor-not-allowed font-bold text-xs transition-all flex items-center gap-2';
            }
            if (label) label.textContent = 'Playing Audio...';
            if (badge) {
                badge.textContent = 'Audio Playing (1/1)';
                badge.className = 'text-[11px] font-bold text-indigo-400 bg-indigo-950/40 border-indigo-500/30 px-2.5 py-0.5 rounded-md border';
            }

            // If question belongs to an audio group, disable other group buttons
            if (audioGroupId) {
                document.querySelectorAll(`[data-audio-group-id="${audioGroupId}"]`).forEach(container => {
                    const otherBtn = container.querySelector('button[id^="btn-play-"]');
                    const otherBadge = container.querySelector('span[id^="audio-badge-"]');
                    if (otherBtn && otherBtn !== btn) {
                        otherBtn.disabled = true;
                        otherBtn.className = 'px-4 py-2 rounded-xl bg-slate-800 text-slate-500 cursor-not-allowed font-bold text-xs transition-all flex items-center gap-2';
                    }
                    if (otherBadge && otherBadge !== badge) {
                        otherBadge.textContent = 'Audio Playing (1/1)';
                        otherBadge.className = 'text-[11px] font-bold text-indigo-400 bg-indigo-950/40 border-indigo-500/30 px-2.5 py-0.5 rounded-md border';
                    }
                });
            }

            audio.src = streamUrl;
            audio.play().catch(err => {
                console.error('Audio playback error:', err);
                if (label) label.textContent = 'Playback Failed';
            });
        }

        function onAudioEnded(questionId, audioGroupId) {
            const label = document.getElementById(`btn-play-label-${questionId}`);
            const badge = document.getElementById(`audio-badge-${questionId}`);

            if (label) label.textContent = 'Already Played';
            if (badge) {
                badge.textContent = 'Audio Played (1/1)';
                badge.className = 'text-[11px] font-bold text-slate-500 bg-slate-900 border-slate-800 px-2.5 py-0.5 rounded-md border';
            }

            if (audioGroupId) {
                document.querySelectorAll(`[data-audio-group-id="${audioGroupId}"]`).forEach(container => {
                    const otherLabel = container.querySelector('span[id^="btn-play-label-"]');
                    const otherBadge = container.querySelector('span[id^="audio-badge-"]');
                    if (otherLabel) otherLabel.textContent = 'Already Played';
                    if (otherBadge) {
                        otherBadge.textContent = 'Audio Played (1/1)';
                        otherBadge.className = 'text-[11px] font-bold text-slate-500 bg-slate-900 border-slate-800 px-2.5 py-0.5 rounded-md border';
                    }
                });
            }

            fetch("{{ route('candidate.exam.violation', $attempt) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ violation_type: 'audio_completed', question_id: questionId })
            });
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
            
            if (e.key === 'n' || e.key === 'N') {
                if (currentQuestionIdx >= 0) {
                    handleNextClick(currentQuestionIdx, 'question', currentQuestionIdx + 1);
                } else if (firstSectionId) {
                    startSectionQuestions(0);
                }
            }
            
            if (!isRealTest && (e.key === 'p' || e.key === 'P')) {
                if (currentQuestionIdx > 0) {
                    navigateQuestion(currentQuestionIdx - 1);
                } else if (currentQuestionIdx === 0 && firstSectionId) {
                    showSectionIntro(firstSectionId);
                }
            }

            if (e.key === 'f' || e.key === 'F') {
                if (currentQuestionIdx >= 0) {
                    const currentCard = document.getElementById(`question-card-${currentQuestionIdx}`);
                    const flagBtn = currentCard ? currentCard.querySelector('.btn-flag') : null;
                    toggleFlag(questionIds[currentQuestionIdx], currentQuestionIdx, flagBtn);
                }
            }
        });

        // Initialize view based on URL hash or default to Section 1 Intro
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash;
            if (hash.startsWith('#q=')) {
                const qIdx = parseInt(hash.replace('#q=', ''), 10);
                if (!isNaN(qIdx) && qIdx >= 0 && qIdx < totalQuestions) {
                    navigateQuestion(qIdx);
                } else {
                    navigateQuestion(0);
                }
            } else if (hash.startsWith('#section=')) {
                const sId = hash.replace('#section=', '');
                showSectionIntro(sId);
            } else if (firstSectionId) {
                showSectionIntro(firstSectionId);
            } else {
                navigateQuestion(0);
            }

            updatePaletteUI();
        });
    </script>

    <x-iap-modal />
</body>
</html>
