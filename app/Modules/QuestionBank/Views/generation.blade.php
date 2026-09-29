@extends('layouts.admin')

@section('title', 'AI Question Generation - ' . $questionBank->title)

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                <a href="{{ route('admin.question-banks.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Question Banks</a>
                <span>/</span>
                <a href="{{ route('admin.question-banks.show', $questionBank->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{{ $questionBank->title }}</a>
                <span>/</span>
                <span class="text-indigo-600 dark:text-indigo-400">AI Generation Workspace</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="p-1.5 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </span>
                    Question Generation Workspace
                </h1>
                @php
                    $statusBadge = match($questionBank->status) {
                        'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                        'needs_revision', 'rejected' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                        default => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                    };
                @endphp
                <span class="px-2.5 py-0.5 text-xs font-bold rounded border uppercase {{ $statusBadge }}">
                    {{ $questionBank->status ?? 'draft' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Target Bank: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $questionBank->title }}</span> 
                | Test Type: <span class="uppercase font-bold text-indigo-600 dark:text-indigo-400">{{ $questionBank->test_type }}</span>
                | Current Drafts: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $questionBank->questions()->count() }} items</span>
            </p>
        </div>
        <div>
            <a href="{{ route('admin.question-banks.show', $questionBank->id) }}" class="px-3.5 py-2 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Question Bank</span>
            </a>
        </div>
    </div>

    <!-- Governance Principle Banner -->
    <div class="p-3.5 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900/50 rounded-xl flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-lg">⚖️</span>
            <div>
                <p class="text-xs font-bold text-indigo-900 dark:text-indigo-200">
                    AI Generates • System Validates • Human Governs
                </p>
                <p class="text-[11px] text-indigo-700 dark:text-indigo-400 mt-0.5">
                    Generated questions are strictly materialized as unapproved drafts in your repository. They will not be published or delivered to assessments without standard Repository Manager review and approval.
                </p>
            </div>
        </div>
        <div class="hidden md:flex items-center gap-2 text-[11px] font-semibold text-slate-600 dark:text-slate-400 bg-white/70 dark:bg-slate-900/70 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800">
            <span>Provider Engine:</span>
            <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $providerDisplay }}</span>
        </div>
    </div>

    <!-- Main 2-Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left Column: Generator Form (5 Cols) -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>⚡</span> Configure Generation
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Target specification for TOEIC Part 5 items</p>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 rounded">
                        v1 Vertical Slice
                    </span>
                </div>

                <form action="{{ route('admin.question-banks.generation.store', $questionBank->id) }}" method="POST" x-data="{ isSubmitting: false }" @submit="isSubmitting = true" class="space-y-4">
                    @csrf

                    <!-- Locked Architecture Scope -->
                    <div class="p-3.5 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-200 dark:border-slate-800/80 space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Assessment Standard:</span>
                            <span class="font-bold text-slate-900 dark:text-white">TOEIC (ETS 2026.1)</span>
                            <input type="hidden" name="assessment" value="toeic">
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Section:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">Reading</span>
                            <input type="hidden" name="section" value="reading">
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Part:</span>
                            <span class="font-semibold text-indigo-600 dark:text-indigo-400">Part 5 — Incomplete Sentences</span>
                            <input type="hidden" name="part" value="5">
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Domain Context:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">General Workplace</span>
                            <input type="hidden" name="domain" value="general_workplace">
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label for="quantity" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Batch Quantity (Items to Generate) <span class="text-rose-500">*</span>
                        </label>
                        <select id="quantity" name="quantity" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                            @foreach ($supportedOptions['quantities'] as $qty)
                                <option value="{{ $qty }}" {{ (old('quantity', 1) == $qty) ? 'selected' : '' }}>
                                    {{ $qty }} {{ $qty === 1 ? 'Question item' : 'Question items' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Safe synchronous batch limit is 1–5 items per run.</p>
                        @error('quantity')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Difficulty Level -->
                    <div>
                        <label for="difficulty" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Target Difficulty Level
                        </label>
                        <select id="difficulty" name="difficulty" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                            @foreach ($supportedOptions['difficulties'] as $key => $label)
                                <option value="{{ $key }}" {{ (old('difficulty', 'any') === $key) ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('difficulty')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Proficiency Target -->
                    <div>
                        <label for="proficiency_target" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Proficiency Target (CEFR Target Band)
                        </label>
                        <select id="proficiency_target" name="proficiency_target" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                            @foreach ($supportedOptions['proficiencies'] as $key => $label)
                                <option value="{{ $key }}" {{ (old('proficiency_target', 'any') === $key) ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('proficiency_target')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Construct / Skill -->
                    <div>
                        <label for="construct" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Target Construct / Competency
                        </label>
                        <select id="construct" name="construct" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                            @foreach ($supportedOptions['constructs'] as $key => $label)
                                <option value="{{ $key }}" {{ (old('construct', 'any') === $key) ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('construct')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" 
                                :disabled="isSubmitting" 
                                class="w-full py-2.5 px-4 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <template x-if="!isSubmitting">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    <span>Execute Question Generation</span>
                                </span>
                            </template>
                            <template x-if="isSubmitting">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Generating and Validating Questions...</span>
                                </span>
                            </template>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Future Assessment Types Notice -->
            <div class="p-4 bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs space-y-1.5">
                <p class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                    <span>🧭</span> Planned Assessment Extensions
                </p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Additional assessment sections (TOEIC Part 6 Text Completion, Part 7 Reading Comprehension, Listening Parts 1–4, and TOEFL iBT Integrated Writing) will unlock automatically once validated through the core generation pipeline.
                </p>
            </div>
        </div>

        <!-- Right Column: Active Batch Results & History (7 Cols) -->
        <div class="lg:col-span-7 space-y-6">
            
            <!-- Active / Latest Batch Results Card -->
            @if ($activeBatch)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Batch Results</h3>
                                @php
                                    $batchBadge = match($activeBatch->status->value) {
                                        'completed' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                        'partially_completed' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                        'failed' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                                        'processing' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
                                        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded border uppercase {{ $batchBadge }}">
                                    {{ $activeBatch->status->value }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-mono">ID: {{ $activeBatch->id }} | {{ $activeBatch->created_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ $activeBatch->validated_slots }}</span>
                                <span class="text-[10px] text-slate-400">/ {{ $activeBatch->total_slots }} materialized</span>
                            </div>
                            @if ($activeBatch->failed_slots > 0)
                                <form action="{{ route('admin.question-banks.generation.retry-batch', [$questionBank->id, $activeBatch->id]) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[11px] font-bold rounded-lg border border-amber-500/20 transition-colors">
                                        🔄 Retry Failed ({{ $activeBatch->failed_slots }})
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        @forelse ($activeBatch->items as $item)
                            <div class="p-4 rounded-xl border {{ $item->status->value === 'materialized' ? 'bg-slate-50/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800' : 'bg-rose-50/30 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/40' }} space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 rounded border border-indigo-200 dark:border-indigo-800">
                                            Slot #{{ $item->slot_sequence }}
                                        </span>
                                        @if ($item->construct)
                                            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase">
                                                {{ $item->construct }}
                                            </span>
                                        @endif
                                        @if ($item->difficulty)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase font-semibold">
                                                {{ $item->difficulty }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @php
                                            $itemBadge = match($item->status->value) {
                                                'materialized' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                                'validation_failed' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                                'failed' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded border uppercase {{ $itemBadge }}">
                                            {{ $item->status->value }}
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-mono">Attempt {{ $item->attempt_count }}</span>
                                    </div>
                                </div>

                                <!-- Materialized Item Details -->
                                @if ($item->status->value === 'materialized' && $item->question)
                                    <div class="space-y-2.5">
                                        <p class="text-xs font-semibold text-slate-900 dark:text-white leading-relaxed">
                                            {{ $item->question->prompt }}
                                        </p>

                                        @if ($item->question->choices->isNotEmpty())
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                @foreach ($item->question->choices as $choice)
                                                    <div class="p-2 rounded-lg border {{ $choice->is_correct ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 font-semibold' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300' }} flex items-center justify-between">
                                                        <span><strong class="mr-1.5">{{ $choice->label }}.</strong> {{ $choice->content }}</span>
                                                        @if ($choice->is_correct)
                                                            <span class="text-emerald-600 dark:text-emerald-400 text-xs">✓ Correct</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($item->question->explanation)
                                            <div class="p-2 bg-indigo-50/50 dark:bg-indigo-950/30 rounded-lg text-[11px] text-indigo-900 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/40">
                                                <strong class="font-semibold">Explanation:</strong> {{ $item->question->explanation }}
                                            </div>
                                        @endif

                                        <div class="flex items-center justify-between pt-1">
                                            <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                                <span>✓</span> Quality Gate Passed & Materialized
                                            </span>
                                            <a href="{{ route('admin.question-banks.show', $questionBank->id) }}" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                                View in Question Bank →
                                            </a>
                                        </div>
                                    </div>
                                @elseif ($item->status->value === 'validation_failed')
                                    <!-- Quality Gate Rejected Item -->
                                    <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-xl space-y-1.5 text-xs">
                                        <p class="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                                            <span>⚠️</span> Quality Gate Validation Flag
                                        </p>
                                        <p class="text-[11px] text-amber-800 dark:text-amber-300">
                                            {{ $item->last_error_message ?? 'Item failed quality gate validation criteria.' }}
                                        </p>
                                        <p class="text-[10px] text-amber-700 dark:text-amber-400 italic">
                                            Under fail-closed governance, quality gate rejections are not automatically retried and remain quarantined.
                                        </p>
                                    </div>
                                @else
                                    <!-- Failed Item Details & Retry Action -->
                                    <div class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/50 rounded-xl space-y-2 text-xs">
                                        <div class="flex items-center justify-between">
                                            <p class="font-bold text-rose-900 dark:text-rose-200 flex items-center gap-1.5">
                                                <span>❌</span> Generation Error: <span class="font-mono text-[11px] font-semibold">{{ $item->last_error_code ?? 'provider_error' }}</span>
                                            </p>
                                            @if ($item->isEligibleForRetry(3))
                                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                    Retryable Transient
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                    Non-Retryable
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-rose-800 dark:text-rose-300">
                                            {{ $item->last_error_message ?? 'Provider generation failed.' }}
                                        </p>

                                        @if ($item->isEligibleForRetry(3))
                                            <div class="pt-1">
                                                <form action="{{ route('admin.question-banks.generation.retry-item', [$questionBank->id, $item->id]) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-colors inline-flex items-center gap-1.5">
                                                        <span>🔄 Retry Slot #{{ $item->slot_sequence }}</span>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-4">No items found in this batch.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <!-- Empty Batch State -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center space-y-3 shadow-sm">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center text-xl shadow-sm">
                        ⚡
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ready for Generation</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                        Configure your desired TOEIC Part 5 quantity and proficiency settings on the left and click "Execute Question Generation".
                    </p>
                </div>
            @endif

            <!-- Generation History Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📜</span> Generation Run History
                    </h3>
                    <span class="text-[11px] text-slate-400 font-semibold">{{ $batches->count() }} recent runs</span>
                </div>

                @if ($batches->isEmpty())
                    <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-6">
                        No previous generation runs recorded for this Question Bank.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 font-semibold uppercase">
                                    <th class="py-2 px-3">Date</th>
                                    <th class="py-2 px-3">Batch ID</th>
                                    <th class="py-2 px-3">Slots</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                @foreach ($batches as $b)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors {{ ($activeBatch && $activeBatch->id === $b->id) ? 'bg-indigo-50/30 dark:bg-indigo-950/20' : '' }}">
                                        <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            {{ $b->created_at?->format('M d, H:i') }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-[11px] text-slate-800 dark:text-slate-200">
                                            {{ substr($b->id, 0, 10) }}...
                                        </td>
                                        <td class="py-2.5 px-3 whitespace-nowrap">
                                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $b->validated_slots }}</span>
                                            <span class="text-slate-400">/ {{ $b->total_slots }}</span>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            @php
                                                $histBadge = match($b->status->value) {
                                                    'completed' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                                    'partially_completed' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                                    'failed' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                                                };
                                            @endphp
                                            <span class="px-2 py-0.5 text-[10px] font-bold rounded border uppercase {{ $histBadge }}">
                                                {{ $b->status->value }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-right">
                                            <a href="{{ route('admin.question-banks.generation.index', ['questionBank' => $questionBank->id, 'batch_id' => $b->id]) }}" 
                                               class="px-2 py-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 hover:underline">
                                                Inspect
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
