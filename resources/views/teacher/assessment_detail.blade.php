@extends('layouts.admin')

@section('title', 'Assessment Revision Summary — ' . $test->title)

@section('content')
<div class="space-y-6">
    {{-- Header & Navigation --}}
    <div class="gov-hero-indigo rounded-2xl p-6 sm:p-7 flex justify-between items-center flex-wrap gap-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 px-2.5 py-0.5 rounded-md text-[11px] font-extrabold uppercase tracking-wider">
                    {{ is_object($test->test_type) ? $test->test_type->value : strtoupper($test->test_type ?? 'TOEIC') }}
                </span>
                @if(in_array($test->status, ['needs_revision', 'revision_requested']))
                    <span class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700/50 px-2.5 py-0.5 rounded-md text-[11px] font-bold">
                        ⚠️ Needs Revision
                    </span>
                @elseif(in_array($test->status, ['pending', 'pending_approval']))
                    <span class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700/50 px-2.5 py-0.5 rounded-md text-[11px] font-bold">
                        ⏳ Pending Approval
                    </span>
                @elseif($test->status === 'approved' || $test->is_published)
                    <span class="bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/50 px-2.5 py-0.5 rounded-md text-[11px] font-bold">
                        🟢 Published & Live
                    </span>
                @else
                    <span class="bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 px-2.5 py-0.5 rounded-md text-[11px] font-bold">
                        📝 Draft
                    </span>
                @endif
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ $test->title }}</h1>
        </div>
        <div class="flex gap-3 flex-wrap items-center">
            @if(request('from') === 'revision_center')
            <a href="{{ route('teacher.revision-center') }}" class="gov-btn-secondary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                ← Back to Revision Center
            </a>
            @else
            <a href="{{ route('teacher.tests.index') }}" class="gov-btn-secondary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                ← Back to Assessments
            </a>
            @endif
            <a href="{{ route('teacher.dashboard') }}" class="gov-btn-primary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                Dashboard
            </a>
        </div>
    </div>

    @if(session('status'))
    <div class="p-4 rounded-xl text-sm font-bold bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl text-sm font-bold bg-rose-50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    @if(session('info'))
    <div class="p-4 rounded-xl text-sm font-bold bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-300 dark:border-indigo-800 text-indigo-800 dark:text-indigo-300">
        ℹ️ {{ session('info') }}
    </div>
    @endif

    {{-- TASK 3: Validation Assistant Summary Box --}}
    @if(isset($validationResult))
    <div class="p-5 rounded-2xl mb-6 {{ $validationResult['is_valid'] ? 'bg-emerald-50 dark:bg-emerald-950/25 border border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-100' : 'bg-rose-50 dark:bg-rose-950/25 border border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-100' }}">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h4 class="text-sm sm:text-base font-extrabold flex items-center gap-2 {{ $validationResult['is_valid'] ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300' }}">
                {{ $validationResult['is_valid'] ? '✅ Validation Assistant Passed' : '⚠️ Assessment Validation Errors Detected' }}
            </h4>
            <span class="text-xs font-bold text-slate-600 dark:text-slate-400">
                {{ count($validationResult['questions']) }} Total Linked Questions
            </span>
        </div>
        @if(!$validationResult['is_valid'])
        <div class="mt-3 text-xs text-rose-800 dark:text-rose-200 flex flex-col gap-1.5 font-medium">
            @foreach($validationResult['errors'] as $err)
            <div>• {{ $err }}</div>
            @endforeach
        </div>
        @else
        <div class="mt-1.5 text-xs text-emerald-800 dark:text-emerald-300/90 font-medium">
            All questions contain valid prompt stems, choice options, and correct answer selections. Ready for submission.
        </div>
        @endif
    </div>
    @endif

    {{-- Main 2-Column Grid Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6 items-start">

        {{-- Left Primary Column: Metadata & Revision Summary --}}
        <div>
            {{-- Repository Manager Feedback Callout --}}
            @if(in_array($test->status, ['needs_revision', 'revision_requested']) || $latestFeedbackLog)
            <div class="p-5 rounded-2xl mb-6 bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800/60 space-y-2.5">
                <div class="flex justify-between items-center">
                    <div class="text-xs font-extrabold text-amber-800 dark:text-amber-300 uppercase tracking-wider flex items-center gap-1.5">
                        <span>💬</span> Repository Manager Reviewer Feedback
                    </div>
                    <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                        {{ $latestFeedbackLog?->created_at?->diffForHumans() ?? 'Recently' }}
                    </div>
                </div>
                <div class="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-relaxed">
                    "{{ $latestFeedbackLog?->approval_note ?? 'Revision requested by Repository Manager. Please review test duration, section structures, and question answer keys before resubmitting.' }}"
                </div>
                <div class="text-xs text-slate-700 dark:text-slate-300 pt-1 flex items-center gap-1.5">
                    <span>👤</span> Reviewer: <strong class="text-slate-900 dark:text-white">{{ $latestFeedbackLog?->reviewer?->name ?? 'Repository Manager' }}</strong>
                </div>
            </div>
            @endif

            {{-- Candidate / Institutional Requirement Brief --}}
            @if($test->assessmentRequest)
            <div class="p-5 rounded-2xl mb-6 bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 space-y-2">
                <div class="flex justify-between items-center flex-wrap gap-2">
                    <div class="text-xs font-extrabold text-indigo-800 dark:text-indigo-300 uppercase tracking-wider flex items-center gap-1.5">
                        <span>📋</span> Institutional Requirement Brief
                    </div>
                    @if($test->assessmentRequest->candidate)
                    <span class="text-xs font-bold text-sky-800 dark:text-sky-300 bg-sky-100 dark:bg-sky-950/50 px-2.5 py-0.5 rounded-full border border-sky-300 dark:border-sky-800/60">
                        Candidate: {{ $test->assessmentRequest->candidate->name }}
                    </span>
                    @endif
                </div>
                <div class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ $test->assessmentRequest->title }}
                </div>
                @if($test->assessmentRequest->program_context)
                <div class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">
                    🎯 Program / Context: {{ $test->assessmentRequest->program_context }}
                </div>
                @endif
                @if($test->assessmentRequest->notes)
                <div class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ $test->assessmentRequest->notes }}
                </div>
                @endif
            </div>
            @endif

            {{-- Assessment Metadata Editor (TASK 1) --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-7 shadow-sm mb-6">
                <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2 mb-5">
                    <span>✏️</span> Assessment Authoring Editor
                </h3>

                <form id="assessment-settings-form" method="POST" action="{{ route('teacher.tests.update', $test->id) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Assessment Title</label>
                        <input type="text" name="title" value="{{ old('title', $test->title) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Assessment Type</label>
                            <select name="test_type" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="toeic" {{ $test->test_type === 'toeic' ? 'selected' : '' }}>TOEIC Simulation</option>
                                <option value="toefl" {{ $test->test_type === 'toefl' ? 'selected' : '' }}>TOEFL iBT / ITP</option>
                                <option value="ielts" {{ $test->test_type === 'ielts' ? 'selected' : '' }}>IELTS Academic</option>
                                <option value="general" {{ $test->test_type === 'general' ? 'selected' : '' }}>General English</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Duration (Minutes)</label>
                            <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $test->duration_minutes) }}" required min="1" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Pass Score Threshold</label>
                            <input type="number" name="pass_score" value="{{ old('pass_score', $test->pass_score) }}" required min="0" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Scoring Method</label>
                            <select name="scoring_method" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="automatic" {{ ($test->scoring_method?->value ?? $test->scoring_method ?? 'automatic') === 'automatic' ? 'selected' : '' }}>Automatic (Automatically scored; no examiner required)</option>
                                <option value="human" {{ ($test->scoring_method?->value ?? $test->scoring_method) === 'human' ? 'selected' : '' }}>Human (Requires examiner evaluation before final result)</option>
                                <option value="hybrid" {{ ($test->scoring_method?->value ?? $test->scoring_method) === 'hybrid' ? 'selected' : '' }}>Hybrid (Automatic scoring plus examiner evaluation)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            General Assessment Introduction / Candidate Instructions
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-normal lowercase ml-1">(shown to candidates on pre-test instruction screen before timed session)</span>
                        </label>
                        <textarea name="instructions" rows="4" placeholder="e.g. Welcome to the TOEIC Listening & Reading Test. Please ensure your headphones are connected..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 leading-relaxed">{{ old('instructions', $test->instructions) }}</textarea>
                    </div>

                    <div class="flex gap-3 pt-2 flex-wrap items-center">
                        {{-- Save Draft Button --}}
                        <button type="submit" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-2">
                            💾 Save Settings Draft
                        </button>
                    </form>

                    {{-- Resubmit Button --}}
                    @if(in_array($test->status, ['needs_revision', 'revision_requested', 'draft']))
                    <form id="resubmit-assessment-form" method="POST" action="{{ route('teacher.tests.resubmit', $test->id) }}" class="inline">
                        @csrf
                        @if(isset($validationResult) && $validationResult['is_valid'])
                        <button type="button"
                                onclick="openResubmitModal()"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-2">
                            🚀 {{ $test->status === 'draft' ? 'Submit for Review' : 'Resubmit for Review' }}
                        </button>
                        @else
                        <button type="button" disabled class="px-5 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700 text-xs font-bold rounded-xl cursor-not-allowed" title="Resolve all validation issues to enable submission.">
                            🚫 Submission Disabled (Validation Required)
                        </button>
                        @endif
                    </form>
                    @endif
                    </div>
            </div>

            {{-- TASK 2 & TASK 4: Revision Summary & Lazy Question Loading Explorer --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-7 shadow-sm">
                <div class="flex justify-between items-center mb-5 flex-wrap gap-4">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📋</span> Assessment Questions &amp; Sections — Progressive Revision Summary
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                            Organize institutional master questions and authored items for this assessment.
                        </p>
                    </div>

                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                    <div class="flex gap-2.5 flex-wrap">
                        <button type="button" onclick="openAttachMasterModal()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-1.5">
                            <span>🏛️</span> + Add from Question Bank
                        </button>
                        <button type="button" onclick="openCreateAuthoredQuestionModal()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all inline-flex items-center gap-1.5">
                            <span>✏️</span> + Add New Question
                        </button>
                        <button type="button" onclick="openAddSectionModal()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-bold rounded-xl transition-colors inline-flex items-center gap-1.5">
                            <span>📑</span> + Add Section
                        </button>
                    </div>
                    @endif
                </div>

                {{-- Assessment Sections & Directions Strip --}}
                @if($test->sections->isNotEmpty())
                <div class="bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-3 flex items-center gap-1.5">
                        <span>📑</span> Sections &amp; Directions Structure
                    </div>
                    <div class="flex flex-col gap-3">
                        @foreach($test->sections as $sec)
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm space-y-3">
                            <div class="flex justify-between items-start flex-wrap gap-3">
                                <div class="flex-1 min-w-[240px]">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="text-sm font-black text-slate-900 dark:text-white">{{ $sec->title }}</span>
                                        <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">
                                            {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type ?? 'Reading') }}
                                        </span>
                                        <span class="text-xs text-slate-600 dark:text-slate-400 font-semibold">
                                            • {{ $sec->testQuestions->count() }} question(s)
                                        </span>
                                    </div>
                                    @if($sec->instructions)
                                    <div class="bg-slate-50 dark:bg-slate-950 p-3 rounded-lg border-l-4 border-indigo-600 text-xs text-slate-800 dark:text-slate-300 leading-relaxed font-medium mt-2">
                                        {{ $sec->instructions }}
                                    </div>
                                    @else
                                    <div class="text-xs text-slate-500 dark:text-slate-400 italic mt-1">
                                        No section directions configured (optional).
                                    </div>
                                    @endif

                                    {{-- Attached Section Media Assets --}}
                                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800">
                                        <div class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                            <span>📂</span> Section Media Assets ({{ $sec->mediaAssets->count() }})
                                        </div>
                                        @if($sec->mediaAssets->isNotEmpty())
                                        <div class="flex flex-col gap-2">
                                            @foreach($sec->mediaAssets as $media)
                                            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg p-2.5 flex justify-between items-center flex-wrap gap-2">
                                                <div class="flex items-center gap-2.5 flex-1 min-w-[200px]">
                                                    <span class="text-lg">{{ $media->typeIcon() }}</span>
                                                    <div>
                                                        <div class="text-xs font-bold text-slate-900 dark:text-white">
                                                            {{ $media->title ?? $media->original_name }}
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-600 dark:text-slate-400 font-medium flex-wrap">
                                                            <span class="uppercase font-bold text-indigo-600 dark:text-indigo-400">{{ $media->type }}</span>
                                                            <span>• Order: {{ $media->pivot->order }}</span>
                                                            @if($media->pivot->caption)
                                                            <span class="italic">• Caption: "{{ $media->pivot->caption }}"</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button type="button"
                                                            onclick="previewAssetModal('{{ $media->id }}', '{{ addslashes($media->title ?? $media->original_name) }}', '{{ $media->type }}', '{{ route('media.preview', $media->id) }}')"
                                                            class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-[11px] font-bold hover:bg-slate-100 dark:hover:bg-slate-700">
                                                        👁️ Preview
                                                    </button>
                                                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                                                    <form method="POST" action="{{ route('teacher.tests.sections.media.detach', ['test' => $test->id, 'section' => $sec->id, 'media' => $media->id]) }}" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: 'Remove Media Asset?', message: 'Remove this media asset from the section?', confirmText: 'Remove', variant: 'danger', form: this });">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 text-[11px] font-bold hover:bg-rose-100">
                                                            ✕ Remove
                                                        </button>
                                                    </form>
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        @else
                                        <div class="text-xs text-slate-500 dark:text-slate-400 italic">
                                            No media assets attached to this section.
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                                @php
                                    $secQCount = $sec->testQuestions->count();
                                    $secMCount = $sec->mediaAssets->count();
                                    $secEscTitle = addslashes($sec->title);

                                    if ($secQCount === 0 && $secMCount === 0) {
                                        $secConfirmMsg = "Are you sure you want to remove section '{$secEscTitle}'? This section contains no questions.";
                                    } elseif ($secQCount > 0 && $secMCount === 0) {
                                        $secConfirmMsg = "Section '{$secEscTitle}' contains {$secQCount} question(s). Removing this section will remove those questions from this Assessment section. The underlying Question content will remain intact.";
                                    } elseif ($secQCount === 0 && $secMCount > 0) {
                                        $secConfirmMsg = "Are you sure you want to remove section '{$secEscTitle}'? Any media attached to this section will be detached but will remain available in the Media Library.";
                                    } else {
                                        $secConfirmMsg = "Section '{$secEscTitle}' contains {$secQCount} question(s). Removing this section will remove those questions from this Assessment section. The underlying Question content will remain intact. Any media attached to this section will be detached but will remain available in the Media Library.";
                                    }
                                @endphp
                                <div class="flex gap-2 flex-wrap items-center">
                                    <button type="button"
                                            onclick="openAttachSectionMediaModal('{{ $sec->id }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}')"
                                            class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 text-xs font-bold inline-flex items-center gap-1.5">
                                        📎 + Attach Media
                                    </button>
                                    <button type="button"
                                            data-section-id="{{ $sec->id }}"
                                            data-section-title="{{ $sec->title }}"
                                            data-section-type="{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}"
                                            data-section-instructions="{{ $sec->instructions ?? '' }}"
                                            onclick="openEditSectionModal(this)"
                                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1.5">
                                        ✏️ Edit Section
                                    </button>
                                    <form method="POST" action="{{ route('teacher.tests.destroy-section', ['test' => $test->id, 'section' => $sec->id]) }}" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: 'Remove Section?', message: '{{ $secConfirmMsg }}', confirmText: 'Remove Section', variant: 'danger', form: this });">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 text-xs font-bold inline-flex items-center gap-1.5">
                                            🗑 Remove Section
                                        </button>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(isset($validationResult) && count($validationResult['questions']) > 0)
                <div class="flex flex-col gap-3">
                    @php
                        $revisionQuestions = in_array($test->status, ['needs_revision', 'revision_requested'])
                            ? array_filter($validationResult['questions'], fn($item) => !empty($item['warnings']))
                            : $validationResult['questions'];
                        if (empty($revisionQuestions)) {
                            $revisionQuestions = $validationResult['questions'];
                        }
                    @endphp
                    @foreach($revisionQuestions as $qItem)
                    @php
                        $q = $qItem['question'];
                        $hasWarning = !empty($qItem['warnings']);
                        $isMaster = !empty($q->question_bank_id);
                    @endphp
                    <div class="bg-slate-50 dark:bg-slate-900 border {{ $hasWarning ? 'border-amber-300 dark:border-amber-700/60 bg-amber-50/40 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-800' }} rounded-xl p-4 shadow-sm flex justify-between items-center flex-wrap gap-3">
                        <div class="flex-1 min-w-[260px]">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400">Question #{{ $qItem['number'] }}</span>
                                <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-950 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-800">Section: {{ $qItem['section']->title }}</span>
                                @if($isMaster)
                                <span class="text-[11px] font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800/40 px-2 py-0.5 rounded">
                                    🏛️ Governed Master Question
                                </span>
                                @else
                                <span class="text-[11px] font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 border border-sky-200 dark:border-sky-800/40 px-2 py-0.5 rounded">
                                    ✍️ Assessment-Authored
                                </span>
                                @endif
                                @if($hasWarning)
                                <span class="text-[11px] font-bold text-amber-800 dark:text-amber-300 bg-amber-100 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-800/60 px-2 py-0.5 rounded">
                                    🟡 {{ implode(' | ', $qItem['warnings']) }}
                                </span>
                                @else
                                <span class="text-[11px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 px-2 py-0.5 rounded">
                                    🟢 Valid
                                </span>
                                @endif
                            </div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ \Illuminate\Support\Str::limit($q->prompt ?? '(Empty Stem)', 75) }}
                            </div>

                            {{-- Question-level Media Status Display --}}
                            @php
                                $qHasImg = !empty($q->image_url);
                                $qHasAudio = !empty($q->audio_url);
                            @endphp
                            <div class="mt-2.5 pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2.5 flex-wrap">
                                <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">MEDIA:</span>
                                @if($qHasImg)
                                    <div class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-950 border border-sky-200 dark:border-sky-800/50 px-2 py-1 rounded-md">
                                        <img src="{{ $q->image_url }}" alt="Thumbnail" class="w-5 h-5 object-cover rounded border border-slate-300 dark:border-slate-700">
                                        <span class="text-xs font-bold text-sky-700 dark:text-sky-300">🖼 Image ✓</span>
                                        <button type="button"
                                                onclick="previewAssetModal('', '{{ addslashes(basename($q->image_url)) }}', 'image', '{{ $q->image_url }}')"
                                                class="px-1.5 py-0.5 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/40 rounded text-[11px] font-bold">
                                            👁️ Preview
                                        </button>
                                    </div>
                                @endif

                                @if($qHasAudio)
                                    <div class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-950 border border-indigo-200 dark:border-indigo-800/50 px-2 py-1 rounded-md">
                                        <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">🎧 Audio ✓</span>
                                        <button type="button"
                                                onclick="previewAssetModal('', '{{ addslashes(basename($q->audio_url)) }}', 'audio', '{{ $q->audio_url }}')"
                                                class="px-1.5 py-0.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded text-[11px] font-bold">
                                            👁️ Preview
                                        </button>
                                    </div>
                                @endif

                                @if(!$qHasImg && !$qHasAudio)
                                    <span class="text-xs text-slate-500 dark:text-slate-400 italic">
                                        No question-level media attached.
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($isMaster)
                                <button type="button" onclick="openTeacherRequestRevisionModal('{{ $q->question_bank_id }}', '{{ $q->id }}', '{{ addslashes(Str::limit($q->prompt, 60)) }}')" class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800/50 text-xs font-bold inline-flex items-center gap-1">
                                    🛠 Request Master Revision
                                </button>
                                <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id]) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1">
                                    🔒 Governed Master
                                </a>
                            @else
                                <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id]) }}" class="px-3 py-1.5 rounded-xl {{ $hasWarning ? 'bg-amber-600 hover:bg-amber-500' : 'bg-indigo-600 hover:bg-indigo-500' }} text-white text-xs font-bold shadow-sm inline-flex items-center gap-1">
                                    {{ $hasWarning ? '✏️ Fix Issue' : '✏️ Edit Question' }}
                                </a>
                            @endif

                            @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                            <form action="{{ route('teacher.tests.destroy-question', ['test' => $test->id, 'question' => $q->id]) }}" method="POST" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: 'Remove Question?', message: 'Remove this question from the assessment?', confirmText: 'Remove', variant: 'danger', form: this });">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 text-xs font-bold">
                                    🗑 Remove
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-10 px-4 text-center bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 space-y-2">
                    <div class="text-3xl">📝</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">No questions linked to this assessment yet</div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 max-w-sm mx-auto">
                        Attach existing Master Questions from institutional Question Banks or author new custom questions for this test.
                    </p>
                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                    <div class="flex justify-center gap-2.5 pt-2">
                        <button type="button" onclick="openAttachMasterModal()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20">
                            🏛️ + Add from Question Bank
                        </button>
                        <button type="button" onclick="openCreateAuthoredQuestionModal()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20">
                            ✏️ + Add New Question
                        </button>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Right Sidebar: Metadata & Workflow History Timeline --}}
        <div>
            {{-- Assessment Summary Card --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm mb-6">
                <h4 class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider mb-4">
                    📊 Assessment Metrics
                </h4>

                <div class="flex flex-col gap-3 text-xs">
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Questions</span>
                        <strong class="text-indigo-600 dark:text-indigo-400 font-extrabold">{{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Sections</span>
                        <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $test->sections->count() }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Duration</span>
                        <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $test->duration_minutes }} Mins</strong>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Pass Threshold</span>
                        <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $test->pass_score }} Points</strong>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Author</span>
                        <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $test->creator?->name ?? 'Teacher' }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Last Updated</span>
                        <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $test->updated_at?->diffForHumans() }}</strong>
                    </div>
                </div>
            </div>

            {{-- Workflow History Timeline --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <h4 class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider mb-5">
                    ⏱ Governance Timeline
                </h4>

                <div class="flex flex-col gap-4 relative pl-4 border-l-2 border-slate-200 dark:border-slate-700">
                    @foreach($workflowTimeline as $item)
                    <div class="relative">
                        <div class="absolute -left-[1.35rem] top-1 w-2.5 h-2.5 rounded-full {{ $item['status'] === 'active' ? 'bg-amber-500 ring-4 ring-amber-100 dark:ring-amber-950/60' : ($item['status'] === 'completed' ? 'bg-emerald-500 ring-4 ring-emerald-100 dark:ring-emerald-950/60' : 'bg-slate-300 dark:bg-slate-700') }}"></div>
                        <div class="text-xs font-bold {{ $item['status'] === 'active' ? 'text-amber-700 dark:text-amber-400' : ($item['status'] === 'completed' ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400') }}">
                            {{ $item['step'] }}
                        </div>
                        @if($item['date'])
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $item['date'] }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Custom IAP Resubmission Confirmation Modal --}}
<div id="resubmit-confirmation-modal"
     class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4"
     role="dialog"
     aria-modal="true"
     aria-labelledby="resubmit-modal-title">

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 text-left transform transition-all">

        {{-- Modal Header --}}
        <div class="flex justify-between items-start">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-extrabold text-lg shadow-inner flex-shrink-0">
                    🚀
                </div>
                <div>
                    <h3 id="resubmit-modal-title" class="text-base font-bold text-slate-900 dark:text-white leading-tight">
                        Resubmit Assessment for Review?
                    </h3>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">
                        {{ $test->title }}
                    </p>
                </div>
            </div>
            <button type="button"
                    onclick="closeResubmitModal()"
                    class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none"
                    aria-label="Close modal">
                ✕
            </button>
        </div>

        {{-- Modal Body Messages --}}
        <div class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
            <p class="leading-relaxed">
                Your assessment has passed the Validation Assistant checks and is ready to be resubmitted to the Repository Manager for governance review.
            </p>
            <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-600 dark:text-slate-400 flex items-start gap-2.5">
                <span class="text-indigo-600 dark:text-indigo-400 text-sm flex-shrink-0">ℹ️</span>
                <span class="leading-normal text-[11px]">
                    After resubmission, the Repository Manager will review the assessment and its linked repository requirements.
                </span>
            </div>
        </div>

        {{-- Modal Footer Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button type="button"
                    onclick="closeResubmitModal()"
                    class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs rounded-xl border border-slate-300 dark:border-slate-700 transition-colors">
                Cancel
            </button>
            <button type="button"
                    id="confirm-resubmit-btn"
                    onclick="confirmAndSubmitResubmit()"
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/30 transition-all inline-flex items-center gap-1.5">
                🚀 {{ $test->status === 'draft' ? 'Submit for Review' : 'Resubmit for Review' }}
            </button>
        </div>
    </div>
</div>

{{-- Modal 1: Attach Master Question from Question Bank --}}
<div id="attach-master-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeAttachMasterModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-xl w-full p-6 sm:p-7 shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🏛️</span> Attach Master Question from Question Bank
            </div>
            <button type="button" onclick="closeAttachMasterModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.tests.attach-master-question', $test->id) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Target Section <span class="text-rose-500">*</span></label>
                <select name="test_section_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    @foreach($test->sections as $sec)
                    <option value="{{ $sec->id }}">{{ $sec->title }} ({{ $sec->testQuestions->count() }} items)</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Select Master Question <span class="text-rose-500">*</span></label>
                <select name="question_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">-- Choose from Published Question Banks --</option>
                    @if(isset($publishedQuestionBanks))
                    @foreach($publishedQuestionBanks as $bank)
                        @php $bankType = is_object($bank->test_type) ? $bank->test_type->value : (string) $bank->test_type; @endphp
                        <optgroup label="🏛️ {{ $bank->title }} ({{ strtoupper($bankType) }})">
                            @foreach($bank->questions as $bq)
                            <option value="{{ $bq->id }}">[{{ strtoupper($bq->difficulty?->value ?? $bq->difficulty ?? 'MED') }}] {{ \Illuminate\Support\Str::limit($bq->prompt, 70) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                    @endif
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeAttachMasterModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20">Attach Master Question</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2: Create Assessment-Authored Question --}}
<div id="create-authored-question-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeCreateAuthoredQuestionModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full p-6 sm:p-7 shadow-2xl max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>✏️</span> Create Assessment-Authored Question
            </div>
            <button type="button" onclick="closeCreateAuthoredQuestionModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        <form id="create-authored-question-form" method="POST" action="{{ route('teacher.tests.create-question', $test->id) }}" onsubmit="return validateCreateQuestionForm(this)" class="space-y-4">
            @csrf

            @php
                $isToeicTest = (is_object($test->test_type) ? $test->test_type->value : (string)$test->test_type) === 'toeic';
            @endphp

            @if($isToeicTest)
            <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 p-4 rounded-xl space-y-1.5">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-indigo-900 dark:text-indigo-200">🎯 TOEIC Part Selection <span class="text-rose-500">*</span></label>
                <select name="part_number" id="create-q-part-number" onchange="onCreateModalToeicPartChange(this.value)" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-indigo-300 dark:border-indigo-700 rounded-xl text-slate-900 dark:text-white text-xs font-bold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="1">Part 1: Photographs (Listening — Image &amp; Audio Required, 4 Choices)</option>
                    <option value="2">Part 2: Question-Response (Listening — Audio Required, Exactly 3 Choices)</option>
                    <option value="3">Part 3: Conversations (Listening — Audio Required, 4 Choices)</option>
                    <option value="4">Part 4: Talks (Listening — Audio Required, 4 Choices)</option>
                    <option value="5">Part 5: Incomplete Sentences (Reading — Audio Forbidden, 4 Choices)</option>
                    <option value="6">Part 6: Text Completion (Reading — Passage Required, 4 Choices)</option>
                    <option value="7">Part 7: Reading Comprehension (Reading — Passage Required, 4 Choices)</option>
                </select>
                <input type="hidden" name="section" id="create-q-section" value="listening">
            </div>

            <div id="create-q-passage-container" class="hidden bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 rounded-xl space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">📖 Reading Passage Text <span class="text-rose-500">*</span></label>
                <textarea name="passage_text" id="create-q-passage-text" rows="3" placeholder="Enter reading passage text for Part 6 / 7..." class="w-full px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs"></textarea>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Target Section <span class="text-rose-500">*</span></label>
                    <select name="test_section_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        @foreach($test->sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Question Type <span class="text-rose-500">*</span></label>
                    <select name="question_type" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="single_choice">Single Choice</option>
                        @if(!$isToeicTest)
                        <option value="short_answer">Short Answer</option>
                        <option value="essay">Essay</option>
                        @endif
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Question Prompt / Stem <span class="text-rose-500">*</span></label>
                <textarea name="prompt" required rows="3" placeholder="Enter the complete question prompt..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Difficulty <span class="text-rose-500">*</span></label>
                <select name="difficulty" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="easy">Easy</option>
                    <option value="medium" selected>Medium</option>
                    <option value="hard">Hard</option>
                </select>
                <input type="hidden" name="points" value="1">
            </div>

            {{-- Question Media Section --}}
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 rounded-xl space-y-3">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-800 dark:text-slate-200 block">🖼️ / 🎧 Question Media (Optional)</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Attach Question-level Photo (Image) and/or Audio Prompt (e.g. TOEIC Part 1 Photographs).</span>
                </div>

                <input type="hidden" id="q-media-asset-id" name="media_asset_id" value="">
                <input type="hidden" id="q-image-url" name="image_url" value="">
                <input type="hidden" id="q-audio-url" name="audio_url" value="">

                {{-- Attached Media Previews --}}
                <div id="q-attached-media-container" class="flex flex-col gap-2.5 mt-2">
                    {{-- Image preview card --}}
                    <div id="q-preview-image-card" class="hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <img id="q-preview-image-thumb" src="" alt="Thumbnail" class="w-12 h-12 object-cover rounded-lg border border-slate-200 dark:border-slate-700">
                            <div>
                                <span class="text-xs font-bold text-sky-700 dark:text-sky-300 block">🖼️ Attached Image (Photograph)</span>
                                <span id="q-preview-image-title" class="text-[11px] text-slate-600 dark:text-slate-400 max-w-xs truncate block"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="previewQuestionModalMedia('create', 'image')" class="px-2.5 py-1 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/40 rounded-lg text-xs font-bold">
                                👁️ Preview
                            </button>
                            <button type="button" onclick="openQuestionMediaPicker('create', 'image')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded-lg text-xs font-bold">
                                Change
                            </button>
                            <button type="button" onclick="removeQuestionAttachedMedia('create', 'image')" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 rounded-lg text-xs font-bold">
                                ✕ Remove
                            </button>
                        </div>
                    </div>

                    {{-- Image empty placeholder --}}
                    <div id="q-empty-image-card" class="flex items-center justify-between bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🖼️</span>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Question Photograph / Image</span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">No image attached</span>
                            </div>
                        </div>
                        <button type="button" onclick="openQuestionMediaPicker('create', 'image')" class="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/40 rounded-xl text-xs font-bold">
                            + Attach Image
                        </button>
                    </div>

                    {{-- Audio preview card --}}
                    <div id="q-preview-audio-card" class="hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3 flex-1">
                            <span class="text-xl">🎧</span>
                            <div class="flex-1">
                                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 block">🎵 Attached Audio Prompt</span>
                                <span id="q-preview-audio-title" class="text-[11px] text-slate-600 dark:text-slate-400 max-w-xs truncate block mb-1"></span>
                                <audio id="q-preview-audio-player" controls class="h-7 w-full max-w-xs" src=""></audio>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="previewQuestionModalMedia('create', 'audio')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded-lg text-xs font-bold">
                                👁️ Preview
                            </button>
                            <button type="button" onclick="openQuestionMediaPicker('create', 'audio')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded-lg text-xs font-bold">
                                Change
                            </button>
                            <button type="button" onclick="removeQuestionAttachedMedia('create', 'audio')" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 rounded-lg text-xs font-bold">
                                ✕ Remove
                            </button>
                        </div>
                    </div>

                    {{-- Audio empty placeholder --}}
                    <div id="q-empty-audio-card" class="flex items-center justify-between bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🎧</span>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Question Audio Prompt</span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">No audio attached</span>
                            </div>
                        </div>
                        <button type="button" onclick="openQuestionMediaPicker('create', 'audio')" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded-xl text-xs font-bold">
                            + Attach Audio
                        </button>
                    </div>
                </div>
            </div>

            {{-- Choices Section with Explicit Visual Correct Answer Indicator --}}
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 rounded-xl space-y-3">
                <div class="flex justify-between items-center flex-wrap gap-2">
                    <label class="text-xs font-extrabold uppercase tracking-wider text-slate-800 dark:text-slate-200 m-0">Multiple Choice Options &amp; Correct Answer</label>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Select exactly one radio button as the correct answer.</span>
                </div>

                <div id="create-q-validation-error" class="hidden bg-rose-50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 p-2.5 rounded-xl text-xs font-bold">
                    ⚠️ Please select the correct answer.
                </div>

                <div class="flex flex-col gap-2.5">
                    @for($i = 0; $i < 4; $i++)
                    @php $label = chr(65 + $i); @endphp
                    <div class="create-choice-row flex items-center gap-2.5 bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 transition-all" id="create-choice-row-{{ $i }}">
                        <input type="radio" name="correct_choice" value="{{ $i }}" id="create-correct-{{ $i }}" onchange="updateCreateModalCorrectChoice()" class="accent-emerald-600 w-4 h-4 cursor-pointer">
                        <label for="create-correct-{{ $i }}" class="font-extrabold text-indigo-600 dark:text-indigo-400 text-xs w-5 cursor-pointer">{{ $label }}.</label>
                        <input type="text" name="choices[]" placeholder="Option {{ $label }} text" {{ $i < 2 ? 'required' : '' }} class="flex-1 px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <span class="create-correct-badge hidden items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-[10px] font-extrabold uppercase whitespace-nowrap" id="create-correct-badge-{{ $i }}">
                            ✓ CORRECT ANSWER
                        </span>
                    </div>
                    @endfor
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Answer Explanation / Rationale</label>
                <textarea name="explanation" rows="2" placeholder="Optional explanation..." class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeCreateAuthoredQuestionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20">Save Question</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 3: Add Section --}}
<div id="add-section-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeAddSectionModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 sm:p-7 shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑</span> Add Assessment Section
            </div>
            <button type="button" onclick="closeAddSectionModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.tests.add-section', $test->id) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Section Title <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required placeholder="e.g. Part 1: Photographs" class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Section Type</label>
                <select name="section_type" class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">⚡ Auto-detect from Section Title (Recommended)</option>
                    <option value="listening">🎧 Listening Section</option>
                    <option value="reading">📖 Reading Section</option>
                    <option value="speaking">🎙 Speaking Section</option>
                    <option value="writing">✍️ Writing Section</option>
                </select>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-1">
                    If left on auto-detect, the system will infer the section type from standard exam terminology.
                </span>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Section Instructions / Directions (Optional)</label>
                <textarea name="instructions" rows="3" placeholder="e.g. Directions: For each question in this part, you will hear four statements..." class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs leading-relaxed"></textarea>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-1">
                    Directions shown to candidates upon entering this section.
                </span>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeAddSectionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20">Add Section</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 3b: Edit Section --}}
<div id="edit-section-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeEditSectionModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 sm:p-7 shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>✏️</span> Edit Assessment Section
            </div>
            <button type="button" onclick="closeEditSectionModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        <form id="edit-section-form" method="POST" action="" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Section Title <span class="text-rose-500">*</span></label>
                <input type="text" id="edit-section-title" name="title" required class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Section Type</label>
                <select id="edit-section-type" name="section_type" class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">⚡ Auto-detect from Section Title (Recommended)</option>
                    <option value="listening">🎧 Listening Section</option>
                    <option value="reading">📖 Reading Section</option>
                    <option value="speaking">🎙 Speaking Section</option>
                    <option value="writing">✍️ Writing Section</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Section Instructions / Directions (Optional)</label>
                <textarea id="edit-section-instructions" name="instructions" rows="3" placeholder="e.g. Directions: For each question in this part, you will hear four statements..." class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs leading-relaxed"></textarea>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-1">
                    Directions displayed to candidates upon entering this section.
                </span>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeEditSectionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20">Update Section</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 4: Teacher Request Repository Revision on Master Question --}}
<div id="teacher-request-revision-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeTeacherRequestRevisionModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 sm:p-7 shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🛠</span> Request Repository Revision
            </div>
            <button type="button" onclick="closeTeacherRequestRevisionModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.repository-revisions.request') }}" class="space-y-4">
            @csrf
            <input type="hidden" id="tr-modal-bank-id" name="question_bank_id" value="">
            <input type="hidden" id="tr-modal-question-id" name="question_id" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Target Question Item</label>
                <div id="tr-modal-target-title" class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-indigo-700 dark:text-indigo-300 text-xs font-bold truncate"></div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Revision Rationale / Specific Feedback <span class="text-rose-500">*</span></label>
                <textarea name="notes" required rows="4" placeholder="Explain the specific issue with this Master Question (typo, wrong key, flawed passage)..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeTeacherRequestRevisionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-md shadow-amber-600/20">Submit Revision Request</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 5: Attach Section Media from Library or Direct Upload --}}
<div id="attach-section-media-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeAttachSectionMediaModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[92vh] flex flex-col p-6 sm:p-7 shadow-2xl space-y-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📎</span> Attach Media Asset to Section
                </div>
                <div id="asm-target-section-title" class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5 font-bold"></div>
            </div>
            <button type="button" onclick="closeAttachSectionMediaModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        {{-- Top Navigation: Choose from Library vs Upload New Media --}}
        <div class="flex gap-1.5 p-1 bg-slate-100 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
            <button type="button" id="asm-tab-library" onclick="switchAsmMode('library')" class="flex-1 py-2 px-3 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                <span>📚</span> Choose from Media Library
            </button>
            <button type="button" id="asm-tab-upload" onclick="switchAsmMode('upload')" class="flex-1 py-2 px-3 bg-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span>⬆️</span> + Upload New Media
            </button>
        </div>

        {{-- Mode A: Direct Media Upload Panel --}}
        <div id="asm-upload-panel" class="hidden bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-3">
            <div class="text-xs font-extrabold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                <span>🚀</span> Upload Media to Institutional Library
            </div>

            <div id="asm-upload-feedback" class="hidden p-2.5 rounded-lg text-xs font-bold"></div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Select Local File <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" id="asm-upload-file" accept=".jpg,.jpeg,.png,.webp,.mp3,.wav,.m4a,.pdf,image/*,audio/*,application/pdf" class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Media Title (Optional)
                    </label>
                    <input type="text" id="asm-upload-title" placeholder="e.g. Part 1 Listening Directions Audio" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs font-medium">
                </div>
            </div>

            <div class="flex justify-between items-center flex-wrap gap-2 pt-1">
                <div class="text-[11px] text-slate-500 dark:text-slate-400">
                    Supported: JPG, PNG, WebP, MP3, WAV, M4A, PDF (Max: 10 MB)
                </div>
                <button type="button" id="asm-upload-btn" onclick="uploadSectionMediaFile()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 inline-flex items-center gap-1.5">
                    <span>⬆️</span> Upload &amp; Select Asset
                </button>
            </div>
        </div>

        <form id="attach-section-media-form" method="POST" action="" class="flex flex-col flex-1 min-h-0 space-y-3">
            @csrf
            <input type="hidden" id="asm-media-asset-id" name="media_asset_id" value="" required>

            {{-- Selected Media Badge --}}
            <div id="asm-selected-preview" class="hidden p-3 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/50 rounded-xl items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span id="asm-selected-icon" class="text-xl">📎</span>
                    <div>
                        <div id="asm-selected-title" class="text-xs font-bold text-indigo-950 dark:text-indigo-100"></div>
                        <div id="asm-selected-meta" class="text-[11px] text-indigo-700 dark:text-indigo-300 font-medium"></div>
                    </div>
                </div>
                <button type="button" onclick="clearSelectedSectionMedia()" class="px-2.5 py-1 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[10px] font-bold">Clear</button>
            </div>

            {{-- Section-Specific Caption and Order Inputs --}}
            <div class="grid grid-cols-1 sm:grid-cols-[2fr_1fr] gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Section Media Caption / Directions Label (Optional)
                    </label>
                    <input type="text" id="asm-caption" name="caption" placeholder="e.g. Listening Directions Audio, Reference Photograph" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Display Order</label>
                    <input type="number" id="asm-order" name="order" min="1" placeholder="Auto (Next)" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs font-medium">
                </div>
            </div>

            {{-- Mode B: Library Browser Panel --}}
            <div id="asm-library-panel" class="flex flex-col flex-1 min-h-0 space-y-2.5">
                {{-- Media Filter Tabs & Search --}}
                <div class="flex gap-2 flex-wrap items-center justify-between">
                    <div class="flex gap-1.5 flex-wrap">
                        <button type="button" onclick="filterSectionMediaModal('all')" class="asm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm">All Media</button>
                        <button type="button" onclick="filterSectionMediaModal('audio')" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">🎵 Audio Tracks</button>
                        <button type="button" onclick="filterSectionMediaModal('image')" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">🖼️ Images</button>
                        <button type="button" onclick="filterSectionMediaModal('passage')" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">📖 Passages</button>
                        <button type="button" onclick="filterSectionMediaModal('pdf')" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">📄 PDFs</button>
                    </div>
                    <input type="text" id="asm-search-input" onkeyup="searchSectionMediaModal(this.value)" placeholder="Search media by title..." class="px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs min-w-[180px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                {{-- Media Grid Container --}}
                <div id="asm-media-list-container" class="flex-1 min-h-[200px] max-h-[260px] overflow-y-auto bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeAttachSectionMediaModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
                <button type="submit" id="asm-submit-btn" disabled class="px-5 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all">Attach Selected Media</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 6: General Asset Preview Modal --}}
<div id="asset-preview-modal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeAssetPreviewModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto p-6 shadow-2xl space-y-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
            <div id="apm-title" class="text-sm sm:text-base font-black text-slate-900 dark:text-white truncate">Preview Asset</div>
            <button type="button" onclick="closeAssetPreviewModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>
        <div id="apm-content" class="flex justify-center items-center min-h-[180px]">
        </div>
    </div>
</div>

{{-- Modal 7: Question Media Picker Modal --}}
<div id="question-media-picker-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeQuestionMediaPicker(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col p-6 sm:p-7 shadow-2xl space-y-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📎</span> Attach Question Media
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 font-medium">
                    Select an Image (photograph) or Audio prompt from the Institutional Media Library, or upload directly.
                </div>
            </div>
            <button type="button" onclick="closeQuestionMediaPicker()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        {{-- Direct Upload Toggle Bar --}}
        <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 flex justify-between items-center flex-wrap gap-2.5">
            <div class="flex items-center gap-2">
                <span class="text-lg">⬆️</span>
                <input type="file" id="qm-direct-file-input" accept="image/*,audio/*,application/pdf" class="text-xs text-slate-700 dark:text-slate-300">
            </div>
            <button type="button" id="qm-upload-btn" onclick="uploadQuestionMediaFile()" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-sm">
                Upload &amp; Attach
            </button>
        </div>

        {{-- Library Filters & Search --}}
        <div class="flex gap-2 flex-wrap items-center justify-between">
            <div class="flex gap-1.5 flex-wrap">
                <button type="button" onclick="filterQuestionMediaModal('all', event)" class="qm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm">All Media</button>
                <button type="button" onclick="filterQuestionMediaModal('image', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">🖼️ Images</button>
                <button type="button" onclick="filterQuestionMediaModal('audio', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">🎵 Audio Tracks</button>
                <button type="button" onclick="filterQuestionMediaModal('passage', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">📖 Passages</button>
                <button type="button" onclick="filterQuestionMediaModal('pdf', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">📄 PDFs</button>
            </div>
            <input type="text" id="qm-search-input" onkeyup="searchQuestionMediaModal(this.value)" placeholder="Search media library..." class="px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs min-w-[180px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
        </div>

        {{-- Media Grid Container --}}
        <div id="qm-media-list-container" class="flex-1 min-h-[220px] max-h-[300px] overflow-y-auto bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" onclick="closeQuestionMediaPicker()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Close</button>
        </div>
    </div>
</div>

<script>
    let sectionMediaLibrary = [];
    let questionMediaLibrary = [];
    let currentAsmSectionId = null;
    let currentQuestionMediaTargetMode = 'create';

    function openQuestionMediaPicker(mode = 'create', defaultType = 'all') {
        currentQuestionMediaTargetMode = mode;
        const modal = document.getElementById('question-media-picker-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
        fetchQuestionMediaLibrary(defaultType);
    }

    function closeQuestionMediaPicker(e) {
        if (!e || e.target === document.getElementById('question-media-picker-modal')) {
            const modal = document.getElementById('question-media-picker-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function fetchQuestionMediaLibrary(defaultType = 'all') {
        const container = document.getElementById('qm-media-list-container');
        if (questionMediaLibrary.length > 0) {
            filterQuestionMediaModal(defaultType);
            return;
        }

        container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>';
        fetch('/admin/media/list')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    questionMediaLibrary = data.data;
                    filterQuestionMediaModal(defaultType);
                } else {
                    container.innerHTML = '<div class="col-span-full text-center text-rose-600 dark:text-rose-400 text-xs py-8">Failed to load media library.</div>';
                }
            })
            .catch(() => {
                container.innerHTML = '<div class="col-span-full text-center text-rose-600 dark:text-rose-400 text-xs py-8">Error communicating with media server.</div>';
            });
    }

    function filterQuestionMediaModal(type, e = null) {
        const buttons = document.querySelectorAll('.qm-filter-btn');
        buttons.forEach(btn => {
            btn.className = 'qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold';
        });
        if (e && e.target && e.target.classList.contains('qm-filter-btn')) {
            e.target.className = 'qm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm';
        }

        const query = (document.getElementById('qm-search-input')?.value || '').toLowerCase();
        let filtered = questionMediaLibrary;
        if (type !== 'all') {
            filtered = filtered.filter(item => item.type === type);
        }
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderQuestionMediaGrid(filtered);
    }

    function searchQuestionMediaModal(query) {
        query = query.toLowerCase();
        let filtered = questionMediaLibrary;
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderQuestionMediaGrid(filtered);
    }

    function renderQuestionMediaGrid(items) {
        const container = document.getElementById('qm-media-list-container');
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">No media assets found in library.</div>';
            return;
        }

        container.innerHTML = '';
        items.forEach(media => {
            const card = document.createElement('div');
            card.className = 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-700 transition-all flex flex-col justify-between gap-2.5';

            const icon = media.type === 'audio' ? '🎵' : (media.type === 'image' ? '🖼️' : (media.type === 'pdf' ? '📄' : (media.type === 'passage' ? '📖' : '📎')));

            card.innerHTML = `
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">${icon} ${media.type}</span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">${media.size || ''}</span>
                    </div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white truncate" title="${media.title || media.name}">
                        ${media.title || media.name}
                    </div>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="previewAssetModal('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-lg text-[11px] font-bold">👁️ Preview</button>
                    <button type="button" onclick="selectQuestionMediaItem('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[11px] font-bold shadow-sm">Attach</button>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function selectQuestionMediaItem(id, title, type, url) {
        const prefix = (currentQuestionMediaTargetMode === 'edit') ? 'eq-' : 'q-';
        const mediaIdInput = document.getElementById(prefix + 'media-asset-id');
        const imgInput = document.getElementById(prefix + 'image-url');
        const audioInput = document.getElementById('q-audio-url');

        if (mediaIdInput) mediaIdInput.value = id;

        if (type === 'image') {
            if (imgInput) imgInput.value = url;
            const prevCard = document.getElementById(prefix + 'preview-image-card');
            const emptyCard = document.getElementById(prefix + 'empty-image-card');
            const thumb = document.getElementById(prefix + 'preview-image-thumb');
            const titleEl = document.getElementById(prefix + 'preview-image-title');

            if (thumb) thumb.src = url;
            if (titleEl) titleEl.textContent = title;
            if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
            if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
        } else if (type === 'audio') {
            if (audioInput) audioInput.value = url;
            const prevCard = document.getElementById(prefix + 'preview-audio-card');
            const emptyCard = document.getElementById(prefix + 'empty-audio-card');
            const titleEl = document.getElementById(prefix + 'preview-audio-title');
            const player = document.getElementById(prefix + 'preview-audio-player');

            if (titleEl) titleEl.textContent = title;
            if (player) player.src = url;
            if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
            if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
        }

        closeQuestionMediaPicker();
    }

    function removeQuestionAttachedMedia(mode, type) {
        const prefix = (mode === 'edit') ? 'eq-' : 'q-';
        const imgInput = document.getElementById(prefix + 'image-url');
        const audioInput = document.getElementById(prefix + 'audio-url');
        const mediaIdInput = document.getElementById(prefix + 'media-asset-id');

        if (type === 'image') {
            if (imgInput) imgInput.value = '';
            const prevCard = document.getElementById(prefix + 'preview-image-card');
            const emptyCard = document.getElementById(prefix + 'empty-image-card');
            const thumb = document.getElementById(prefix + 'preview-image-thumb');
            if (thumb) thumb.src = '';
            if (prevCard) { prevCard.classList.add('hidden'); prevCard.style.display = 'none'; }
            if (emptyCard) { emptyCard.classList.remove('hidden'); emptyCard.style.display = 'flex'; }
        } else if (type === 'audio') {
            if (audioInput) audioInput.value = '';
            const prevCard = document.getElementById(prefix + 'preview-audio-card');
            const emptyCard = document.getElementById(prefix + 'empty-audio-card');
            const player = document.getElementById(prefix + 'preview-audio-player');
            if (player) player.src = '';
            if (prevCard) { prevCard.classList.add('hidden'); prevCard.style.display = 'none'; }
            if (emptyCard) { emptyCard.classList.remove('hidden'); emptyCard.style.display = 'flex'; }
        }

        if (imgInput && audioInput && !imgInput.value && !audioInput.value && mediaIdInput) {
            mediaIdInput.value = '';
        }
    }

    function uploadQuestionMediaFile() {
        const fileInput = document.getElementById('qm-direct-file-input');
        const uploadBtn = document.getElementById('qm-upload-btn');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert('Please select a file to upload.');
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '⏳ Uploading...';

        fetch('{{ route('admin.media.store') }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok || !data.success) {
                const msg = data.message || 'Upload failed.';
                throw new Error(msg);
            }
            return data;
        })
        .then(data => {
            questionMediaLibrary.unshift({
                id: data.id,
                title: data.title || data.filename,
                name: data.filename,
                type: data.type,
                size: data.size,
                url: data.url
            });
            selectQuestionMediaItem(data.id, data.title || data.filename, data.type, data.url);
            fileInput.value = '';
        })
        .catch(err => {
            alert('Upload error: ' + err.message);
        })
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = 'Upload &amp; Attach';
        });
    }

    function openAttachMasterModal() {
        const modal = document.getElementById('attach-master-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }
    function closeAttachMasterModal(e) {
        if (!e || e.target === document.getElementById('attach-master-modal')) {
            const modal = document.getElementById('attach-master-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function onCreateModalToeicPartChange(part) {
        part = parseInt(part);
        const secInput = document.getElementById('create-q-section');
        if (secInput) {
            secInput.value = (part >= 1 && part <= 4) ? 'listening' : 'reading';
        }

        const passageBox = document.getElementById('create-q-passage-container');
        if (passageBox) {
            if (part === 6 || part === 7) {
                passageBox.classList.remove('hidden');
                passageBox.style.display = 'block';
            } else {
                passageBox.classList.add('hidden');
                passageBox.style.display = 'none';
            }
        }

        const choiceRow3 = document.getElementById('create-choice-row-3');
        const choiceInput3 = choiceRow3 ? choiceRow3.querySelector('input[type="text"]') : null;

        if (part === 2) {
            if (choiceRow3) choiceRow3.style.display = 'none';
            if (choiceInput3) {
                choiceInput3.value = '';
                choiceInput3.removeAttribute('required');
            }
        } else {
            if (choiceRow3) choiceRow3.style.display = 'flex';
        }
    }

    function updateCreateModalCorrectChoice() {
        const form = document.getElementById('create-authored-question-form');
        if (!form) return;
        const selected = form.querySelector('input[name="correct_choice"]:checked');
        const errBox = document.getElementById('create-q-validation-error');
        if (errBox && selected) {
            errBox.classList.add('hidden');
            errBox.style.display = 'none';
        }

        for (let i = 0; i < 4; i++) {
            const badge = document.getElementById(`create-correct-badge-${i}`);
            const row = document.getElementById(`create-choice-row-${i}`);
            const isSelected = selected && selected.value === String(i);

            if (badge) {
                if (isSelected) {
                    badge.classList.remove('hidden');
                    badge.style.display = 'inline-flex';
                } else {
                    badge.classList.add('hidden');
                    badge.style.display = 'none';
                }
            }
            if (row) {
                if (isSelected) {
                    row.className = 'create-choice-row flex items-center gap-2.5 bg-emerald-50/60 dark:bg-emerald-950/20 p-2.5 rounded-xl border border-emerald-400 dark:border-emerald-700 transition-all';
                } else {
                    row.className = 'create-choice-row flex items-center gap-2.5 bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 transition-all';
                }
            }
        }
    }

    function validateCreateQuestionForm(form) {
        const qType = form.querySelector('select[name="question_type"]')?.value || 'multiple_choice';
        if (['multiple_choice', 'single_choice'].includes(qType) || form.querySelectorAll('input[name="choices[]"]').length > 0) {
            const selected = form.querySelector('input[name="correct_choice"]:checked');
            if (!selected) {
                const errBox = document.getElementById('create-q-validation-error');
                if (errBox) {
                    errBox.textContent = '⚠️ Please select the correct answer.';
                    errBox.classList.remove('hidden');
                    errBox.style.display = 'block';
                    errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                return false;
            }
        }
        return true;
    }

    function openCreateAuthoredQuestionModal() {
        const modal = document.getElementById('create-authored-question-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            // Reset correct radio selection
            const checkedRadios = modal.querySelectorAll('input[name="correct_choice"]:checked');
            checkedRadios.forEach(r => r.checked = false);
            const errBox = document.getElementById('create-q-validation-error');
            if (errBox) {
                errBox.classList.add('hidden');
                errBox.style.display = 'none';
            }

            // Reset media inputs
            const imgInput = document.getElementById('q-image-url');
            const audioInput = document.getElementById('q-audio-url');
            const mediaIdInput = document.getElementById('q-media-asset-id');
            if (imgInput) imgInput.value = '';
            if (audioInput) audioInput.value = '';
            if (mediaIdInput) mediaIdInput.value = '';

            const prevImg = document.getElementById('q-preview-image-card');
            const emptyImg = document.getElementById('q-empty-image-card');
            const prevAudio = document.getElementById('q-preview-audio-card');
            const emptyAudio = document.getElementById('q-empty-audio-card');
            const audioPlayer = document.getElementById('q-preview-audio-player');

            if (prevImg) { prevImg.classList.add('hidden'); prevImg.style.display = 'none'; }
            if (emptyImg) { emptyImg.classList.remove('hidden'); emptyImg.style.display = 'flex'; }
            if (prevAudio) { prevAudio.classList.add('hidden'); prevAudio.style.display = 'none'; }
            if (emptyAudio) { emptyAudio.classList.remove('hidden'); emptyAudio.style.display = 'flex'; }
            if (audioPlayer) audioPlayer.src = '';

            const partSelect = document.getElementById('create-q-part-number');
            if (partSelect) {
                onCreateModalToeicPartChange(partSelect.value);
            }

            updateCreateModalCorrectChoice();
        }
    }
    function closeCreateAuthoredQuestionModal(e) {
        const modal = document.getElementById('create-authored-question-modal');
        if (!modal) return;

        // Direct invocation (no event argument) or backdrop click (e.target is the backdrop itself)
        if (!e || e.target === modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function openAddSectionModal() {
        const modal = document.getElementById('add-section-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }
    function closeAddSectionModal(e) {
        if (!e || e.target === document.getElementById('add-section-modal')) {
            const modal = document.getElementById('add-section-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function openEditSectionModal(btn) {
        if (!btn) return;
        const sectionId = btn.getAttribute('data-section-id') || '';
        const title = btn.getAttribute('data-section-title') || '';
        const sectionType = btn.getAttribute('data-section-type') || '';
        const instructions = btn.getAttribute('data-section-instructions') || '';

        const form = document.getElementById('edit-section-form');
        if (form && sectionId) {
            form.action = `/teacher/assessments/{{ $test->id }}/sections/${sectionId}`;
        }
        const titleInput = document.getElementById('edit-section-title');
        if (titleInput) titleInput.value = title;
        const typeSelect = document.getElementById('edit-section-type');
        if (typeSelect) typeSelect.value = sectionType;
        const instructionsInput = document.getElementById('edit-section-instructions');
        if (instructionsInput) instructionsInput.value = instructions;

        const modal = document.getElementById('edit-section-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }
    function closeEditSectionModal(e) {
        if (!e || e.target === document.getElementById('edit-section-modal')) {
            const modal = document.getElementById('edit-section-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function openAttachSectionMediaModal(sectionId, title, sectionType) {
        currentAsmSectionId = sectionId;
        const form = document.getElementById('attach-section-media-form');
        if (form) {
            form.action = `/teacher/assessments/{{ $test->id }}/sections/${sectionId}/media`;
        }
        const titleEl = document.getElementById('asm-target-section-title');
        if (titleEl) {
            titleEl.textContent = `Target Section: ${title} (${sectionType ? sectionType.toUpperCase() : 'GENERAL'})`;
        }
        clearSelectedSectionMedia();
        document.getElementById('asm-caption').value = '';
        document.getElementById('asm-order').value = '';
        document.getElementById('asm-upload-file').value = '';
        document.getElementById('asm-upload-title').value = '';
        hideAsmUploadFeedback();
        switchAsmMode('library');

        const modal = document.getElementById('attach-section-media-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        fetchSectionMediaLibrary(sectionType);
    }

    function closeAttachSectionMediaModal(e) {
        if (!e || e.target === document.getElementById('attach-section-media-modal')) {
            const modal = document.getElementById('attach-section-media-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function switchAsmMode(mode) {
        const tabLib = document.getElementById('asm-tab-library');
        const tabUpload = document.getElementById('asm-tab-upload');
        const uploadPanel = document.getElementById('asm-upload-panel');

        if (mode === 'upload') {
            tabUpload.className = 'flex-1 py-2 px-3 bg-emerald-600 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5';
            tabLib.className = 'flex-1 py-2 px-3 bg-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5';
            if (uploadPanel) {
                uploadPanel.classList.remove('hidden');
                uploadPanel.style.display = 'block';
            }
        } else {
            tabLib.className = 'flex-1 py-2 px-3 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5';
            tabUpload.className = 'flex-1 py-2 px-3 bg-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5';
            if (uploadPanel) {
                uploadPanel.classList.add('hidden');
                uploadPanel.style.display = 'none';
            }
        }
    }

    function showAsmUploadFeedback(message, isSuccess = false) {
        const el = document.getElementById('asm-upload-feedback');
        if (el) {
            el.classList.remove('hidden');
            el.style.display = 'block';
            el.className = isSuccess
                ? 'p-2.5 rounded-lg text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
                : 'p-2.5 rounded-lg text-xs font-bold bg-rose-50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300';
            el.textContent = message;
        }
    }

    function hideAsmUploadFeedback() {
        const el = document.getElementById('asm-upload-feedback');
        if (el) {
            el.classList.add('hidden');
            el.style.display = 'none';
            el.textContent = '';
        }
    }

    function uploadSectionMediaFile() {
        const fileInput = document.getElementById('asm-upload-file');
        const titleInput = document.getElementById('asm-upload-title');
        const uploadBtn = document.getElementById('asm-upload-btn');

        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            showAsmUploadFeedback('Please choose a file to upload.', false);
            return;
        }

        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) {
            showAsmUploadFeedback('File size exceeds the 10 MB limit.', false);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        if (titleInput && titleInput.value.trim()) {
            formData.append('title', titleInput.value.trim());
        }
        formData.append('_token', '{{ csrf_token() }}');

        uploadBtn.disabled = true;
        uploadBtn.classList.add('opacity-75', 'cursor-not-allowed');
        uploadBtn.innerHTML = '⏳ Uploading...';
        hideAsmUploadFeedback();

        fetch('{{ route('admin.media.store') }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async (res) => {
            const data = await res.json();
            if (!res.ok || !data.success) {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed.');
                throw new Error(msg);
            }
            return data;
        })
        .then(data => {
            const icon = data.type === 'audio' ? '🎵' : (data.type === 'image' ? '🖼️' : (data.type === 'pdf' ? '📄' : (data.type === 'passage' ? '📖' : '📎')));

            const newAsset = {
                id: data.id,
                title: data.title || data.filename,
                name: data.filename,
                type: data.type,
                size: data.size,
                url: data.url
            };
            sectionMediaLibrary.unshift(newAsset);

            renderSectionMediaGrid(sectionMediaLibrary);
            selectSectionMediaItem(data.id, data.title || data.filename, data.type, icon);

            fileInput.value = '';
            titleInput.value = '';
            switchAsmMode('library');
            showAsmUploadFeedback(`✅ "${data.title || data.filename}" uploaded & selected!`, true);
        })
        .catch(err => {
            showAsmUploadFeedback(`⚠️ ${err.message}`, false);
        })
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            uploadBtn.innerHTML = '<span>⬆️</span> Upload &amp; Select Asset';
        });
    }

    function fetchSectionMediaLibrary(preferredType) {
        const container = document.getElementById('asm-media-list-container');
        if (sectionMediaLibrary.length > 0) {
            renderSectionMediaGrid(sectionMediaLibrary, preferredType);
            return;
        }

        container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>';
        fetch('/admin/media/list')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    sectionMediaLibrary = data.data;
                    renderSectionMediaGrid(sectionMediaLibrary, preferredType);
                } else {
                    container.innerHTML = '<div class="col-span-full text-center text-rose-600 dark:text-rose-400 text-xs py-8">Failed to load media library.</div>';
                }
            })
            .catch(() => {
                container.innerHTML = '<div class="col-span-full text-center text-rose-600 dark:text-rose-400 text-xs py-8">Error communicating with media server.</div>';
            });
    }

    function filterSectionMediaModal(type) {
        const buttons = document.querySelectorAll('.asm-filter-btn');
        buttons.forEach(btn => {
            btn.className = 'asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold';
        });
        if (event && event.target && event.target.classList.contains('asm-filter-btn')) {
            event.target.className = 'asm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm';
        }

        const query = (document.getElementById('asm-search-input')?.value || '').toLowerCase();
        let filtered = sectionMediaLibrary;
        if (type !== 'all') {
            filtered = filtered.filter(item => item.type === type);
        }
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderSectionMediaGrid(filtered);
    }

    function searchSectionMediaModal(query) {
        query = query.toLowerCase();
        let filtered = sectionMediaLibrary;
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderSectionMediaGrid(filtered);
    }

    function renderSectionMediaGrid(items, preferredType) {
        const container = document.getElementById('asm-media-list-container');
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">No media assets found in library.</div>';
            return;
        }

        container.innerHTML = '';
        items.forEach(media => {
            const card = document.createElement('div');
            card.className = 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-700 transition-all flex flex-col justify-between gap-2.5';

            const icon = media.type === 'audio' ? '🎵' : (media.type === 'image' ? '🖼️' : (media.type === 'pdf' ? '📄' : (media.type === 'passage' ? '📖' : '📎')));

            card.innerHTML = `
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">${icon} ${media.type}</span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">${media.size || ''}</span>
                    </div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white truncate" title="${media.title || media.name}">
                        ${media.title || media.name}
                    </div>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="previewAssetModal('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-lg text-[11px] font-bold">👁️ Preview</button>
                    <button type="button" onclick="selectSectionMediaItem('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${icon}')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[11px] font-bold shadow-sm">Select</button>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function selectSectionMediaItem(id, title, type, icon) {
        document.getElementById('asm-media-asset-id').value = id;
        document.getElementById('asm-selected-title').textContent = title;
        document.getElementById('asm-selected-meta').textContent = `Type: ${type.toUpperCase()}`;
        document.getElementById('asm-selected-icon').textContent = icon;
        const prev = document.getElementById('asm-selected-preview');
        if (prev) {
            prev.classList.remove('hidden');
            prev.style.display = 'flex';
        }

        const submitBtn = document.getElementById('asm-submit-btn');
        submitBtn.disabled = false;
        submitBtn.className = 'px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 cursor-pointer transition-all';
    }

    function clearSelectedSectionMedia() {
        document.getElementById('asm-media-asset-id').value = '';
        const prev = document.getElementById('asm-selected-preview');
        if (prev) {
            prev.classList.add('hidden');
            prev.style.display = 'none';
        }
        const submitBtn = document.getElementById('asm-submit-btn');
        submitBtn.disabled = true;
        submitBtn.className = 'px-5 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all';
    }

    function previewAssetModal(id, title, type, url) {
        document.getElementById('apm-title').textContent = `${type.toUpperCase()}: ${title}`;
        const contentEl = document.getElementById('apm-content');

        if (type === 'image') {
            contentEl.innerHTML = `<img src="${url}" alt="${title}" class="max-w-full max-h-[450px] rounded-xl object-contain border border-slate-200 dark:border-slate-700">`;
        } else if (type === 'audio') {
            contentEl.innerHTML = `
                <div class="w-full text-center p-6 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
                    <div class="text-4xl">🎵</div>
                    <audio controls controlsList="nodownload noplaybackrate" src="${url}" preload="metadata" class="w-full max-w-md mx-auto accent-indigo-600"></audio>
                </div>`;
        } else if (type === 'pdf') {
            contentEl.innerHTML = `<iframe src="${url}#toolbar=0" class="w-full h-[450px] border border-slate-200 dark:border-slate-700 rounded-xl bg-white"></iframe>`;
        } else {
            contentEl.innerHTML = `<div class="p-6 text-slate-800 dark:text-slate-200 text-sm leading-relaxed whitespace-pre-wrap bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl w-full">Reading / text passage preview...</div>`;
        }

        const modal = document.getElementById('asset-preview-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeAssetPreviewModal(e) {
        if (!e || e.target === document.getElementById('asset-preview-modal')) {
            const modal = document.getElementById('asset-preview-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
                document.getElementById('apm-content').innerHTML = '';
            }
        }
    }

    function previewQuestionModalMedia(mode, type) {
        const prefix = (mode === 'edit') ? 'eq-' : 'q-';
        const url = document.getElementById(prefix + type + '-url')?.value;
        const title = document.getElementById(prefix + 'preview-' + type + '-title')?.innerText || (type === 'image' ? 'Photograph' : 'Audio Prompt');
        if (url) {
            previewAssetModal('', title, type, url);
        }
    }

    function openTeacherRequestRevisionModal(bankId, questionId, targetTitle) {
        document.getElementById('tr-modal-bank-id').value = bankId || '';
        document.getElementById('tr-modal-question-id').value = questionId || '';
        document.getElementById('tr-modal-target-title').innerText = targetTitle || 'Master Question Item';
        const modal = document.getElementById('teacher-request-revision-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }
    function closeTeacherRequestRevisionModal(e) {
        if (!e || e.target === document.getElementById('teacher-request-revision-modal')) {
            const modal = document.getElementById('teacher-request-revision-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function openResubmitModal() {
        const modal = document.getElementById('resubmit-confirmation-modal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            const confirmBtn = document.getElementById('confirm-resubmit-btn');
            if (confirmBtn) confirmBtn.focus();
        }
    }

    function closeResubmitModal() {
        const modal = document.getElementById('resubmit-confirmation-modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    function confirmAndSubmitResubmit() {
        const form = document.getElementById('resubmit-assessment-form');
        const btn = document.getElementById('confirm-resubmit-btn');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = '🚀 Submitting...';
        }
        if (form) {
            form.submit();
        }
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeResubmitModal();
            closeAttachMasterModal();
            closeCreateAuthoredQuestionModal();
            closeAddSectionModal();
            closeEditSectionModal();
            closeAttachSectionMediaModal();
            closeAssetPreviewModal();
            closeTeacherRequestRevisionModal();
            closeQuestionMediaPicker();
        }
    });
</script>
@endsection
