<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-theme="{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Candidate Assessment Preview — {{ $test->title }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Early Theme Initialization -->
    <script>
        (function() {
            var preference = '{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}';
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
</head>
<body class="min-h-full font-sans antialiased bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col">

    <!-- Top Preview Status Header Bar -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 py-3.5 sticky top-0 z-50 shadow-sm flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-0.5">
                    <span class="px-2.5 py-0.5 rounded-md text-[11px] font-extrabold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700 uppercase tracking-wider">
                        CANDIDATE PREVIEW
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium hidden sm:inline">
                        Preview only — no attempt, score, or result will be recorded.
                    </span>
                </div>
                <h1 class="text-sm sm:text-base font-black text-slate-900 dark:text-white leading-snug">
                    {{ $test->title }}
                </h1>
            </div>
        </div>

        <!-- Non-Persistent Visual Countdown Timer & Return to Builder Action -->
        <div class="flex items-center gap-3">
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 px-3.5 py-1.5 rounded-xl text-center shadow-sm">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block uppercase font-bold tracking-wider">Time Remaining</span>
                <span id="preview-countdown-timer" class="text-sm sm:text-base font-mono font-black text-indigo-600 dark:text-indigo-400">--:--:--</span>
            </div>

            <a href="{{ route('teacher.tests.show', $test->id) }}"
               class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-bold rounded-xl transition-colors inline-flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Test Builder</span>
            </a>
        </div>
    </header>

    @php
        $totalQuestionsCount = $totalQuestionsCount ?? $questions->count();
        $totalSectionsCount = $sections->count();
        $totalUnitsCount = $totalUnitsCount ?? $deliveryUnits->count();
    @endphp

    <!-- Main Workspace Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6">
        @if($totalSectionsCount === 0 || $totalQuestionsCount === 0)
            {{-- Incomplete Assessment Structure Screen --}}
            <div class="max-w-2xl mx-auto my-12 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 shadow-sm text-center space-y-5">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800/60 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white">Preview Structure Incomplete</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                        Preview unavailable because the assessment structure is incomplete. Please ensure at least one section and valid questions are configured in the Test Builder.
                    </p>
                </div>

                @if(!empty($validationResult['errors']))
                    <div class="text-left bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/50 rounded-xl p-4 space-y-2">
                        <span class="text-xs font-extrabold text-rose-800 dark:text-rose-300 uppercase tracking-wider block">Structural Validation Issues:</span>
                        <ul class="text-xs text-rose-700 dark:text-rose-300 space-y-1 list-disc pl-5">
                            @foreach($validationResult['errors'] as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="pt-3">
                    <a href="{{ route('teacher.tests.show', $test->id) }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                        &larr; Return to Test Builder
                    </a>
                </div>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-4 items-start">
                <!-- Left Column: Assessment Overview, Section Intros & Delivery Units (3 Cols) -->
                <div class="lg:col-span-3 space-y-6">

                    <!-- 0. PREVIEW ASSESSMENT OVERVIEW / PRE-TEST INSTRUCTIONS CARD -->
                    <div id="preview-overview-card" class="preview-overview-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-5">
                            <div class="flex items-center gap-2.5 mb-2.5 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-extrabold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700 uppercase tracking-wider">
                                    {{ is_object($test->test_type) ? $test->test_type->label() : strtoupper($test->test_type ?? 'ASSESSMENT') }}
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    Pre-Assessment Briefing &amp; Instructions
                                </span>
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-snug">
                                {{ $test->title }}
                            </h2>
                        </div>

                        <!-- Metrics Strip -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <div>
                                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Duration</span>
                                <span class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white mt-0.5 block">⏱ {{ $test->duration_minutes }} Minutes</span>
                            </div>
                            <div>
                                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Pass Threshold</span>
                                <span class="text-sm sm:text-base font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5 block">🎯 {{ $test->pass_score }} Points</span>
                            </div>
                            <div>
                                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Sections</span>
                                <span class="text-sm sm:text-base font-extrabold text-indigo-600 dark:text-indigo-400 mt-0.5 block">📑 {{ $totalSectionsCount }} Section(s)</span>
                            </div>
                            <div>
                                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Total Questions</span>
                                <span class="text-sm sm:text-base font-extrabold text-slate-800 dark:text-slate-200 mt-0.5 block">📝 {{ $totalQuestionsCount }} Questions</span>
                            </div>
                        </div>

                        <!-- General Instructions Body -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>General Assessment Instructions</span>
                            </h3>
                            <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-300 text-xs sm:text-sm leading-relaxed whitespace-pre-line">
                                {{ $test->instructions ?: 'Please read the instructions for each section carefully. You may navigate between questions and review your answers before completing your assessment.' }}
                            </div>
                        </div>

                        <!-- Section Breakdown -->
                        @if($sections->isNotEmpty())
                        <div class="space-y-3 pt-1">
                            <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                <span>📑</span> Assessment Section Breakdown
                            </h3>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                @foreach($sections as $secIdx => $sec)
                                @php
                                    $secQCount = $sec->testQuestions->count();
                                @endphp
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">{{ $sec->title }}</div>
                                        <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold capitalize mt-0.5">
                                            {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type ?? 'GENERAL') }} SECTION
                                        </div>
                                    </div>
                                    <span class="text-xs text-slate-600 dark:text-slate-400 font-bold bg-white dark:bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-800">
                                        {{ $secQCount }} Qs
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Important Notice & Begin Action Bar -->
                        <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <p class="text-xs text-slate-500 dark:text-slate-400 text-center sm:text-left">
                                💡 Preview Mode: Clicking <strong class="text-slate-700 dark:text-slate-200">Begin Preview</strong> starts the visual timer and displays the first section directions.
                            </p>

                            <button type="button" onclick="startPreview()" id="btn-begin-preview" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs sm:text-sm transition-all shadow-lg shadow-indigo-600/25 hover:scale-[1.02] active:scale-[0.98]">
                                <span>🚀 Begin Preview</span>
                            </button>
                        </div>
                    </div>

                    <!-- 1. DEDICATED SECTION DIRECTIONS SCREENS -->
                    @foreach($sections as $secIndex => $sec)
                        @php
                            $firstUnitIdx = $sectionFirstUnitIndex[$sec->id] ?? 0;
                            $secQuestionCount = $sec->testQuestions->count();
                        @endphp
                        <div id="section-intro-card-{{ $sec->id }}" class="section-intro-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6 hidden">
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                                <div class="flex items-center gap-2.5 mb-2 flex-wrap">
                                    <span class="px-3 py-1 text-xs font-extrabold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 uppercase tracking-wider">
                                        {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type ?? 'GENERAL') }} SECTION
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">
                                        Section {{ $secIndex + 1 }} of {{ $totalSectionsCount }} ({{ $secQuestionCount }} {{ \Illuminate\Support\Str::plural('Question', $secQuestionCount) }})
                                    </span>
                                </div>
                                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                                    {{ $sec->title }}
                                </h2>
                            </div>

                            <!-- Directions Text -->
                            <div class="space-y-3">
                                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>Section Directions</span>
                                </h3>
                                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 text-sm leading-relaxed whitespace-pre-line">
                                    {{ $sec->instructions ?: 'Please read the questions in this section carefully and select the best answer option.' }}
                                </div>
                            </div>

                            <!-- Section Media Assets (if any) -->
                            @if($sec->mediaAssets && $sec->mediaAssets->isNotEmpty())
                                <div class="space-y-3 pt-2">
                                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                                        </svg>
                                        <span>Section Reference Media</span>
                                    </h3>
                                    <div class="space-y-4">
                                        @foreach($sec->mediaAssets as $sectionMedia)
                                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200">
                                                <div class="flex items-center gap-2 mb-2 text-xs font-bold text-indigo-700 dark:text-indigo-300">
                                                    <span>{{ $sectionMedia->title ?: $sectionMedia->name }}</span>
                                                </div>
                                                @php
                                                    $isSecImage = $sectionMedia->type === 'image' || str_starts_with($sectionMedia->mime_type ?? '', 'image/');
                                                    $isSecAudio = $sectionMedia->type === 'audio' || str_starts_with($sectionMedia->mime_type ?? '', 'audio/');
                                                    $secMediaSrc = $sectionMedia->publicUrl() ?: ($sectionMedia->url ?? $sectionMedia->path);
                                                @endphp
                                                @if($isSecImage)
                                                    <img src="{{ $secMediaSrc }}" alt="{{ $sectionMedia->title }}" class="max-h-64 object-contain rounded-lg border border-slate-300 dark:border-slate-700">
                                                @elseif($isSecAudio)
                                                    <audio controls class="w-full" src="{{ $secMediaSrc }}" preload="metadata">
                                                        <source src="{{ $secMediaSrc }}" type="{{ $sectionMedia->mime_type ?? 'audio/mpeg' }}">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($secQuestionCount === 0)
                                <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 rounded-xl text-xs font-bold text-amber-800 dark:text-amber-300 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <span>Section contains no questions.</span>
                                </div>
                            @endif

                            <!-- Action Bar to Advance into Section Questions -->
                            <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-3">
                                @if($secIndex > 0 && isset($sections[$secIndex - 1]))
                                    <button type="button" onclick="showSectionIntro('{{ $sections[$secIndex - 1]->id }}')" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-1.5">
                                        &larr; Previous Section
                                    </button>
                                @else
                                    <button type="button" onclick="showAssessmentOverview()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-1.5">
                                        &larr; Overview
                                    </button>
                                @endif

                                @if($secQuestionCount > 0)
                                    <button type="button" onclick="beginSectionQuestions({{ $firstUnitIdx }})" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs sm:text-sm shadow-md shadow-indigo-600/20 transition-all">
                                        <span>Begin {{ $sec->title }} &rarr;</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <!-- 2. DELIVERY UNIT CARDS (Single-Page Audio Groups & Standalone Questions) -->
                    @foreach($deliveryUnits as $unitIndex => $unit)
                        @php
                            $unitType = $unit['type'];
                            $section = $unit['section'];
                            $isAudioGroup = ($unitType === 'audio_group');
                            $groupTypeLabel = $unit['group_type'] === 'talk' ? 'Talk' : 'Conversation';
                            $unitAudioUrl = $unit['audio_url'];
                            $isFirstUnitOfSection = $unit['is_first_unit_of_section'];
                            $isLastUnitOfSection = $unit['is_last_unit_of_section'];
                            $nextSectionId = $unit['next_section_id'];
                        @endphp

                        <div id="delivery-unit-card-{{ $unitIndex }}" class="delivery-unit-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-7 shadow-sm hidden" data-unit-type="{{ $unitType }}" data-unit-index="{{ $unitIndex }}">

                            @if($isAudioGroup)
                                <!-- ========================================================================= -->
                                <!-- AUDIO GROUP DELIVERY UNIT (Part 3 / Part 4 — One Audio + 3 Child Questions) -->
                                <!-- ========================================================================= -->

                                <!-- Group Header & Meta -->
                                <div class="border-b border-slate-200 dark:border-slate-800 pb-4 mb-6">
                                    <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-indigo-100 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700 uppercase tracking-wider">
                                                PART {{ $unit['part_number'] }} — {{ strtoupper($groupTypeLabel) }}
                                            </span>
                                            <span class="text-slate-400 dark:text-slate-600">•</span>
                                            <span class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wide">
                                                {{ $unit['display_question_range'] }}
                                            </span>
                                            @if($section)
                                                <span class="text-slate-400 dark:text-slate-600">•</span>
                                                <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline transition-colors" title="View Section Directions">
                                                    {{ $section->title }}
                                                </button>
                                            @endif
                                        </div>

                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 uppercase">
                                            Shared Audio Group
                                        </span>
                                    </div>

                                    @if(!empty($unit['title']))
                                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white mt-1">
                                            {{ $unit['title'] }}
                                        </h3>
                                    @endif
                                </div>

                                <!-- ONE Shared Audio Stimulus Player at Group Level -->
                                <div class="mb-8 p-4 sm:p-5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 shadow-sm space-y-3">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">🎧</span>
                                            <div>
                                                <span class="text-xs font-black text-indigo-900 dark:text-indigo-200 uppercase tracking-wider block">
                                                    Shared {{ $groupTypeLabel }} Audio (Part {{ $unit['part_number'] }})
                                                </span>
                                                <span class="text-[11px] text-indigo-700 dark:text-indigo-300 font-medium">
                                                    Listen to the {{ strtolower($groupTypeLabel) }} to answer {{ $unit['display_question_range'] }} below.
                                                </span>
                                            </div>
                                        </div>

                                        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-white dark:bg-slate-900 px-2.5 py-1 rounded-lg border border-indigo-200 dark:border-indigo-800">
                                            Preview Mode Player
                                        </span>
                                    </div>

                                    @if(!empty($unitAudioUrl))
                                        <audio controls class="w-full h-10 rounded-lg" src="{{ $unitAudioUrl }}" preload="metadata">
                                            <source src="{{ $unitAudioUrl }}">
                                            Your browser does not support the audio element.
                                        </audio>
                                    @else
                                        <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800/50 rounded-xl text-xs font-bold text-amber-800 dark:text-amber-300 flex items-center gap-2">
                                            <span>⚠️</span>
                                            <span>Notice: Audio stimulus file has not yet been attached.</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- THREE Child Questions Rendered Vertically on the Same Page -->
                                <div class="space-y-6">
                                    @foreach($unit['questions'] as $cIdx => $question)
                                        @php
                                            $globalQIdx = $unit['question_indices'][$cIdx];
                                        @endphp

                                        <div id="unit-question-block-{{ $globalQIdx }}" class="p-5 sm:p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4 transition-all">
                                            <!-- Child Question Header -->
                                            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5 flex-wrap gap-2">
                                                <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                    Question {{ $globalQIdx + 1 }}
                                                </span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 uppercase">
                                                    Part {{ $unit['part_number'] }} Child Question
                                                </span>
                                            </div>

                                            <!-- Stem Prompt -->
                                            @if($question->prompt)
                                                <div class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-relaxed">
                                                    {!! nl2br(e($question->prompt)) !!}
                                                </div>
                                            @elseif((int)$unit['part_number'] !== 6)
                                                <div class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-relaxed text-slate-400">
                                                    (No stem prompt)
                                                </div>
                                            @endif

                                            <!-- Candidate Choices (4 Choices, Independent Selection) -->
                                            <div class="space-y-2.5 pt-1">
                                                @foreach($question->choices as $choice)
                                                    <label class="preview-choice-label flex items-center p-3 sm:p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-600 cursor-pointer transition-all">
                                                        <input type="radio"
                                                               name="preview_choice_{{ $question->id }}"
                                                               value="{{ $choice->id }}"
                                                               onchange="selectPreviewChoice('{{ $question->id }}', '{{ $choice->id }}', {{ $globalQIdx }})"
                                                               class="w-4 h-4 text-indigo-600 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 focus:ring-indigo-500">
                                                        <span class="ml-3 text-xs sm:text-sm text-slate-900 dark:text-slate-200 font-semibold">
                                                            <strong class="text-indigo-600 dark:text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Audio Group Navigation Controls -->
                                <div class="flex justify-between items-center pt-6 mt-8 border-t border-slate-200 dark:border-slate-800 flex-wrap gap-3">
                                    @if($isFirstUnitOfSection && $section)
                                        <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition-colors">
                                            &larr; Section Directions
                                        </button>
                                    @else
                                        <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex - 1 }})" {{ $unitIndex === 0 ? 'disabled' : '' }} class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                            &larr; Previous Group
                                        </button>
                                    @endif

                                    @if($isLastUnitOfSection && $nextSectionId)
                                        <button type="button" onclick="showSectionIntro('{{ $nextSectionId }}')" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 transition-all">
                                            Next Section &rarr;
                                        </button>
                                    @elseif($unitIndex === $totalUnitsCount - 1)
                                        <button type="button" onclick="finishPreview()" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/20 transition-all">
                                            Finish Preview &rarr;
                                        </button>
                                    @else
                                        <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex + 1 }})" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 transition-all">
                                            Next Group &rarr;
                                        </button>
                                    @endif
                                </div>

                            @else
                                <!-- ========================================================================= -->
                                <!-- STANDALONE QUESTION DELIVERY UNIT (Part 1, Part 2, Part 5, 6, 7 or legacy) -->
                                <!-- ========================================================================= -->
                                @php
                                    $question = $unit['questions']->first();
                                    $globalQIdx = $unit['first_question_index'];
                                    $effectivePassages = $question->getEffectivePassages();
                                    $hasPassages = $effectivePassages->isNotEmpty();
                                    $partNum = (int) ($question->part_number ?? 0);
                                    $audioUrl = $question->getEffectiveAudioUrl();
                                    $imageUrl = method_exists($question, 'getEffectiveImageUrl') ? $question->getEffectiveImageUrl() : ($question->image_url ?: ($question->mediaAsset && ($question->mediaAsset->type === 'image' || str_starts_with($question->mediaAsset->mime_type ?? '', 'image/')) ? ($question->mediaAsset->publicUrl() ?? $question->mediaAsset->path) : null));
                                    $isToeicPart1 = ($partNum === 1);
                                    $isMissingPart1Media = $isToeicPart1 && (empty($imageUrl) || empty($audioUrl));
                                @endphp

                                <div id="unit-question-block-{{ $globalQIdx }}">
                                    <!-- Header & Breadcrumbs -->
                                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3.5 mb-5 flex-wrap gap-2">
                                        <div class="flex items-center gap-2.5 flex-wrap">
                                            <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                Question {{ $globalQIdx + 1 }} of {{ $totalQuestionsCount }}
                                            </span>
                                            @if($section)
                                                <span class="text-slate-400 dark:text-slate-600">•</span>
                                                <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 underline underline-offset-2 transition-colors" title="View Section Directions">
                                                    Section: {{ $section->title }}
                                                </button>
                                            @endif
                                        </div>
                                        <span class="px-2.5 py-0.5 rounded text-[11px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 uppercase">
                                            {{ is_object($question->question_type) ? $question->question_type->value : ($question->question_type ?? 'Multiple Choice') }}
                                        </span>
                                    </div>

                                    @if($isMissingPart1Media)
                                        <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800/50 rounded-xl text-xs font-bold text-amber-800 dark:text-amber-300 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            <span>Notice: Question media is not yet attached.</span>
                                        </div>
                                    @endif

                                    @if($hasPassages)
                                        <!-- Reading Dual-Pane Split Screen Layout -->
                                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                                            <!-- Left Pane: Passages -->
                                            <div class="lg:col-span-6 xl:col-span-7 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-5 flex flex-col max-h-[65vh] overflow-hidden">
                                                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-3 flex-wrap gap-2">
                                                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">
                                                        📖 Reading Passage
                                                    </span>
                                                    @if($effectivePassages->count() > 1)
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            @foreach($effectivePassages as $pIdx => $pass)
                                                                <button type="button" onclick="switchPassageTab('{{ $question->id }}', {{ $pIdx }})" id="passage-tab-{{ $question->id }}-{{ $pIdx }}" class="passage-tab-btn px-2.5 py-1 rounded text-xs font-bold border transition-all {{ $pIdx === 0 ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800' }}">
                                                                    {{ $pass->title ?: ('Doc ' . ($pIdx + 1)) }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="overflow-y-auto pr-2 space-y-4 text-xs sm:text-sm text-slate-800 dark:text-slate-200 leading-relaxed select-text">
                                                    @foreach($effectivePassages as $pIdx => $pass)
                                                        <div id="passage-content-{{ $question->id }}-{{ $pIdx }}" class="passage-doc-content {{ $pIdx > 0 ? 'hidden' : '' }}">
                                                            @if($pass->title && $effectivePassages->count() === 1)
                                                                <h4 class="font-bold text-sm text-indigo-700 dark:text-indigo-400 mb-2 border-b border-slate-200 dark:border-slate-800 pb-1">{{ $pass->title }}</h4>
                                                            @endif
                                                            <div class="prose dark:prose-invert max-w-none text-xs sm:text-sm whitespace-pre-line leading-relaxed">
                                                                {!! nl2br(e($pass->content)) !!}
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Right Pane: Stem Prompt & Options -->
                                            <div class="lg:col-span-6 xl:col-span-5 flex flex-col justify-between space-y-6">
                                                @if(!empty($imageUrl))
                                                    <div class="flex justify-center p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl">
                                                        <img src="{{ $imageUrl }}" alt="Question Image" class="max-h-56 object-contain rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                                    </div>
                                                @endif

                                                @if(!empty($audioUrl))
                                                    <div class="p-3 bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 rounded-xl space-y-1.5">
                                                        <span class="text-[11px] font-extrabold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider block">🎧 Audio Prompt Player</span>
                                                        <audio controls class="w-full h-8" src="{{ $audioUrl }}" preload="metadata">
                                                            <source src="{{ $audioUrl }}">
                                                            Your browser does not support the audio element.
                                                        </audio>
                                                    </div>
                                                @endif

                                                <div class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-relaxed">
                                                    {!! nl2br(e($question->prompt)) !!}
                                                </div>

                                                <div class="space-y-3">
                                                    @foreach($question->choices as $choice)
                                                        <label class="preview-choice-label flex items-center p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-600 cursor-pointer transition-all">
                                                            <input type="radio" name="preview_choice_{{ $question->id }}" value="{{ $choice->id }}" onchange="selectPreviewChoice('{{ $question->id }}', '{{ $choice->id }}', {{ $globalQIdx }})" class="w-4 h-4 text-indigo-600 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 focus:ring-indigo-500">
                                                            <span class="ml-3 text-xs sm:text-sm text-slate-900 dark:text-slate-200 font-semibold">
                                                                @if(in_array((int)$partNum, [1, 2], true))
                                                                    <strong class="text-indigo-600 dark:text-indigo-400">({{ $choice->label }})</strong>
                                                                @else
                                                                    <strong class="text-indigo-600 dark:text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                                                @endif
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Standard Single-Pane Layout -->
                                        <div class="space-y-6">
                                            @if(!empty($imageUrl))
                                                <div class="flex justify-center p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl">
                                                    <img src="{{ $imageUrl }}" alt="Question Image" class="max-h-72 object-contain rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                                </div>
                                            @endif

                                            @if(!empty($audioUrl))
                                                <div class="p-3 bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 rounded-xl space-y-1.5">
                                                    <span class="text-[11px] font-extrabold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider block">🎧 Audio Prompt Player</span>
                                                    <audio controls class="w-full h-8" src="{{ $audioUrl }}" preload="metadata">
                                                        <source src="{{ $audioUrl }}">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                </div>
                                            @endif

                                            @if($question->prompt)
                                                <div class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-relaxed">
                                                    {!! nl2br(e($question->prompt)) !!}
                                                </div>
                                            @elseif($partNum !== 6 && $partNum !== 2 && $partNum !== 1)
                                                <div class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-relaxed text-slate-400">
                                                    (No stem prompt)
                                                </div>
                                            @endif

                                            <div class="space-y-3">
                                                @foreach($question->choices as $choice)
                                                    <label class="preview-choice-label flex items-center p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-600 cursor-pointer transition-all">
                                                        <input type="radio" name="preview_choice_{{ $question->id }}" value="{{ $choice->id }}" onchange="selectPreviewChoice('{{ $question->id }}', '{{ $choice->id }}', {{ $globalQIdx }})" class="w-4 h-4 text-indigo-600 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 focus:ring-indigo-500">
                                                        <span class="ml-3 text-xs sm:text-sm text-slate-900 dark:text-slate-200 font-semibold">
                                                            @if(in_array((int)$partNum, [1, 2], true))
                                                                <strong class="text-indigo-600 dark:text-indigo-400">({{ $choice->label }})</strong>
                                                            @else
                                                                <strong class="text-indigo-600 dark:text-indigo-400 mr-2">{{ $choice->label }}.</strong> {{ $choice->content }}
                                                            @endif
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Bottom Navigation Action Bar -->
                                    <div class="flex justify-between items-center pt-6 mt-6 border-t border-slate-100 dark:border-slate-800 flex-wrap gap-3">
                                        @if($isFirstUnitOfSection && $section)
                                            <button type="button" onclick="showSectionIntro('{{ $section->id }}')" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition-colors">
                                                &larr; Section Directions
                                            </button>
                                        @else
                                            <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex - 1 }})" {{ $unitIndex === 0 ? 'disabled' : '' }} class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                                &larr; Previous
                                            </button>
                                        @endif

                                        @if($isLastUnitOfSection && $nextSectionId)
                                            <button type="button" onclick="showSectionIntro('{{ $nextSectionId }}')" class="px-5 py-2.5 text-xs font-extrabold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 transition-all">
                                                Next Section &rarr;
                                            </button>
                                        @elseif($unitIndex === $totalUnitsCount - 1)
                                            <button type="button" onclick="finishPreview()" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/20 transition-all">
                                                Finish Preview &rarr;
                                            </button>
                                        @else
                                            <button type="button" onclick="navigateDeliveryUnit({{ $unitIndex + 1 }})" class="px-5 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/20 transition-all">
                                                Next &rarr;
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>

                <!-- Right Column: Navigation Palette & Overview Sidebar (1 Col) -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Question Palette Card -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4 sticky top-20">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <span class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Question Palette</span>
                            <span id="preview-answered-progress" class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                0 / {{ $totalQuestionsCount }} Answered
                            </span>
                        </div>

                        <!-- Grid Numbers -->
                        <div class="grid grid-cols-5 gap-2 max-h-[45vh] overflow-y-auto pr-1">
                            @foreach($questions as $pIdx => $q)
                                <button type="button"
                                        id="palette-btn-{{ $pIdx }}"
                                        data-question-id="{{ $q->id }}"
                                        onclick="handlePaletteQuestionClick({{ $pIdx }})"
                                        class="palette-item-btn flex items-center justify-center h-9 rounded-lg font-extrabold text-xs border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-indigo-500 transition-all"
                                        title="Jump to Question {{ $pIdx + 1 }}">
                                    {{ $pIdx + 1 }}
                                </button>
                            @endforeach
                        </div>

                        <!-- Section & Overview Shortcuts -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                            <span class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Assessment Navigation</span>
                            <div class="flex flex-col gap-1.5">
                                <button type="button" onclick="showAssessmentOverview()" class="text-left px-2.5 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 transition-colors">
                                    📋 Assessment Overview
                                </button>
                                @foreach($sections as $sec)
                                    <button type="button" onclick="showSectionIntro('{{ $sec->id }}')" class="text-left px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-600 truncate transition-colors">
                                        📁 {{ $sec->title }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Assessment Metadata Box -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                            <div class="flex justify-between">
                                <span class="font-medium">Test Type:</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100">{{ strtoupper(is_object($test->test_type) ? ($test->test_type->value ?? $test->test_type->name) : ($test->test_type ?? 'TOEIC')) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Duration:</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100">{{ $test->duration_minutes }} Min</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Pass Score:</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100">{{ $test->pass_score }} pts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>

    <!-- Finish Preview Modal / Overlay -->
    <div id="preview-complete-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 sm:p-7 shadow-2xl space-y-5 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Preview Complete</h3>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                    You have reached the end of the candidate assessment preview session. No candidate attempt, answers, or scores have been recorded in the database.
                </p>
                <div class="mt-3 p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-indigo-700 dark:text-indigo-300">
                    <span id="preview-summary-text">Answered 0 of {{ $totalQuestionsCount }} questions.</span>
                </div>
            </div>
            <div class="flex gap-3 justify-center pt-2">
                <button type="button" onclick="restartPreview()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors">
                    Restart Preview
                </button>
                <a href="{{ route('teacher.tests.show', $test->id) }}" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Return to Test Builder
                </a>
            </div>
        </div>
    </div>

    <!-- Candidate Preview Runtime JavaScript Engine -->
    <script>
        const totalQuestions = {{ $totalQuestionsCount }};
        const totalUnits = {{ $totalUnitsCount }};
        const questionIndexToUnitIndex = @json($questionIndexToUnitIndex);
        const questionIdToUnitIndex = @json($questionIdToUnitIndex);
        const unitIndexToQuestionIndices = @json($unitIndexToQuestionIndices);
        const sectionFirstUnitIndex = @json($sectionFirstUnitIndex);
        const firstSectionId = "{{ $sections->isNotEmpty() ? $sections->first()->id : '' }}";

        let currentUnitIndex = -2; // -2: overview, -1: section intro, 0..totalUnits-1: delivery unit
        const temporaryAnswers = {};

        // Non-Persistent Visual Countdown Timer
        let timerSeconds = {{ (int) ($test->duration_minutes ?: 60) * 60 }};
        const timerEl = document.getElementById('preview-countdown-timer');
        let timerInterval = null;
        let isPreviewStarted = false;

        function updateCountdownDisplay() {
            if (!timerEl) return;
            const hrs = Math.floor(timerSeconds / 3600);
            const mins = Math.floor((timerSeconds % 3600) / 60);
            const secs = timerSeconds % 60;
            timerEl.textContent =
                String(hrs).padStart(2, '0') + ':' +
                String(mins).padStart(2, '0') + ':' +
                String(secs).padStart(2, '0');
        }

        function startTimer() {
            if (timerInterval) return;
            if (timerSeconds > 0) {
                timerInterval = setInterval(function() {
                    if (timerSeconds > 0) {
                        timerSeconds--;
                        updateCountdownDisplay();
                    } else {
                        clearInterval(timerInterval);
                    }
                }, 1000);
            }
        }

        function hideAllViews() {
            const overviewCard = document.getElementById('preview-overview-card');
            if (overviewCard) overviewCard.classList.add('hidden');
            document.querySelectorAll('.section-intro-card').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.delivery-unit-card').forEach(el => el.classList.add('hidden'));
        }

        function showAssessmentOverview() {
            hideAllViews();
            currentUnitIndex = -2;
            const overviewCard = document.getElementById('preview-overview-card');
            if (overviewCard) {
                overviewCard.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            updatePaletteHighlights();
        }

        function startPreview() {
            isPreviewStarted = true;
            startTimer();
            if (firstSectionId) {
                showSectionIntro(firstSectionId);
            } else if (totalUnits > 0) {
                navigateDeliveryUnit(0);
            }
        }

        function showSectionIntro(sectionId) {
            hideAllViews();
            currentUnitIndex = -1;
            const introCard = document.getElementById(`section-intro-card-${sectionId}`);
            if (introCard) {
                introCard.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            updatePaletteHighlights();
        }

        function beginSectionQuestions(firstUnitIdx) {
            navigateDeliveryUnit(firstUnitIdx);
        }

        function navigateDeliveryUnit(unitIdx, targetQIndex = null) {
            if (unitIdx < 0 || unitIdx >= totalUnits) return;
            if (!isPreviewStarted) {
                isPreviewStarted = true;
                startTimer();
            }
            currentUnitIndex = unitIdx;
            hideAllViews();

            const card = document.getElementById(`delivery-unit-card-${unitIdx}`);
            if (card) {
                card.classList.remove('hidden');
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

            updatePaletteHighlights();
        }

        function handlePaletteQuestionClick(qIndex) {
            const unitIdx = questionIndexToUnitIndex[qIndex];
            if (unitIdx !== undefined) {
                navigateDeliveryUnit(unitIdx, qIndex);
            }
        }

        function selectPreviewChoice(questionId, choiceId, qIndex) {
            temporaryAnswers[questionId] = choiceId;
            updatePaletteHighlights();
        }

        function updatePaletteHighlights() {
            const answeredCount = Object.keys(temporaryAnswers).length;
            const progressEl = document.getElementById('preview-answered-progress');
            if (progressEl) {
                progressEl.textContent = `${answeredCount} / ${totalQuestions} Answered`;
            }

            const currentUnitQIndices = (currentUnitIndex >= 0 && unitIndexToQuestionIndices[currentUnitIndex])
                ? new Set(unitIndexToQuestionIndices[currentUnitIndex])
                : new Set();

            for (let i = 0; i < totalQuestions; i++) {
                const btn = document.getElementById(`palette-btn-${i}`);
                if (!btn) continue;

                const qId = btn.getAttribute('data-question-id');
                const isAnswered = qId && temporaryAnswers[qId];
                const isCurrentUnit = currentUnitQIndices.has(i);

                if (isCurrentUnit) {
                    btn.className = 'palette-item-btn flex items-center justify-center h-9 rounded-lg font-black text-xs border-2 border-indigo-600 bg-indigo-50 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 shadow-sm';
                } else if (isAnswered) {
                    btn.className = 'palette-item-btn flex items-center justify-center h-9 rounded-lg font-bold text-xs border border-emerald-400 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300';
                } else {
                    btn.className = 'palette-item-btn flex items-center justify-center h-9 rounded-lg font-semibold text-xs border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-indigo-400';
                }
            }
        }

        function switchPassageTab(questionId, tabIndex) {
            document.querySelectorAll(`[id^="passage-content-${questionId}-"]`).forEach(el => el.classList.add('hidden'));
            document.querySelectorAll(`[id^="passage-tab-${questionId}-"]`).forEach(btn => {
                btn.className = 'passage-tab-btn px-2.5 py-1 rounded text-xs font-bold border transition-all bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800';
            });

            const content = document.getElementById(`passage-content-${questionId}-${tabIndex}`);
            const btn = document.getElementById(`passage-tab-${questionId}-${tabIndex}`);
            if (content) content.classList.remove('hidden');
            if (btn) btn.className = 'passage-tab-btn px-2.5 py-1 rounded text-xs font-bold border transition-all bg-indigo-600 text-white border-indigo-600 shadow-sm';
        }

        function finishPreview() {
            const modal = document.getElementById('preview-complete-modal');
            const summaryText = document.getElementById('preview-summary-text');
            if (summaryText) {
                summaryText.textContent = `Answered ${Object.keys(temporaryAnswers).length} of ${totalQuestions} questions.`;
            }
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function restartPreview() {
            for (const key in temporaryAnswers) {
                delete temporaryAnswers[key];
            }
            document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
            const modal = document.getElementById('preview-complete-modal');
            if (modal) modal.classList.add('hidden');

            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            timerSeconds = {{ (int) ($test->duration_minutes ?: 60) * 60 }};
            updateCountdownDisplay();
            isPreviewStarted = false;
            showAssessmentOverview();
        }

        // Initialize preview: show assessment overview, update countdown display
        document.addEventListener('DOMContentLoaded', function() {
            updateCountdownDisplay();
            showAssessmentOverview();
        });

        // Keyboard navigation shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') return;

            if (event.key === 'ArrowRight') {
                if (currentUnitIndex >= 0 && currentUnitIndex < totalUnits - 1) {
                    navigateDeliveryUnit(currentUnitIndex + 1);
                }
            } else if (event.key === 'ArrowLeft') {
                if (currentUnitIndex > 0) {
                    navigateDeliveryUnit(currentUnitIndex - 1);
                }
            } else if (event.key === 'Escape') {
                const modal = document.getElementById('preview-complete-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            }
        });
    </script>
</body>
</html>
