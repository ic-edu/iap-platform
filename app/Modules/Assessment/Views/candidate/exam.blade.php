<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>CBT Exam Session — {{ $attempt->test?->title }}</title>

    <!-- Early Theme Initialization to prevent flash of wrong theme -->
    <script>
        (function() {
            var preference = '{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'light') }}';
            function resolveTheme(pref) {
                if (pref === 'system') {
                    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                return pref === 'light' ? 'light' : 'dark';
            }
            var activeTheme = resolveTheme(preference);
            var root = document.documentElement;
            root.setAttribute('data-theme', activeTheme);
            root.setAttribute('data-preference', preference);
            if (activeTheme === 'dark') {
                root.classList.add('dark');
                root.classList.remove('light');
            } else {
                root.classList.add('light');
                root.classList.remove('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col select-none transition-colors">
    @php
        $existingAnswers = $attempt->answers->keyBy('question_id');
        $answeredQuestionIds = $existingAnswers->filter(fn($a) => !is_null($a->selected_choice_id))->keys()->values();
        $existingChoiceMap = $existingAnswers->filter(fn($a) => !is_null($a->selected_choice_id))->mapWithKeys(fn($a) => [$a->question_id => $a->selected_choice_id]);
        $flaggedQuestionIds = $attempt->flagged_questions ?? [];
        $totalQuestionsCount = $totalQuestionsCount ?? $shuffledQuestions->count();
        $totalUnitsCount = $totalUnitsCount ?? $deliveryUnits->count();
        $isAllInitiallyAnswered = ($totalQuestionsCount > 0 && $answeredQuestionIds->count() === $totalQuestionsCount);
        $isRealTest = $isRealTest ?? ($attempt->test?->isRealTest() ?? false);
        $playedAudioSet = collect($playedAudioQuestionIds ?? []);
        $deliveryUnitsMeta = $deliveryUnits->map(function ($u, $idx) {
            return [
                'index' => $idx,
                'type' => $u['type'],
                'part_number' => (int) ($u['part_number'] ?? 0),
                'passage_type' => $u['passage_type'] ?? null,
                'display_question_range' => $u['display_question_range'] ?? '',
                'documents_count' => isset($u['passages']) ? count($u['passages']) : ($u['passage_type'] === 'triple' ? 3 : ($u['passage_type'] === 'double' ? 2 : 1)),
            ];
        })->values();
    @endphp

    <!-- Fullscreen Warning Overlay for Real Test Mode (When Exited) -->
    @if($isRealTest)
        <div id="fullscreen-warning-overlay" class="fixed inset-0 z-[100] bg-slate-950/95 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center hidden">
            <div class="max-w-md w-full p-8 rounded-2xl bg-white dark:bg-slate-900 border border-rose-500/40 shadow-2xl space-y-4">
                <span class="text-4xl">⚠️</span>
                <h2 class="text-xl font-black text-slate-900 dark:text-white">Secure Fullscreen Exited</h2>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    You have exited secure fullscreen mode. For secure test integrity, this incident has been logged. Please return to fullscreen mode immediately to continue your assessment.
                </p>
                <button type="button" onclick="returnToFullscreen()" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all">
                    Return to Fullscreen &rarr;
                </button>
            </div>
        </div>

        <!-- Fullscreen Required Recovery Overlay (When Entry Failed/Blocked) -->
        <div id="fullscreen-required-overlay" class="fixed inset-0 z-[100] bg-slate-950/95 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center hidden">
            <div class="max-w-md w-full p-8 rounded-2xl bg-white dark:bg-slate-900 border border-amber-500/40 shadow-2xl space-y-4">
                <span class="text-4xl">⛶</span>
                <h2 class="text-xl font-black text-slate-900 dark:text-white">Fullscreen Required</h2>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    This secure assessment must run in fullscreen mode. Enter fullscreen to continue.
                </p>
                <button type="button" onclick="retryEnterFullscreenAndContinue()" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all">
                    Enter Fullscreen &amp; Continue &rarr;
                </button>
            </div>
        </div>
    @endif

    <!-- Top CBT Status Header Bar -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 py-3 sticky top-0 z-50 flex items-center justify-between flex-wrap gap-3 shadow-sm">
        <div class="flex items-center gap-3">
            @if(!$isRealTest)
                <button type="button"
                        id="btn-simulator-back-to-dashboard"
                        onclick="confirmExitSimulator()"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition-all inline-flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                        title="Return to Candidate Dashboard">
                    <span>&larr; Back to Dashboard</span>
                </button>
            @endif

            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold uppercase tracking-wider">CBT Examination Session</span>
                    @if($isRealTest)
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300 border border-rose-500/30 uppercase tracking-wide">
                            SECURE MOCK TEST
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-500/30 uppercase tracking-wide">
                            TEST SIMULATOR
                        </span>
                    @endif
                </div>
                <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-snug">{{ $attempt->test?->title }}</h1>
            </div>
        </div>

        <!-- Live Server-Time Timer Countdown -->
        <div class="flex items-center gap-3">
            <div class="bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 px-3 sm:px-4 py-1.5 rounded-lg text-center shadow-inner">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block uppercase font-medium">Time Remaining</span>
                <span id="countdown-timer" class="text-base sm:text-lg font-mono font-bold text-emerald-600 dark:text-emerald-400">--:--:--</span>
            </div>
        </div>
    </header>

    <form id="form-final-submit" method="POST" action="{{ route('candidate.exam.submit', $attempt) }}" class="hidden">
        @csrf
    </form>

    @if (session('error'))
        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 pt-4">
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Workspace -->
    <div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid gap-6 lg:grid-cols-4">
        <!-- Delivery Units & Section Workspace (3 Cols) -->
        <div class="lg:col-span-3 space-y-6">

            <!-- 1. DEDICATED SECTION DIRECTIONS SCREENS (Rendered per Section) -->
            @if(isset($sections) && $sections->isNotEmpty())
                @foreach($sections as $secIndex => $sec)
                    @php
                        $firstUnitIdx = $sectionFirstUnitIndex[$sec->id] ?? 0;
                        $btnLabel = 'Begin ' . $sec->title;
                    @endphp
                    <div id="section-intro-card-{{ $sec->id }}" class="section-intro-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm dark:shadow-lg space-y-6 hidden">
                        <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
                            <div class="flex items-center gap-2.5 mb-2">
                                <span class="px-3 py-1 text-xs font-extrabold rounded-lg bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300 border border-indigo-500/30 uppercase tracking-wider">
                                    {{ is_object($sec->section_type) ? $sec->section_type->label() : (str_ends_with(ucwords((string) $sec->section_type), 'Section') ? ucwords((string) $sec->section_type) : ucwords((string) $sec->section_type) . ' Section') }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    Section {{ $secIndex + 1 }} of {{ $sections->count() }}
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                                {{ $sec->title }}
                            </h2>
                        </div>

                        <!-- Directions Text -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-1.5">
                                <span>📌</span> Section Directions
                            </h3>
                            <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-sm leading-relaxed whitespace-pre-line">
                                {{ $sec->instructions ?: 'Please read the questions carefully and select the best answer option.' }}
                            </div>
                        </div>

                        <!-- Section Media Assets / Examples (if any) -->
                        @if($sec->mediaAssets && $sec->mediaAssets->isNotEmpty())
                            <div class="space-y-3 pt-2">
                                <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🎧</span> Section Reference / Example Media
                                </h3>
                                <div class="space-y-4">
                                    @foreach($sec->mediaAssets as $sectionMedia)
                                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200">
                                            @if($sectionMedia->pivot?->caption)
                                                <div class="flex items-center gap-1.5 mb-2 text-xs font-bold text-indigo-600 dark:text-indigo-300 uppercase tracking-wider">
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
                        <div class="pt-6 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between flex-wrap gap-3">
                            @if(!$isRealTest && $secIndex > 0 && isset($sections[$secIndex - 1]))
                                @php
                                    $prevLastUnitIdx = ($sectionFirstUnitIndex[$sec->id] ?? 1) - 1;
                                @endphp
                                <button type="button" onclick="navigateDeliveryUnit({{ $prevLastUnitIdx }})" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition-colors">
                                    &larr; Previous Section
                                </button>
                            @else
                                <div></div>
                            @endif

                            <button type="button" onclick="startSectionQuestions({{ $firstUnitIdx }})" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-indigo-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]">
                                <span>🚀 {{ $btnLabel }} &rarr;</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            @endif

            <!-- PASSAGE TYPE TRANSITION INTERSTITIAL (Part 7 Format Boundaries: Single -> Double, Double -> Triple, Single -> Triple) -->
            <div id="passage-type-transition-card" class="passage-type-transition-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6 hidden max-w-3xl mx-auto">
                <!-- Header Badge & Title -->
                <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex items-center gap-2.5 mb-2 flex-wrap">
                        <span class="px-3 py-1 text-xs font-black rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 uppercase tracking-wider">
                            TOEIC PART 7 — READING COMPREHENSION
                        </span>
                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700 uppercase">
                            Passage Format Change
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        Passage Format Change
                    </h2>
                </div>

                <!-- Transition Overview Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Completed Format -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>✓</span> Completed Format
                        </div>
                        <div id="transition-from-type" class="text-base font-black text-slate-800 dark:text-slate-200">
                            Single Passage
                        </div>
                    </div>

                    <!-- Next Format -->
                    <div class="p-4 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 space-y-1">
                        <div class="text-[11px] font-extrabold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider flex items-center gap-1.5">
                            <span>→</span> Next Format
                        </div>
                        <div id="transition-to-type" class="text-base font-black text-indigo-900 dark:text-indigo-200">
                            Double Passage
                        </div>
                        <div id="transition-doc-count" class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                            2 related documents
                        </div>
                    </div>
                </div>

                <!-- Instructions / Explanatory Box -->
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Format Instructions</span>
                    </div>
                    <p id="transition-description" class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-medium">
                        You will read 2 related documents and answer the questions that follow.
                    </p>
                    <div id="transition-question-range-container" class="pt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                        <span>Target Questions:</span>
                        <span id="transition-question-range" class="font-extrabold text-indigo-600 dark:text-indigo-400">Questions 176–180</span>
                    </div>
                </div>

                <!-- Action / CTA Strip -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button type="button"
                            id="btn-begin-transition-passage"
                            onclick="proceedPassageTypeTransition()"
                            class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs sm:text-sm shadow-md shadow-indigo-600/25 transition-all inline-flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 cursor-pointer">
                        <span id="transition-cta-label">Begin Double Passage</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </div>

            <!-- 2. DELIVERY UNIT CARDS -->
            @forelse ($deliveryUnits as $unitIndex => $unit)
                @php
                    $unitType = $unit['type'];
                    $section = $unit['section'];
                    $isAudioGroup = ($unitType === 'audio_group');
                    $isPassageGroup = ($unitType === 'passage_group');
                    $groupTypeLabel = ($unit['group_type'] ?? '') === 'talk' ? 'Talk' : 'Conversation';
                    $isFirstUnitOfSection = $unit['is_first_unit_of_section'];
                    $isLastUnitOfSection = $unit['is_last_unit_of_section'];
                    $nextSectionId = $unit['next_section_id'];
                @endphp

                <div id="delivery-unit-card-{{ $unitIndex }}" class="delivery-unit-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-7 shadow-sm hidden" data-unit-type="{{ $unitType }}" data-unit-index="{{ $unitIndex }}">

                    @if($isAudioGroup)
                        <!-- ========================================================================= -->
                        <!-- AUDIO GROUP DELIVERY UNIT (Part 3 / Part 4 — One Audio + Child Questions) -->
                        <!-- ========================================================================= -->
                        @php
                            $primaryQuestion = $unit['questions']->first();
                            $isGroupAudioPlayed = $unit['questions']->contains(fn($q) => $playedAudioSet->contains($q->id));
                            $audioStreamUrl = route('candidate.exam.audio-stream', [$attempt, $primaryQuestion]);
                        @endphp

                        <!-- Header with Part & Range -->
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3.5 mb-5 flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                    PART {{ $unit['part_number'] }} — {{ strtoupper($groupTypeLabel) }} • {{ $unit['display_question_range'] }}
                                </span>
                                @if($section && !$isRealTest)
                                    <span class="text-slate-400 dark:text-slate-600">•</span>
                                    <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline underline-offset-2 transition-colors">
                                        {{ $section->title }} (View Directions)
                                    </button>
                                @elseif($section)
                                    <span class="text-slate-400 dark:text-slate-600">•</span>
                                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ $section->title }}</span>
                                @endif
                            </div>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-indigo-50 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/40 uppercase">
                                Shared {{ $groupTypeLabel }} Group
                            </span>
                        </div>

                        <!-- ONE Shared Group Audio Stimulus Action / Player -->
                        <div id="group-audio-container-{{ $unitIndex }}" class="mb-6 p-4 sm:p-5 rounded-2xl bg-indigo-50/50 dark:bg-slate-950/90 border border-indigo-200 dark:border-indigo-500/40 dark:bg-indigo-950/20 text-slate-800 dark:text-slate-200" data-audio-group-id="{{ $unit['audio_group_id'] }}">
                            <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                    <span>🎧</span>
                                    <span>Shared {{ $groupTypeLabel }} Audio @if(!empty($unit['part_number']))(Part {{ $unit['part_number'] }})@endif</span>
                                    <span>• {{ $unit['display_question_range'] }}</span>
                                    @if($isRealTest)
                                        <span class="text-[10px] text-rose-500 dark:text-rose-400 font-extrabold">(Single Play)</span>
                                    @endif
                                </div>
                                @if($isRealTest)
                                    <span id="audio-badge-unit-{{ $unitIndex }}" class="text-[11px] font-bold {{ $isGroupAudioPlayed ? 'text-slate-500 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-slate-800' : 'text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-950/40 border-amber-300 dark:border-amber-500/30' }} px-2.5 py-0.5 rounded-md border">
                                        {{ $isGroupAudioPlayed ? 'Audio Played (1/1)' : 'Play Available (1/1)' }}
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-950/60 border border-indigo-300 dark:border-indigo-500/30 px-2 py-0.5 rounded">
                                        Shared Audio Stimulus
                                    </span>
                                @endif
                            </div>

                            @if($unit['title'])
                                <div class="text-xs text-slate-600 dark:text-slate-300 font-medium mb-3 italic">
                                    📌 Questions refer to the following {{ strtolower($groupTypeLabel) }}: <strong class="text-slate-900 dark:text-white">{{ $unit['title'] }}</strong>
                                </div>
                            @endif

                            @if($isRealTest)
                                <div class="flex items-center gap-3">
                                    <button id="btn-play-unit-{{ $unitIndex }}" type="button"
                                            onclick="playRealTestUnitAudio({{ $unitIndex }}, '{{ $unit['audio_group_id'] }}', '{{ $audioStreamUrl }}')"
                                            {{ $isGroupAudioPlayed ? 'disabled' : '' }}
                                            class="px-4 py-2 rounded-xl {{ $isGroupAudioPlayed ? 'bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/30' }} font-bold text-xs transition-all flex items-center gap-2">
                                        <span>▶</span>
                                        <span id="btn-play-label-unit-{{ $unitIndex }}">{{ $isGroupAudioPlayed ? 'Already Played' : "Play {$groupTypeLabel} Audio" }}</span>
                                    </button>
                                    <audio id="audio-unit-elem-{{ $unitIndex }}" data-exam-audio="true" class="hidden" preload="none" onended="onUnitAudioEnded({{ $unitIndex }}, '{{ $unit['audio_group_id'] }}', '{{ $primaryQuestion->id }}')"></audio>
                                </div>
                            @else
                                <audio controls controlsList="nodownload noplaybackrate" data-exam-audio="true" class="w-full" src="{{ (!empty($unit['audio_url']) && filter_var($unit['audio_url'], FILTER_VALIDATE_URL)) ? $unit['audio_url'] : $audioStreamUrl }}" preload="metadata"></audio>
                            @endif
                        </div>

                        <!-- Child Questions Rendered Vertically on the Same Page -->
                        <div class="space-y-6">
                            @foreach($unit['questions'] as $cIdx => $question)
                                @php
                                    $globalQIdx = $unit['question_indices'][$cIdx];
                                    $agn = $unit['canonical_question_numbers'][$cIdx] ?? ($globalQIdx + 1);
                                    $existingAnswer = $existingAnswers->get($question->id);
                                @endphp

                                <div id="unit-question-block-{{ $globalQIdx }}" class="p-5 sm:p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4 transition-all">
                                    @php
                                        $isFlagged = in_array($question->id, $flaggedQuestionIds, true);
                                    @endphp
                                    <!-- Child Header with Flag -->
                                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800/80 pb-2.5 flex-wrap gap-2">
                                        <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                            Question {{ $agn }}
                                        </span>
                                        <button type="button" id="btn-flag-{{ $question->id }}" onclick="toggleFlag('{{ $question->id }}', {{ $globalQIdx }}, this)"
                                                class="btn-flag text-xs font-semibold px-3 py-1 rounded {{ $isFlagged ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }} transition-colors">
                                            🚩 Flag Question
                                        </button>
                                    </div>

                                    <!-- Stem Prompt -->
                                    @if(!empty($question->prompt))
                                        <div class="text-base font-semibold text-slate-900 dark:text-white leading-snug">
                                            {!! e($question->prompt) !!}
                                        </div>
                                    @endif

                                    <!-- Choices Options (Independent Autosave & Selection) -->
                                    <div class="space-y-3 pt-1">
                                        @foreach ($question->choices as $choice)
                                            @php
                                                $isChecked = $existingAnswer && $existingAnswer->selected_choice_id === $choice->id;
                                            @endphp
                                            <label class="flex items-center p-3.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/50 cursor-pointer transition-colors shadow-sm">
                                                <input type="radio" name="q_{{ $question->id }}" value="{{ $choice->id }}"
                                                       {{ $isChecked ? 'checked' : '' }}
                                                       onchange="autoSaveAnswer('{{ $question->id }}', '{{ $choice->id }}', {{ $globalQIdx }})"
                                                       aria-label="Option {{ $choice->label }}"
                                                       class="w-4 h-4 text-indigo-600 bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 focus:ring-0" />
                                                <span class="ml-3 text-sm text-slate-800 dark:text-slate-200 font-medium">
                                                    <strong class="text-indigo-600 dark:text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Audio Group Navigation Action Bar -->
                        <div class="flex justify-between items-center pt-6 mt-8 border-t border-slate-200 dark:border-slate-800 flex-wrap gap-3">
                            @if(!$isRealTest)
                                @if($isFirstUnitOfSection && $section)
                                    <button type="button" onclick="showSectionIntro('{{ $section->id }}')"
                                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors">
                                        &larr; Section Directions
                                    </button>
                                @else
                                    <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex - 1 }})" {{ $unitIndex === 0 ? 'disabled' : '' }}
                                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-40 text-slate-700 dark:text-white transition-colors">
                                        &larr; Previous (P)
                                    </button>
                                @endif
                            @else
                                <div></div>
                            @endif

                            @if($isLastUnitOfSection && $nextSectionId)
                                <button type="button" onclick="handleNextClick({{ $unitIndex }}, 'section', '{{ $nextSectionId }}')"
                                        class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 cursor-pointer">
                                    Next Section &rarr;
                                </button>
                            @elseif($unitIndex === $totalUnitsCount - 1)
                                <div id="final-unit-action-container-audio-{{ $unitIndex }}" class="flex items-center gap-2">
                                    <button type="button" id="btn-review-unanswered-audio-{{ $unitIndex }}" onclick="reviewFirstUnanswered()"
                                            class="btn-review-unanswered {{ $isAllInitiallyAnswered ? 'hidden' : 'inline-flex' }} px-5 py-2.5 text-xs font-bold rounded-xl bg-amber-600 hover:bg-amber-500 text-white transition-all shadow-md shadow-amber-600/30 items-center gap-1.5 cursor-pointer">
                                        <span>Review Unanswered (<span class="unanswered-count-text">{{ $totalQuestionsCount - count($answeredQuestionIds) }}</span>)</span>
                                    </button>
                                    <button type="button" id="btn-final-review-submit-audio-{{ $unitIndex }}" onclick="triggerFinalSubmitModal()"
                                            class="btn-final-review-submit {{ $isAllInitiallyAnswered ? 'inline-flex' : 'hidden' }} px-5 py-2.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition-all shadow-md shadow-emerald-600/30 items-center gap-1.5 cursor-pointer">
                                        <span>Final Review &amp; Submit &rarr;</span>
                                    </button>
                                </div>
                            @else
                                <button type="button" onclick="handleNextClick({{ $unitIndex }}, 'unit', {{ $unitIndex + 1 }})"
                                        class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 cursor-pointer">
                                    Next (N) &rarr;
                                </button>
                            @endif
                        </div>

                    @elseif($isPassageGroup)
                        <!-- ========================================================================= -->
                        <!-- PASSAGE GROUP DELIVERY UNIT (Part 6 Text Completion / Part 7 Reading)    -->
                        <!-- ========================================================================= -->
                        @php
                            $effectivePassages = $unit['passages'];
                            $partNum = $unit['part_number'];
                            $groupTitle = $unit['title'] ?? ($partNum === 6 ? 'Text Completion' : 'Reading Comprehension');
                        @endphp

                        <!-- Header with Part & Range -->
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3.5 mb-5 flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                    PART {{ $partNum }} — {{ strtoupper($groupTitle) }} • {{ $unit['display_question_range'] }}
                                </span>
                                @if($section && !$isRealTest)
                                    <span class="text-slate-400 dark:text-slate-600">•</span>
                                    <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline underline-offset-2 transition-colors">
                                        {{ $section->title }} (View Directions)
                                    </button>
                                @elseif($section)
                                    <span class="text-slate-400 dark:text-slate-600">•</span>
                                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ $section->title }}</span>
                                @endif
                            </div>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-indigo-50 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/40 uppercase">
                                {{ $partNum === 6 ? 'Text Completion Group' : 'Passage Group' }}
                            </span>
                        </div>

                        <!-- Reading Dual Pane Split Screen -->
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <!-- Left Pane: Passages & Documents -->
                            <div class="lg:col-span-6 xl:col-span-7 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-5 flex flex-col min-h-[420px] max-h-[75vh] lg:sticky lg:top-20 overflow-hidden" id="passage-pane-unit-{{ $unitIndex }}">
                                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-3 flex-wrap gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>📄</span>
                                            <span>{{ ($unit['passage_type'] ?? '') ? ('Part ' . $partNum . ' ' . ucfirst($unit['passage_type']) . ' Passage') : ($partNum === 6 ? 'Text Completion Passage' : 'Reading Passage') }}</span>
                                        </span>
                                        @if($effectivePassages->count() > 1)
                                            <div class="flex items-center gap-1.5 ml-2 flex-wrap">
                                                <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 mr-1 hidden sm:inline">Jump to:</span>
                                                @foreach($effectivePassages as $pIdx => $pass)
                                                    @php
                                                        $rawTitle = trim($pass->title ?? '');
                                                        $docType = trim($pass->document_type ?? '');
                                                        $docTypeLabel = $docType ? ucfirst($docType) : '';
                                                        if (!empty($rawTitle) && !empty($docTypeLabel)) {
                                                            if (strcasecmp($rawTitle, $docTypeLabel) === 0 || stripos($rawTitle, $docTypeLabel) !== false) {
                                                                $docLabel = $rawTitle;
                                                            } else {
                                                                $docLabel = $rawTitle . ' (' . $docTypeLabel . ')';
                                                            }
                                                        } elseif (!empty($rawTitle)) {
                                                            $docLabel = $rawTitle;
                                                        } elseif (!empty($docTypeLabel)) {
                                                            $docLabel = $docTypeLabel;
                                                        } else {
                                                            $docLabel = 'Document ' . ($pIdx + 1);
                                                        }
                                                    @endphp
                                                    <button type="button"
                                                            id="passage-tab-unit-{{ $unitIndex }}-{{ $pIdx }}"
                                                            onclick="switchPassageDocUnit({{ $unitIndex }}, {{ $pIdx }})"
                                                            class="passage-doc-tab text-[11px] font-bold px-2.5 py-1 rounded-lg border transition-all {{ $pIdx === 0 ? 'bg-indigo-600 text-white border-indigo-500 shadow-sm' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700' }}"
                                                            title="Jump to Document {{ $pIdx + 1 }}: {{ $docLabel }}">
                                                        {{ ($pIdx + 1) }} — {{ $docLabel }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @if($unit['title'])
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400 italic truncate max-w-[200px]">{{ $unit['title'] }}</span>
                                    @endif
                                </div>

                                <div class="passage-scroll-container overflow-y-auto pr-2 space-y-6 text-sm text-slate-800 dark:text-slate-200 leading-relaxed max-h-[62vh]" id="passage-scroll-unit-{{ $unitIndex }}">
                                    @forelse($effectivePassages as $pIdx => $pass)
                                        @php
                                            $passImg = $pass->mediaAsset ? route('media.preview', $pass->mediaAsset->id) : $pass->getEffectiveImageUrl();
                                            $rawTitle = trim($pass->title ?? '');
                                            $docType = trim($pass->document_type ?? '');
                                            $docTypeLabel = $docType ? ucfirst($docType) : '';
                                            if (!empty($rawTitle) && !empty($docTypeLabel)) {
                                                if (strcasecmp($rawTitle, $docTypeLabel) === 0 || stripos($rawTitle, $docTypeLabel) !== false) {
                                                    $docLabel = $rawTitle;
                                                } else {
                                                    $docLabel = $rawTitle . ' (' . $docTypeLabel . ')';
                                                }
                                            } elseif (!empty($rawTitle)) {
                                                $docLabel = $rawTitle;
                                            } elseif (!empty($docTypeLabel)) {
                                                $docLabel = $docTypeLabel;
                                            } else {
                                                $docLabel = 'Document ' . ($pIdx + 1);
                                            }
                                        @endphp
                                        <div id="passage-doc-unit-{{ $unitIndex }}-{{ $pIdx }}" class="passage-doc-content-unit-{{ $unitIndex }} {{ $pIdx > 0 ? 'pt-6 border-t-2 border-dashed border-slate-200 dark:border-slate-800' : '' }}">
                                            @if($effectivePassages->count() > 1)
                                                <div class="flex items-center justify-between gap-2 mb-3 bg-slate-100/90 dark:bg-slate-900/90 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-indigo-100 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                                            Document {{ $pIdx + 1 }} of {{ $effectivePassages->count() }}
                                                        </span>
                                                        @if($docLabel)
                                                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $docLabel }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @elseif($pass->title)
                                                <h4 class="font-bold text-base text-indigo-700 dark:text-indigo-300 mb-2 border-b border-slate-200 dark:border-slate-800 pb-1.5">{{ $pass->title }}</h4>
                                            @endif

                                            @if(!empty($passImg))
                                                <div class="mb-4 text-center group relative">
                                                    <img src="{{ $passImg }}"
                                                         alt="{{ $pass->title ?: ('Document ' . ($pIdx + 1)) }}"
                                                         data-stimulus-zoomable="true"
                                                         class="stimulus-zoomable max-w-full rounded-lg mx-auto border border-slate-200 dark:border-slate-800 shadow-md object-contain cursor-zoom-in hover:opacity-95 transition-all"
                                                         style="max-height: 500px;"
                                                         onclick="openStimulusLightbox(this.src, this.alt)">
                                                    <div class="mt-1.5 text-center">
                                                        <button type="button"
                                                                onclick="openStimulusLightbox('{{ $passImg }}', '{{ addslashes($pass->title ?: ('Document ' . ($pIdx + 1))) }}')"
                                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                                                            <span>🔍</span>
                                                            <span>Click image to enlarge</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                            @if(!empty($pass->content))
                                                <div class="prose dark:prose-invert max-w-none text-slate-800 dark:text-slate-200 text-sm whitespace-pre-line leading-relaxed select-text">
                                                    {!! nl2br(e($pass->content)) !!}
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-slate-500 italic text-sm">Passage stimulus content.</div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Right Pane: Associated Child Questions -->
                            <div class="lg:col-span-6 xl:col-span-5 space-y-6">
                                @foreach($unit['questions'] as $cIdx => $question)
                                    @php
                                        $globalQIdx = $unit['question_indices'][$cIdx];
                                        $agn = $unit['canonical_question_numbers'][$cIdx] ?? ($globalQIdx + 1);
                                        $existingAnswer = $existingAnswers->get($question->id);
                                    @endphp

                                    @php
                                        $isFlagged = in_array($question->id, $flaggedQuestionIds, true);
                                    @endphp
                                    <div id="unit-question-block-{{ $globalQIdx }}" class="p-5 sm:p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4 transition-all">
                                        <!-- Header with AGN & Flag -->
                                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800/80 pb-2.5 flex-wrap gap-2">
                                            <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                Question {{ $agn }}
                                            </span>
                                            <button type="button" id="btn-flag-{{ $question->id }}" onclick="toggleFlag('{{ $question->id }}', {{ $globalQIdx }}, this)"
                                                    class="btn-flag text-xs font-semibold px-3 py-1 rounded {{ $isFlagged ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }} transition-colors">
                                                🚩 Flag Question
                                            </button>
                                        </div>

                                        <!-- Stem Prompt (may be blank in Part 6 blanks) -->
                                        @if(!empty($question->prompt))
                                            <div class="text-base font-semibold text-slate-900 dark:text-white leading-snug">
                                                {!! e($question->prompt) !!}
                                            </div>
                                        @endif

                                        <!-- Choices Options -->
                                        <div class="space-y-3 pt-1">
                                            @foreach ($question->choices as $choice)
                                                @php
                                                    $isChecked = $existingAnswer && $existingAnswer->selected_choice_id === $choice->id;
                                                @endphp
                                                <label class="flex items-center p-3.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/50 cursor-pointer transition-colors shadow-sm">
                                                    <input type="radio" name="q_{{ $question->id }}" value="{{ $choice->id }}"
                                                           {{ $isChecked ? 'checked' : '' }}
                                                           onchange="autoSaveAnswer('{{ $question->id }}', '{{ $choice->id }}', {{ $globalQIdx }})"
                                                           aria-label="Option {{ $choice->label }}"
                                                           class="w-4 h-4 text-indigo-600 bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 focus:ring-0" />
                                                    <span class="ml-3 text-sm text-slate-800 dark:text-slate-200 font-medium">
                                                        <strong class="text-indigo-600 dark:text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Passage Group Navigation Action Bar -->
                        <div class="flex justify-between items-center pt-6 mt-8 border-t border-slate-200 dark:border-slate-800 flex-wrap gap-3">
                            @if(!$isRealTest)
                                @if($isFirstUnitOfSection && $section)
                                    <button type="button" onclick="showSectionIntro('{{ $section->id }}')"
                                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors">
                                        &larr; Section Directions
                                    </button>
                                @else
                                    <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex - 1 }})" {{ $unitIndex === 0 ? 'disabled' : '' }}
                                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-40 text-slate-700 dark:text-white transition-colors">
                                        &larr; Previous (P)
                                    </button>
                                @endif
                            @else
                                <div></div>
                            @endif

                            @if($isLastUnitOfSection && $nextSectionId)
                                <button type="button" onclick="handleNextClick({{ $unitIndex }}, 'section', '{{ $nextSectionId }}')"
                                        class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 cursor-pointer">
                                    Next Section &rarr;
                                </button>
                            @elseif($unitIndex === $totalUnitsCount - 1)
                                <div id="final-unit-action-container-passage-{{ $unitIndex }}" class="flex items-center gap-2">
                                    <button type="button" id="btn-review-unanswered-passage-{{ $unitIndex }}" onclick="reviewFirstUnanswered()"
                                            class="btn-review-unanswered {{ $isAllInitiallyAnswered ? 'hidden' : 'inline-flex' }} px-5 py-2.5 text-xs font-bold rounded-xl bg-amber-600 hover:bg-amber-500 text-white transition-all shadow-md shadow-amber-600/30 items-center gap-1.5 cursor-pointer">
                                        <span>Review Unanswered (<span class="unanswered-count-text">{{ $totalQuestionsCount - count($answeredQuestionIds) }}</span>)</span>
                                    </button>
                                    <button type="button" id="btn-final-review-submit-passage-{{ $unitIndex }}" onclick="triggerFinalSubmitModal()"
                                            class="btn-final-review-submit {{ $isAllInitiallyAnswered ? 'inline-flex' : 'hidden' }} px-5 py-2.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition-all shadow-md shadow-emerald-600/30 items-center gap-1.5 cursor-pointer">
                                        <span>Final Review &amp; Submit &rarr;</span>
                                    </button>
                                </div>
                            @else
                                <button type="button" onclick="handleNextClick({{ $unitIndex }}, 'unit', {{ $unitIndex + 1 }})"
                                        class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 cursor-pointer">
                                    Next (N) &rarr;
                                </button>
                            @endif
                        </div>

                    @else
                        <!-- ========================================================================= -->
                        <!-- STANDALONE QUESTION DELIVERY UNIT (Part 1, Part 2, Part 5, or generic)    -->
                        <!-- ========================================================================= -->
                        @php
                            $question = $unit['questions']->first();
                            $globalQIdx = $unit['first_question_index'];
                            $agn = $unit['canonical_question_numbers'][0] ?? ($globalQIdx + 1);
                            $existingAnswer = $existingAnswers->get($question->id);
                            $isToeic = \App\Services\ToeicQuestionValidator::isToeic($attempt->test) || \App\Services\ToeicQuestionValidator::isToeic($question);
                            $partNum = (int) ($question->part_number ?? 0);
                            $isLetterOnly = $isToeic && in_array($partNum, [1, 2], true);
                            $isAudioPlayed = $playedAudioSet->contains($question->id);

                            $renderChoices = $question->choices;
                            if ($isToeic && $partNum === 2) {
                                $renderChoices = $question->choices->filter(function($c) {
                                    return in_array(strtoupper((string) $c->label), ['A', 'B', 'C'], true);
                                })->take(3);
                            }

                            $imgMedia = $question->getEffectiveImageMedia();
                            $qImageUrl = $imgMedia ? route('media.preview', $imgMedia->id) : ($question->getEffectiveImageUrl() ?: null);
                            $audMedia = $question->getEffectiveAudioMedia();
                            $hasAudioSource = !empty($audMedia) || !empty($question->audio_media_asset_id) || !empty($question->audio_url);
                            $audioStreamUrl = route('candidate.exam.audio-stream', [$attempt, $question]);
                        @endphp

                        <div id="unit-question-block-{{ $globalQIdx }}" data-part-number="{{ $partNum }}">
                            <!-- Header with AGN, Breadcrumb & Flag -->
                            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-4 flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Question {{ $agn }} of {{ $totalQuestionsCount }}
                                    </span>
                                    @if($section && !$isRealTest)
                                        <span class="text-slate-400 dark:text-slate-600">•</span>
                                        <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline underline-offset-2 transition-colors">
                                            {{ $section->title }} (View Directions)
                                        </button>
                                    @elseif($section)
                                        <span class="text-slate-400 dark:text-slate-600">•</span>
                                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ $section->title }}</span>
                                    @endif
                                </div>
                                @php
                                    $isFlagged = in_array($question->id, $flaggedQuestionIds, true);
                                @endphp
                                <button type="button" id="btn-flag-{{ $question->id }}" onclick="toggleFlag('{{ $question->id }}', {{ $globalQIdx }}, this)"
                                        class="btn-flag text-xs font-semibold px-3 py-1 rounded {{ $isFlagged ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300' }} transition-colors">
                                    🚩 Flag Question
                                </button>
                            </div>

                            <!-- Standard Layout Media Attachment -->
                            @if (!empty($qImageUrl))
                                <div class="mb-5 text-center group relative">
                                    <img src="{{ $qImageUrl }}"
                                         alt="Question Attachment"
                                         data-stimulus-zoomable="true"
                                         class="stimulus-zoomable max-h-72 max-w-full rounded-xl mx-auto border border-slate-200 dark:border-slate-800 shadow-md object-contain cursor-zoom-in hover:opacity-95 transition-all"
                                         onclick="openStimulusLightbox(this.src, this.alt)">
                                    <div class="mt-1.5 text-center">
                                        <button type="button"
                                                onclick="openStimulusLightbox('{{ $qImageUrl }}', 'Question Attachment')"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                                            <span>🔍</span>
                                            <span>Click image to enlarge</span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            @if ($hasAudioSource)
                                @if ($isRealTest)
                                    <div id="audio-container-{{ $question->id }}" class="mb-5 p-4 rounded-xl bg-slate-50 dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200">
                                        <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                            <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                <span>🎧</span>
                                                <span>Question Audio Prompt (Single Play)</span>
                                            </div>
                                            <span id="audio-badge-{{ $question->id }}" class="text-[11px] font-bold {{ $isAudioPlayed ? 'text-slate-500 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-slate-800' : 'text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-950/40 border-amber-300 dark:border-amber-500/30' }} px-2.5 py-0.5 rounded-md border">
                                                {{ $isAudioPlayed ? 'Audio Played (1/1)' : 'Play Available (1/1)' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button id="btn-play-{{ $question->id }}" type="button"
                                                    onclick="playRealTestSingleAudio('{{ $question->id }}', '{{ $audioStreamUrl }}')"
                                                    {{ $isAudioPlayed ? 'disabled' : '' }}
                                                    class="px-4 py-2 rounded-xl {{ $isAudioPlayed ? 'bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/30' }} font-bold text-xs transition-all flex items-center gap-2">
                                                <span>▶</span>
                                                <span id="btn-play-label-{{ $question->id }}">{{ $isAudioPlayed ? 'Already Played' : 'Play Audio Prompt' }}</span>
                                            </button>
                                            <audio id="audio-elem-{{ $question->id }}" data-exam-audio="true" class="hidden" preload="none" onended="onSingleAudioEnded('{{ $question->id }}')"></audio>
                                        </div>
                                    </div>
                                @else
                                    <div class="mb-5 p-4 rounded-xl bg-slate-50 dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200">
                                        <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                            <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                <span>🎧</span>
                                                <span>Question Audio Prompt</span>
                                            </div>
                                        </div>
                                        <audio controls controlsList="nodownload noplaybackrate" data-exam-audio="true" class="w-full" src="{{ $audioStreamUrl }}" preload="metadata"></audio>
                                    </div>
                                @endif
                            @endif

                            <!-- Prompt -->
                            @if(!empty($question->prompt))
                                <div class="text-base font-semibold text-slate-900 dark:text-white mb-6 leading-snug">
                                    {!! e($question->prompt) !!}
                                </div>
                            @endif

                            <!-- Choices Options -->
                            <div class="space-y-3">
                                @foreach ($renderChoices as $choice)
                                    @php
                                        $isChecked = $existingAnswer && $existingAnswer->selected_choice_id === $choice->id;
                                    @endphp
                                    <label class="flex items-center p-3.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/50 cursor-pointer transition-colors shadow-sm">
                                        <input type="radio" name="q_{{ $question->id }}" value="{{ $choice->id }}"
                                               {{ $isChecked ? 'checked' : '' }}
                                               onchange="autoSaveAnswer('{{ $question->id }}', '{{ $choice->id }}', {{ $globalQIdx }})"
                                               aria-label="Option {{ $choice->label }}"
                                               class="w-4 h-4 text-indigo-600 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 focus:ring-0" />
                                        @if ($isLetterOnly)
                                            <span class="ml-3 text-sm text-slate-800 dark:text-slate-200 font-bold">
                                                <strong class="text-indigo-600 dark:text-indigo-400">({{ $choice->label }})</strong>
                                            </span>
                                        @else
                                            <span class="ml-3 text-sm text-slate-800 dark:text-slate-200 font-medium">
                                                <strong class="text-indigo-600 dark:text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                            </span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>

                            <!-- Navigation Controls -->
                            <div class="flex justify-between items-center pt-6 mt-6 border-t border-slate-200 dark:border-slate-800 flex-wrap gap-3">
                                @if (!$isRealTest)
                                    @if($isFirstUnitOfSection && $section)
                                        <button type="button" onclick="showSectionIntro('{{ $section->id }}')"
                                                class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors">
                                            &larr; Section Directions
                                        </button>
                                    @else
                                        <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex - 1 }})" {{ $unitIndex === 0 ? 'disabled' : '' }}
                                                class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-40 text-slate-700 dark:text-white transition-colors">
                                            &larr; Previous (P)
                                        </button>
                                    @endif
                                @else
                                    <div></div>
                                @endif

                                @if($isLastUnitOfSection && $nextSectionId)
                                    <button type="button" onclick="handleNextClick({{ $unitIndex }}, 'section', '{{ $nextSectionId }}')"
                                            class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 cursor-pointer">
                                        Next Section &rarr;
                                    </button>
                                @elseif($unitIndex === $totalUnitsCount - 1)
                                    <div id="final-unit-action-container-single-{{ $unitIndex }}" class="flex items-center gap-2">
                                        <button type="button" id="btn-review-unanswered-single-{{ $unitIndex }}" onclick="reviewFirstUnanswered()"
                                                class="btn-review-unanswered {{ $isAllInitiallyAnswered ? 'hidden' : 'inline-flex' }} px-5 py-2.5 text-xs font-bold rounded-xl bg-amber-600 hover:bg-amber-500 text-white transition-all shadow-md shadow-amber-600/30 items-center gap-1.5 cursor-pointer">
                                            <span>Review Unanswered (<span class="unanswered-count-text">{{ $totalQuestionsCount - count($answeredQuestionIds) }}</span>)</span>
                                        </button>
                                        <button type="button" id="btn-final-review-submit-single-{{ $unitIndex }}" onclick="triggerFinalSubmitModal()"
                                                class="btn-final-review-submit {{ $isAllInitiallyAnswered ? 'inline-flex' : 'hidden' }} px-5 py-2.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition-all shadow-md shadow-emerald-600/30 items-center gap-1.5 cursor-pointer">
                                            <span>Final Review &amp; Submit &rarr;</span>
                                        </button>
                                    </div>
                                @else
                                    <button type="button" onclick="handleNextClick({{ $unitIndex }}, 'unit', {{ $unitIndex + 1 }})"
                                            class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-all shadow-md shadow-indigo-600/30 cursor-pointer">
                                        Next (N) &rarr;
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            @empty
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-8 text-center text-slate-500">
                    No questions assigned to this test.
                </div>
            @endforelse
        </div>

        <!-- Question Palette Sidebar (1 Col) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm h-fit">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Question Navigation</h3>
                <span id="answered-counter-badge" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                    {{ count($answeredQuestionIds) }}/{{ $totalQuestionsCount }}
                </span>
            </div>

            <div id="palette-grid" class="flex flex-wrap gap-1.5 text-xs font-bold">
                @foreach ($shuffledQuestions as $idx => $q)
                    @php
                        $agn = $q->canonical_global_number ?? ($idx + 1);
                        $isAns = $existingAnswers->has($q->id) && !is_null($existingAnswers->get($q->id)->selected_choice_id);
                    @endphp
                    <button id="palette-btn-{{ $idx }}" type="button" onclick="handlePaletteClick({{ $idx }})"
                            class="palette-btn px-2.5 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center gap-1 {{ $isAns ? 'border-emerald-500/40 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700' }}">
                        <span>{{ $agn }}</span>
                        <span id="palette-icon-{{ $idx }}" class="text-[10px]">{{ $isAns ? '✓' : '—' }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/80 text-[10px] text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span> Answered
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-slate-400 dark:text-slate-500 font-bold">—</span> Unanswered
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-amber-500 dark:text-amber-400 font-bold">⚑</span> Flagged
                </div>
            </div>

            <div class="mt-3 text-[10px] space-y-1 text-slate-400 dark:text-slate-500">
                @if(!$isRealTest)
                    <p><kbd class="px-1 py-0.2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded">N</kbd> Next • <kbd class="px-1 py-0.2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded">P</kbd> Previous • <kbd class="px-1 py-0.2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded">F</kbd> Flag</p>
                @else
                    <p><kbd class="px-1 py-0.2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded">N</kbd> Next • <kbd class="px-1 py-0.2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded">F</kbd> Flag</p>
                @endif
            </div>

            <div id="palette-completion-container" class="{{ $isAllInitiallyAnswered ? 'block' : 'hidden' }} mt-4 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" id="btn-palette-final-submit" onclick="triggerFinalSubmitModal()"
                        class="w-full py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition-all shadow-md shadow-emerald-600/30 flex items-center justify-center gap-1.5 cursor-pointer">
                    <span>✓ Final Review &amp; Submit</span>
                </button>
            </div>
        </div>
    </div>

    <!-- CBT Exam Runtime JavaScript Engine -->
    <script>
        const isRealTest = {{ $isRealTest ? 'true' : 'false' }};
        const serverRemainingSeconds = {{ $remainingSeconds }};
        const timerDeadlineMs = Date.now() + (serverRemainingSeconds * 1000);
        let remainingSeconds = serverRemainingSeconds;
        let expiryFlowTriggered = false;
        let currentUnitIdx = -1; // -1 represents section intro view, -3 represents passage format transition
        let pendingTransitionTargetUnitIdx = null;
        const totalQuestions = {{ $totalQuestionsCount }};
        const totalUnits = {{ $totalUnitsCount }};
        const deliveryUnitsMeta = @json($deliveryUnitsMeta);
        const questionIds = @json($shuffledQuestions->pluck('id'));
        const questionIndexToUnitIndex = @json($questionIndexToUnitIndex);
        const questionIdToUnitIndex = @json($questionIdToUnitIndex);
        const unitIndexToQuestionIndices = @json($unitIndexToQuestionIndices);
        const sectionFirstUnitIndex = @json($sectionFirstUnitIndex);
        const firstSectionId = "{{ isset($sections) && $sections->isNotEmpty() ? $sections->first()->id : '' }}";
        let timerInterval = null;
        const answeredQuestionIds = new Set(@json($answeredQuestionIds));
        const confirmedAnswerChoiceByQuestion = @json($existingChoiceMap);
        const pendingAnswerQuestionIds = new Set();
        const initialFlaggedQuestionIds = @json($flaggedQuestionIds);
        const flaggedQuestionIndices = new Set();
        initialFlaggedQuestionIds.forEach(qId => {
            const idx = questionIds.indexOf(qId);
            if (idx !== -1) {
                flaggedQuestionIndices.add(idx);
            }
        });

        function triggerExpiryFlow(reason = 'time_expired') {
            if (expiryFlowTriggered) return;
            expiryFlowTriggered = true;

            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }

            CandidateExamAudioManager.stopAll({ forceReset: true });

            document.querySelectorAll('input[type="radio"], button').forEach(el => {
                if (el.id !== 'btn-confirm-ok' && el.id !== 'btn-modal-ok') {
                    el.disabled = true;
                }
            });

            const timerEl = document.getElementById('countdown-timer');
            if (timerEl) {
                timerEl.innerText = "00:00:00";
            }

            iapAlert({
                title: 'Time Expired',
                message: 'The test time has ended. Your saved answers are being submitted.',
                okText: 'Submit Now',
                variant: 'warning',
                onOk: () => {
                    const form = document.getElementById('form-final-submit');
                    if (form) form.submit();
                }
            });

            setTimeout(() => {
                const form = document.getElementById('form-final-submit');
                if (form) form.submit();
            }, 2500);
        }

        function setQuestionRadiosDisabled(questionId, disabled) {
            document.querySelectorAll(`input[name="q_${questionId}"]`).forEach(input => {
                input.disabled = disabled;
            });
        }

        function updateFlagButtonUI(btn, isFlagged) {
            if (!btn) return;
            if (isFlagged) {
                btn.classList.remove('bg-slate-100', 'bg-slate-200', 'hover:bg-slate-200', 'hover:bg-slate-300', 'text-slate-700', 'dark:bg-slate-800', 'dark:hover:bg-slate-700', 'dark:text-slate-300');
                btn.classList.add('bg-amber-500/20', 'text-amber-600', 'dark:text-amber-400');
            } else {
                btn.classList.remove('bg-amber-500/20', 'text-amber-600', 'dark:text-amber-400');
                btn.classList.add('bg-slate-100', 'hover:bg-slate-200', 'dark:bg-slate-800', 'dark:hover:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
            }
        }

        function hasPendingSaveInCurrentUnit() {
            if (pendingAnswerQuestionIds.size === 0) return false;
            if (currentUnitIdx < 0) return pendingAnswerQuestionIds.size > 0;
            const currentUnitQIndices = unitIndexToQuestionIndices[currentUnitIdx] || [];
            return currentUnitQIndices.some(qIdx => pendingAnswerQuestionIds.has(questionIds[qIdx]));
        }

        // Universal Candidate Examination Audio Lifecycle Manager
        const CandidateExamAudioManager = (function() {
            let activeAudio = null;

            function getAllExamAudioElements() {
                return document.querySelectorAll('audio[data-exam-audio], audio');
            }

            function stopAll(options = {}) {
                const elements = getAllExamAudioElements();
                elements.forEach(audio => {
                    try {
                        if (!audio.paused) {
                            audio.pause();
                        }
                        if (!isRealTest || options.forceReset) {
                            audio.currentTime = 0;
                        }
                    } catch (err) {
                        console.error('CandidateExamAudioManager: error pausing audio', err);
                    }
                });
                activeAudio = null;
            }

            function handlePlayEvent(e) {
                if (e.target && e.target.tagName === 'AUDIO') {
                    const currentAudio = e.target;
                    activeAudio = currentAudio;
                    getAllExamAudioElements().forEach(audio => {
                        if (audio !== currentAudio && !audio.paused) {
                            try {
                                audio.pause();
                                if (!isRealTest) {
                                    audio.currentTime = 0;
                                }
                            } catch (err) {}
                        }
                    });
                }
            }

            function beforeDeliveryTransition(context = {}) {
                stopAll(context);
            }

            function init() {
                document.addEventListener('play', handlePlayEvent, true);
                window.addEventListener('beforeunload', () => stopAll());
                window.addEventListener('pagehide', () => stopAll());
            }

            init();

            return {
                stopAll: stopAll,
                getActiveAudio: () => activeAudio,
                beforeDeliveryTransition: beforeDeliveryTransition,
                getAllElements: getAllExamAudioElements
            };
        })();

        // Backwards-compatible global aliases
        function stopAllExamAudio(options = {}) {
            CandidateExamAudioManager.stopAll(options);
        }
        window.CandidateExamAudioManager = CandidateExamAudioManager;
        window.stopAllExamAudio = stopAllExamAudio;

        // Fullscreen Mode & Secure Session Handler for Real Test
        let secureSessionActive = false;
        let pendingTargetUnitIdx = null;
        let pendingTargetQIndex = null;

        async function enterFullscreen() {
            if (document.fullscreenElement) {
                return true;
            }
            if (!document.documentElement.requestFullscreen) {
                console.warn('Fullscreen API is not supported on this browser/device.');
                return false;
            }
            try {
                await document.documentElement.requestFullscreen();
                return true;
            } catch (err) {
                console.warn('Fullscreen request rejected/failed:', err);
                return false;
            }
        }

        async function returnToFullscreen() {
            const entered = await enterFullscreen();
            if (entered) {
                const overlay = document.getElementById('fullscreen-warning-overlay');
                if (overlay) overlay.classList.add('hidden');
                logViolation('fullscreen_enter');
            }
        }

        async function retryEnterFullscreenAndContinue() {
            const entered = await enterFullscreen();
            if (entered) {
                secureSessionActive = true;
                const reqOverlay = document.getElementById('fullscreen-required-overlay');
                if (reqOverlay) reqOverlay.classList.add('hidden');
                logViolation('fullscreen_enter');
                if (pendingTargetUnitIdx !== null) {
                    const targetU = pendingTargetUnitIdx;
                    const targetQ = pendingTargetQIndex;
                    pendingTargetUnitIdx = null;
                    pendingTargetQIndex = null;
                    renderDeliveryUnit(targetU, targetQ);
                }
            }
        }

        function showFullscreenRequiredOverlay() {
            const overlay = document.getElementById('fullscreen-required-overlay');
            if (overlay) overlay.classList.remove('hidden');
        }

        function hideFullscreenRequiredOverlay() {
            const overlay = document.getElementById('fullscreen-required-overlay');
            if (overlay) overlay.classList.add('hidden');
        }

        if (isRealTest) {
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) {
                    if (secureSessionActive) {
                        logViolation('fullscreen_exit');
                        const overlay = document.getElementById('fullscreen-warning-overlay');
                        if (overlay) overlay.classList.remove('hidden');
                    }
                } else {
                    const warnOverlay = document.getElementById('fullscreen-warning-overlay');
                    if (warnOverlay) warnOverlay.classList.add('hidden');
                    const reqOverlay = document.getElementById('fullscreen-required-overlay');
                    if (reqOverlay) reqOverlay.classList.add('hidden');
                }
            });
        }

        // Section Initiation (Fullscreen-gated for Real Test via navigateDeliveryUnit)
        async function startSectionQuestions(firstUnitIdx) {
            CandidateExamAudioManager.beforeDeliveryTransition({ type: 'start_section', targetUnit: firstUnitIdx });
            navigateDeliveryUnit(firstUnitIdx);
        }

        // Timer Countdown Engine (Wall-Clock Absolute Deadline Driven)
        function updateTimerDisplay() {
            if (expiryFlowTriggered) return;

            remainingSeconds = Math.max(0, Math.ceil((timerDeadlineMs - Date.now()) / 1000));

            if (remainingSeconds <= 0) {
                triggerExpiryFlow('time_expired');
                return;
            }

            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            const timerEl = document.getElementById('countdown-timer');
            if (timerEl) {
                timerEl.innerText = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }

        timerInterval = setInterval(updateTimerDisplay, 1000);
        updateTimerDisplay();

        // Exit Simulator Confirmation
        function confirmExitSimulator() {
            if (hasPendingSaveInCurrentUnit()) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            CandidateExamAudioManager.beforeDeliveryTransition({ type: 'exit_simulator' });
            iapConfirm({
                title: 'Return to Dashboard?',
                message: 'Your saved Simulator progress will be preserved. You can resume the test later.',
                confirmText: 'Back to Dashboard',
                cancelText: 'Stay in Test',
                variant: 'info',
                onConfirm: () => {
                    CandidateExamAudioManager.beforeDeliveryTransition({ type: 'exit_simulator_confirmed' });
                    window.location.href = "{{ route('candidate.portal') }}";
                }
            });
        }

        // Trigger Final Submit Confirmation Modal
        function triggerFinalSubmitModal() {
            if (pendingAnswerQuestionIds.size > 0) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            CandidateExamAudioManager.beforeDeliveryTransition({ type: 'final_submit' });
            if (answeredQuestionIds.size < totalQuestions) {
                const unansweredCount = totalQuestions - answeredQuestionIds.size;
                iapAlert({
                    title: 'Unanswered Questions Remaining',
                    message: `You still have ${unansweredCount} unanswered question(s). Please answer all questions before submitting.`,
                    variant: 'warning',
                    okText: 'Review Questions',
                    onOk: () => reviewFirstUnanswered()
                });
                return;
            }

            iapConfirm({
                title: 'Final Submit Assessment?',
                message: `You have answered all ${totalQuestions} questions. Once submitted, your answers can no longer be changed. Are you sure you want to finalize and submit?`,
                confirmText: 'Submit Assessment',
                cancelText: 'Cancel',
                variant: 'success',
                form: document.getElementById('form-final-submit')
            });
        }

        // Review First Unanswered Question
        function reviewFirstUnanswered() {
            for (let i = 0; i < totalQuestions; i++) {
                const qId = questionIds[i];
                if (!answeredQuestionIds.has(qId)) {
                    const targetUnitIdx = questionIndexToUnitIndex[i];
                    if (targetUnitIdx !== undefined) {
                        navigateDeliveryUnit(targetUnitIdx, i);
                        return;
                    }
                }
            }
        }

        // Final Submit & Palette Completion State Updater
        function updateFinalSubmitState() {
            const isAllAnswered = (answeredQuestionIds.size === totalQuestions && totalQuestions > 0);
            const unansweredCount = Math.max(0, totalQuestions - answeredQuestionIds.size);

            const paletteContainer = document.getElementById('palette-completion-container');
            if (paletteContainer) {
                if (isAllAnswered) {
                    paletteContainer.classList.remove('hidden');
                    paletteContainer.classList.add('block');
                } else {
                    paletteContainer.classList.add('hidden');
                    paletteContainer.classList.remove('block');
                }
            }

            document.querySelectorAll('.btn-review-unanswered').forEach(btn => {
                if (isAllAnswered) {
                    btn.classList.add('hidden');
                    btn.classList.remove('inline-flex');
                } else {
                    btn.classList.remove('hidden');
                    btn.classList.add('inline-flex');
                }
            });

            document.querySelectorAll('.unanswered-count-text').forEach(el => {
                el.textContent = unansweredCount;
            });

            document.querySelectorAll('.btn-final-review-submit').forEach(btn => {
                if (isAllAnswered) {
                    btn.classList.remove('hidden');
                    btn.classList.add('inline-flex');
                } else {
                    btn.classList.add('hidden');
                    btn.classList.remove('inline-flex');
                }
            });
        }

        // Palette State & Highlighting Engine
        function updatePaletteUI() {
            const currentUnitQIndices = (currentUnitIdx >= 0 && unitIndexToQuestionIndices[currentUnitIdx])
                ? new Set(unitIndexToQuestionIndices[currentUnitIdx])
                : new Set();

            document.querySelectorAll('.palette-btn').forEach((btn, idx) => {
                const icon = document.getElementById(`palette-icon-${idx}`);
                const qId = questionIds[idx];
                const isAnswered = answeredQuestionIds.has(qId);
                const isFlagged = flaggedQuestionIndices.has(idx);
                const isCurrentUnit = currentUnitQIndices.has(idx);

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
                if (isCurrentUnit) {
                    btn.className += 'ring-2 ring-indigo-500 border-indigo-500 bg-indigo-50 dark:bg-indigo-950/80 text-indigo-700 dark:text-white shadow-sm';
                } else if (isFlagged) {
                    btn.className += 'border-amber-500/40 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300';
                } else if (isAnswered) {
                    btn.className += 'border-emerald-500/40 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300';
                } else {
                    btn.className += 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700';
                }
            });

            const counter = document.getElementById('answered-counter-badge');
            if (counter) {
                counter.textContent = `${answeredQuestionIds.size}/${totalQuestions}`;
            }

            updateFinalSubmitState();
        }

        function formatPassageTypeLabel(type) {
            if (type === 'double') return 'Double Passage';
            if (type === 'triple') return 'Triple Passage';
            if (type === 'single') return 'Single Passage';
            if (!type) return 'Passage';
            return type.charAt(0).toUpperCase() + type.slice(1) + ' Passage';
        }

        function getPassageTypeDocumentCount(unit) {
            if (unit && unit.documents_count && unit.documents_count > 0) {
                return unit.documents_count === 1 ? '1 document' : `${unit.documents_count} related documents`;
            }
            if (unit && unit.passage_type === 'triple') return '3 related documents';
            if (unit && unit.passage_type === 'double') return '2 related documents';
            return '1 document';
        }

        function getPassageTypeDescription(unit) {
            if (!unit) return 'You will read the documents and answer the questions that follow.';
            if (unit.passage_type === 'triple' || unit.documents_count === 3) {
                return 'You will read 3 related documents and answer the questions that follow.';
            }
            if (unit.passage_type === 'double' || unit.documents_count === 2) {
                return 'You will read 2 related documents and answer the questions that follow.';
            }
            if (unit.passage_type === 'single' || unit.documents_count === 1) {
                return 'You will read 1 document and answer the questions that follow.';
            }
            return 'You will read the documents and answer the questions that follow.';
        }

        function shouldShowPassageTypeTransition(fromIdx, toIdx) {
            if (fromIdx < 0 || fromIdx >= totalUnits || toIdx < 0 || toIdx >= totalUnits) return false;
            const fromUnit = deliveryUnitsMeta[fromIdx];
            const toUnit = deliveryUnitsMeta[toIdx];
            if (!fromUnit || !toUnit) return false;

            return fromUnit.type === 'passage_group' &&
                   fromUnit.part_number === 7 &&
                   toUnit.type === 'passage_group' &&
                   toUnit.part_number === 7 &&
                   Boolean(fromUnit.passage_type) &&
                   Boolean(toUnit.passage_type) &&
                   fromUnit.passage_type !== toUnit.passage_type;
        }

        function showPassageTypeTransition(fromUnitIdx, toUnitIdx) {
            if (hasPendingSaveInCurrentUnit()) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            CandidateExamAudioManager.beforeDeliveryTransition({
                type: 'passage_type_transition',
                fromUnit: fromUnitIdx,
                toUnit: toUnitIdx
            });
            document.querySelectorAll('.delivery-unit-card, .section-intro-card, .passage-type-transition-card').forEach(card => card.classList.add('hidden'));
            pendingTransitionTargetUnitIdx = toUnitIdx;
            currentUnitIdx = -3;

            const fromUnit = deliveryUnitsMeta[fromUnitIdx] || {};
            const toUnit = deliveryUnitsMeta[toUnitIdx] || {};

            const fromTypeEl = document.getElementById('transition-from-type');
            if (fromTypeEl) fromTypeEl.textContent = formatPassageTypeLabel(fromUnit.passage_type);

            const toTypeEl = document.getElementById('transition-to-type');
            if (toTypeEl) toTypeEl.textContent = formatPassageTypeLabel(toUnit.passage_type);

            const docCountEl = document.getElementById('transition-doc-count');
            if (docCountEl) docCountEl.textContent = getPassageTypeDocumentCount(toUnit);

            const descEl = document.getElementById('transition-description');
            if (descEl) descEl.textContent = getPassageTypeDescription(toUnit);

            const qRangeEl = document.getElementById('transition-question-range');
            const qRangeContainer = document.getElementById('transition-question-range-container');
            if (toUnit.display_question_range) {
                if (qRangeEl) qRangeEl.textContent = toUnit.display_question_range;
                if (qRangeContainer) qRangeContainer.classList.remove('hidden');
            } else if (qRangeContainer) {
                qRangeContainer.classList.add('hidden');
            }

            const ctaLabelEl = document.getElementById('transition-cta-label');
            if (ctaLabelEl) {
                if (toUnit.passage_type === 'double') {
                    ctaLabelEl.textContent = 'Begin Double Passage';
                } else if (toUnit.passage_type === 'triple') {
                    ctaLabelEl.textContent = 'Begin Triple Passage';
                } else if (toUnit.passage_type === 'single') {
                    ctaLabelEl.textContent = 'Begin Single Passage';
                } else {
                    ctaLabelEl.textContent = 'Continue to Next Passage';
                }
            }

            const card = document.getElementById('passage-type-transition-card');
            if (card) {
                card.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            const ctaBtn = document.getElementById('btn-begin-transition-passage');
            if (ctaBtn) {
                setTimeout(() => ctaBtn.focus(), 50);
            }

            updatePaletteUI();
        }

        function proceedPassageTypeTransition() {
            if (pendingTransitionTargetUnitIdx !== null) {
                const target = pendingTransitionTargetUnitIdx;
                pendingTransitionTargetUnitIdx = null;
                navigateDeliveryUnit(target);
            }
        }

        // Show Dedicated Section Introduction Screen
        function showSectionIntro(sectionId) {
            if (!sectionId) return;
            if (hasPendingSaveInCurrentUnit()) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            CandidateExamAudioManager.beforeDeliveryTransition({ type: 'section_intro', targetSection: sectionId });
            document.querySelectorAll('.delivery-unit-card, .section-intro-card, .passage-type-transition-card').forEach(card => card.classList.add('hidden'));
            const targetSection = document.getElementById('section-intro-card-' + sectionId);
            if (targetSection) {
                targetSection.classList.remove('hidden');
                currentUnitIdx = -1;
                window.location.hash = 'section=' + sectionId;
                updatePaletteUI();
            }
        }

        // Render Delivery Unit to DOM (Ungated rendering logic)
        function renderDeliveryUnit(unitIdx, targetQIndex = null) {
            if (unitIdx < 0 || unitIdx >= totalUnits) return;
            document.querySelectorAll('.delivery-unit-card, .section-intro-card, .passage-type-transition-card').forEach(card => card.classList.add('hidden'));
            pendingTransitionTargetUnitIdx = null;

            const targetCard = document.getElementById(`delivery-unit-card-${unitIdx}`);
            if (targetCard) {
                targetCard.classList.remove('hidden');
                if (targetQIndex !== null) {
                    const targetEl = document.getElementById(`unit-question-block-${targetQIndex}`);
                    if (targetEl) {
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        targetEl.classList.add('ring-2', 'ring-indigo-500');
                        setTimeout(() => targetEl.classList.remove('ring-2', 'ring-indigo-500'), 1500);
                    }
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            currentUnitIdx = unitIdx;
            const firstQInUnit = unitIndexToQuestionIndices[unitIdx]?.[0] ?? 0;
            window.location.hash = 'q=' + (targetQIndex !== null ? targetQIndex : firstQInUnit);
            updatePaletteUI();
        }

        // Navigate to Delivery Unit (Group Page or Single Question Page) - Secure Gate in Real Test Mode
        function navigateDeliveryUnit(unitIdx, targetQIndex = null) {
            if (unitIdx < 0 || unitIdx >= totalUnits) return;
            if (currentUnitIdx >= 0 && unitIdx !== currentUnitIdx && hasPendingSaveInCurrentUnit()) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            CandidateExamAudioManager.beforeDeliveryTransition({ type: 'delivery_unit', targetUnit: unitIdx, targetQ: targetQIndex });
            if (isRealTest) {
                if (!secureSessionActive) {
                    pendingTargetUnitIdx = unitIdx;
                    pendingTargetQIndex = targetQIndex;
                    enterFullscreen().then(entered => {
                        if (!entered) {
                            showFullscreenRequiredOverlay();
                            return;
                        }
                        secureSessionActive = true;
                        hideFullscreenRequiredOverlay();
                        logViolation('fullscreen_enter');
                        renderDeliveryUnit(unitIdx, targetQIndex);
                    });
                    return;
                } else if (!document.fullscreenElement) {
                    pendingTargetUnitIdx = unitIdx;
                    pendingTargetQIndex = targetQIndex;
                    const warnOverlay = document.getElementById('fullscreen-warning-overlay');
                    if (warnOverlay) warnOverlay.classList.remove('hidden');
                    return;
                }
            }
            renderDeliveryUnit(unitIdx, targetQIndex);
        }

        // Quick-Jump to Passage Document in Passage Group Unit (Multi-Document Part 7 Double/Triple Passages)
        function switchPassageDocUnit(unitIndex, docIndex) {
            const targetDoc = document.getElementById(`passage-doc-unit-${unitIndex}-${docIndex}`);
            const scrollContainer = document.getElementById(`passage-scroll-unit-${unitIndex}`);
            if (scrollContainer && targetDoc) {
                const containerRect = scrollContainer.getBoundingClientRect();
                const docRect = targetDoc.getBoundingClientRect();
                scrollContainer.scrollTo({
                    top: scrollContainer.scrollTop + (docRect.top - containerRect.top) - 8,
                    behavior: 'smooth'
                });
            } else if (targetDoc) {
                targetDoc.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            const tabs = document.querySelectorAll(`[id^="passage-tab-unit-${unitIndex}-"]`);
            tabs.forEach((tab, idx) => {
                if (idx === docIndex) {
                    tab.className = 'passage-doc-tab text-[11px] font-bold px-2.5 py-1 rounded-lg border transition-all bg-indigo-600 text-white border-indigo-500 shadow-sm';
                } else {
                    tab.className = 'passage-doc-tab text-[11px] font-bold px-2.5 py-1 rounded-lg border transition-all bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700';
                }
            });
        }

        // Mode-Aware Next Action Handler
        function handleNextClick(currentUnit, type, target) {
            if (hasPendingSaveInCurrentUnit()) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            if (isRealTest) {
                // Real Test / Mock Test: All child questions in current group must be answered before forward navigation
                const unitQIndices = unitIndexToQuestionIndices[currentUnit] || [];
                const unansweredQIdx = unitQIndices.find(qIdx => !answeredQuestionIds.has(questionIds[qIdx]));

                if (unansweredQIdx !== undefined) {
                    iapAlert({
                        title: 'Answer Required',
                        message: 'For Mock Test examination, you must select an answer for all questions in this group before proceeding.',
                        variant: 'warning',
                        okText: 'Understood'
                    });
                    return;
                }
            }

            if (type === 'section') {
                showSectionIntro(target);
            } else {
                if (target < totalUnits) {
                    if (shouldShowPassageTypeTransition(currentUnit, target)) {
                        showPassageTypeTransition(currentUnit, target);
                    } else {
                        navigateDeliveryUnit(target);
                    }
                } else if (!isRealTest && answeredQuestionIds.size < totalQuestions) {
                    // Simulator Mode: Loop back to first unanswered question
                    for (let i = 0; i < totalQuestions; i++) {
                        if (!answeredQuestionIds.has(questionIds[i])) {
                            const uIdx = questionIndexToUnitIndex[i];
                            iapAlert({
                                title: 'Review Unanswered Questions',
                                message: 'You have reached the end of the simulation. Navigating to your first unanswered question.',
                                variant: 'info',
                                okText: 'Continue',
                                onOk: () => navigateDeliveryUnit(uIdx, i)
                            });
                            return;
                        }
                    }
                }
            }
        }

        // Mode-Aware Palette Click Handler
        function handlePaletteClick(targetQIdx) {
            if (hasPendingSaveInCurrentUnit()) {
                iapAlert({
                    title: 'Saving Answer',
                    message: 'Saving your answer. Please wait.',
                    variant: 'info',
                    okText: 'Understood'
                });
                return;
            }
            const targetUnitIdx = questionIndexToUnitIndex[targetQIdx];

            if (isRealTest) {
                if (currentUnitIdx === -1) {
                    iapAlert({
                        title: 'Begin Section Required',
                        message: 'Please begin the section to start the assessment in secure fullscreen mode.',
                        variant: 'info',
                        okText: 'Understood'
                    });
                    return;
                }

                if (currentUnitIdx >= 0) {
                    // Mock Test: cannot jump forward over unanswered questions in current group
                    if (targetUnitIdx > currentUnitIdx) {
                        const currentUnitQIndices = unitIndexToQuestionIndices[currentUnitIdx] || [];
                        const hasUnansweredInCurrent = currentUnitQIndices.some(qIdx => !answeredQuestionIds.has(questionIds[qIdx]));

                        if (hasUnansweredInCurrent) {
                            iapAlert({
                                title: 'Answer Current Group First',
                                message: 'You must answer all questions in the current group before moving forward in Mock Test mode.',
                                variant: 'warning',
                                okText: 'Understood'
                            });
                            return;
                        }
                    }
                }
            }

            if (targetUnitIdx !== undefined) {
                navigateDeliveryUnit(targetUnitIdx, targetQIdx);
            }
        }

        // Real Test Group Audio Play Engine
        function playRealTestUnitAudio(unitIndex, audioGroupId, streamUrl) {
            const btn = document.getElementById(`btn-play-unit-${unitIndex}`);
            const label = document.getElementById(`btn-play-label-unit-${unitIndex}`);
            const badge = document.getElementById(`audio-badge-unit-${unitIndex}`);
            const audio = document.getElementById(`audio-unit-elem-${unitIndex}`);

            if (!audio || (btn && btn.disabled)) return;

            CandidateExamAudioManager.stopAll();

            if (btn) {
                btn.disabled = true;
                btn.className = 'px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed font-bold text-xs transition-all flex items-center gap-2';
            }
            if (label) label.textContent = 'Playing Audio...';
            if (badge) {
                badge.textContent = 'Audio Playing (1/1)';
                badge.className = 'text-[11px] font-bold text-indigo-700 bg-indigo-50 dark:text-indigo-400 dark:bg-indigo-950/40 border-indigo-300 dark:border-indigo-500/30 px-2.5 py-0.5 rounded-md border';
            }

            audio.src = streamUrl;
            audio.play().catch(err => {
                console.error('Audio playback error:', err);
                if (label) label.textContent = 'Playback Failed';
            });
        }

        function onUnitAudioEnded(unitIndex, audioGroupId, primaryQId) {
            const label = document.getElementById(`btn-play-label-unit-${unitIndex}`);
            const badge = document.getElementById(`audio-badge-unit-${unitIndex}`);

            if (label) label.textContent = 'Already Played';
            if (badge) {
                badge.textContent = 'Audio Played (1/1)';
                badge.className = 'text-[11px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-slate-800 px-2.5 py-0.5 rounded-md border';
            }

            fetch("{{ route('candidate.exam.violation', $attempt) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ violation_type: 'audio_completed', question_id: primaryQId })
            });
        }

        // Real Test Single Audio for Standalone Questions
        function playRealTestSingleAudio(questionId, streamUrl) {
            const btn = document.getElementById(`btn-play-${questionId}`);
            const label = document.getElementById(`btn-play-label-${questionId}`);
            const badge = document.getElementById(`audio-badge-${questionId}`);
            const audio = document.getElementById(`audio-elem-${questionId}`);

            if (!audio || (btn && btn.disabled)) return;

            CandidateExamAudioManager.stopAll();

            if (btn) {
                btn.disabled = true;
                btn.className = 'px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed font-bold text-xs transition-all flex items-center gap-2';
            }
            if (label) label.textContent = 'Playing Audio...';
            if (badge) {
                badge.textContent = 'Audio Playing (1/1)';
                badge.className = 'text-[11px] font-bold text-indigo-700 bg-indigo-50 dark:text-indigo-400 dark:bg-indigo-950/40 border-indigo-300 dark:border-indigo-500/30 px-2.5 py-0.5 rounded-md border';
            }

            audio.src = streamUrl;
            audio.play().catch(err => {
                console.error('Audio playback error:', err);
                if (label) label.textContent = 'Playback Failed';
            });
        }

        function onSingleAudioEnded(questionId) {
            const label = document.getElementById(`btn-play-label-${questionId}`);
            const badge = document.getElementById(`audio-badge-${questionId}`);

            if (label) label.textContent = 'Already Played';
            if (badge) {
                badge.textContent = 'Audio Played (1/1)';
                badge.className = 'text-[11px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-slate-800 px-2.5 py-0.5 rounded-md border';
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

        // Auto Save Answer AJAX with Server-Acknowledged Persistence & State Synchronization
        async function autoSaveAnswer(questionId, choiceId, index) {
            if (expiryFlowTriggered) return;

            pendingAnswerQuestionIds.add(questionId);
            setQuestionRadiosDisabled(questionId, true);

            try {
                const response = await fetch("{{ route('candidate.exam.autosave', $attempt) }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ question_id: questionId, selected_choice: choiceId })
                });

                const data = await response.json().catch(() => null);

                if (response.ok && data && data.status === 'saved') {
                    answeredQuestionIds.add(questionId);
                    confirmedAnswerChoiceByQuestion[questionId] = choiceId;
                    updatePaletteUI();
                    return;
                }

                if (data && data.error_code === 'ATTEMPT_EXPIRED') {
                    handleAutoSaveFailure(questionId, true);
                    triggerExpiryFlow('attempt_expired');
                    return;
                }

                // If non-2xx or status != 'saved', handle rollback
                handleAutoSaveFailure(questionId, false);
            } catch (err) {
                console.error('Autosave error:', err);
                handleAutoSaveFailure(questionId, false);
            } finally {
                pendingAnswerQuestionIds.delete(questionId);
                if (!expiryFlowTriggered) {
                    setQuestionRadiosDisabled(questionId, false);
                }
            }
        }

        function handleAutoSaveFailure(questionId, isExpired = false) {
            const prevChoiceId = confirmedAnswerChoiceByQuestion[questionId];
            if (prevChoiceId) {
                const prevInput = document.querySelector(`input[name="q_${questionId}"][value="${prevChoiceId}"]`);
                if (prevInput) {
                    prevInput.checked = true;
                }
                answeredQuestionIds.add(questionId);
            } else {
                document.querySelectorAll(`input[name="q_${questionId}"]`).forEach(input => {
                    input.checked = false;
                });
                answeredQuestionIds.delete(questionId);
            }
            updatePaletteUI();

            if (!isExpired) {
                iapAlert({
                    title: 'Save Failed',
                    message: 'Answer could not be saved. Please select your answer again.',
                    variant: 'danger',
                    okText: 'Understood'
                });
            }
        }

        // Toggle Flag AJAX with Server Acknowledgement & State Synchronization
        async function toggleFlag(questionId, index, btn) {
            if (expiryFlowTriggered) return;

            const targetBtn = btn || document.getElementById('btn-flag-' + questionId);
            if (targetBtn) {
                targetBtn.disabled = true;
            }

            try {
                const response = await fetch("{{ route('candidate.exam.flag', $attempt) }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ question_id: questionId })
                });

                const data = await response.json().catch(() => null);

                if (response.ok && data && data.status === 'saved') {
                    const isFlagged = Boolean(data.flagged);
                    if (isFlagged) {
                        flaggedQuestionIndices.add(index);
                    } else {
                        flaggedQuestionIndices.delete(index);
                    }
                    updateFlagButtonUI(targetBtn, isFlagged);
                    updatePaletteUI();
                    return;
                }

                if (data && data.error_code === 'ATTEMPT_EXPIRED') {
                    triggerExpiryFlow('attempt_expired');
                    return;
                }

                iapAlert({
                    title: 'Flag Update Failed',
                    message: 'Flag state could not be updated. Please try again.',
                    variant: 'danger',
                    okText: 'Understood'
                });
            } catch (err) {
                console.error('Flag toggle error:', err);
                iapAlert({
                    title: 'Flag Update Failed',
                    message: 'Flag state could not be updated. Please try again.',
                    variant: 'danger',
                    okText: 'Understood'
                });
            } finally {
                if (targetBtn && !expiryFlowTriggered) {
                    targetBtn.disabled = false;
                }
            }
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

        // =========================================================================
        // REUSABLE CANDIDATE STIMULUS IMAGE LIGHTBOX / ZOOM VIEWER
        // =========================================================================
        let stimulusLightboxLastFocus = null;

        function openStimulusLightbox(src, caption) {
            if (!src) return;
            const modal = document.getElementById('stimulus-lightbox-modal');
            const img = document.getElementById('stimulus-lightbox-image');
            const cap = document.getElementById('stimulus-lightbox-caption');
            const closeBtn = document.getElementById('stimulus-lightbox-close-btn');

            if (!modal || !img) return;

            stimulusLightboxLastFocus = document.activeElement;
            img.src = src;
            img.alt = caption || 'Enlarged Stimulus';
            if (cap) {
                cap.textContent = caption || 'Stimulus Document';
            }

            modal.classList.remove('hidden');
            if (closeBtn) {
                closeBtn.focus();
            }
        }

        function closeStimulusLightbox() {
            const modal = document.getElementById('stimulus-lightbox-modal');
            const img = document.getElementById('stimulus-lightbox-image');

            if (!modal) return;
            modal.classList.add('hidden');
            if (img) {
                img.src = '';
            }
            if (stimulusLightboxLastFocus && typeof stimulusLightboxLastFocus.focus === 'function') {
                stimulusLightboxLastFocus.focus();
            }
        }

        function handleStimulusLightboxBackdropClick(event) {
            if (event.target.id === 'stimulus-lightbox-modal' || event.target.closest('#stimulus-lightbox-image') === null) {
                closeStimulusLightbox();
            }
        }

        function isStimulusLightboxOpen() {
            const modal = document.getElementById('stimulus-lightbox-modal');
            return modal && !modal.classList.contains('hidden');
        }

        window.addEventListener('blur', () => {
            if (isRealTest) {
                if (secureSessionActive) {
                    logViolation('window_blur');
                }
            } else {
                logViolation('window_blur');
            }
        });
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;

            if (e.key === 'Escape') {
                if (isStimulusLightboxOpen()) {
                    closeStimulusLightbox();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }

            if (isStimulusLightboxOpen()) return;

            if (e.key === 'n' || e.key === 'N') {
                if (currentUnitIdx >= 0) {
                    handleNextClick(currentUnitIdx, 'unit', currentUnitIdx + 1);
                } else if (currentUnitIdx === -3 && pendingTransitionTargetUnitIdx !== null) {
                    proceedPassageTypeTransition();
                } else if (firstSectionId) {
                    startSectionQuestions(0);
                }
            }

            if (!isRealTest && (e.key === 'p' || e.key === 'P')) {
                if (currentUnitIdx > 0) {
                    navigateDeliveryUnit(currentUnitIdx - 1);
                } else if (currentUnitIdx === -3 && pendingTransitionTargetUnitIdx !== null) {
                    navigateDeliveryUnit(pendingTransitionTargetUnitIdx - 1);
                } else if (currentUnitIdx === 0 && firstSectionId) {
                    showSectionIntro(firstSectionId);
                }
            }
        });

        // Initialize view based on URL hash or default to Section 1 Intro
        document.addEventListener('DOMContentLoaded', () => {
            if (serverRemainingSeconds <= 0) {
                triggerExpiryFlow('initial_zero_time');
                return;
            }

            const hash = window.location.hash;
            if (hash.startsWith('#q=')) {
                const qIdx = parseInt(hash.replace('#q=', ''), 10);
                const unitIdx = questionIndexToUnitIndex[qIdx];
                const resolvedUnitIdx = (unitIdx !== undefined) ? unitIdx : 0;
                const resolvedQIdx = (unitIdx !== undefined) ? qIdx : (unitIndexToQuestionIndices[0]?.[0] ?? 0);

                if (isRealTest && !document.fullscreenElement) {
                    pendingTargetUnitIdx = resolvedUnitIdx;
                    pendingTargetQIndex = resolvedQIdx;
                    document.querySelectorAll('.delivery-unit-card, .section-intro-card, .passage-type-transition-card').forEach(card => card.classList.add('hidden'));
                    showFullscreenRequiredOverlay();
                } else {
                    renderDeliveryUnit(resolvedUnitIdx, resolvedQIdx);
                }
            } else if (hash.startsWith('#section=')) {
                const sId = hash.replace('#section=', '');
                showSectionIntro(sId);
            } else if (firstSectionId) {
                showSectionIntro(firstSectionId);
            } else {
                if (isRealTest && !document.fullscreenElement) {
                    pendingTargetUnitIdx = 0;
                    pendingTargetQIndex = 0;
                    document.querySelectorAll('.delivery-unit-card, .section-intro-card, .passage-type-transition-card').forEach(card => card.classList.add('hidden'));
                    showFullscreenRequiredOverlay();
                } else {
                    renderDeliveryUnit(0);
                }
            }

            updatePaletteUI();
        });
    </script>

    <!-- Reusable Candidate Stimulus Image Lightbox -->
    <div id="stimulus-lightbox-modal"
         class="hidden fixed inset-0 z-[110] bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center p-3 sm:p-6 select-none"
         role="dialog"
         aria-modal="true"
         aria-label="Enlarged Stimulus Image"
         onclick="handleStimulusLightboxBackdropClick(event)">

        <!-- Lightbox Action Bar -->
        <div class="w-full max-w-7xl flex items-center justify-between px-2 py-2 text-white mb-2" onclick="event.stopPropagation()">
            <div class="flex items-center gap-2.5">
                <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-slate-800 border border-slate-600 text-white shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                    </svg>
                </span>
                <span id="stimulus-lightbox-caption" class="text-xs sm:text-sm font-bold truncate max-w-xs sm:max-w-md text-white tracking-wide">Stimulus Image</span>
                <span class="text-[10px] uppercase font-black tracking-wider px-2.5 py-0.5 rounded-md bg-slate-800 text-white border border-slate-500 shadow-sm">Zoom Mode</span>
            </div>
            <button type="button"
                    id="stimulus-lightbox-close-btn"
                    onclick="closeStimulusLightbox()"
                    aria-label="Close enlarged view"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white hover:text-white border border-slate-500 hover:border-slate-300 font-bold text-xs transition-all cursor-pointer shadow-md focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-slate-950">
                <span class="text-base font-black leading-none text-white">&times;</span>
                <span class="hidden sm:inline font-bold text-white">Close (Esc)</span>
            </button>
        </div>

        <!-- Lightbox Image Viewer Container -->
        <div class="relative max-w-full max-h-[85vh] sm:max-h-[88vh] overflow-auto rounded-2xl flex items-center justify-center p-1" onclick="event.stopPropagation()">
            <img id="stimulus-lightbox-image"
                 src=""
                 alt="Enlarged Stimulus"
                 class="max-w-[95vw] sm:max-w-[92vw] max-h-[82vh] sm:max-h-[85vh] object-contain rounded-xl shadow-2xl transition-all"
                 onclick="event.stopPropagation()" />
        </div>

        <!-- Subtle Bottom Hint -->
        <div class="mt-2 text-center text-xs text-slate-200 font-medium">
            <span>Click outside image or press <kbd class="px-2 py-0.5 rounded bg-slate-800 text-white border border-slate-500 font-mono text-[10px] font-bold shadow-sm">Esc</kbd> to close</span>
        </div>
    </div>

    <x-iap-modal />
</body>
</html>
