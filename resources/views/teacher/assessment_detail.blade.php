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
                @elseif($test->status === 'published' && $test->is_published)
                    <span class="bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/50 px-2.5 py-0.5 rounded-md text-[11px] font-bold">
                        🟢 Published & Live
                    </span>
                @elseif($test->status === 'approved' && !$test->is_published)
                    <span class="bg-sky-100 dark:bg-sky-950/50 text-sky-800 dark:text-sky-300 border border-sky-300 dark:border-sky-700/50 px-2.5 py-0.5 rounded-md text-[11px] font-bold">
                        ✅ Approved
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

    @php
        $validationErrors = ($errors->any()) ? $errors->all() : (session('errors') instanceof \Illuminate\Support\ViewErrorBag ? session('errors')->all() : (is_array(session('errors')) ? session('errors') : []));
    @endphp

    @if(!empty($validationErrors))
    <div class="p-4 rounded-xl text-sm font-bold bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300 space-y-1.5 shadow-sm">
        <div class="flex items-center gap-2 font-black text-rose-900 dark:text-rose-200">
            <span>⚠️</span>
            <span>Unable to save assessment item. Please correct the validation errors below:</span>
        </div>
        <ul class="list-disc list-inside text-xs font-semibold pl-2 space-y-0.5 text-rose-700 dark:text-rose-300">
            @foreach($validationErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
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
                                <option value="toeic" {{ $test->test_type === 'toeic' ? 'selected' : '' }}>TOEIC</option>
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
                            <svg class="w-4 h-4 text-slate-600 dark:text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            <span>Save Settings Draft</span>
                        </button>
                    </form>

                    {{-- Preview as Candidate Button --}}
                    <a href="{{ route('teacher.tests.preview', $test->id) }}"
                       class="px-5 py-2.5 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 text-sky-800 dark:text-sky-300 border border-sky-300 dark:border-sky-800/60 text-xs font-bold rounded-xl transition-all shadow-sm inline-flex items-center gap-2"
                       title="Preview assessment as a candidate before submission">
                        <svg class="w-4 h-4 text-sky-700 dark:text-sky-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span>Preview as Candidate</span>
                    </a>

                @php
                    $isToeicTest = (is_object($test->test_type) ? $test->test_type->value : (string)$test->test_type) === 'toeic';
                    $questionsBySection = collect($validationResult['questions'] ?? [])->groupBy(fn($item) => (string) $item['section']->id);
                    $globalQuestionNumberMap = collect($validationResult['questions'] ?? [])->mapWithKeys(fn($item) => [
                        (string)$item['question']->id => [
                            'number'       => $item['canonical_number'] ?? $item['number'],
                            'label'        => $item['display_label'] ?? "Q{$item['number']}",
                            'is_overflow'  => $item['is_overflow'] ?? false,
                        ]
                    ]);

                    // Pre-load all audio groups for the test once to avoid N+1 queries
                    $allTestAudioGroups = \App\Modules\QuestionBank\Models\AudioGroup::where('test_id', (string)$test->id)
                        ->with(['questions.choices', 'mediaAsset'])
                        ->orderBy('order')
                        ->get()
                        ->groupBy(fn($ag) => (int)$ag->part_number);

                    // Pre-load all passage groups for the test once to avoid N+1 queries
                    $allTestPassageGroups = \App\Modules\QuestionBank\Models\PassageGroup::where('test_id', (string)$test->id)
                        ->with(['passages', 'questions.choices'])
                        ->orderBy('order')
                        ->get()
                        ->groupBy(fn($pg) => (int)$pg->part_number);

                    // Compute Section Validation Rollups
                    $sectionRollups = [];
                    $partsNeedingAttentionCount = 0;
                    $totalAssessmentIssuesCount = 0;

                    foreach ($test->sections as $sIdx => $sModel) {
                        $sQuestions = $questionsBySection->get((string)$sModel->id) ?? collect();
                        $sQCount = $sQuestions->count();
                        $sFirstQ = $sModel->testQuestions->first()?->question;
                        $sPartNum = \App\Services\ToeicQuestionValidator::detectPartNumber($sModel, $sFirstQ);
                        $sAudioGroups = ($sPartNum && in_array((int)$sPartNum, [3, 4], true))
                            ? ($allTestAudioGroups->get((int)$sPartNum) ?? collect())
                            : collect();
                        $sPassageGroups = ($sPartNum && in_array((int)$sPartNum, [6, 7], true))
                            ? ($allTestPassageGroups->get((int)$sPartNum) ?? collect())
                            : collect();

                        $blockingIssues = [];
                        $firstIssueId = null;
                        $completeQCount = 0;

                        $blueprint = ($isToeicTest && $sPartNum) ? \App\Services\ToeicQuestionValidator::getPartBlueprint($sPartNum) : null;
                        $targetCount = $blueprint['target_count'] ?? $sQCount;

                        if ($sQCount === 0 && $sAudioGroups->isEmpty() && $sPassageGroups->isEmpty()) {
                            $status = 'not_started';
                            $blockingIssues[] = "Section '{$sModel->title}' has no questions assigned.";
                            $firstIssueId = "section-card-{$sModel->id}";
                        } else {
                            // 1. Evaluate Question-level completeness and warnings
                            foreach ($sQuestions as $qItem) {
                                $qModel = $qItem['question'];
                                $qWarnings = $qItem['warnings'] ?? [];

                                if (!empty($qModel->audio_group_id)) {
                                    // Child question belongs to AudioGroup
                                    $pureQWarnings = array_filter($qWarnings, fn($w) => !str_starts_with($w, 'Audio Group Finding:'));
                                    if (!empty($pureQWarnings) || !$qModel->isCompleteChild()) {
                                        $qNum = $qItem['number'];
                                        $errText = !empty($pureQWarnings) ? implode('; ', $pureQWarnings) : 'Incomplete question details';
                                        $blockingIssues[] = "Q#{$qNum}: {$errText}";
                                        if (!$firstIssueId) {
                                            $firstIssueId = "audio-group-card-{$qModel->audio_group_id}";
                                        }
                                    } else {
                                        $completeQCount++;
                                    }
                                } elseif (!empty($qModel->passage_group_id)) {
                                    // Child question belongs to PassageGroup
                                    $pureQWarnings = array_filter($qWarnings, fn($w) => !str_starts_with($w, 'Passage Group Finding:'));
                                    if (!empty($pureQWarnings) || !$qModel->isCompleteChild()) {
                                        $qNum = $qItem['number'];
                                        $errText = !empty($pureQWarnings) ? implode('; ', $pureQWarnings) : 'Incomplete question details';
                                        $blockingIssues[] = "Q#{$qNum}: {$errText}";
                                        if (!$firstIssueId) {
                                            $firstIssueId = "passage-group-card-{$qModel->passage_group_id}";
                                        }
                                    } else {
                                        $completeQCount++;
                                    }
                                } else {
                                    // Standalone question
                                    $isQComplete = empty($qWarnings);
                                    if ($isQComplete) {
                                        $completeQCount++;
                                    } else {
                                        $qNum = $qItem['number'];
                                        $errText = !empty($qWarnings) ? implode('; ', $qWarnings) : 'Incomplete question details';
                                        $blockingIssues[] = "Q#{$qNum}: {$errText}";
                                        if (!$firstIssueId) {
                                            $firstIssueId = "question-card-{$qModel->id}";
                                        }
                                    }
                                }
                            }

                            // 2. Evaluate Audio Group completeness (for Part 3 / Part 4)
                            if ($sAudioGroups->isNotEmpty()) {
                                foreach ($sAudioGroups as $agModel) {
                                    $agIsComp = $agModel->isComplete();
                                    if (!$agIsComp) {
                                        $agCompCount = $agModel->questions->filter(fn($cq) => $cq->isCompleteChild())->count();
                                        $agTitle = $agModel->title ?: ($agModel->isTalk() ? 'Talk Audio Group' : 'Conversation Audio Group');
                                        $blockingIssues[] = "Audio Group '{$agTitle}' ({$agCompCount}/3 complete): Requires 1 shared audio and exactly 3 complete questions.";
                                        if (!$firstIssueId) {
                                            $firstIssueId = "audio-group-card-{$agModel->id}";
                                        }
                                    }
                                }
                            }

                            // 3. Evaluate Passage Group completeness (for Part 6 / Part 7)
                            if ($sPassageGroups->isNotEmpty()) {
                                foreach ($sPassageGroups as $pgModel) {
                                    $pgCheck = \App\Services\ToeicQuestionValidator::checkPassageGroup($pgModel);
                                    if (!$pgCheck['is_valid']) {
                                        $isPart6 = ((int)$pgModel->part_number === 6);
                                        $pgTitle = $pgModel->title ?: ($isPart6 ? 'Text Completion Group' : 'Reading Passage Group');
                                        foreach ($pgCheck['errors'] as $pgErr) {
                                            $blockingIssues[] = "Passage Group '{$pgTitle}': {$pgErr}";
                                        }
                                        if (!$firstIssueId) {
                                            $firstIssueId = "passage-group-card-{$pgModel->id}";
                                        }
                                    }
                                }
                            }

                            // 4. Canonical TOEIC Part Blueprint Evaluation (UNDER / EXACT / OVER)
                            if ($isToeicTest && $sPartNum) {
                                $eval = \App\Services\ToeicQuestionValidator::evaluatePartCompleteness($sPartNum, $completeQCount, $sQCount);
                                if ($eval['is_under']) {
                                    $missingCount = $eval['diff'];
                                    $blockingIssues[] = "Part {$sPartNum} requires {$targetCount} completed questions (currently {$completeQCount}/{$targetCount}; {$missingCount} " . \Illuminate\Support\Str::plural('question', $missingCount) . " missing).";
                                    if (!$firstIssueId) {
                                        $firstIssueId = "section-card-{$sModel->id}";
                                    }
                                    $status = 'needs_attention';
                                } elseif ($eval['is_over']) {
                                    $exceedCount = $eval['diff'];
                                    $blockingIssues[] = "Part {$sPartNum} exceeds TOEIC blueprint: target is {$targetCount} questions, but " . max($sQCount, $completeQCount) . " are present ({$exceedCount} " . \Illuminate\Support\Str::plural('question', $exceedCount) . " exceed blueprint).";
                                    if (!$firstIssueId) {
                                        $firstIssueId = "section-card-{$sModel->id}";
                                    }
                                    $status = 'needs_attention';
                                } else {
                                    $status = empty($blockingIssues) ? 'ready' : 'needs_attention';
                                }
                            } else {
                                $status = empty($blockingIssues) ? 'ready' : 'needs_attention';
                            }
                        }

                        $uniqueIssues = array_values(array_unique($blockingIssues));
                        $issueCount = count($uniqueIssues);

                        if ($status === 'needs_attention') {
                            $partsNeedingAttentionCount++;
                        }
                        $totalAssessmentIssuesCount += $issueCount;

                        $sectionRollups[(string)$sModel->id] = [
                            'status'             => $status,
                            'total_questions'    => $sQCount,
                            'target_questions'   => $targetCount,
                            'complete_questions' => $completeQCount,
                            'issue_count'        => $issueCount,
                            'issues'             => $uniqueIssues,
                            'first_issue_id'     => $firstIssueId,
                            'audio_groups_count' => $sAudioGroups->count(),
                        ];
                    }
                @endphp

                    {{-- Resubmit Button --}}
                    @if(in_array($test->status, ['needs_revision', 'revision_requested', 'draft']))
                    <form id="resubmit-assessment-form" method="POST" action="{{ route('teacher.tests.resubmit', $test->id) }}" class="inline">
                        @csrf
                        @if(isset($validationResult) && $validationResult['is_valid'])
                        <button type="button"
                                onclick="openResubmitModal()"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-2">
                            <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>{{ $test->status === 'draft' ? 'Submit for Review' : 'Resubmit for Review' }}</span>
                        </button>
                        @else
                        <button type="button"
                                onclick="focusFirstIssueSection()"
                                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-xs font-bold rounded-xl transition-colors inline-flex items-center gap-2 cursor-pointer"
                                title="Resolve all validation issues to enable submission. Click to view sections needing attention.">
                            <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            @if($partsNeedingAttentionCount > 0)
                                <span>Submission Disabled ({{ $partsNeedingAttentionCount }} {{ \Illuminate\Support\Str::plural('Part', $partsNeedingAttentionCount) }} Need Attention)</span>
                                <span class="sr-only">Submission Disabled (Validation Required)</span>
                            @else
                                <span>Submission Disabled (Validation Required)</span>
                            @endif
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
                            Sections &amp; Directions Structure — Organize institutional master questions and authored items for this assessment.
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

                @if($test->sections->isNotEmpty())
                    <div class="space-y-6">
                        @foreach($test->sections as $secIndex => $sec)
                            @php
                                $secRollup = $sectionRollups[(string)$sec->id] ?? [
                                    'status'             => 'not_started',
                                    'total_questions'    => 0,
                                    'complete_questions' => 0,
                                    'issue_count'        => 0,
                                    'issues'             => [],
                                    'first_issue_id'     => null,
                                ];
                                $secQuestions = $questionsBySection->get((string)$sec->id) ?? collect();
                                $secQCount = $secQuestions->count();
                                $secFirstNum = $secQuestions->first()['number'] ?? null;
                                $secLastNum = $secQuestions->last()['number'] ?? null;

                                $firstQ = $sec->testQuestions->first()?->question;
                                $secPartNumber = \App\Services\ToeicQuestionValidator::detectPartNumber($sec, $firstQ);
                                $secPassageGroups = ($secPartNumber && in_array((int)$secPartNumber, [6, 7])) ? ($allTestPassageGroups->get((int)$secPartNumber) ?? collect()) : collect();
                                $canonicalBlueprint = ($isToeicTest && $secPartNumber) ? \App\Services\ToeicQuestionValidator::getPartBlueprint($secPartNumber) : null;
                                $secTargetCount = $canonicalBlueprint['target_count'] ?? $secRollup['target_questions'] ?? $secQCount;
                                $canonicalRange = $canonicalBlueprint ? ['start' => $canonicalBlueprint['start_number'], 'end' => $canonicalBlueprint['end_number']] : null;
                                $rangeLabel = $canonicalRange ? "Questions {$canonicalRange['start']}–{$canonicalRange['end']}" : ($secFirstNum ? ($secFirstNum === $secLastNum ? "Question #{$secFirstNum}" : "Questions {$secFirstNum}–{$secLastNum}") : "0 Questions");

                                $secMCount = $sec->mediaAssets ? $sec->mediaAssets->count() : 0;
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
                            <div id="section-card-{{ $sec->id }}" class="section-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4" data-section-id="{{ $sec->id }}" data-status="{{ $secRollup['status'] }}" data-first-issue-id="{{ $secRollup['first_issue_id'] }}">
                                <!-- Section Card Header & Metrics (Clickable row to toggle expansion) -->
                                <div onclick="toggleSectionCollapse('{{ $sec->id }}')" class="flex justify-between items-start flex-wrap gap-3 cursor-pointer select-none" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleSectionCollapse('{{ $sec->id }}');}">
                                    <div class="flex-1 min-w-[240px]">
                                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                            <span class="text-base sm:text-lg font-black text-slate-900 dark:text-white">{{ $sec->title }}</span>
                                            <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">
                                                {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type ?? 'Reading') }} SECTION
                                            </span>
                                            @if($secPartNumber)
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                PART {{ $secPartNumber }}
                                            </span>
                                            @endif
                                            <span class="text-xs text-slate-600 dark:text-slate-400 font-bold">
                                                @if($isToeicTest && $secPartNumber && $secTargetCount)
                                                    • {{ $secRollup['complete_questions'] }} of {{ $secTargetCount }} questions
                                                @else
                                                    • {{ $secQCount }} {{ \Illuminate\Support\Str::plural('question', $secQCount) }}
                                                @endif
                                                @if($canonicalRange || $secFirstNum)
                                                    <span class="text-indigo-600 dark:text-indigo-400 font-extrabold">({{ $rangeLabel }})</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap text-xs text-slate-500 dark:text-slate-400">
                                            <span>Section {{ $secIndex + 1 }} of {{ $test->sections->count() }}</span>
                                            @php
                                                $partEval = ($isToeicTest && $secPartNumber) ? \App\Services\ToeicQuestionValidator::evaluatePartCompleteness($secPartNumber, $secRollup['complete_questions'], $secRollup['total_questions']) : null;
                                            @endphp
                                            @if($secRollup['status'] === 'needs_attention')
                                                <span class="text-amber-700 dark:text-amber-300 font-semibold">• {{ $secRollup['complete_questions'] }}/{{ $secRollup['target_questions'] ?? $secRollup['total_questions'] }} Complete</span>
                                                @if($partEval && $partEval['is_under'])
                                                    <span class="text-amber-700 dark:text-amber-300 font-bold">• {{ $partEval['diff'] }} {{ \Illuminate\Support\Str::plural('question', $partEval['diff']) }} missing</span>
                                                @elseif($partEval && $partEval['is_over'])
                                                    <span class="text-rose-700 dark:text-rose-300 font-bold">• {{ $partEval['diff'] }} {{ \Illuminate\Support\Str::plural('question', $partEval['diff']) }} over limit</span>
                                                @else
                                                    <span class="text-amber-700 dark:text-amber-300 font-bold">• {{ $secRollup['issue_count'] }} {{ \Illuminate\Support\Str::plural('Issue', $secRollup['issue_count']) }}</span>
                                                @endif
                                            @elseif($secRollup['status'] === 'ready')
                                                <span class="text-emerald-700 dark:text-emerald-300 font-semibold">• {{ $secRollup['complete_questions'] }}/{{ $secRollup['target_questions'] ?? $secRollup['total_questions'] }} Complete</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Section Validation Status Badge & Collapse Toggle -->
                                    <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
                                        @if($secRollup['status'] === 'ready')
                                            <div class="px-2.5 py-1 rounded-lg text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800 inline-flex items-center gap-1.5 shadow-sm" title="This section has passed all validation checks and is ready.">
                                                <span>✓</span>
                                                <span>READY</span>
                                            </div>
                                        @elseif($secRollup['status'] === 'needs_attention')
                                            <button type="button"
                                                    onclick="event.stopPropagation(); expandAndFocusFirstIssue('{{ $sec->id }}', '{{ $secRollup['first_issue_id'] }}')"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-black bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 dark:text-amber-300 dark:border-amber-800 inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer"
                                                    title="Click to expand and view {{ $secRollup['issue_count'] }} unresolved {{ \Illuminate\Support\Str::plural('issue', $secRollup['issue_count']) }}">
                                                <span>⚠</span>
                                                <span>NEEDS ATTENTION</span>
                                                @if($secRollup['issue_count'] > 0)
                                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100 ml-0.5">
                                                        {{ $secRollup['issue_count'] }}
                                                    </span>
                                                @endif
                                            </button>
                                        @else
                                            <div class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 border border-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 inline-flex items-center gap-1.5" title="This section contains no questions.">
                                                <span>○</span>
                                                <span>NOT STARTED</span>
                                                @if($secRollup['issue_count'] > 0)
                                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 ml-0.5">
                                                        {{ $secRollup['issue_count'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        <!-- Collapse / Expand Toggle Button -->
                                        <button type="button"
                                                onclick="event.stopPropagation(); toggleSectionCollapse('{{ $sec->id }}')"
                                                id="btn-collapse-{{ $sec->id }}"
                                                aria-expanded="false"
                                                aria-controls="section-body-{{ $sec->id }}"
                                                class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all inline-flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                            <span id="collapse-icon-{{ $sec->id }}" class="text-xs">▸</span>
                                            <span id="collapse-label-{{ $sec->id }}">Expand</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Collapsible Section Body (Hidden by default) -->
                                <div id="section-body-{{ $sec->id }}" class="hidden space-y-5 pt-4 border-t border-slate-100 dark:border-slate-800" style="display: none;">
                                    <!-- Directions & Instructions Strip -->
                                    <div class="bg-slate-50 dark:bg-slate-950/60 p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5">
                                        <div class="flex items-center justify-between gap-2 flex-wrap">
                                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                                <span>📋</span> Section Directions
                                            </span>
                                            <span class="text-[10px] text-slate-500 dark:text-slate-400 italic">
                                                Presented to candidate at section boundary
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-medium bg-white dark:bg-slate-900 p-3 rounded-lg border border-slate-200 dark:border-slate-800">
                                            {{ $sec->instructions ?: 'No section directions configured (optional).' }}
                                        </div>
                                    </div>

                                    {{-- Attached Section Media Assets --}}
                                    <div class="bg-slate-50 dark:bg-slate-950/40 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
                                        <div class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>📂</span> Section Media Assets ({{ $sec->mediaAssets ? $sec->mediaAssets->count() : 0 }})
                                        </div>
                                        @if($sec->mediaAssets && $sec->mediaAssets->isNotEmpty())
                                        <div class="flex flex-col gap-2">
                                            @foreach($sec->mediaAssets as $media)
                                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-2.5 flex justify-between items-center flex-wrap gap-2">
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
                                                            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-[11px] font-bold">
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
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500 italic py-1">
                                            No media assets attached to this section directions yet. Click "Attach Media" to add listening prompts or reference images.
                                        </div>
                                        @endif
                                    </div>

                                    <!-- Contextual Section Action Bar -->
                                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                                    <div class="flex items-center justify-between flex-wrap gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            @if($isToeicTest && in_array((int)$secPartNumber, [6, 7], true))
                                            <button type="button"
                                                    onclick="openCreatePassageGroupModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}', {{ $secPassageGroups->count() + 1 }})"
                                                    class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5 transition-all">
                                                <span>📖</span> + Add Passage Group
                                            </button>
                                            @elseif($isToeicTest && in_array((int)$secPartNumber, [3, 4], true))
                                            <button type="button"
                                                    onclick="openCreateAudioGroupModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}')"
                                                    class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5 transition-all">
                                                <span>🎧</span> + Add Audio Group
                                            </button>
                                            <button type="button"
                                                    onclick="openCreateAuthoredQuestionModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
                                                <span>➕</span> + Add Question
                                            </button>
                                            @else
                                            <button type="button"
                                                    onclick="openCreateAuthoredQuestionModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}')"
                                                    class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5">
                                                <span>➕</span> + Add Question
                                            </button>
                                            @endif
                                            <button type="button"
                                                    onclick="openAttachSectionMediaModal('{{ $sec->id }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}')"
                                                    class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 text-xs font-bold inline-flex items-center gap-1.5">
                                                📎 Attach Media
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
                                        </div>
                                        <form method="POST" action="{{ route('teacher.tests.destroy-section', ['test' => $test->id, 'section' => $sec->id]) }}" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: 'Remove Section?', message: '{{ $secConfirmMsg }}', confirmText: 'Remove Section', variant: 'danger', form: this });">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 text-xs font-bold inline-flex items-center gap-1.5">
                                                🗑 Remove Section
                                            </button>
                                        </form>
                                    </div>
                                    @endif

                                    <!-- Section Questions List -->
                                    <div class="space-y-3 pt-2">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center justify-between">
                                            <span>📝 Questions in this Section ({{ $secQCount }})</span>
                                        </div>

                                        @php
                                            $secAudioGroups = collect();
                                        @endphp
                                        @if($secPartNumber && in_array((int)$secPartNumber, [3, 4]))
                                            @php
                                                $secAudioGroups = \App\Modules\QuestionBank\Models\AudioGroup::where('test_id', (string)$test->id)
                                                    ->where('part_number', (int)$secPartNumber)
                                                    ->orderBy('order')
                                                    ->with(['questions.choices', 'mediaAsset'])
                                                    ->get();
                                            @endphp
                                            @foreach($secAudioGroups as $ag)
                                                @php
                                                    $agCompleteCount = $ag->complete_questions_count;
                                                    $agIsComplete = $ag->isComplete();
                                                    $agAudioUrl = $ag->getEffectiveAudioUrl();
                                                    $agAudioTitle = $ag->mediaAsset?->title ?: ($ag->title ?: basename($agAudioUrl ?? 'Shared Audio'));
                                                    $agSortedQuestions = $ag->questions->sortBy(function($cq) use ($globalQuestionNumberMap) {
                                                        return $globalQuestionNumberMap[(string)$cq->id] ?? ($cq->created_at?->timestamp ?? 0);
                                                    })->values();
                                                    $agJson = [
                                                        'id'                  => $ag->id,
                                                        'title'               => $ag->title,
                                                        'group_type'          => $ag->group_type,
                                                        'part_number'         => $ag->part_number,
                                                        'media_asset_id'      => $ag->media_asset_id,
                                                        'audio_url'           => $ag->audio_url,
                                                        'audio_script'        => $ag->audio_script,
                                                        'audio_title'         => $agAudioTitle,
                                                        'audio_effective_url' => $agAudioUrl,
                                                        'complete_count'      => $agCompleteCount,
                                                        'is_complete'         => $agIsComplete,
                                                        'questions'           => $agSortedQuestions->map(function($cq) {
                                                            $choices = $cq->choices->sortBy('order')->values();
                                                            $correctIdx = $choices->search(fn($c) => (bool)$c->is_correct);
                                                            return [
                                                                'id'             => $cq->id,
                                                                'prompt'         => $cq->prompt,
                                                                'explanation'    => $cq->explanation,
                                                                'difficulty'     => is_object($cq->difficulty) ? $cq->difficulty->value : $cq->difficulty,
                                                                'choices'        => $choices->map(fn($c) => $c->content ?? $c->choice_text)->toArray(),
                                                                'correct_choice' => $correctIdx !== false ? $correctIdx : 0,
                                                                'is_complete'    => $cq->isCompleteChild(),
                                                            ];
                                                        })->toArray(),
                                                    ];
                                                @endphp
                                                <div id="audio-group-card-{{ $ag->id }}" class="bg-white dark:bg-slate-900 border {{ $agIsComplete ? 'border-slate-200 dark:border-slate-800' : 'border-amber-300 dark:border-amber-700/60 bg-amber-50/20' }} rounded-xl p-4 shadow-sm space-y-3">
                                                    <div class="flex justify-between items-start flex-wrap gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                                                        <div>
                                                            <div class="flex items-center gap-2 flex-wrap">
                                                                <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                                                    <span>🎧</span> {{ $ag->title ?: ($ag->isTalk() ? 'Talk Audio Group' : 'Conversation Audio Group') }}
                                                                </span>
                                                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">
                                                                    Audio Group ({{ $ag->isTalk() ? 'Talk' : 'Conversation' }})
                                                                </span>
                                                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full {{ $agIsComplete ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800' : 'bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800' }}">
                                                                    Progress: {{ $agCompleteCount }} / 3 Complete
                                                                </span>
                                                                @if($agIsComplete)
                                                                    <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300">🟢 VALID</span>
                                                                @else
                                                                    <span class="text-[10px] font-bold text-amber-700 dark:text-amber-300">🟡 INCOMPLETE</span>
                                                                @endif
                                                            </div>
                                                            @if($agAudioUrl)
                                                            <div class="flex items-center gap-2 mt-1.5">
                                                                <span class="text-xs text-slate-500 dark:text-slate-400">Shared Audio: {{ \Illuminate\Support\Str::limit($agAudioTitle, 35) }}</span>
                                                                <button type="button" onclick="previewAssetModal('{{ $ag->media_asset_id ?? '' }}', '{{ addslashes($agAudioTitle) }}', 'audio', '{{ $agAudioUrl }}')" class="text-xs font-bold text-indigo-600 hover:text-indigo-500">
                                                                    👁️ Preview Audio
                                                                </button>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <button type="button"
                                                                    onclick="openEditAudioGroupModal({{ json_encode($agJson) }}, '{{ $sec->id }}', '{{ addslashes($sec->title) }}')"
                                                                    class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-extrabold shadow-sm inline-flex items-center gap-1 transition-all">
                                                                <span>✏️</span> Edit Group
                                                            </button>

                                                            @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']) && !$test->is_published)
                                                            @php
                                                                $isTalk = $ag->isTalk();
                                                                $groupTypeName = $isTalk ? 'Talk' : 'Conversation';
                                                                $groupConfirmTitle = "Remove {$groupTypeName} Group?";
                                                                $groupConfirmMsg = "This will remove the shared audio-group association, all questions belonging to this group, and their assessment placements. This action cannot be undone.";
                                                            @endphp
                                                            <form action="{{ route('teacher.tests.destroy-audio-group', ['test' => $test->id, 'audioGroup' => $ag->id]) }}" method="POST" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: '{{ $groupConfirmTitle }}', message: '{{ $groupConfirmMsg }}', confirmText: 'Remove Group', variant: 'danger', form: this });">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 text-xs font-bold inline-flex items-center gap-1 transition-all">
                                                                    🗑 Remove Group
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- Slot Overview Tree with Global Question Numbering --}}
                                                    <div class="space-y-1.5 pl-2 text-xs">
                                                        @for($s = 0; $s < 3; $s++)
                                                            @php
                                                                $slotQ = $agSortedQuestions->get($s);
                                                                $slotComplete = $slotQ && $slotQ->isCompleteChild();
                                                                $slotQInfo = $slotQ ? ($globalQuestionNumberMap[(string)$slotQ->id] ?? null) : null;
                                                                $slotGlobalNum = is_array($slotQInfo) ? ($slotQInfo['number'] ?? null) : $slotQInfo;
                                                                $slotLabel = is_array($slotQInfo) ? ($slotQInfo['label'] ?? "Q{$slotGlobalNum}") : ($slotGlobalNum ? "Q{$slotGlobalNum}" : ("Q#" . ($s + 1)));
                                                                $slotIsOverflow = is_array($slotQInfo) ? ($slotQInfo['is_overflow'] ?? false) : false;
                                                                $slotDiffVal = $slotQ ? (is_object($slotQ->difficulty) ? $slotQ->difficulty->value : (string)($slotQ->difficulty ?? 'medium')) : null;
                                                                $slotDiffBadgeColor = match($slotDiffVal) {
                                                                    'easy' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
                                                                    'hard' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800',
                                                                    default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
                                                                };
                                                            @endphp
                                                            <div @if($slotQ) id="question-card-{{ $slotQ->id }}" @endif class="flex items-center gap-2 flex-wrap rounded-lg p-1 transition-all">
                                                                <span class="text-slate-400 font-mono">{{ $s === 2 ? '└──' : '├──' }}</span>
                                                                @if($slotComplete && $slotIsOverflow)
                                                                    <span class="font-extrabold text-amber-700 dark:text-amber-300">[{{ $slotLabel }}] ✓ Complete (Over Limit):</span>
                                                                    <span class="text-slate-700 dark:text-slate-300 truncate max-w-md">{{ \Illuminate\Support\Str::limit($slotQ->prompt, 60) }}</span>
                                                                @elseif($slotComplete && $slotGlobalNum)
                                                                    <span class="font-extrabold text-emerald-700 dark:text-emerald-300">Q{{ $slotGlobalNum }} ✓ Complete:</span>
                                                                    <span class="text-slate-700 dark:text-slate-300 truncate max-w-md">{{ \Illuminate\Support\Str::limit($slotQ->prompt, 60) }}</span>
                                                                @elseif($slotComplete)
                                                                    <span class="font-extrabold text-emerald-700 dark:text-emerald-300">{{ $slotLabel }} ✓ Complete:</span>
                                                                    <span class="text-slate-700 dark:text-slate-300 truncate max-w-md">{{ \Illuminate\Support\Str::limit($slotQ->prompt, 60) }}</span>
                                                                @elseif($slotQ)
                                                                    @if($slotGlobalNum)
                                                                        <span class="font-bold text-amber-700 dark:text-amber-300">Q{{ $slotGlobalNum }} ○ Incomplete:</span>
                                                                    @else
                                                                        <span class="font-bold text-amber-700 dark:text-amber-300">{{ $slotLabel }} ○ Incomplete:</span>
                                                                    @endif
                                                                    <span class="text-slate-500 italic truncate max-w-md">{{ \Illuminate\Support\Str::limit($slotQ->prompt ?: 'Missing choices or correct answer', 50) }}</span>
                                                                @else
                                                                    <span class="font-bold text-slate-400">Slot {{ $s + 1 }} ○ Incomplete:</span>
                                                                    <span class="text-slate-400 italic">Pending authoring</span>
                                                                @endif
                                                                @if($slotDiffVal)
                                                                    <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded {{ $slotDiffBadgeColor }}">
                                                                        Auto: {{ ucfirst($slotDiffVal) }}
                                                                    </span>
                                                                @endif
                                                                @php
                                                                    $slotCorrectChoice = $slotQ && $slotQ->choices ? $slotQ->choices->firstWhere('is_correct', true) : null;
                                                                    $slotCorrectLabel = $slotCorrectChoice ? strtoupper(trim((string)$slotCorrectChoice->label)) : null;
                                                                    $slotCorrectDisplay = $slotCorrectLabel ?: '—';
                                                                    $slotAnsBadgeColor = $slotCorrectLabel
                                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                                                        : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
                                                                @endphp
                                                                <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded {{ $slotAnsBadgeColor }}" title="Persisted correct answer key">
                                                                    Correct Answer: {{ $slotCorrectDisplay }}
                                                                </span>
                                                            </div>
                                                        @endfor
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif

                                        @php
                                            $secPassageGroups = collect();
                                        @endphp
                                        @if($secPartNumber && in_array((int)$secPartNumber, [6, 7]))
                                            @php
                                                $secPassageGroups = $allTestPassageGroups->get((int)$secPartNumber) ?? collect();
                                            @endphp
                                            @foreach($secPassageGroups->values() as $pgOrdinalIdx => $pg)
                                                @php
                                                    $pgOrdinal = $pgOrdinalIdx + 1;
                                                    $pgPassages = $pg->passages;
                                                    $pgFirstPassage = $pgPassages->first();
                                                    $pgSortedQuestions = $pg->questions->sortBy(function($cq) use ($globalQuestionNumberMap) {
                                                        return $globalQuestionNumberMap[(string)$cq->id] ?? ($cq->created_at?->timestamp ?? 0);
                                                    })->values();
                                                    $pgCheck = \App\Services\ToeicQuestionValidator::checkPassageGroup($pg);
                                                    $pgIsComplete = $pgCheck['is_valid'] ?? false;
                                                    $pgCompleteCount = $pgSortedQuestions->filter(fn($cq) => $cq->isCompleteChild())->count();
                                                    $isPart6 = ((int)$pg->part_number === 6);
                                                    $groupTypeName = $isPart6 ? 'Text Completion' : 'Reading Passage';
                                                    $draftSlots = $pg->context_metadata['draft_slots'] ?? null;
                                                    $qSlots = [];
                                                    if (is_array($draftSlots) && !empty($draftSlots)) {
                                                        foreach ($draftSlots as $slotIdx => $slotData) {
                                                            $state = $slotData['state'] ?? 'untouched';
                                                            if ($state === 'complete' && !empty($slotData['question_id'])) {
                                                                $cq = $pg->questions->firstWhere('id', $slotData['question_id']);
                                                                if ($cq) {
                                                                    $choices = $cq->choices->sortBy('order')->values();
                                                                    $correctIdx = $choices->search(fn($c) => (bool)$c->is_correct);
                                                                    $qSlots[] = [
                                                                        'id'             => $cq->id,
                                                                        'prompt'         => $cq->prompt,
                                                                        'explanation'    => $cq->explanation,
                                                                        'difficulty'     => is_object($cq->difficulty) ? $cq->difficulty->value : $cq->difficulty,
                                                                        'choices'        => $choices->map(fn($c) => $c->content ?? $c->choice_text)->toArray(),
                                                                        'correct_choice' => $correctIdx !== false ? $correctIdx : null,
                                                                        'is_complete'    => $cq->isCompleteChild(),
                                                                        'slot_state'     => 'complete',
                                                                    ];
                                                                    continue;
                                                                }
                                                            }
                                                            if ($state === 'partial') {
                                                                $qSlots[] = [
                                                                    'id'             => null,
                                                                    'prompt'         => $slotData['prompt'] ?? '',
                                                                    'explanation'    => $slotData['explanation'] ?? null,
                                                                    'difficulty'     => $slotData['difficulty'] ?? 'medium',
                                                                    'choices'        => $slotData['choices'] ?? ['', '', '', ''],
                                                                    'correct_choice' => $slotData['correct_choice'] ?? null,
                                                                    'is_complete'    => false,
                                                                    'slot_state'     => 'partial',
                                                                ];
                                                                continue;
                                                            }
                                                            $qSlots[] = [
                                                                'id'             => null,
                                                                'prompt'         => '',
                                                                'explanation'    => null,
                                                                'difficulty'     => 'medium',
                                                                'choices'        => ['', '', '', ''],
                                                                'correct_choice' => null,
                                                                'is_complete'    => false,
                                                                'slot_state'     => 'untouched',
                                                            ];
                                                        }
                                                    } else {
                                                        $qSlots = $pgSortedQuestions->map(function($cq) {
                                                            $choices = $cq->choices->sortBy('order')->values();
                                                            $correctIdx = $choices->search(fn($c) => (bool)$c->is_correct);
                                                            return [
                                                                'id'             => $cq->id,
                                                                'prompt'         => $cq->prompt,
                                                                'explanation'    => $cq->explanation,
                                                                'difficulty'     => is_object($cq->difficulty) ? $cq->difficulty->value : $cq->difficulty,
                                                                'choices'        => $choices->map(fn($c) => $c->content ?? $c->choice_text)->toArray(),
                                                                'correct_choice' => $correctIdx !== false ? $correctIdx : null,
                                                                'is_complete'    => $cq->isCompleteChild(),
                                                                'slot_state'     => 'complete',
                                                            ];
                                                        })->toArray();
                                                    }

                                                    $pgJson = [
                                                        'id'             => $pg->id,
                                                        'title'          => $pg->title,
                                                        'part_number'    => $pg->part_number,
                                                        'group_ordinal'  => $pgOrdinal,
                                                        'passage_type'   => $pg->passage_type,
                                                        'passages'       => $pg->passages->map(fn($p) => [
                                                            'id'             => $p->id,
                                                            'title'          => $p->title,
                                                            'content'        => $p->content,
                                                            'document_type'  => $p->document_type,
                                                            'order_in_group' => $p->order_in_group,
                                                            'image_url'      => $p->getEffectiveImageUrl(),
                                                            'media_asset_id' => $p->media_asset_id,
                                                        ])->toArray(),
                                                        'complete_count' => $pgCompleteCount,
                                                        'is_complete'    => $pgIsComplete,
                                                        'questions'      => $qSlots,
                                                    ];
                                                @endphp
                                                <div id="passage-group-card-{{ $pg->id }}" class="bg-white dark:bg-slate-900 border {{ $pgIsComplete ? 'border-slate-200 dark:border-slate-800' : 'border-amber-300 dark:border-amber-700/60 bg-amber-50/20' }} rounded-xl p-4 shadow-sm space-y-3">
                                                    <div class="flex justify-between items-start flex-wrap gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                                                        <div>
                                                            <div class="flex items-center gap-2 flex-wrap">
                                                                <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                                                    <span>📖</span> {{ $pg->title ?: ($isPart6 ? "Text Completion Group {$pgOrdinal}" : ('Part ' . $pg->part_number . ' ' . ucfirst($pg->passage_type) . ' Passage Group')) }}
                                                                    @if($isPart6)
                                                                        @php
                                                                            $startSlotNum = 130 + ($pgOrdinal - 1) * 4 + 1;
                                                                            $endSlotNum = 130 + $pgOrdinal * 4;
                                                                            $p6RangeLabel = ($pgOrdinal <= 4) ? "Questions {$startSlotNum}–{$endSlotNum}" : "Questions [Overflow +{$pgOrdinal}]";
                                                                        @endphp
                                                                        <span class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400">({{ $p6RangeLabel }})</span>
                                                                    @endif
                                                                </span>
                                                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">
                                                                    {{ $isPart6 ? 'Text Completion Group' : 'Passage Group (' . ucfirst($pg->passage_type) . ')' }}
                                                                </span>
                                                                <span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full {{ $pgIsComplete ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800' : 'bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800' }}">
                                                                    Progress: {{ $pgCompleteCount }} / {{ $isPart6 ? 4 : $pgSortedQuestions->count() }} Complete
                                                                </span>
                                                                @if($pgIsComplete)
                                                                    <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300">🟢 VALID</span>
                                                                @else
                                                                    <span class="text-[10px] font-bold text-amber-700 dark:text-amber-300">🟡 INCOMPLETE</span>
                                                                @endif
                                                            </div>
                                                            @if($pgFirstPassage)
                                                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 italic line-clamp-2">
                                                                Passage: {{ \Illuminate\Support\Str::limit($pgFirstPassage->content, 120) }}
                                                            </div>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <button type="button"
                                                                    onclick="openPassageGroupPreviewModal({{ json_encode($pgJson) }})"
                                                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-extrabold border border-slate-300 dark:border-slate-700 shadow-sm inline-flex items-center gap-1 transition-all">
                                                                <span>👁</span> Preview Passage
                                                            </button>
                                                            <button type="button"
                                                                    onclick="openEditPassageGroupModal({{ json_encode($pgJson) }}, '{{ $sec->id }}', '{{ addslashes($sec->title) }}')"
                                                                    class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-extrabold shadow-sm inline-flex items-center gap-1 transition-all">
                                                                <span>✏️</span> Edit Group
                                                            </button>

                                                            @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']) && !$test->is_published)
                                                            @php
                                                                $groupConfirmTitle = "Remove {$groupTypeName} Group?";
                                                                $groupConfirmMsg = "This will remove this assessment Passage Group and its assessment-authored child questions. This action cannot be undone.";
                                                            @endphp
                                                            <form action="{{ route('teacher.tests.destroy-passage-group', ['test' => $test->id, 'passageGroup' => $pg->id]) }}" method="POST" class="inline" onsubmit="event.preventDefault(); iapConfirm({ title: '{{ $groupConfirmTitle }}', message: '{{ $groupConfirmMsg }}', confirmText: 'Remove Group', variant: 'danger', form: this });">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40 text-xs font-bold inline-flex items-center gap-1 transition-all">
                                                                    🗑 Remove Group
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- Slot Overview Tree with Global Question Numbering --}}
                                                    <div class="space-y-1.5 pl-2 text-xs">
                                                        @if($isPart6)
                                                            @for($sIdx = 0; $sIdx < 4; $sIdx++)
                                                                @php
                                                                    $slotInPart = ($pgOrdinal - 1) * 4 + ($sIdx + 1);
                                                                    $slotDisp = \App\Services\ToeicQuestionValidator::getQuestionDisplayNumber(6, $slotInPart);
                                                                    $slotLabel = $slotDisp['label'] ?? "Q" . (130 + $slotInPart);
                                                                    $slotIsOverflow = $slotDisp['is_overflow'] ?? false;
                                                                    $isLastSlot = ($sIdx === 3);
                                                                    $slotMeta = is_array($draftSlots) ? ($draftSlots[$sIdx] ?? null) : null;
                                                                    $slotState = $slotMeta['state'] ?? null;
                                                                    $pq = null;

                                                                    if ($slotState === 'complete' && !empty($slotMeta['question_id'])) {
                                                                        $pq = $pg->questions->firstWhere('id', $slotMeta['question_id']);
                                                                    } elseif (!$slotState && $pgSortedQuestions->has($sIdx)) {
                                                                        $pq = $pgSortedQuestions->get($sIdx);
                                                                    }
                                                                @endphp
                                                                @if($pq)
                                                                    @php
                                                                        $pqDiffVal = is_object($pq->difficulty) ? $pq->difficulty->value : (string)($pq->difficulty ?? 'medium');
                                                                        $pqDiffBadgeColor = match($pqDiffVal) {
                                                                            'easy' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
                                                                            'hard' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800',
                                                                            default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
                                                                        };
                                                                    @endphp
                                                                    <div id="question-card-{{ $pq->id }}" class="flex items-center gap-2 flex-wrap rounded-lg p-1 transition-all">
                                                                        <span class="text-slate-400 font-mono">{{ $isLastSlot ? '└──' : '├──' }}</span>
                                                                        @if($slotIsOverflow)
                                                                            <span class="font-extrabold text-amber-700 dark:text-amber-300">[{{ $slotLabel }}] ✓ Complete (Over Limit):</span>
                                                                        @else
                                                                            <span class="font-extrabold text-emerald-700 dark:text-emerald-300">{{ $slotLabel }} ✓ Complete:</span>
                                                                        @endif
                                                                        <span class="text-slate-700 dark:text-slate-300 truncate max-w-md">{{ $pq->prompt ? \Illuminate\Support\Str::limit($pq->prompt, 60) : 'Passage-embedded blank' }}</span>
                                                                        @if($pqDiffVal)
                                                                            <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded {{ $pqDiffBadgeColor }}">
                                                                                Auto: {{ ucfirst($pqDiffVal) }}
                                                                            </span>
                                                                        @endif
                                                                        @php
                                                                            $pqCorrectChoice = $pq->choices ? $pq->choices->firstWhere('is_correct', true) : null;
                                                                            $pqCorrectLabel = $pqCorrectChoice ? strtoupper(trim((string)$pqCorrectChoice->label)) : null;
                                                                            $pqCorrectDisplay = $pqCorrectLabel ?: '—';
                                                                            $pqAnsBadgeColor = $pqCorrectLabel
                                                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                                                                : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
                                                                        @endphp
                                                                        <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded {{ $pqAnsBadgeColor }}" title="Persisted correct answer key">
                                                                            Correct Answer: {{ $pqCorrectDisplay }}
                                                                        </span>
                                                                    </div>
                                                                @elseif($slotState === 'partial')
                                                                    <div class="flex items-center gap-2 flex-wrap rounded-lg p-1 text-slate-500 dark:text-slate-400 italic">
                                                                        <span class="font-mono">{{ $isLastSlot ? '└──' : '├──' }}</span>
                                                                        <span class="font-bold text-amber-600 dark:text-amber-400">{{ $slotLabel }} (○ Incomplete / Draft):</span>
                                                                        <span class="truncate max-w-md">{{ !empty($slotMeta['prompt']) ? \Illuminate\Support\Str::limit($slotMeta['prompt'], 60) : 'Draft blank context saved' }}</span>
                                                                        <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 not-italic" title="Persisted correct answer key">
                                                                            Correct Answer: —
                                                                        </span>
                                                                    </div>
                                                                @else
                                                                    <div class="flex items-center gap-2 flex-wrap rounded-lg p-1 text-slate-400 dark:text-slate-500 italic">
                                                                        <span class="font-mono">{{ $isLastSlot ? '└──' : '├──' }}</span>
                                                                        <span class="font-bold text-amber-600 dark:text-amber-400">{{ $slotLabel }} (○ Incomplete / Draft):</span>
                                                                        <span>Question not yet completed</span>
                                                                        <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700 not-italic" title="Persisted correct answer key">
                                                                            Correct Answer: —
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            @endfor
                                                        @else
                                                            @foreach($pgSortedQuestions as $pqIdx => $pq)
                                                                @php
                                                                    $pqInfo = $globalQuestionNumberMap[(string)$pq->id] ?? null;
                                                                    $pqGlobalNum = is_array($pqInfo) ? ($pqInfo['number'] ?? null) : $pqInfo;
                                                                    $pqLabel = is_array($pqInfo) ? ($pqInfo['label'] ?? "Q{$pqGlobalNum}") : ($pqGlobalNum ? "Q{$pqGlobalNum}" : "Q#" . ($pqIdx + 1));
                                                                    $pqIsOverflow = is_array($pqInfo) ? ($pqInfo['is_overflow'] ?? false) : false;
                                                                    $pqDiffVal = is_object($pq->difficulty) ? $pq->difficulty->value : (string)($pq->difficulty ?? 'medium');
                                                                    $pqDiffBadgeColor = match($pqDiffVal) {
                                                                        'easy' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
                                                                        'hard' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800',
                                                                        default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
                                                                    };
                                                                    $isLastPq = ($pqIdx === count($pgSortedQuestions) - 1);
                                                                    $pqCorrectChoice = $pq->choices ? $pq->choices->firstWhere('is_correct', true) : null;
                                                                    $pqCorrectLabel = $pqCorrectChoice ? strtoupper(trim((string)$pqCorrectChoice->label)) : null;
                                                                    $pqCorrectDisplay = $pqCorrectLabel ?: '—';
                                                                    $pqAnsBadgeColor = $pqCorrectLabel
                                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                                                        : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
                                                                @endphp
                                                                <div id="question-card-{{ $pq->id }}" class="flex items-center gap-2 flex-wrap rounded-lg p-1 transition-all">
                                                                    <span class="text-slate-400 font-mono">{{ $isLastPq ? '└──' : '├──' }}</span>
                                                                    @if($pqIsOverflow)
                                                                        <span class="font-extrabold text-amber-700 dark:text-amber-300">[{{ $pqLabel }}] ✓ Complete (Over Limit):</span>
                                                                    @elseif($pqGlobalNum)
                                                                        <span class="font-extrabold text-emerald-700 dark:text-emerald-300">Q{{ $pqGlobalNum }} ✓ Complete:</span>
                                                                    @else
                                                                        <span class="font-extrabold text-emerald-700 dark:text-emerald-300">{{ $pqLabel }} ✓ Complete:</span>
                                                                    @endif
                                                                    <span class="text-slate-700 dark:text-slate-300 truncate max-w-md">{{ $pq->prompt ? \Illuminate\Support\Str::limit($pq->prompt, 60) : 'Passage-embedded blank' }}</span>
                                                                    @if($pqDiffVal)
                                                                        <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded {{ $pqDiffBadgeColor }}">
                                                                            Auto: {{ ucfirst($pqDiffVal) }}
                                                                        </span>
                                                                    @endif
                                                                    <span class="text-[10px] font-bold border px-1.5 py-0.5 rounded {{ $pqAnsBadgeColor }}" title="Persisted correct answer key">
                                                                        Correct Answer: {{ $pqCorrectDisplay }}
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif

                                        @php
                                            $standaloneQuestions = $secQuestions->filter(fn($item) => empty($item['question']->audio_group_id) && empty($item['question']->passage_group_id));
                                        @endphp

                                        @forelse($standaloneQuestions as $qItem)
                                            @php
                                                $q = $qItem['question'];
                                                $hasWarning = !empty($qItem['warnings']);
                                                $isMaster = !empty($q->question_bank_id);
                                                $qInfo = $globalQuestionNumberMap[(string)$q->id] ?? null;
                                                $qGlobalNum = is_array($qInfo) ? ($qInfo['number'] ?? null) : ($qItem['number'] ?? null);
                                                $qLabel = is_array($qInfo) ? ($qInfo['label'] ?? "Question #{$qGlobalNum}") : "Question #{$qGlobalNum}";
                                                $qIsOverflow = is_array($qInfo) ? ($qInfo['is_overflow'] ?? false) : false;
                                                $qPartNum = (int) ($q->part_number ?: $secPartNumber);
                                                $isStandaloneToeicPart = $isToeicTest && in_array($qPartNum, [1, 2, 5], true);
                                                $correctChoice = $q->choices ? $q->choices->firstWhere('is_correct', true) : null;
                                                $correctLabel = $correctChoice ? strtoupper(trim((string)$correctChoice->label)) : null;
                                                if ($qPartNum === 2 && $correctLabel === 'D') {
                                                    $correctLabel = null;
                                                }
                                                $correctAnswerDisplay = $correctLabel ?: '—';
                                            @endphp
                                            <div id="question-card-{{ $q->id }}" class="bg-slate-50 dark:bg-slate-950/70 border {{ $hasWarning ? 'border-amber-300 dark:border-amber-700/60 bg-amber-50/40 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-800' }} rounded-xl p-4 shadow-sm flex justify-between items-center flex-wrap gap-3">
                                                    <div class="flex-1 min-w-[260px]">
                                                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                                            <span class="text-xs font-extrabold {{ $qIsOverflow ? 'text-amber-600 dark:text-amber-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                                                                @if($qIsOverflow)
                                                                    [{{ $qLabel }}] (Over Limit)
                                                                @elseif($qGlobalNum)
                                                                    Question #{{ $qGlobalNum }}
                                                                @else
                                                                    {{ $qLabel }}
                                                                @endif
                                                            </span>
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
                                                            @php
                                                                $diffVal = is_object($q->difficulty) ? $q->difficulty->value : (string) ($q->difficulty ?? 'medium');
                                                                $diffBadgeColor = match($diffVal) {
                                                                    'easy' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
                                                                    'hard' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800',
                                                                    default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
                                                                };
                                                            @endphp
                                                            <span class="text-[11px] font-bold border px-2 py-0.5 rounded {{ $diffBadgeColor }}">
                                                                ⚡ Auto: {{ ucfirst($diffVal) }}@if(!empty($q->difficulty_score)) ({{ $q->difficulty_score }})@endif
                                                            </span>
                                                        </div>
                                                        <div class="text-sm font-bold text-slate-900 dark:text-white">
                                                            {{ \Illuminate\Support\Str::limit($q->prompt ?? '(Empty Stem)', 75) }}
                                                        </div>

                                                        {{-- Question-level / Shared Media Status Display --}}
                                                        @php
                                                            $qImgMedia = $q->getEffectiveImageMedia();
                                                            $qAudMedia = $q->getEffectiveAudioMedia();
                                                            $qEffectiveImgUrl = $q->getEffectiveImageUrl();
                                                            $qEffectiveAudUrl = $q->getEffectiveAudioUrl();
                                                            $qHasImg = !empty($qImgMedia) || !empty($qEffectiveImgUrl);
                                                            $qHasAudio = !empty($qAudMedia) || !empty($qEffectiveAudUrl);
                                                            $qImgTitle = $qImgMedia?->title ?: ($qImgMedia?->original_name ?: basename($qEffectiveImgUrl ?? 'image'));
                                                            $qAudTitle = $qAudMedia?->title ?: ($qAudMedia?->original_name ?: basename($qEffectiveAudUrl ?? 'audio'));
                                                        @endphp
                                                        <div class="mt-2.5 pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2.5 flex-wrap">
                                                            <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">MEDIA:</span>
                                                            @if($qHasImg)
                                                                <div class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-900 border border-sky-200 dark:border-sky-800/50 px-2 py-1 rounded-md">
                                                                    <img src="{{ $qEffectiveImgUrl }}" alt="Thumbnail" class="w-5 h-5 object-cover rounded border border-slate-300 dark:border-slate-700">
                                                                    <span class="text-xs font-bold text-sky-700 dark:text-sky-300">🖼 Image ✓</span>
                                                                    <button type="button"
                                                                            onclick="previewAssetModal('{{ $qImgMedia?->id ?? '' }}', '{{ addslashes($qImgTitle) }}', 'image', '{{ $qEffectiveImgUrl }}')"
                                                                            class="px-1.5 py-0.5 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/40 rounded text-[11px] font-bold">
                                                                        👁️ Preview
                                                                    </button>
                                                                </div>
                                                            @endif

                                                            @if($qHasAudio)
                                                                <div class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800/50 px-2 py-1 rounded-md">
                                                                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">🎧 Audio ✓</span>
                                                                    <button type="button"
                                                                            onclick="previewAssetModal('{{ $qAudMedia?->id ?? '' }}', '{{ addslashes($qAudTitle) }}', 'audio', '{{ $qEffectiveAudUrl }}')"
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
                                                    @if($isStandaloneToeicPart)
                                                        @php
                                                            $ansBadgeColor = $correctLabel
                                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                                                : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
                                                        @endphp
                                                        <span class="px-2.5 py-1.5 rounded-xl border text-xs font-bold inline-flex items-center {{ $ansBadgeColor }}" title="Persisted correct answer key">
                                                            Correct Answer: {{ $correctAnswerDisplay }}
                                                        </span>
                                                    @endif
                                                    @if($isMaster)
                                                        <button type="button" onclick="openTeacherRequestRevisionModal('{{ $q->question_bank_id }}', '{{ $q->id }}', '{{ addslashes(Str::limit($q->prompt, 60)) }}')" class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800/50 text-xs font-bold inline-flex items-center gap-1">
                                                            🛠 Request Master Revision
                                                        </button>
                                                        <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id, 'return_section' => $sec->id, 'return_focus' => 'question-card-' . $q->id]) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-xs font-bold inline-flex items-center gap-1">
                                                            🔒 Governed Master
                                                        </a>
                                                    @else
                                                        <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id, 'return_section' => $sec->id, 'return_focus' => 'question-card-' . $q->id]) }}" class="px-3 py-1.5 rounded-xl {{ $hasWarning ? 'bg-amber-600 hover:bg-amber-500' : 'bg-indigo-600 hover:bg-indigo-500' }} text-white text-xs font-bold shadow-sm inline-flex items-center gap-1">
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
                                        @empty
                                            @if($secAudioGroups->isEmpty() && $secPassageGroups->isEmpty())
                                                <div class="p-6 text-center bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800 space-y-2">
                                                    <div class="text-slate-400 font-bold text-xs">No questions assigned to this section yet.</div>
                                                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                                                        @if($isToeicTest && in_array((int)$secPartNumber, [6, 7], true))
                                                        <button type="button"
                                                                onclick="openCreatePassageGroupModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}', {{ $secPassageGroups->count() + 1 }})"
                                                                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5 transition-all">
                                                            <span>📖</span> + Add Passage Group to this Section
                                                        </button>
                                                        @elseif($isToeicTest && in_array((int)$secPartNumber, [3, 4], true))
                                                        <button type="button"
                                                                onclick="openCreateAudioGroupModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}')"
                                                                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5 transition-all">
                                                            <span>🎧</span> + Add Audio Group to this Section
                                                        </button>
                                                        @else
                                                        <button type="button"
                                                                onclick="openCreateAuthoredQuestionModal('{{ $sec->id }}', '{{ $secPartNumber }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}')"
                                                                class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1">
                                                            <span>➕</span> + Add Question to this Section
                                                        </button>
                                                        @endif
                                                    @endif
                                                </div>
                                            @endif
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 px-4 text-center bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 space-y-2">
                        <div class="text-3xl">📑</div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">No sections created for this assessment yet</div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 max-w-sm mx-auto">
                            Create your first assessment section (or part) to organize and author questions.
                        </p>
                        @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                        <div class="flex justify-center gap-2.5 pt-2">
                            <button type="button" onclick="openAddSectionModal()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20">
                                📑 + Add Section
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
                            @if($item['status'] === 'completed')
                                <span class="text-emerald-600 dark:text-emerald-400 ml-1">✅</span>
                            @elseif($item['status'] === 'active')
                                <span class="text-amber-600 dark:text-amber-400 ml-1">⚡</span>
                            @elseif($item['status'] === 'pending')
                                <span class="text-slate-400 ml-1">⏳</span>
                            @endif
                        </div>
                        @if(!empty($item['note']))
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $item['note'] }}
                        </div>
                        @endif
                        @if($item['date'])
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
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
            <button type="button" onclick="attemptCloseAuthoredQuestionModal()" aria-label="Close question modal" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">×</button>
        </div>

        {{-- Draft Restored Notice Banner --}}
        <div id="q-draft-restored-banner" class="hidden items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 text-amber-900 dark:text-amber-200 text-xs mb-4">
            <div class="flex items-center gap-2">
                <span class="text-base">📝</span>
                <span class="font-bold">Unsaved draft restored.</span>
            </div>
            <button type="button" onclick="discardAuthoredQuestionDraft(true)" class="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/60 dark:hover:bg-amber-800 text-amber-900 dark:text-amber-100 text-xs font-extrabold rounded-lg border border-amber-300 dark:border-amber-700 transition-colors">
                Discard Draft
            </button>
        </div>

        <form id="create-authored-question-form" method="POST" action="{{ route('teacher.tests.create-question', $test->id) }}" onsubmit="return validateCreateQuestionForm(this)" class="space-y-4">
            @csrf

            @php
                $isToeicTest = (is_object($test->test_type) ? $test->test_type->value : (string)$test->test_type) === 'toeic';
            @endphp

            <!-- Contextual Section Banner (when opened from a specific Section Card) -->
            <div id="create-q-context-banner" class="hidden p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700/60 rounded-xl flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 block">Adding Question To Section</span>
                    <span id="create-q-context-title" class="text-xs font-black text-emerald-950 dark:text-emerald-100"></span>
                </div>
                <span id="create-q-context-badge" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 uppercase"></span>
            </div>

            <!-- Global Section / Part Selector (when opened from top global + Add New Question) -->
            <div id="create-q-global-selector-container" class="space-y-4">
                @if($isToeicTest)
                <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 p-4 rounded-xl space-y-1.5">
                    <label class="block text-xs font-extrabold uppercase tracking-wider text-indigo-900 dark:text-indigo-200">🎯 Section / TOEIC Part Selection <span class="text-rose-500">*</span></label>
                    <select id="create-q-unified-part-section" onchange="onGlobalUnifiedSectionChange(this)" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-indigo-300 dark:border-indigo-700 rounded-xl text-slate-900 dark:text-white text-xs font-bold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        @foreach($test->sections as $secIdx => $sec)
                            @php
                                $firstQ = $sec->testQuestions->first()?->question;
                                $optPartNumber = $firstQ?->part_number ?? ($sec->order ?? ($secIdx + 1));
                                $isPassagePart = in_array((int)$optPartNumber, [6, 7], true);
                            @endphp
                            <option value="{{ $sec->id }}" data-part="{{ $optPartNumber }}" data-title="{{ $sec->title }}" data-type="{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}" {{ $isPassagePart ? 'disabled' : '' }}>
                                {{ $sec->title }} (Part {{ $optPartNumber }} • {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type ?? 'Listening') }}){{ $isPassagePart ? ' — Must author via Passage Group' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @else
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Target Section <span class="text-rose-500">*</span></label>
                    <select id="create-q-generic-section-select" onchange="onGlobalGenericSectionChange(this)" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        @foreach($test->sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->title }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>

            <!-- Hidden Foreign Key & Part State -->
            <input type="hidden" name="test_section_id" id="create-q-section-id" value="{{ $test->sections->first()?->id }}">
            <input type="hidden" name="part_number" id="create-q-part-number" value="1">
            <input type="hidden" name="section" id="create-q-section" value="listening">

            @if($isToeicTest)
            <div id="create-q-passage-container" class="hidden bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 rounded-xl space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">📖 Reading Passage Text <span class="text-rose-500">*</span></label>
                <textarea name="passage_text" id="create-q-passage-text" rows="3" oninput="updateCreateModalAutoDifficulty()" placeholder="Enter reading passage text for Part 6 / 7..." class="w-full px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs"></textarea>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                <div></div>
            </div>

            <div>
                <label id="create-q-prompt-label" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">Question Prompt / Stem</label>
                <textarea id="create-q-prompt" name="prompt" rows="3" oninput="updateCreateModalAutoDifficulty()" placeholder="Enter question prompt..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
            </div>

            {{-- Auto-Difficulty Status Area (Read-Only) --}}
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3.5 rounded-xl space-y-1.5">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        ⚡ Question Difficulty
                    </label>
                    <span id="create-q-auto-diff-badge" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                        <span id="create-q-auto-diff-dot" class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        <span id="create-q-auto-diff-text">Waiting for required inputs</span>
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span id="create-q-auto-diff-hint" class="text-[11px] text-slate-500 dark:text-slate-400">
                        Difficulty is detected automatically from question content, part rules, and attached media.
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        Auto-Detected
                    </span>
                </div>
                <input type="hidden" name="points" value="1">
            </div>

            {{-- Question Media Section --}}
            <div id="create-q-media-section" class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 rounded-xl space-y-3">
                <div>
                    <span id="create-q-media-title" class="text-xs font-extrabold uppercase tracking-wider text-slate-800 dark:text-slate-200 block">🖼️ / 🎧 Question Media (Optional)</span>
                    <span id="create-q-media-hint" class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Attach Question-level Photo (Image) and/or Audio Prompt (e.g. TOEIC Part 1 Photographs).</span>
                </div>

                <input type="hidden" id="q-media-asset-id" name="media_asset_id" value="">
                <input type="hidden" id="q-image-url" name="image_url" value="">
                <input type="hidden" id="q-audio-url" name="audio_url" value="">

                {{-- Attached Media Previews --}}
                <div id="q-attached-media-container" class="flex flex-col gap-2.5 mt-2">
                    {{-- Image Controls Wrapper --}}
                    <div id="q-image-controls-wrapper" class="flex flex-col gap-2.5">
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
                    </div>

                    {{-- Audio Controls Wrapper --}}
                    <div id="q-audio-controls-wrapper" class="flex flex-col gap-2.5">
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
                                    <span id="q-empty-audio-title" class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Question Audio Prompt</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">No audio attached</span>
                                </div>
                            </div>
                            <button type="button" onclick="openQuestionMediaPicker('create', 'audio')" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded-xl text-xs font-bold">
                                + Attach Audio
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Choices Section with Explicit Visual Correct Answer Indicator --}}
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-4 rounded-xl space-y-3">
                <div class="flex justify-between items-center flex-wrap gap-2">
                    <label id="create-q-choices-label" class="text-xs font-extrabold uppercase tracking-wider text-slate-800 dark:text-slate-200 m-0">Multiple Choice Options &amp; Correct Answer</label>
                    <span id="create-q-choices-hint" class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Select exactly one radio button as the correct answer.</span>
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
                        <input type="text" name="choices[]" oninput="updateCreateModalAutoDifficulty()" placeholder="Option {{ $label }} text" {{ $i < 2 ? 'required' : '' }} class="flex-1 px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
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
                <button type="button" onclick="attemptCloseAuthoredQuestionModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Cancel</button>
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
<div id="attach-section-media-modal" class="hidden fixed inset-0 z-[10001] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 10001;" onclick="closeAttachSectionMediaModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[92vh] flex flex-col p-5 sm:p-6 shadow-2xl space-y-4 overflow-y-auto" onclick="event.stopPropagation()">
        {{-- Header --}}
        <div class="flex justify-between items-start pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                    <span>Attach Media Asset to Section</span>
                </div>
                <div id="asm-target-section-title" class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 font-bold"></div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                    Upload a new working file or choose from existing media library.
                </div>
            </div>
            <button type="button" onclick="closeAttachSectionMediaModal()" aria-label="Close section media modal" class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
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

        {{-- Mode A: Direct Media Upload Panel (Modernized Dropzone) --}}
        <div id="asm-upload-panel" class="hidden space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        Upload New Media
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                        New uploads are saved to My Media and can be selected immediately.
                    </div>
                </div>
                <div id="asm-accepted-formats-label" class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">
                    Max 10 MB
                </div>
            </div>

            {{-- Dropzone / Upload Area --}}
            <div id="asm-dropzone" ondragover="handleSectionMediaDragOver(event)" ondragleave="handleSectionMediaDragLeave(event)" ondrop="handleSectionMediaDrop(event)" class="bg-slate-50 dark:bg-slate-950/80 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl p-4 transition-all">

                {{-- Accessible Hidden Native Input --}}
                <input type="file" id="asm-upload-file" accept="image/jpeg,image/png,image/webp,audio/mpeg,audio/mp3,audio/wav,audio/x-m4a,audio/m4a,application/pdf" onchange="handleSectionMediaFileSelect(this)" class="sr-only" aria-label="Select media file to upload">

                {{-- State 1: No file selected --}}
                <div id="asm-empty-upload-state" class="flex flex-col items-center justify-center py-2 space-y-2.5 text-center">
                    <div class="w-10 h-10 rounded-full bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                            Select a file to upload or drag and drop here
                        </div>
                        <div id="asm-no-file-text" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                            No file selected
                        </div>
                        <div id="asm-accepted-formats-text" class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 font-mono">
                            JPG, JPEG, PNG, WebP, MP3, M4A, WAV, PDF
                        </div>
                    </div>
                    <div class="pt-1">
                        <button type="button" id="asm-choose-file-btn" onclick="document.getElementById('asm-upload-file').click()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-xl text-xs font-extrabold shadow-sm inline-flex items-center gap-1.5 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>Choose File</span>
                        </button>
                    </div>
                </div>

                {{-- State 2: Selected file state (Hidden initially) --}}
                <div id="asm-selected-upload-state" class="hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div id="asm-selected-icon-container" class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div class="min-w-0 text-left">
                                <div class="flex items-center gap-2">
                                    <span id="asm-selected-type-badge" class="text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">FILE</span>
                                    <span id="asm-selected-filesize" class="text-[11px] text-slate-500 dark:text-slate-400 font-mono"></span>
                                </div>
                                <div id="asm-selected-filename" class="text-xs font-extrabold text-slate-900 dark:text-white truncate max-w-xs sm:max-w-md mt-0.5"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="document.getElementById('asm-upload-file').click()" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 transition-colors">
                                Change File
                            </button>
                            <button type="button" onclick="clearSectionMediaFileSelection()" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-xs font-bold rounded-lg border border-rose-200 dark:border-rose-900/40 transition-colors" title="Remove selected file">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Optional Media Title Field --}}
                <div class="mt-3">
                    <label for="asm-upload-title" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Media Title (Optional)
                    </label>
                    <input type="text" id="asm-upload-title" placeholder="e.g. Part 1 Listening Directions Audio" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                {{-- Action Row --}}
                <div class="mt-3 flex justify-end items-center gap-2">
                    <button type="button" id="asm-upload-btn" onclick="uploadSectionMediaFile()" disabled aria-disabled="true" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-extrabold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span>Upload &amp; Select Asset</span>
                    </button>
                </div>

                {{-- Inline Feedback / Error Box --}}
                <div id="asm-upload-feedback" class="hidden p-2.5 rounded-xl text-xs font-bold text-left mt-2"></div>
            </div>
        </div>

        {{-- Mode B: Library Browser Panel --}}
        <div id="asm-library-panel" class="flex flex-col flex-1 min-h-0 space-y-2.5">
            {{-- Header & Source Selector Tabs: [ My Media ] [ Institutional Library ] --}}
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <div class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        Choose Existing Media
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                        Browse your working drafts or approved institutional media.
                    </div>
                </div>

                {{-- Source Selector Tabs --}}
                <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700" role="tablist">
                    <button type="button" id="asm-source-my-btn" onclick="setSectionMediaSource('my', event)" role="tab" aria-selected="true" class="px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all">
                        My Media
                    </button>
                    <button type="button" id="asm-source-inst-btn" onclick="setSectionMediaSource('institutional', event)" role="tab" aria-selected="false" class="px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all">
                        Institutional Library
                    </button>
                </div>
            </div>

            {{-- Media Filter Tabs & Search --}}
            <div class="flex gap-2 flex-wrap items-center justify-between">
                <div class="flex gap-1.5 flex-wrap">
                    <button type="button" onclick="filterSectionMediaModal('all', event)" class="asm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm">All Media</button>
                    <button type="button" onclick="filterSectionMediaModal('audio', event)" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition-colors">🎵 Audio Tracks</button>
                    <button type="button" onclick="filterSectionMediaModal('image', event)" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition-colors">🖼️ Images</button>
                    <button type="button" onclick="filterSectionMediaModal('passage', event)" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition-colors">📖 Passages</button>
                    <button type="button" onclick="filterSectionMediaModal('pdf', event)" class="asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition-colors">📄 PDFs</button>
                </div>
                <div class="relative min-w-[180px]">
                    <input type="text" id="asm-search-input" onkeyup="searchSectionMediaModal(this.value)" placeholder="Search media by title..." class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            {{-- Media Grid Container --}}
            <div id="asm-media-list-container" class="flex-1 min-h-[180px] max-h-[240px] overflow-y-auto bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>
            </div>
        </div>

        {{-- FORM: Section Media Attachment Form (Selected Badge + Caption + Order + Submit) --}}
        <form id="attach-section-media-form" method="POST" action="" class="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            @csrf
            <input type="hidden" id="asm-media-asset-id" name="media_asset_id" value="" required>

            {{-- Selected Media Badge --}}
            <div id="asm-selected-preview" class="hidden p-3 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/50 rounded-xl items-center justify-between">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span id="asm-selected-icon" class="text-xl shrink-0">📎</span>
                    <div class="min-w-0 text-left">
                        <div id="asm-selected-title" class="text-xs font-bold text-indigo-950 dark:text-indigo-100 truncate"></div>
                        <div id="asm-selected-meta" class="text-[11px] text-indigo-700 dark:text-indigo-300 font-medium"></div>
                    </div>
                </div>
                <button type="button" onclick="clearSelectedSectionMedia()" class="px-2.5 py-1 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[10px] font-bold shrink-0">Clear</button>
            </div>

            {{-- Section-Specific Caption and Order Inputs --}}
            <div class="grid grid-cols-1 sm:grid-cols-[2fr_1fr] gap-3">
                <div>
                    <label for="asm-caption" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Section Media Caption / Directions Label (Optional)
                    </label>
                    <input type="text" id="asm-caption" name="caption" placeholder="e.g. Listening Directions Audio, Reference Photograph" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="asm-order" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Display Order</label>
                    <input type="number" id="asm-order" name="order" min="1" placeholder="Auto (Next)" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeAttachSectionMediaModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors">Cancel</button>
                <button type="submit" id="asm-submit-btn" disabled aria-disabled="true" class="px-5 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all">Attach Selected Media</button>
            </div>
        </form>
    </div>
</div>

@push('modals')
{{-- Modal 2b: Create / Edit Assessment-Authored Shared Audio Group (Part 3 / Part 4) --}}
<div id="create-audio-group-modal" class="hidden fixed inset-0 z-[10000] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 10000;">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-4xl w-full max-h-[92vh] flex flex-col p-5 sm:p-6 shadow-2xl space-y-4 overflow-y-auto" onclick="event.stopPropagation()">
        {{-- Header --}}
        <div class="flex justify-between items-start pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="text-indigo-600 dark:text-indigo-400">🎧</span>
                    <span id="ag-modal-title">Create Audio Question Group</span>
                </div>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                    <span id="ag-target-section-title" class="text-xs text-indigo-700 dark:text-indigo-300 font-bold"></span>
                    <span id="ag-part-badge" class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800/40">LISTENING • PART 3</span>
                    <span id="ag-header-progress" class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">Progress: 0 / 3 Complete</span>
                </div>
                <div class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 font-medium">
                    TOEIC Part 3/4 uses exactly 3 questions per audio group. You may save your progress before all 3 questions are complete.
                </div>
            </div>
            <button type="button" onclick="attemptCloseAudioGroupModal()" aria-label="Close audio group modal" class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- Draft Restored Notice Banner --}}
        <div id="ag-draft-restored-banner" class="hidden items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 text-amber-900 dark:text-amber-200 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-base">📝</span>
                <span class="font-bold">Unsaved draft restored.</span>
            </div>
            <button type="button" onclick="discardAudioGroupDraft(true)" class="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/60 dark:hover:bg-amber-800 text-amber-900 dark:text-amber-100 text-xs font-extrabold rounded-lg border border-amber-300 dark:border-amber-700 transition-colors">
                Discard Draft
            </button>
        </div>

        <form id="create-audio-group-form" method="POST" action="{{ route('teacher.tests.create-audio-group', $test->id) }}" onsubmit="return validateCreateAudioGroupForm(this)" class="space-y-5">
            @csrf
            <input type="hidden" name="_method" id="ag-form-method" value="POST">
            {{-- Locked Contextual Fields --}}
            <input type="hidden" name="test_section_id" id="ag-section-id" value="" required>
            <input type="hidden" name="part_number" id="ag-part-number" value="3" required>
            <input type="hidden" name="group_type" id="ag-group-type" value="conversation" required>
            <input type="hidden" name="media_asset_id" id="ag-media-asset-id" value="">
            <input type="hidden" name="audio_url" id="ag-audio-url" value="">

            {{-- 1. Shared Audio Stimulus Section --}}
            <div class="bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                            <span>🎧</span>
                            <span>Shared Audio Stimulus</span>
                            <span class="text-rose-600 dark:text-rose-400">*</span>
                        </div>
                        <div class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 font-medium">
                            This audio will be shared by all 3 questions in this group.
                        </div>
                    </div>
                </div>

                {{-- Empty State (No audio chosen yet) --}}
                <div id="ag-empty-audio-card" class="bg-white dark:bg-slate-900 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-4 flex flex-col items-center justify-center text-center space-y-2">
                    <div class="w-9 h-9 rounded-full bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-700 dark:text-indigo-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-900 dark:text-slate-100">No shared audio attached yet</div>
                        <div class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 font-medium">Choose from My Media, Institutional Library, or upload new audio (MP3, M4A, WAV).</div>
                    </div>
                    <button type="button" onclick="openQuestionMediaPicker('audio-group', 'audio')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-extrabold shadow-sm inline-flex items-center gap-1.5 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Select / Upload Shared Audio</span>
                    </button>
                </div>

                {{-- Selected Audio Card (Hidden initially) --}}
                <div id="ag-preview-audio-card" class="hidden bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800/60 rounded-xl p-3 shadow-sm space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-700 dark:text-indigo-300 shrink-0 text-xl">
                                🎵
                            </div>
                            <div class="min-w-0 text-left">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-indigo-800 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800/40">SHARED AUDIO</span>
                                </div>
                                <div id="ag-preview-audio-title" class="text-xs font-extrabold text-slate-900 dark:text-white truncate max-w-xs sm:max-w-md mt-0.5"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="openQuestionMediaPicker('audio-group', 'audio')" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-lg border border-slate-300 dark:border-slate-700 transition-colors">
                                Change Audio
                            </button>
                            <button type="button" onclick="removeAudioGroupAttachedMedia()" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs font-bold rounded-lg border border-rose-200 dark:border-rose-900/40 transition-colors" title="Remove audio">
                                Clear
                            </button>
                        </div>
                    </div>
                    <div class="pt-1">
                        <audio id="ag-preview-audio-player" controls class="w-full h-8" preload="metadata"></audio>
                    </div>
                </div>

                {{-- Optional Group Title & Audio Script --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label for="ag-title" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Group Title (Optional)
                        </label>
                        <input type="text" name="title" id="ag-title" placeholder="e.g. Office Meeting Conversation, Airport Announcement" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="ag-audio-script" id="ag-script-label" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Conversation Script / Transcript (Optional)
                        </label>
                        <input type="text" name="audio_script" id="ag-audio-script" placeholder="Enter dialogue transcript or audio script..." class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            {{-- 2. Exactly 3 Child Question Panels --}}
            <div class="space-y-4">
                <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center justify-between">
                    <span>Child Questions</span>
                    <span class="text-[11px] font-bold text-indigo-700 dark:text-indigo-300 lowercase">Questions 1, 2, 3</span>
                </div>

                @for($i = 0; $i < 3; $i++)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm space-y-3">
                    <input type="hidden" name="questions[{{ $i }}][id]" id="ag-q{{ $i }}-id" value="">
                    {{-- Question Panel Header --}}
                    <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">Question {{ $i + 1 }} of 3</span>
                            <span id="ag-q{{ $i }}-status-badge" class="text-[11px] font-bold px-2.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700">○ Incomplete</span>
                        </div>
                        <div id="ag-q{{ $i }}-diff-badge" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700">
                            <span id="ag-q{{ $i }}-diff-dot" class="w-1.5 h-1.5 rounded-full bg-slate-500 dark:bg-slate-400"></span>
                            <span id="ag-q{{ $i }}-diff-text">Waiting for input</span>
                        </div>
                    </div>

                    {{-- Question Prompt --}}
                    <div>
                        <label for="ag-q{{ $i }}-prompt" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Question {{ $i + 1 }} Prompt / Stem
                        </label>
                        <textarea name="questions[{{ $i }}][prompt]" id="ag-q{{ $i }}-prompt" rows="2" oninput="updateAudioGroupAutoDifficulty()" placeholder="e.g. What does the woman suggest the man do?" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    {{-- Answer Choices (A, B, C, D) --}}
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                                Answer Choices (A–D) &amp; Correct Answer
                            </label>
                            <span class="text-xs text-slate-600 dark:text-slate-300 font-semibold">Select radio for correct answer</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach(['A', 'B', 'C', 'D'] as $cIdx => $optLabel)
                            <div class="flex items-center gap-2 p-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg">
                                <input type="radio" name="questions[{{ $i }}][correct_choice]" value="{{ $cIdx }}" id="ag-q{{ $i }}-correct-{{ $cIdx }}" {{ $cIdx === 0 ? 'checked' : '' }} onchange="updateAudioGroupAutoDifficulty()" class="accent-emerald-600 w-4 h-4 cursor-pointer" title="Mark Option {{ $optLabel }} as correct">
                                <span class="text-xs font-black text-slate-900 dark:text-white w-4">{{ $optLabel }}</span>
                                <input type="text" name="questions[{{ $i }}][choices][]" id="ag-q{{ $i }}-choice-{{ $cIdx }}" oninput="updateAudioGroupAutoDifficulty()" placeholder="Option {{ $optLabel }} text" class="flex-1 px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Optional Explanation --}}
                    <div>
                        <label for="ag-q{{ $i }}-explanation" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Answer Explanation / Rationale (Optional)
                        </label>
                        <input type="text" name="questions[{{ $i }}][explanation]" id="ag-q{{ $i }}-explanation" placeholder="Explain why the correct answer is right..." class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>
                @endfor
            </div>

            {{-- 3. Action Footer --}}
            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="attemptCloseAudioGroupModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="ag-submit-btn" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-md shadow-emerald-600/20 inline-flex items-center gap-1.5 transition-all">
                    <span id="ag-submit-icon">💾</span>
                    <span id="ag-submit-text">Save Draft Group</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2c: Create/Edit Assessment-Authored Shared Passage Group (Part 6 / Part 7) --}}
<div id="create-passage-group-modal" class="hidden fixed inset-0 z-[10000] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 10000;" onclick="closeCreatePassageGroupModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-4xl w-full max-h-[92vh] flex flex-col p-5 sm:p-6 shadow-2xl space-y-4 overflow-y-auto" onclick="event.stopPropagation()">
        {{-- Header --}}
        <div class="flex justify-between items-start pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="text-indigo-600 dark:text-indigo-400">📖</span>
                    <span id="pg-modal-title">Create Text Completion Group</span>
                </div>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                    <span id="pg-target-section-title" class="text-xs text-indigo-700 dark:text-indigo-300 font-bold"></span>
                    <span id="pg-part-badge" class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800/40">READING • PART 6</span>
                    <span id="pg-count-badge" class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">4 Questions per Text</span>
                    <span id="pg-header-progress" class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800">Progress: 0 / 4 Complete</span>
                </div>
                <div id="pg-helper-text" class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 font-medium">
                    Each Part 6 text contains four Text Completion questions. You may save your progress before all four questions are complete.
                </div>
            </div>
            <button type="button" onclick="attemptClosePassageGroupModal()" aria-label="Close passage group modal" class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- Draft Restored Notice Banner --}}
        <div id="pg-draft-restored-banner" class="hidden items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 text-amber-900 dark:text-amber-200 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-base">📝</span>
                <span class="font-bold">Unsaved draft restored.</span>
            </div>
            <button type="button" onclick="discardPassageGroupDraft(true)" class="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/60 dark:hover:bg-amber-800 text-amber-900 dark:text-amber-100 text-xs font-extrabold rounded-lg border border-amber-300 dark:border-amber-700 transition-colors">
                Discard Draft
            </button>
        </div>

        <form id="create-passage-group-form" method="POST" action="{{ route('teacher.tests.create-passage-group', $test->id) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="_method" id="pg-form-method" value="POST">
            {{-- Locked Contextual Fields --}}
            <input type="hidden" name="test_section_id" id="pg-section-id" value="" required>
            <input type="hidden" name="part_number" id="pg-part-number" value="6" required>
            <input type="hidden" name="passage_type" id="pg-passage-type" value="single" required>

            {{-- Part 7 Standards-Aware Passage Set Type Selector --}}
            <div id="pg-set-type-wrapper" class="hidden bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/50 rounded-xl p-3.5 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                        <span>📑</span> PASSAGE SET TYPE <span class="text-rose-600 dark:text-rose-400">*</span>
                    </span>
                    <span class="text-[11px] text-slate-600 dark:text-slate-300 font-medium">Controls documents &amp; question count</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <button type="button" id="pg-type-btn-single" onclick="setPassageSetType('single')" class="flex flex-col items-start p-3 rounded-xl border-2 transition-all text-left bg-white dark:bg-slate-900 border-indigo-600 shadow-sm">
                        <div class="flex items-center gap-2 font-black text-xs text-indigo-700 dark:text-indigo-300">
                            <span>📄</span> Single Passage
                        </div>
                        <div class="text-[10px] text-slate-600 dark:text-slate-300 mt-1 font-medium leading-tight">
                            1 Document • 2 to 4 Questions
                        </div>
                    </button>
                    <button type="button" id="pg-type-btn-double" onclick="setPassageSetType('double')" class="flex flex-col items-start p-3 rounded-xl border-2 transition-all text-left bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 hover:border-indigo-400">
                        <div class="flex items-center gap-2 font-black text-xs text-slate-800 dark:text-slate-200">
                            <span>📄📄</span> Double Passage
                        </div>
                        <div class="text-[10px] text-slate-600 dark:text-slate-300 mt-1 font-medium leading-tight">
                            2 Documents • Exactly 5 Questions
                        </div>
                    </button>
                    <button type="button" id="pg-type-btn-triple" onclick="setPassageSetType('triple')" class="flex flex-col items-start p-3 rounded-xl border-2 transition-all text-left bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 hover:border-indigo-400">
                        <div class="flex items-center gap-2 font-black text-xs text-slate-800 dark:text-slate-200">
                            <span>📄📄📄</span> Triple Passage
                        </div>
                        <div class="text-[10px] text-slate-600 dark:text-slate-300 mt-1 font-medium leading-tight">
                            3 Documents • Exactly 5 Questions
                        </div>
                    </button>
                </div>
            </div>

            {{-- 1. Passage Group Title & Stimulus Section --}}
            <div class="bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                            <span>📄</span>
                            <span id="pg-stimulus-heading">Text / Passage Stimulus</span>
                            <span class="text-rose-600 dark:text-rose-400">*</span>
                        </div>
                        <div id="pg-stimulus-subheading" class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 font-medium">
                            This text is shared by all 4 questions in this group.
                        </div>
                    </div>
                </div>

                <div>
                    <label for="pg-group-title" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                        Group Title (Optional)
                    </label>
                    <input type="text" name="title" id="pg-group-title" placeholder="e.g. Training Course Announcement, Customer Email, Staff Memo..." class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                {{-- Document Tabs for Double/Triple --}}
                <div id="pg-doc-tabs-bar" class="hidden flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                    <button type="button" id="pg-doc-tab-btn-0" onclick="switchPassageDocTab(0)" class="px-3 py-1.5 rounded-lg text-xs font-black border bg-indigo-600 text-white border-indigo-600 shadow-sm transition-all">Document 1</button>
                    <button type="button" id="pg-doc-tab-btn-1" onclick="switchPassageDocTab(1)" class="px-3 py-1.5 rounded-lg text-xs font-black border bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700 hover:border-indigo-300 transition-all">Document 2</button>
                    <button type="button" id="pg-doc-tab-btn-2" onclick="switchPassageDocTab(2)" class="hidden px-3 py-1.5 rounded-lg text-xs font-black border bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700 hover:border-indigo-300 transition-all">Document 3</button>
                </div>

                {{-- Document Panels (Up to 3) --}}
                @for($d = 0; $d < 3; $d++)
                <div id="pg-doc-panel-{{ $d }}" class="{{ $d > 0 ? 'hidden' : '' }} space-y-3 p-3.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
                    <input type="hidden" name="passages[{{ $d }}][id]" id="pg-doc-{{ $d }}-id" value="">
                    <input type="hidden" name="passages[{{ $d }}][order_in_group]" id="pg-doc-{{ $d }}-order" value="{{ $d + 1 }}">
                    <input type="hidden" name="passages[{{ $d }}][content_mode]" id="pg-doc-{{ $d }}-content-mode" value="text">
                    <input type="hidden" name="passages[{{ $d }}][image_url]" id="pg-doc-{{ $d }}-image-url" value="">
                    <input type="hidden" name="passages[{{ $d }}][media_asset_id]" id="pg-doc-{{ $d }}-media-asset-id" value="">

                    <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 font-black text-[11px] flex items-center justify-center">{{ $d + 1 }}</span>
                            <span class="text-xs font-extrabold text-slate-900 dark:text-white">Document {{ $d + 1 }}</span>
                        </div>
                        {{-- Document Type Selector --}}
                        <div class="flex items-center gap-1.5">
                            <label for="pg-doc-{{ $d }}-type" class="text-[11px] font-bold text-slate-700 dark:text-slate-300">Type:</label>
                            <select name="passages[{{ $d }}][document_type]" id="pg-doc-{{ $d }}-type" class="px-2.5 py-1 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-800 dark:text-slate-200">
                                <option value="article">Article / Report</option>
                                <option value="email">Email</option>
                                <option value="memo">Memo</option>
                                <option value="advertisement">Advertisement</option>
                                <option value="notice">Notice / Announcement</option>
                                <option value="schedule">Schedule / Timetable</option>
                                <option value="form">Form / Application</option>
                                <option value="invoice">Invoice / Receipt</option>
                                <option value="chat">Text Message Chain</option>
                                <option value="webpage">Web Page</option>
                                <option value="letter">Letter</option>
                                <option value="other">Other Document</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="pg-doc-{{ $d }}-title" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                                Document Title (Optional)
                            </label>
                            <input type="text" name="passages[{{ $d }}][title]" id="pg-doc-{{ $d }}-title" placeholder="e.g. Flight Schedule, Customer Feedback..." class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        {{-- Content Mode Selector (Only Part 7) --}}
                        <div id="pg-doc-{{ $d }}-mode-selector-wrap" class="hidden">
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                                Content Stimulus Format
                            </label>
                            <div class="grid grid-cols-3 gap-1 p-1 bg-slate-100 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                                <button type="button" id="pg-doc-{{ $d }}-mode-text-btn" onclick="setPassageDocContentMode({{ $d }}, 'text')" class="px-2 py-1 rounded-lg text-[11px] font-extrabold bg-indigo-600 text-white shadow-sm transition-all">Text</button>
                                <button type="button" id="pg-doc-{{ $d }}-mode-image-btn" onclick="setPassageDocContentMode({{ $d }}, 'image')" class="px-2 py-1 rounded-lg text-[11px] font-extrabold text-slate-800 dark:text-slate-200 hover:text-indigo-600 transition-all">Visual Image</button>
                                <button type="button" id="pg-doc-{{ $d }}-mode-text_image-btn" onclick="setPassageDocContentMode({{ $d }}, 'text_image')" class="px-2 py-1 rounded-lg text-[11px] font-extrabold text-slate-800 dark:text-slate-200 hover:text-indigo-600 transition-all">Text + Image</button>
                            </div>
                        </div>
                    </div>

                    {{-- Passage Text Input --}}
                    <div id="pg-doc-{{ $d }}-text-wrap">
                        <label for="pg-doc-{{ $d }}-content" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Passage Text <span class="text-rose-600 dark:text-rose-400" id="pg-doc-{{ $d }}-text-req">*</span>
                        </label>
                        <textarea name="passages[{{ $d }}][content]" id="{{ $d === 0 ? 'pg-passage-content' : 'pg-doc-' . $d . '-content' }}" rows="5" placeholder="Enter reading passage text..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium leading-relaxed focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    {{-- Visual Document Image Attachment --}}
                    <div id="pg-doc-{{ $d }}-image-wrap" class="hidden space-y-2">
                        <label class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                            Attached Visual Document Stimulus <span class="text-rose-600 dark:text-rose-400">*</span>
                        </label>
                        <div class="text-[11px] text-slate-600 dark:text-slate-300 font-medium">
                            Attach candidate-readable visual document (e.g. advertisement layout, form, timetable, invoice).
                        </div>
                        {{-- Empty state --}}
                        <div id="pg-doc-{{ $d }}-empty-image-card" class="flex flex-col items-center justify-center p-4 bg-slate-50 dark:bg-slate-950 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl text-center">
                            <svg class="w-8 h-8 text-indigo-500 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-2">No visual document attached</span>
                            <button type="button" onclick="openQuestionMediaPicker('passage-{{ $d }}', 'image')" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-sm inline-flex items-center gap-1.5 transition-all">
                                <span>🖼</span> Attach Visual Document
                            </button>
                        </div>
                        {{-- Attached state --}}
                        <div id="pg-doc-{{ $d }}-preview-image-card" class="hidden flex items-center justify-between p-3 bg-indigo-50/50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 rounded-xl">
                            <div class="flex items-center gap-3">
                                <img id="pg-doc-{{ $d }}-preview-image-thumb" src="" alt="Attached Document" class="w-16 h-16 object-contain rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
                                <div>
                                    <div id="pg-doc-{{ $d }}-preview-image-title" class="text-xs font-bold text-slate-900 dark:text-white truncate max-w-xs sm:max-w-sm">Document.png</div>
                                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-200 dark:border-emerald-700">Visual Document Attached</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="previewAssetModal(document.getElementById('pg-doc-{{ $d }}-media-asset-id').value, document.getElementById('pg-doc-{{ $d }}-preview-image-title').textContent, 'image', document.getElementById('pg-doc-{{ $d }}-image-url').value)" class="px-2.5 py-1.5 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-lg text-xs font-bold border border-slate-300 dark:border-slate-700 hover:bg-slate-100 transition-colors">Preview</button>
                                <button type="button" onclick="removePassageDocMedia({{ $d }})" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 dark:text-rose-300 rounded-lg text-xs font-bold border border-rose-300 dark:border-rose-900 transition-colors">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- 2. Child Questions Panel (2 to 5 Questions) --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 dark:border-slate-800 flex-wrap gap-2">
                    <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span>📝</span>
                        <span id="pg-questions-heading">Child Questions (Exactly 4 Questions Required)</span>
                    </div>
                    <button type="button" id="pg-add-question-btn" onclick="addPassageChildQuestion()" class="hidden px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 rounded-xl text-xs font-extrabold inline-flex items-center gap-1 transition-all">
                        <span>+</span> Add Question
                    </button>
                </div>

                @for($i = 0; $i < 5; $i++)
                <div id="pg-q-card-{{ $i }}" class="{{ $i >= 4 ? 'hidden' : '' }} bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm space-y-3">
                    <input type="hidden" name="questions[{{ $i }}][id]" id="pg-q{{ $i }}-id" value="">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center">{{ $i + 1 }}</span>
                            <span class="text-xs font-extrabold text-slate-900 dark:text-white" id="pg-q{{ $i }}-label">{{ $i < 4 ? 'Question ' . ($i + 1) . ' of 4' : 'Question ' . ($i + 1) }}</span>
                            <span id="pg-q{{ $i }}-status-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">○ Incomplete</span>
                        </div>
                        <button type="button" id="pg-q{{ $i }}-remove-btn" onclick="removePassageChildQuestion({{ $i }})" class="hidden px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition-colors">
                            ✕ Remove
                        </button>
                    </div>

                    {{-- Question Prompt / Stem --}}
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="pg-q{{ $i }}-prompt" class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                                <span id="pg-q{{ $i }}-prompt-label">Question Note / Blank Context (Optional)</span> <span class="text-rose-600 dark:text-rose-400 hidden" id="pg-q{{ $i }}-prompt-req">*</span>
                            </label>
                            <span class="text-[10px] text-slate-600 dark:text-slate-300 font-medium" id="pg-q{{ $i }}-prompt-note">Optional note</span>
                        </div>
                        <div class="text-[11px] text-slate-600 dark:text-slate-300 mb-1" id="pg-q{{ $i }}-prompt-help">
                            The numbered blank is normally placed directly in the passage. Use this field only when additional authoring context is needed.
                        </div>
                        <textarea name="questions[{{ $i }}][prompt]" id="pg-q{{ $i }}-prompt" rows="2" placeholder="e.g. What is suggested about the advertisement?" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    {{-- Answer Choices (A, B, C, D) --}}
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                                Answer Choices (A–D) &amp; Correct Answer <span class="text-rose-600 dark:text-rose-400">*</span>
                            </label>
                            <span class="text-xs text-slate-600 dark:text-slate-300 font-semibold">Select radio for correct answer</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach(['A', 'B', 'C', 'D'] as $cIdx => $optLabel)
                            <div class="flex items-center gap-2 p-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg">
                                <input type="radio" name="questions[{{ $i }}][correct_choice]" value="{{ $cIdx }}" id="pg-q{{ $i }}-correct-{{ $cIdx }}" class="accent-emerald-600 w-4 h-4 cursor-pointer" title="Mark Option {{ $optLabel }} as correct">
                                <span class="text-xs font-black text-slate-900 dark:text-white w-4">{{ $optLabel }}</span>
                                <input type="text" name="questions[{{ $i }}][choices][]" id="pg-q{{ $i }}-choice-{{ $cIdx }}" placeholder="Option {{ $optLabel }} text" class="flex-1 px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Optional Explanation --}}
                    <div>
                        <label for="pg-q{{ $i }}-explanation" class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1">
                            Answer Explanation / Rationale (Optional)
                        </label>
                        <input type="text" name="questions[{{ $i }}][explanation]" id="pg-q{{ $i }}-explanation" placeholder="Explain why the correct answer is right..." class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-500 dark:placeholder:text-slate-400 text-xs font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>
                @endfor
            </div>

            {{-- 3. Action Footer --}}
            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="attemptClosePassageGroupModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="pg-submit-btn" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-md shadow-emerald-600/20 inline-flex items-center gap-1.5 transition-all">
                    <span>💾</span>
                    <span id="pg-submit-text">Save Passage Group</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2d: Passage Group Full Documents Preview Modal (Root Portal Layer) --}}
<div id="passage-group-preview-modal" class="hidden fixed inset-0 z-[10002] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 10002;" onclick="closePassageGroupPreviewModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col p-5 sm:p-6 shadow-2xl space-y-4 overflow-y-auto" onclick="event.stopPropagation()">
        <div class="flex justify-between items-start pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📖</span>
                    <span id="pg-prev-modal-title">Reading Passage Documents</span>
                </div>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                    <span id="pg-prev-badge-type" class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800/40">Single Passage</span>
                    <span id="pg-prev-badge-docs" class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">1 Document</span>
                </div>
            </div>
            <button type="button" onclick="closePassageGroupPreviewModal()" aria-label="Close preview" class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div id="pg-prev-tabs-bar" class="hidden flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        </div>

        <div id="pg-prev-documents-container" class="space-y-4 max-h-[65vh] overflow-y-auto pr-1">
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" onclick="closePassageGroupPreviewModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors">
                Close Preview
            </button>
        </div>
    </div>
</div>

{{-- Modal 6: General Asset Preview Modal (Root Portal Layer) --}}
<div id="asset-preview-modal" class="hidden fixed inset-0 z-[10002] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 10002;" onclick="closeAssetPreviewModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto p-6 shadow-2xl space-y-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
            <div id="apm-title" class="text-sm sm:text-base font-black text-slate-900 dark:text-white truncate">Preview Asset</div>
            <button type="button" onclick="closeAssetPreviewModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-lg p-1">✕</button>
        </div>
        <div id="apm-content" class="flex justify-center items-center min-h-[180px]">
        </div>
    </div>
</div>

{{-- Modal 7: Question Media Picker Modal (Root Portal Layer) --}}
<div id="question-media-picker-modal" class="hidden fixed inset-0 z-[10001] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 10001;" onclick="closeQuestionMediaPicker(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col p-5 sm:p-6 shadow-2xl space-y-4" onclick="event.stopPropagation()">

        {{-- Header --}}
        <div class="flex justify-between items-start pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                    <span>Attach Question Media</span>
                </div>
                <div class="text-xs text-slate-600 dark:text-slate-400 mt-1 font-medium">
                    Upload a new working file or choose from existing media library.
                </div>
            </div>
            <button type="button" onclick="closeQuestionMediaPicker()" aria-label="Close media picker" class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- SECTION A: UPLOAD NEW FILE --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        Upload New File
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        New uploads are saved to My Media and can be attached immediately.
                    </div>
                </div>
                <div id="qm-accepted-formats-label" class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">
                    Max 10 MB
                </div>
            </div>

            {{-- Dropzone / Upload Panel --}}
            <div id="qm-dropzone" ondragover="handleQuestionMediaDragOver(event)" ondragleave="handleQuestionMediaDragLeave(event)" ondrop="handleQuestionMediaDrop(event)" class="bg-slate-50 dark:bg-slate-950/80 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl p-4 transition-all">

                {{-- Accessible Hidden Native Input --}}
                <input type="file" id="qm-direct-file-input" accept="image/jpeg,image/png,image/webp,audio/mpeg,audio/mp3,audio/wav,audio/x-m4a,audio/m4a,application/pdf" onchange="handleQuestionMediaFileSelect(this)" class="sr-only" aria-label="Select media file to upload">

                {{-- State 1: No file selected --}}
                <div id="qm-empty-upload-state" class="flex flex-col items-center justify-center py-2 space-y-2.5 text-center">
                    <div class="w-10 h-10 rounded-full bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                            Select a file to upload or drag and drop here
                        </div>
                        <div id="qm-no-file-text" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                            No file selected
                        </div>
                        <div id="qm-accepted-formats-text" class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 font-mono">
                            JPG, JPEG, PNG, WebP, MP3, M4A, WAV, PDF
                        </div>
                    </div>
                    <div class="pt-1">
                        <button type="button" id="qm-choose-file-btn" onclick="document.getElementById('qm-direct-file-input').click()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-xl text-xs font-extrabold shadow-sm inline-flex items-center gap-1.5 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>Choose File</span>
                        </button>
                    </div>
                </div>

                {{-- State 2: Selected file state (Hidden initially) --}}
                <div id="qm-selected-upload-state" class="hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div id="qm-selected-icon-container" class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div class="min-w-0 text-left">
                                <div class="flex items-center gap-2">
                                    <span id="qm-selected-type-badge" class="text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40">FILE</span>
                                    <span id="qm-selected-filesize" class="text-[11px] text-slate-500 dark:text-slate-400 font-mono"></span>
                                </div>
                                <div id="qm-selected-filename" class="text-xs font-extrabold text-slate-900 dark:text-white truncate max-w-xs sm:max-w-md mt-0.5"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="document.getElementById('qm-direct-file-input').click()" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 transition-colors">
                                Change File
                            </button>
                            <button type="button" onclick="clearQuestionMediaFileSelection()" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-xs font-bold rounded-lg border border-rose-200 dark:border-rose-900/40 transition-colors" title="Remove selected file">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Action Row --}}
                <div class="mt-3 flex justify-end items-center gap-2">
                    <button type="button" id="qm-upload-btn" onclick="uploadQuestionMediaFile()" disabled aria-disabled="true" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-extrabold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span>Upload &amp; Attach</span>
                    </button>
                </div>

                {{-- Inline Error Box --}}
                <div id="qm-upload-error" class="hidden bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 p-2.5 rounded-xl text-xs font-bold text-left mt-2"></div>
            </div>
        </div>

        {{-- VISUAL SEPARATOR --}}
        <div class="relative my-0.5">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200 dark:border-slate-800"></div></div>
            <div class="relative flex justify-center text-[10px] uppercase font-black tracking-widest">
                <span class="bg-white dark:bg-slate-900 px-3 text-slate-400 dark:text-slate-500">OR CHOOSE EXISTING MEDIA</span>
            </div>
        </div>

        {{-- SECTION B: CHOOSE EXISTING MEDIA (SOURCE TABS + FILTERS) --}}
        <div class="space-y-2.5 flex-1 flex flex-col min-h-0">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <div class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        Choose Existing Media
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                        Browse your working drafts or approved institutional media.
                    </div>
                </div>

                {{-- Source Selector Tabs: [ My Media ] [ Institutional Library ] --}}
                <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700" role="tablist">
                    <button type="button" id="qm-source-my-btn" onclick="setQuestionMediaSource('my', event)" role="tab" aria-selected="true" class="px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all">
                        My Media
                    </button>
                    <button type="button" id="qm-source-inst-btn" onclick="setQuestionMediaSource('institutional', event)" role="tab" aria-selected="false" class="px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all">
                        Institutional Library
                    </button>
                </div>
            </div>

            {{-- Filters & Search --}}
            <div class="flex gap-2 flex-wrap items-center justify-between">
                <div class="flex gap-1.5 flex-wrap">
                    <button type="button" onclick="filterQuestionMediaModal('all', event)" class="qm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm">All Media</button>
                    <button type="button" onclick="filterQuestionMediaModal('image', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">Images</button>
                    <button type="button" onclick="filterQuestionMediaModal('audio', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">Audio Tracks</button>
                    <button type="button" onclick="filterQuestionMediaModal('passage', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">Passages</button>
                    <button type="button" onclick="filterQuestionMediaModal('pdf', event)" class="qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold">PDFs</button>
                </div>
                <div class="relative min-w-[200px]">
                    <input type="text" id="qm-search-input" onkeyup="searchQuestionMediaModal(this.value)" placeholder="Search media..." class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            {{-- Media Grid Container --}}
            <div id="qm-media-list-container" class="flex-1 min-h-[180px] max-h-[260px] overflow-y-auto bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" onclick="closeQuestionMediaPicker()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700">Close</button>
        </div>
    </div>
</div>
@endpush

<script>
    let sectionMediaLibrary = [];
    let questionMediaLibrary = [];
    let currentAsmSectionId = null;
    let currentQuestionMediaTargetMode = 'create';
    let currentQuestionMediaSource = 'my';
    let currentQuestionMediaType = 'all';

    function setQuestionMediaSource(source, e = null) {
        currentQuestionMediaSource = source;
        const myBtn = document.getElementById('qm-source-my-btn');
        const instBtn = document.getElementById('qm-source-inst-btn');
        if (myBtn && instBtn) {
            if (source === 'my') {
                myBtn.className = 'px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all';
                myBtn.setAttribute('aria-selected', 'true');
                instBtn.className = 'px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all';
                instBtn.setAttribute('aria-selected', 'false');
            } else {
                instBtn.className = 'px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all';
                instBtn.setAttribute('aria-selected', 'true');
                myBtn.className = 'px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all';
                myBtn.setAttribute('aria-selected', 'false');
            }
        }
        loadQuestionMediaList(currentQuestionMediaType);
    }

    function openQuestionMediaPicker(mode = 'create', defaultType = 'all') {
        currentQuestionMediaTargetMode = mode;
        currentQuestionMediaType = defaultType;
        const modal = document.getElementById('question-media-picker-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        // Configure accepted formats context helper
        const formatsText = document.getElementById('qm-accepted-formats-text');
        const formatsLabel = document.getElementById('qm-accepted-formats-label');
        if (defaultType === 'image') {
            if (formatsText) formatsText.textContent = 'Accepted image formats: JPG, JPEG, PNG, WebP';
            if (formatsLabel) formatsLabel.textContent = 'Max 10 MB (Image)';
        } else if (defaultType === 'audio') {
            if (formatsText) formatsText.textContent = 'Accepted audio formats: MP3, M4A, WAV';
            if (formatsLabel) formatsLabel.textContent = 'Max 10 MB (Audio)';
        } else {
            if (formatsText) formatsText.textContent = 'JPG, JPEG, PNG, WebP, MP3, M4A, WAV, PDF';
            if (formatsLabel) formatsLabel.textContent = 'Max 10 MB';
        }

        clearQuestionMediaFileSelection();
        loadQuestionMediaList(defaultType);
    }

    function closeQuestionMediaPicker(e) {
        if (!e || e.target === document.getElementById('question-media-picker-modal')) {
            const modal = document.getElementById('question-media-picker-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
            clearQuestionMediaFileSelection();
        }
    }

    function handleQuestionMediaFileSelect(input) {
        const file = input?.files?.[0];
        const emptyState = document.getElementById('qm-empty-upload-state');
        const selectedState = document.getElementById('qm-selected-upload-state');
        const filenameEl = document.getElementById('qm-selected-filename');
        const filesizeEl = document.getElementById('qm-selected-filesize');
        const typeBadgeEl = document.getElementById('qm-selected-type-badge');
        const uploadBtn = document.getElementById('qm-upload-btn');
        const errBox = document.getElementById('qm-upload-error');

        if (errBox) {
            errBox.classList.add('hidden');
            errBox.style.display = 'none';
            errBox.textContent = '';
        }

        if (!file) {
            clearQuestionMediaFileSelection();
            return;
        }

        // Format file size
        const sizeFormatted = file.size > 1048576
            ? (file.size / 1048576).toFixed(1) + ' MB'
            : (file.size / 1024).toFixed(1) + ' KB';

        // Detect Type
        let typeName = 'FILE';
        const mime = file.type || '';
        const nameLower = file.name.toLowerCase();
        if (mime.startsWith('image/') || /\.(jpg|jpeg|png|webp)$/i.test(nameLower)) {
            typeName = 'IMAGE';
        } else if (mime.startsWith('audio/') || /\.(mp3|m4a|wav)$/i.test(nameLower)) {
            typeName = 'AUDIO';
        } else if (mime === 'application/pdf' || /\.pdf$/i.test(nameLower)) {
            typeName = 'PDF';
        }

        if (filenameEl) filenameEl.textContent = file.name;
        if (filesizeEl) filesizeEl.textContent = sizeFormatted;
        if (typeBadgeEl) typeBadgeEl.textContent = typeName;

        if (emptyState) {
            emptyState.classList.add('hidden');
            emptyState.style.display = 'none';
        }
        if (selectedState) {
            selectedState.classList.remove('hidden');
            selectedState.style.display = 'block';
        }

        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.removeAttribute('aria-disabled');
            uploadBtn.className = 'px-4 py-2 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-extrabold rounded-xl shadow-md shadow-emerald-500/20 cursor-pointer transition-all inline-flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1';
        }
    }

    function clearQuestionMediaFileSelection() {
        const input = document.getElementById('qm-direct-file-input');
        if (input) input.value = '';

        const emptyState = document.getElementById('qm-empty-upload-state');
        const selectedState = document.getElementById('qm-selected-upload-state');
        const uploadBtn = document.getElementById('qm-upload-btn');
        const errBox = document.getElementById('qm-upload-error');

        if (emptyState) {
            emptyState.classList.remove('hidden');
            emptyState.style.display = 'flex';
        }
        if (selectedState) {
            selectedState.classList.add('hidden');
            selectedState.style.display = 'none';
        }
        if (errBox) {
            errBox.classList.add('hidden');
            errBox.style.display = 'none';
            errBox.textContent = '';
        }

        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.setAttribute('aria-disabled', 'true');
            uploadBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span>Upload &amp; Attach</span>
            `;
            uploadBtn.className = 'px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-extrabold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all inline-flex items-center gap-1.5';
        }
    }

    function handleQuestionMediaDragOver(e) {
        e.preventDefault();
        const dropzone = document.getElementById('qm-dropzone');
        if (dropzone) {
            dropzone.classList.add('border-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
        }
    }

    function handleQuestionMediaDragLeave(e) {
        e.preventDefault();
        const dropzone = document.getElementById('qm-dropzone');
        if (dropzone) {
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
        }
    }

    function handleQuestionMediaDrop(e) {
        e.preventDefault();
        const dropzone = document.getElementById('qm-dropzone');
        if (dropzone) {
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
        }
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const input = document.getElementById('qm-direct-file-input');
            if (input) {
                input.files = e.dataTransfer.files;
                handleQuestionMediaFileSelect(input);
            }
        }
    }

    function loadQuestionMediaList(defaultType = 'all') {
        const container = document.getElementById('qm-media-list-container');
        if (!container) return;
        container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>';

        fetch(`/admin/media/list?source=${currentQuestionMediaSource}`)
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
        currentQuestionMediaType = type;
        const buttons = document.querySelectorAll('.qm-filter-btn');
        buttons.forEach(btn => {
            btn.className = 'qm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition-colors';
        });

        if (e && e.target && e.target.classList.contains('qm-filter-btn')) {
            e.target.className = 'qm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm';
        } else {
            buttons.forEach(btn => {
                const text = btn.textContent.toLowerCase();
                if ((type === 'all' && text.includes('all')) ||
                    (type === 'image' && text.includes('images')) ||
                    (type === 'audio' && text.includes('audio')) ||
                    (type === 'passage' && text.includes('passages')) ||
                    (type === 'pdf' && text.includes('pdfs'))) {
                    btn.className = 'qm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm';
                }
            });
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

            let typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>';
            if (media.type === 'audio') {
                typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>';
            } else if (media.type === 'image') {
                typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
            } else if (media.type === 'pdf') {
                typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
            }

            card.innerHTML = `
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 inline-flex items-center gap-1">
                            ${typeIconSvg}
                            <span>${media.type}</span>
                        </span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">${media.size || ''}</span>
                    </div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white truncate" title="${media.title || media.name}">
                        ${media.title || media.name}
                    </div>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="previewAssetModal('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-lg text-[11px] font-bold">Preview</button>
                    <button type="button" onclick="selectQuestionMediaItem('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[11px] font-bold shadow-sm">Attach</button>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function selectQuestionMediaItem(id, title, type, url) {
        if (currentQuestionMediaTargetMode && currentQuestionMediaTargetMode.startsWith('passage-')) {
            const d = parseInt(currentQuestionMediaTargetMode.split('-')[1]);
            const mediaIdInput = document.getElementById(`pg-doc-${d}-media-asset-id`);
            const imgInput = document.getElementById(`pg-doc-${d}-image-url`);
            if (mediaIdInput) mediaIdInput.value = id;
            if (imgInput) imgInput.value = url;

            const prevCard = document.getElementById(`pg-doc-${d}-preview-image-card`);
            const emptyCard = document.getElementById(`pg-doc-${d}-empty-image-card`);
            const thumb = document.getElementById(`pg-doc-${d}-preview-image-thumb`);
            const titleEl = document.getElementById(`pg-doc-${d}-preview-image-title`);

            if (thumb) thumb.src = url;
            if (titleEl) titleEl.textContent = title;
            if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
            if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
            closeQuestionMediaPicker();
            if (typeof triggerPassageGroupDraftSave === 'function') {
                triggerPassageGroupDraftSave();
            }
            return;
        }

        if (currentQuestionMediaTargetMode === 'audio-group') {
            const mediaIdInput = document.getElementById('ag-media-asset-id');
            const audioInput = document.getElementById('ag-audio-url');
            if (mediaIdInput) mediaIdInput.value = id;
            if (audioInput) audioInput.value = url;
            const prevCard = document.getElementById('ag-preview-audio-card');
            const emptyCard = document.getElementById('ag-empty-audio-card');
            const titleEl = document.getElementById('ag-preview-audio-title');
            const player = document.getElementById('ag-preview-audio-player');

            if (titleEl) titleEl.textContent = title;
            if (player) player.src = url;
            if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'block'; }
            if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
            closeQuestionMediaPicker();
            if (typeof updateAudioGroupAutoDifficulty === 'function') {
                updateAudioGroupAutoDifficulty();
            }
            if (typeof triggerAudioGroupDraftSave === 'function') {
                triggerAudioGroupDraftSave();
            }
            return;
        }

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
        if (typeof updateCreateModalAutoDifficulty === 'function') {
            updateCreateModalAutoDifficulty();
        }
        if (typeof triggerAuthoredQuestionDraftSave === 'function') {
            triggerAuthoredQuestionDraftSave();
        }
    }

    function removeQuestionAttachedMedia(mode, type) {
        const prefix = (mode === 'edit') ? 'eq-' : 'q-';
        const imgInput = document.getElementById(prefix + 'image-url');
        const audioInput = document.getElementById('q-audio-url');
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

        if (typeof updateCreateModalAutoDifficulty === 'function') {
            updateCreateModalAutoDifficulty();
        }
        if (mode !== 'edit' && typeof triggerAuthoredQuestionDraftSave === 'function') {
            triggerAuthoredQuestionDraftSave();
        }
    }

    function uploadQuestionMediaFile() {
        const fileInput = document.getElementById('qm-direct-file-input');
        const uploadBtn = document.getElementById('qm-upload-btn');
        const errBox = document.getElementById('qm-upload-error');

        if (errBox) {
            errBox.classList.add('hidden');
            errBox.style.display = 'none';
            errBox.textContent = '';
        }

        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            if (errBox) {
                errBox.textContent = '⚠️ Please select a file to upload first.';
                errBox.classList.remove('hidden');
                errBox.style.display = 'block';
            }
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        uploadBtn.disabled = true;
        uploadBtn.setAttribute('aria-disabled', 'true');
        uploadBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Uploading...</span>
        `;

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
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed.');
                throw new Error(msg);
            }
            return data;
        })
        .then(data => {
            const assetData = data.asset || data;
            const assetId = assetData.id || data.id;
            const item = {
                id: assetId,
                title: assetData.title || assetData.filename || assetData.original_name,
                name: assetData.filename || assetData.original_name,
                type: assetData.type,
                size: assetData.size,
                url: assetData.url || (assetId ? `/media/${assetId}/preview` : '')
            };
            questionMediaLibrary.unshift(item);
            selectQuestionMediaItem(item.id, item.title, item.type, item.url);
            clearQuestionMediaFileSelection();
        })
        .catch(err => {
            if (errBox) {
                errBox.textContent = '⚠️ ' + (err.message || 'Upload error. Please try again.');
                errBox.classList.remove('hidden');
                errBox.style.display = 'block';
            }
        })
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.removeAttribute('aria-disabled');
            uploadBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span>Upload &amp; Attach</span>
            `;
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

        const imgControlsWrapper = document.getElementById('q-image-controls-wrapper');
        const imgCard = document.getElementById('q-preview-image-card');
        const imgEmpty = document.getElementById('q-empty-image-card');
        const imgUrlInput = document.getElementById('q-image-url');
        const mediaTitle = document.getElementById('create-q-media-title');
        const mediaHint = document.getElementById('create-q-media-hint');
        const audioEmptyTitle = document.getElementById('q-empty-audio-title');

        const choiceRow3 = document.getElementById('create-choice-row-3');
        const choiceInput3 = choiceRow3 ? choiceRow3.querySelector('input[type="text"]') : null;
        const choiceRadio3 = choiceRow3 ? choiceRow3.querySelector('input[type="radio"]') : null;
        const choicesLabel = document.getElementById('create-q-choices-label');
        const choicesHint = document.getElementById('create-q-choices-hint');
        const promptInput = document.getElementById('create-q-prompt') || document.querySelector('#create-authored-question-form textarea[name="prompt"]');
        const promptLabel = document.getElementById('create-q-prompt-label');

        if (promptInput) {
            if (part === 1 || part === 2 || part === 6) {
                promptInput.removeAttribute('required');
                if (promptLabel) promptLabel.innerHTML = 'Question Prompt / Stem <span class="text-slate-400 font-normal lowercase">(Optional for Part ' + part + ')</span>';
                promptInput.placeholder = (part === 1) ? 'Optional statement transcript...' : ((part === 2) ? 'Optional question transcript...' : 'Enter prompt if applicable...');
            } else {
                promptInput.setAttribute('required', 'required');
                if (promptLabel) promptLabel.innerHTML = 'Question Prompt / Stem <span class="text-rose-500">*</span>';
                promptInput.placeholder = 'Enter the complete question prompt...';
            }
        }

        if (part === 1) {
            // Part 1: Photograph REQUIRED, Audio REQUIRED, 4 choices A/B/C/D
            if (imgControlsWrapper) imgControlsWrapper.style.display = 'flex';
            if (mediaTitle) mediaTitle.innerHTML = '🖼️ / 🎧 Question Media <span class="text-rose-500 font-extrabold">* (Photograph & Audio Required)</span>';
            if (mediaHint) mediaHint.textContent = 'Part 1 (Photographs) requires both an Image/Photograph attachment and an Audio prompt.';
            if (audioEmptyTitle) audioEmptyTitle.innerHTML = 'Question Audio Prompt <span class="text-rose-500 font-extrabold">* (Required)</span>';

            if (choiceRow3) choiceRow3.style.display = 'flex';
            if (choiceInput3) {
                choiceInput3.disabled = false;
                choiceInput3.removeAttribute('required');
            }
            if (choiceRadio3) {
                choiceRadio3.disabled = false;
            }
            if (choicesLabel) choicesLabel.textContent = 'STATEMENTS & CORRECT ANSWER';
            if (choicesHint) choicesHint.textContent = 'Candidates hear the four statements in the audio and select A, B, C, or D. Statement transcripts are optional authoring metadata and are not shown during the test.';
            for (let i = 0; i < 4; i++) {
                const row = document.getElementById(`create-choice-row-${i}`);
                const inp = row ? row.querySelector('input[type="text"]') : null;
                const rad = row ? row.querySelector('input[type="radio"]') : null;
                if (inp) {
                    inp.disabled = false;
                    inp.placeholder = 'Optional statement transcript...';
                    inp.removeAttribute('required');
                }
                if (rad) {
                    rad.disabled = false;
                }
            }
        } else if (part === 2) {
            // Part 2: Audio REQUIRED, Image NOT APPLICABLE, exactly 3 choices A/B/C (D disabled & removed from submit)
            if (imgControlsWrapper) imgControlsWrapper.style.display = 'none';
            if (imgCard) { imgCard.classList.add('hidden'); imgCard.style.display = 'none'; }
            if (imgEmpty) { imgEmpty.classList.add('hidden'); imgEmpty.style.display = 'none'; }
            if (imgUrlInput) imgUrlInput.value = '';

            if (mediaTitle) mediaTitle.innerHTML = '🎧 Audio Prompt <span class="text-rose-500 font-extrabold">* Required for Part 2</span>';
            if (mediaHint) mediaHint.textContent = 'Part 2 (Question–Response) is audio-delivered. Spoken question and 3 choices are in the audio file. Photograph/Image is not applicable.';
            if (audioEmptyTitle) audioEmptyTitle.innerHTML = 'Question Audio Prompt <span class="text-rose-500 font-extrabold">* (Required)</span>';

            if (choiceRow3) choiceRow3.style.display = 'none';
            if (choiceInput3) {
                choiceInput3.value = '';
                choiceInput3.disabled = true;
                choiceInput3.removeAttribute('required');
            }
            if (choiceRadio3) {
                choiceRadio3.disabled = true;
                if (choiceRadio3.checked) {
                    choiceRadio3.checked = false;
                }
            }

            if (choicesLabel) choicesLabel.textContent = 'Responses & Correct Answer (Part 2 — Listening)';
            if (choicesHint) choicesHint.textContent = 'Candidates hear the three responses in the audio and select A, B, or C. Response transcripts are optional authoring metadata and are not shown during the test.';
            for (let i = 0; i < 3; i++) {
                const row = document.getElementById(`create-choice-row-${i}`);
                const inp = row ? row.querySelector('input[type="text"]') : null;
                const rad = row ? row.querySelector('input[type="radio"]') : null;
                if (inp) {
                    inp.disabled = false;
                    inp.placeholder = 'Optional response transcript...';
                    inp.removeAttribute('required');
                }
                if (rad) {
                    rad.disabled = false;
                }
            }
        } else {
            // Parts 3-7: Standard Media & 4 Choices
            if (imgControlsWrapper) imgControlsWrapper.style.display = 'flex';
            if (mediaTitle) mediaTitle.innerHTML = '🖼️ / 🎧 Question Media (Optional)';
            if (mediaHint) mediaHint.textContent = 'Attach Question-level Photo (Image) and/or Audio Prompt if applicable.';
            if (audioEmptyTitle) audioEmptyTitle.innerHTML = 'Question Audio Prompt';

            if (choiceRow3) choiceRow3.style.display = 'flex';
            if (choiceInput3) {
                choiceInput3.disabled = false;
            }
            if (choiceRadio3) {
                choiceRadio3.disabled = false;
            }
            if (choicesLabel) choicesLabel.textContent = 'Multiple Choice Options & Correct Answer';
            if (choicesHint) choicesHint.textContent = 'Select exactly one radio button as the correct answer.';
            for (let i = 0; i < 4; i++) {
                const row = document.getElementById(`create-choice-row-${i}`);
                const inp = row ? row.querySelector('input[type="text"]') : null;
                const rad = row ? row.querySelector('input[type="radio"]') : null;
                const lbl = String.fromCharCode(65 + i);
                if (inp) {
                    inp.disabled = false;
                    inp.placeholder = `Option ${lbl} text`;
                    if (i < 2) inp.setAttribute('required', 'required');
                }
                if (rad) {
                    rad.disabled = false;
                }
            }
        }

        updateCreateModalAutoDifficulty();
    }

    function updateCreateModalAutoDifficulty() {
        const form = document.getElementById('create-authored-question-form');
        if (!form) return;

        const partVal = parseInt(form.querySelector('#create-q-part-number')?.value || '1');
        const prompt = (form.querySelector('textarea[name="prompt"]')?.value || '').trim();
        const choices = Array.from(form.querySelectorAll('input[name="choices[]"]'))
            .map(input => input.value.trim())
            .filter(v => v.length > 0);
        const hasImg = !document.getElementById('q-preview-image-card')?.classList.contains('hidden') && !!document.getElementById('q-image-url')?.value;
        const hasAudio = !document.getElementById('q-preview-audio-card')?.classList.contains('hidden') && !!document.getElementById('q-audio-url')?.value;
        const passageText = (form.querySelector('#create-q-passage-text')?.value || '').trim();

        const badge = document.getElementById('create-q-auto-diff-badge');
        const dot = document.getElementById('create-q-auto-diff-dot');
        const text = document.getElementById('create-q-auto-diff-text');
        const hint = document.getElementById('create-q-auto-diff-hint');

        if (!badge || !text) return;

        let status = 'pending';
        let level = 'Medium';
        let badgeClass = 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        let dotColor = 'bg-slate-400';
        let hintMsg = 'Difficulty is detected automatically from question content and media.';

        if (partVal === 1) {
            if (!hasImg && !hasAudio && choices.length === 0 && !prompt) {
                status = 'pending';
                text.textContent = 'Waiting for required inputs';
                hintMsg = 'Attach image + audio for Part 1 detection.';
            } else if (hasImg && hasAudio) {
                status = 'final';
                let totalWords = choices.reduce((acc, c) => acc + c.split(/\s+/).filter(Boolean).length, 0);
                let avg = choices.length > 0 ? totalWords / choices.length : 0;
                level = choices.length > 0 ? (avg >= 8 ? 'Hard' : (avg <= 5.5 ? 'Easy' : 'Medium')) : 'Easy';
                text.textContent = `Final — ${level}`;
                hintMsg = `Part 1 fully specified (Image + Audio). Auto-detected: ${level}.`;
            } else {
                status = 'provisional';
                let missing = [];
                if (!hasImg) missing.push('Image');
                if (!hasAudio) missing.push('Audio');
                level = 'Medium';
                text.textContent = `Provisional — ${level}`;
                hintMsg = `Partially complete (Waiting for: ${missing.join(', ')}).`;
            }
        } else if (partVal === 2) {
            if (!hasAudio && choices.length === 0 && !prompt) {
                status = 'pending';
                text.textContent = 'Waiting for required inputs';
                hintMsg = 'Attach audio for Part 2 Question–Response.';
            } else if (hasAudio || (choices.length >= 3 && prompt)) {
                status = 'final';
                level = prompt.toLowerCase().match(/^(when|where|who|what time)\b/) ? 'Easy' : (prompt.toLowerCase().match(/^(why don\'t|could you|would you)\b/) ? 'Medium' : 'Hard');
                text.textContent = `Final — ${level}`;
                hintMsg = `Part 2 fully specified (Audio Question–Response). Auto-detected: ${level}.`;
            } else {
                status = 'provisional';
                text.textContent = 'Provisional — Medium';
                hintMsg = `Partially complete (Waiting for audio attachment).`;
            }
        } else if (partVal >= 3 && partVal <= 4) {
            if (!hasAudio && choices.length === 0 && !prompt) {
                status = 'pending';
                text.textContent = 'Waiting for required inputs';
            } else if (prompt && choices.length >= 4) {
                status = 'final';
                level = prompt.toLowerCase().match(/(imply|suggest|probably do next|look at)/) ? 'Hard' : (prompt.toLowerCase().match(/(problem|where|what time)/) ? 'Easy' : 'Medium');
                text.textContent = `Final — ${level}`;
                hintMsg = `Stem and options complete. Auto-detected: ${level}.`;
            } else {
                status = 'provisional';
                text.textContent = 'Provisional — Medium';
                hintMsg = 'Partially complete.';
            }
        } else if (partVal === 5) {
            if (!prompt && choices.length === 0) {
                status = 'pending';
                text.textContent = 'Waiting for required inputs';
            } else if (prompt && choices.length >= 4) {
                status = 'final';
                let words = prompt.split(/\s+/).filter(Boolean).length;
                level = words >= 20 ? 'Hard' : (words <= 12 ? 'Easy' : 'Medium');
                text.textContent = `Final — ${level}`;
                hintMsg = `Sentence prompt + 4 choices complete. Auto-detected: ${level}.`;
            } else {
                status = 'provisional';
                text.textContent = 'Provisional — Medium';
            }
        } else if (partVal === 6 || partVal === 7) {
            if (!passageText && !prompt && choices.length === 0) {
                status = 'pending';
                text.textContent = 'Waiting for required inputs';
            } else if (passageText && prompt && choices.length >= 4) {
                status = 'final';
                level = prompt.toLowerCase().match(/(suggest|infer|not mentioned|except)/) ? 'Hard' : 'Medium';
                text.textContent = `Final — ${level}`;
                hintMsg = `Passage and question complete. Auto-detected: ${level}.`;
            } else {
                status = 'provisional';
                text.textContent = 'Provisional — Medium';
                hintMsg = 'Waiting for passage text or choices completion.';
            }
        } else {
            if (!prompt && choices.length === 0) {
                status = 'pending';
                text.textContent = 'Waiting for required inputs';
            } else if (prompt && choices.length >= 2) {
                status = 'final';
                text.textContent = 'Final — Medium';
            } else {
                status = 'provisional';
                text.textContent = 'Provisional — Medium';
            }
        }

        if (status === 'final') {
            if (level === 'Easy') {
                badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800';
                dotColor = 'bg-emerald-500';
            } else if (level === 'Hard') {
                badgeClass = 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800';
                dotColor = 'bg-rose-500';
            } else {
                badgeClass = 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800';
                dotColor = 'bg-amber-500';
            }
        } else if (status === 'provisional') {
            badgeClass = 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800';
            dotColor = 'bg-sky-500';
        } else {
            badgeClass = 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
            dotColor = 'bg-slate-400';
        }

        badge.className = `inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-extrabold border ${badgeClass}`;
        dot.className = `w-1.5 h-1.5 rounded-full ${dotColor}`;
        if (hint) hint.textContent = hintMsg;
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
        updateCreateModalAutoDifficulty();
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

    function getAuthoredQuestionDraftStorageKey(sectionId, partNumber = null) {
        const partSuffix = partNumber ? `_p${partNumber}` : '';
        return `iap:question_draft_{{ auth()->id() }}_{{ $test->id }}_${sectionId || 'default'}${partSuffix}`;
    }

    let _qDraftSaveTimeout = null;
    window._qBaselineSnapshot = null;
    window._qSubmitting = false;
    window._currentQContext = { sectionId: '', partNumber: '', sectionTitle: '', sectionType: '' };

    function getAuthoredQuestionCurrentSnapshot() {
        const form = document.getElementById('create-authored-question-form');
        const secId = document.getElementById('create-q-section-id')?.value || '';
        const partNum = document.getElementById('create-q-part-number')?.value || '1';
        const secType = document.getElementById('create-q-section')?.value || 'listening';
        const qType = form?.querySelector('select[name="question_type"]')?.value || 'multiple_choice';
        const prompt = document.getElementById('create-q-prompt')?.value || '';
        const passageText = document.getElementById('create-q-passage-text')?.value || '';
        const explanation = form?.querySelector('textarea[name="explanation"]')?.value || '';
        const mediaAssetId = document.getElementById('q-media-asset-id')?.value || '';
        const imageUrl = document.getElementById('q-image-url')?.value || '';
        const imageTitle = document.getElementById('q-preview-image-title')?.textContent || '';
        const imageThumb = document.getElementById('q-preview-image-thumb')?.src || '';
        const audioUrl = document.getElementById('q-audio-url')?.value || '';
        const audioTitle = document.getElementById('q-preview-audio-title')?.textContent || '';

        const choices = [];
        const choiceInputs = form ? form.querySelectorAll('input[name="choices[]"]') : [];
        choiceInputs.forEach(ci => choices.push(ci.value || ''));

        const checkedRadio = form?.querySelector('input[name="correct_choice"]:checked');
        const correctChoice = checkedRadio ? checkedRadio.value : null;

        return {
            section_id: secId,
            part_number: partNum,
            section_type: secType,
            question_type: qType,
            prompt,
            passage_text: passageText,
            explanation,
            media_asset_id: mediaAssetId,
            image_url: imageUrl,
            image_title: imageTitle,
            image_thumb: imageThumb,
            audio_url: audioUrl,
            audio_title: audioTitle,
            choices,
            correct_choice: correctChoice
        };
    }

    function isAuthoredQuestionFormDirty() {
        const current = getAuthoredQuestionCurrentSnapshot();
        if (!window._qBaselineSnapshot) {
            if (current.prompt.trim() || current.passage_text.trim() || current.explanation.trim() || current.media_asset_id || current.image_url || current.audio_url || current.correct_choice !== null) {
                return true;
            }
            if (current.choices.some(c => c.trim().length > 0)) {
                return true;
            }
            return false;
        }
        return JSON.stringify(current) !== JSON.stringify(window._qBaselineSnapshot);
    }

    function triggerAuthoredQuestionDraftSave() {
        clearTimeout(_qDraftSaveTimeout);
        _qDraftSaveTimeout = setTimeout(() => {
            const modal = document.getElementById('create-authored-question-modal');
            if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;

            const secId = document.getElementById('create-q-section-id')?.value;
            const partNum = document.getElementById('create-q-part-number')?.value || '1';
            if (!secId) return;

            const snapshot = getAuthoredQuestionCurrentSnapshot();
            try {
                sessionStorage.setItem(getAuthoredQuestionDraftStorageKey(secId, partNum), JSON.stringify(snapshot));
            } catch (e) {
                console.warn('Unable to save question draft to sessionStorage', e);
            }
        }, 300);
    }

    function discardAuthoredQuestionDraft(reopenFresh = false) {
        const secId = document.getElementById('create-q-section-id')?.value;
        const partNum = document.getElementById('create-q-part-number')?.value || '1';
        if (secId) {
            try {
                sessionStorage.removeItem(getAuthoredQuestionDraftStorageKey(secId, partNum));
            } catch (e) {}
        }
        const banner = document.getElementById('q-draft-restored-banner');
        if (banner) {
            banner.classList.add('hidden');
        }
        if (reopenFresh) {
            const ctx = window._currentQContext || {};
            openCreateAuthoredQuestionModal(ctx.sectionId, ctx.partNumber, ctx.sectionTitle, ctx.sectionType);
        }
    }

    function forceCloseAuthoredQuestionModal() {
        const modal = document.getElementById('create-authored-question-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        const audioPlayer = document.getElementById('q-preview-audio-player');
        if (audioPlayer) {
            audioPlayer.pause();
        }
        window._qBaselineSnapshot = null;
    }

    function attemptCloseAuthoredQuestionModal() {
        const modal = document.getElementById('create-authored-question-modal');
        if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;

        if (!isAuthoredQuestionFormDirty()) {
            forceCloseAuthoredQuestionModal();
            return;
        }

        const message = 'You have unsaved changes in this question. Closing now will discard changes that have not been saved.';
        if (typeof window.iapConfirm === 'function') {
            window.iapConfirm({
                title: 'Unsaved Changes',
                message: message,
                confirmText: 'Discard Changes',
                cancelText: 'Continue Editing',
                variant: 'warning',
                onConfirm: () => {
                    discardAuthoredQuestionDraft(false);
                    forceCloseAuthoredQuestionModal();
                }
            });
        } else {
            if (confirm(message)) {
                discardAuthoredQuestionDraft(false);
                forceCloseAuthoredQuestionModal();
            }
        }
    }

    function openCreateAuthoredQuestionModal(sectionId, partNumber, sectionTitle, sectionType) {
        const modal = document.getElementById('create-authored-question-modal');
        const form = document.getElementById('create-authored-question-form');
        if (!modal || !form) return;

        window._currentQContext = { sectionId, partNumber, sectionTitle, sectionType };

        const secIdInput = document.getElementById('create-q-section-id');
        const partInput = document.getElementById('create-q-part-number');
        const secTypeInput = document.getElementById('create-q-section');
        const contextBanner = document.getElementById('create-q-context-banner');
        const globalSelector = document.getElementById('create-q-global-selector-container');
        const contextTitle = document.getElementById('create-q-context-title');
        const contextBadge = document.getElementById('create-q-context-badge');

        let effectiveSecId = sectionId;
        let effectivePartNum = partNumber;
        let effectiveSecType = sectionType;

        if (sectionId) {
            // Contextual Section Mode
            if (secIdInput) secIdInput.value = sectionId;
            if (partInput && partNumber) partInput.value = partNumber;
            if (secTypeInput && sectionType) secTypeInput.value = sectionType;

            if (contextBanner) {
                contextBanner.classList.remove('hidden');
                contextBanner.style.display = 'flex';
            }
            if (globalSelector) {
                globalSelector.classList.add('hidden');
                globalSelector.style.display = 'none';
            }
            if (contextTitle) contextTitle.textContent = sectionTitle || 'Selected Section';
            if (contextBadge) contextBadge.textContent = (sectionType || 'SECTION') + (partNumber ? ` • Part ${partNumber}` : '');
        } else {
            // Global Modal Mode
            if (contextBanner) {
                contextBanner.classList.add('hidden');
                contextBanner.style.display = 'none';
            }
            if (globalSelector) {
                globalSelector.classList.remove('hidden');
                globalSelector.style.display = 'block';
            }
            const unifiedSelect = document.getElementById('create-q-unified-part-section');
            if (unifiedSelect) {
                const opt = unifiedSelect.options[unifiedSelect.selectedIndex];
                if (opt) {
                    effectiveSecId = opt.value;
                    effectivePartNum = opt.getAttribute('data-part') || '1';
                    effectiveSecType = opt.getAttribute('data-type') || 'listening';
                    if (secIdInput) secIdInput.value = effectiveSecId;
                    if (partInput) partInput.value = effectivePartNum;
                    if (secTypeInput) secTypeInput.value = effectiveSecType;
                }
            } else {
                const genericSelect = document.getElementById('create-q-generic-section-select');
                if (genericSelect) {
                    effectiveSecId = genericSelect.value;
                    if (secIdInput) secIdInput.value = effectiveSecId;
                }
            }
        }

        const partNumInt = parseInt(effectivePartNum) || 1;
        onCreateModalToeicPartChange(partNumInt);

        // Check for existing sessionStorage draft
        let draftData = null;
        try {
            const rawDraft = sessionStorage.getItem(getAuthoredQuestionDraftStorageKey(effectiveSecId || secIdInput?.value, partNumInt));
            if (rawDraft) {
                draftData = JSON.parse(rawDraft);
            }
        } catch (e) {
            draftData = null;
        }

        const banner = document.getElementById('q-draft-restored-banner');
        const errBox = document.getElementById('create-q-validation-error');
        if (errBox) {
            errBox.classList.add('hidden');
            errBox.style.display = 'none';
        }

        if (draftData) {
            // Restore from draft
            const promptInput = document.getElementById('create-q-prompt');
            const passageInput = document.getElementById('create-q-passage-text');
            const explInput = form.querySelector('textarea[name="explanation"]');
            const qTypeSelect = form.querySelector('select[name="question_type"]');

            if (promptInput) promptInput.value = draftData.prompt || '';
            if (passageInput) passageInput.value = draftData.passage_text || '';
            if (explInput) explInput.value = draftData.explanation || '';
            if (qTypeSelect && draftData.question_type) qTypeSelect.value = draftData.question_type;

            // Restore media
            const imgInput = document.getElementById('q-image-url');
            const audioInput = document.getElementById('q-audio-url');
            const mediaIdInput = document.getElementById('q-media-asset-id');
            if (imgInput) imgInput.value = draftData.image_url || '';
            if (audioInput) audioInput.value = draftData.audio_url || '';
            if (mediaIdInput) mediaIdInput.value = draftData.media_asset_id || '';

            const prevImg = document.getElementById('q-preview-image-card');
            const emptyImg = document.getElementById('q-empty-image-card');
            const thumbImg = document.getElementById('q-preview-image-thumb');
            const titleImg = document.getElementById('q-preview-image-title');

            if (draftData.image_url || draftData.media_asset_id) {
                if (thumbImg) thumbImg.src = draftData.image_thumb || draftData.image_url || '';
                if (titleImg) titleImg.textContent = draftData.image_title || 'Attached Image';
                if (prevImg) { prevImg.classList.remove('hidden'); prevImg.style.display = 'flex'; }
                if (emptyImg) { emptyImg.classList.add('hidden'); emptyImg.style.display = 'none'; }
            } else {
                if (prevImg) { prevImg.classList.add('hidden'); prevImg.style.display = 'none'; }
                if (emptyImg) { emptyImg.classList.remove('hidden'); emptyImg.style.display = 'flex'; }
            }

            const prevAudio = document.getElementById('q-preview-audio-card');
            const emptyAudio = document.getElementById('q-empty-audio-card');
            const playerAudio = document.getElementById('q-preview-audio-player');
            const titleAudio = document.getElementById('q-preview-audio-title');

            if (draftData.audio_url) {
                if (playerAudio) playerAudio.src = draftData.audio_url;
                if (titleAudio) titleAudio.textContent = draftData.audio_title || 'Attached Audio';
                if (prevAudio) { prevAudio.classList.remove('hidden'); prevAudio.style.display = 'flex'; }
                if (emptyAudio) { emptyAudio.classList.add('hidden'); emptyAudio.style.display = 'none'; }
            } else {
                if (prevAudio) { prevAudio.classList.add('hidden'); prevAudio.style.display = 'none'; }
                if (emptyAudio) { emptyAudio.classList.remove('hidden'); emptyAudio.style.display = 'flex'; }
                if (playerAudio) playerAudio.src = '';
            }

            // Restore choices
            const choiceInputs = form.querySelectorAll('input[name="choices[]"]');
            const draftChoices = draftData.choices || [];
            choiceInputs.forEach((ci, idx) => {
                ci.value = draftChoices[idx] !== undefined ? draftChoices[idx] : '';
            });

            // Restore correct choice radio
            const checkedRadios = modal.querySelectorAll('input[name="correct_choice"]');
            checkedRadios.forEach(r => {
                r.checked = (draftData.correct_choice !== null && draftData.correct_choice !== undefined && String(r.value) === String(draftData.correct_choice));
            });

            if (banner) banner.classList.remove('hidden');
        } else {
            // Clean initial state
            const promptInput = document.getElementById('create-q-prompt');
            const passageInput = document.getElementById('create-q-passage-text');
            const explInput = form.querySelector('textarea[name="explanation"]');
            if (promptInput) promptInput.value = '';
            if (passageInput) passageInput.value = '';
            if (explInput) explInput.value = '';

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

            const choiceInputs = form.querySelectorAll('input[name="choices[]"]');
            choiceInputs.forEach(ci => ci.value = '');

            const checkedRadios = modal.querySelectorAll('input[name="correct_choice"]:checked');
            checkedRadios.forEach(r => r.checked = false);

            if (banner) banner.classList.add('hidden');
        }

        updateCreateModalCorrectChoice();
        updateCreateModalAutoDifficulty();
        window._qBaselineSnapshot = getAuthoredQuestionCurrentSnapshot();

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function onGlobalUnifiedSectionChange(selectEl) {
        if (!selectEl) return;
        const opt = selectEl.options[selectEl.selectedIndex];
        if (!opt) return;

        const secId = opt.value;
        const partNum = opt.getAttribute('data-part') || '1';
        const secType = opt.getAttribute('data-type') || 'listening';

        const secIdInput = document.getElementById('create-q-section-id');
        const partInput = document.getElementById('create-q-part-number');
        const secTypeInput = document.getElementById('create-q-section');

        if (secIdInput) secIdInput.value = secId;
        if (partInput) partInput.value = partNum;
        if (secTypeInput) secTypeInput.value = secType;

        onCreateModalToeicPartChange(partNum);
        triggerAuthoredQuestionDraftSave();
    }

    function onGlobalGenericSectionChange(selectEl) {
        if (!selectEl) return;
        const secIdInput = document.getElementById('create-q-section-id');
        if (secIdInput) secIdInput.value = selectEl.value;
        triggerAuthoredQuestionDraftSave();
    }

    function toggleSectionCollapse(secId, forceState = null) {
        const body = document.getElementById(`section-body-${secId}`);
        const icon = document.getElementById(`collapse-icon-${secId}`);
        const label = document.getElementById(`collapse-label-${secId}`);
        const btn = document.getElementById(`btn-collapse-${secId}`);
        if (!body) return;

        const isHidden = body.classList.contains('hidden') || body.style.display === 'none';
        const shouldOpen = (forceState !== null) ? forceState : isHidden;

        if (shouldOpen) {
            body.classList.remove('hidden');
            body.style.display = 'block';
            if (icon) icon.textContent = '▾';
            if (label) label.textContent = 'Collapse';
            if (btn) btn.setAttribute('aria-expanded', 'true');
        } else {
            body.classList.add('hidden');
            body.style.display = 'none';
            if (icon) icon.textContent = '▸';
            if (label) label.textContent = 'Expand';
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }

    function expandAndFocusFirstIssue(secId, firstIssueId = null) {
        toggleSectionCollapse(secId, true);
        setTimeout(() => {
            if (firstIssueId) {
                const targetEl = document.getElementById(firstIssueId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetEl.classList.add('ring-2', 'ring-amber-500', 'transition-all');
                    setTimeout(() => {
                        targetEl.classList.remove('ring-2', 'ring-amber-500');
                    }, 2500);
                    return;
                }
            }
            const secCard = document.getElementById(`section-card-${secId}`);
            if (secCard) {
                secCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }

    function focusFirstIssueSection() {
        const problematicCard = document.querySelector('.section-card[data-status="needs_attention"], .section-card[data-status="not_started"]');
        if (problematicCard) {
            const secId = problematicCard.getAttribute('data-section-id');
            const firstIssueId = problematicCard.getAttribute('data-first-issue-id');
            if (secId) {
                expandAndFocusFirstIssue(secId, firstIssueId);
            }
        }
    }

    function closeCreateAuthoredQuestionModal(e) {
        if (e && e.target === document.getElementById('create-authored-question-modal')) {
            // Backdrop click: do not dismiss modal to protect unsaved work
            return;
        }
        attemptCloseAuthoredQuestionModal();
    }

    function getAudioGroupDraftStorageKey(sectionId) {
        return `iap:audio_group_draft_{{ auth()->id() }}_{{ $test->id }}_${sectionId || 'default'}`;
    }

    let _agDraftSaveTimeout = null;
    window._agBaselineSnapshot = null;
    window._agSubmitting = false;

    function getAudioGroupCurrentSnapshot() {
        const secId = document.getElementById('ag-section-id')?.value || '';
        const partNum = document.getElementById('ag-part-number')?.value || '';
        const groupType = document.getElementById('ag-group-type')?.value || '';
        const title = document.getElementById('ag-title')?.value || '';
        const audioScript = document.getElementById('ag-audio-script')?.value || '';
        const mediaAssetId = document.getElementById('ag-media-asset-id')?.value || '';
        const audioUrl = document.getElementById('ag-audio-url')?.value || '';
        const audioTitle = document.getElementById('ag-preview-audio-title')?.textContent || '';
        const audioSrc = document.getElementById('ag-preview-audio-player')?.src || '';

        const questions = [];
        for (let i = 0; i < 3; i++) {
            const id = document.getElementById(`ag-q${i}-id`)?.value || '';
            const prompt = document.getElementById(`ag-q${i}-prompt`)?.value || '';
            const explanation = document.getElementById(`ag-q${i}-explanation`)?.value || '';
            const choices = [];
            for (let c = 0; c < 4; c++) {
                choices.push(document.getElementById(`ag-q${i}-choice-${c}`)?.value || '');
            }
            let correctChoice = 0;
            for (let c = 0; c < 4; c++) {
                const radio = document.getElementById(`ag-q${i}-correct-${c}`);
                if (radio && radio.checked) {
                    correctChoice = c;
                    break;
                }
            }
            questions.push({ id, prompt, explanation, choices, correct_choice: correctChoice });
        }

        return {
            section_id: secId,
            part_number: partNum,
            group_type: groupType,
            title,
            audio_script: audioScript,
            media_asset_id: mediaAssetId,
            audio_url: audioUrl,
            audio_title: audioTitle,
            audio_effective_url: audioSrc,
            questions
        };
    }

    function isAudioGroupFormDirty() {
        const current = getAudioGroupCurrentSnapshot();
        if (!window._agBaselineSnapshot) {
            if (current.title.trim() || current.audio_script.trim() || current.media_asset_id || current.audio_url) {
                return true;
            }
            for (let i = 0; i < 3; i++) {
                const q = current.questions[i];
                if (q.prompt.trim() || q.explanation.trim() || q.choices.some(c => c.trim().length > 0)) {
                    return true;
                }
            }
            return false;
        }
        return JSON.stringify(current) !== JSON.stringify(window._agBaselineSnapshot);
    }

    function triggerAudioGroupDraftSave() {
        clearTimeout(_agDraftSaveTimeout);
        _agDraftSaveTimeout = setTimeout(() => {
            const modal = document.getElementById('create-audio-group-modal');
            const methodInput = document.getElementById('ag-form-method');
            if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;
            if (methodInput && methodInput.value !== 'POST') return;

            const secId = document.getElementById('ag-section-id')?.value;
            if (!secId) return;

            const snapshot = getAudioGroupCurrentSnapshot();
            try {
                sessionStorage.setItem(getAudioGroupDraftStorageKey(secId), JSON.stringify(snapshot));
            } catch (e) {
                console.warn('Unable to save audio group draft to sessionStorage', e);
            }
        }, 300);
    }

    function discardAudioGroupDraft(reopenFresh = false) {
        const secId = document.getElementById('ag-section-id')?.value;
        if (secId) {
            try {
                sessionStorage.removeItem(getAudioGroupDraftStorageKey(secId));
            } catch (e) {}
        }
        const banner = document.getElementById('ag-draft-restored-banner');
        if (banner) {
            banner.classList.add('hidden');
        }
        if (reopenFresh) {
            const titleInput = document.getElementById('ag-title');
            const scriptInput = document.getElementById('ag-audio-script');
            if (titleInput) titleInput.value = '';
            if (scriptInput) scriptInput.value = '';
            removeAudioGroupAttachedMedia();

            for (let i = 0; i < 3; i++) {
                const idEl = document.getElementById(`ag-q${i}-id`);
                const promptEl = document.getElementById(`ag-q${i}-prompt`);
                const explEl = document.getElementById(`ag-q${i}-explanation`);
                if (idEl) idEl.value = '';
                if (promptEl) promptEl.value = '';
                if (explEl) explEl.value = '';
                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`ag-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = '';
                }
                const correct0 = document.getElementById(`ag-q${i}-correct-0`);
                if (correct0) correct0.checked = true;
            }

            updateAudioGroupAutoDifficulty();
            window._agBaselineSnapshot = getAudioGroupCurrentSnapshot();
        }
    }

    function forceCloseAudioGroupModal() {
        const modal = document.getElementById('create-audio-group-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        const player = document.getElementById('ag-preview-audio-player');
        if (player) {
            player.pause();
        }
        window._agBaselineSnapshot = null;
    }

    function attemptCloseAudioGroupModal() {
        const modal = document.getElementById('create-audio-group-modal');
        if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;

        if (!isAudioGroupFormDirty()) {
            forceCloseAudioGroupModal();
            return;
        }

        const message = 'You have unsaved changes in this audio group. Closing now will discard changes that have not been saved.';
        if (typeof window.iapConfirm === 'function') {
            window.iapConfirm({
                title: 'Unsaved Changes',
                message: message,
                confirmText: 'Discard Changes',
                cancelText: 'Continue Editing',
                variant: 'warning',
                onConfirm: () => {
                    discardAudioGroupDraft(true);
                    forceCloseAudioGroupModal();
                }
            });
        } else {
            if (confirm(message)) {
                discardAudioGroupDraft(true);
                forceCloseAudioGroupModal();
            }
        }
    }

    function openCreateAudioGroupModal(sectionId, partNumber, sectionTitle = '') {
        const modal = document.getElementById('create-audio-group-modal');
        const form = document.getElementById('create-audio-group-form');
        const methodInput = document.getElementById('ag-form-method');
        if (!modal) return;

        const partNum = parseInt(partNumber) || 3;
        const isTalk = partNum === 4;
        const groupType = isTalk ? 'talk' : 'conversation';

        if (form) {
            form.action = "{{ route('teacher.tests.create-audio-group', $test->id) }}";
        }
        if (methodInput) methodInput.value = 'POST';

        // Locked context values
        const secInput = document.getElementById('ag-section-id');
        const partInput = document.getElementById('ag-part-number');
        const typeInput = document.getElementById('ag-group-type');
        if (secInput) secInput.value = sectionId;
        if (partInput) partInput.value = partNum;
        if (typeInput) typeInput.value = groupType;

        // Context header and badge
        const modalTitle = document.getElementById('ag-modal-title');
        const targetSecTitle = document.getElementById('ag-target-section-title');
        const partBadge = document.getElementById('ag-part-badge');
        const scriptLabel = document.getElementById('ag-script-label');

        if (modalTitle) modalTitle.textContent = isTalk ? 'Create Talk Group' : 'Create Conversation Group';
        if (targetSecTitle) targetSecTitle.textContent = `Target Section: ${sectionTitle || (isTalk ? 'Part 4: Talks' : 'Part 3: Conversations')}`;
        if (partBadge) partBadge.textContent = isTalk ? 'LISTENING • PART 4' : 'LISTENING • PART 3';
        if (scriptLabel) scriptLabel.textContent = isTalk ? 'Talk Script / Transcript (Optional)' : 'Conversation Script / Transcript (Optional)';

        // Check for existing sessionStorage draft
        let draftData = null;
        try {
            const rawDraft = sessionStorage.getItem(getAudioGroupDraftStorageKey(sectionId));
            if (rawDraft) {
                draftData = JSON.parse(rawDraft);
            }
        } catch (e) {
            draftData = null;
        }

        const banner = document.getElementById('ag-draft-restored-banner');

        if (draftData) {
            // Restore from draft
            const titleInput = document.getElementById('ag-title');
            const scriptInput = document.getElementById('ag-audio-script');
            if (titleInput) titleInput.value = draftData.title || '';
            if (scriptInput) scriptInput.value = draftData.audio_script || '';

            const mediaIdInput = document.getElementById('ag-media-asset-id');
            const audioInput = document.getElementById('ag-audio-url');
            if (mediaIdInput) mediaIdInput.value = draftData.media_asset_id || '';
            if (audioInput) audioInput.value = draftData.audio_url || '';

            const effectiveUrl = draftData.audio_effective_url || draftData.audio_url || '';
            const audioTitle = draftData.audio_title || draftData.title || 'Shared Audio';
            const prevCard = document.getElementById('ag-preview-audio-card');
            const emptyCard = document.getElementById('ag-empty-audio-card');
            const prevTitle = document.getElementById('ag-preview-audio-title');
            const player = document.getElementById('ag-preview-audio-player');

            if (effectiveUrl || draftData.media_asset_id) {
                if (prevTitle) prevTitle.textContent = audioTitle;
                if (player) player.src = effectiveUrl;
                if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
                if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
            } else {
                removeAudioGroupAttachedMedia();
            }

            const qList = draftData.questions || [];
            for (let i = 0; i < 3; i++) {
                const q = qList[i] || null;
                const idEl = document.getElementById(`ag-q${i}-id`);
                const promptEl = document.getElementById(`ag-q${i}-prompt`);
                const explEl = document.getElementById(`ag-q${i}-explanation`);
                if (idEl) idEl.value = q ? (q.id || '') : '';
                if (promptEl) promptEl.value = q ? (q.prompt || '') : '';
                if (explEl) explEl.value = q ? (q.explanation || '') : '';

                const choices = q ? (q.choices || []) : [];
                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`ag-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = choices[c] || '';
                }
                const correctIdx = q ? (q.correct_choice ?? 0) : 0;
                const radio = document.getElementById(`ag-q${i}-correct-${correctIdx}`) || document.getElementById(`ag-q${i}-correct-0`);
                if (radio) radio.checked = true;
            }

            if (banner) {
                banner.classList.remove('hidden');
            }
        } else {
            // Clear previous values
            const titleInput = document.getElementById('ag-title');
            const scriptInput = document.getElementById('ag-audio-script');
            if (titleInput) titleInput.value = '';
            if (scriptInput) scriptInput.value = '';
            removeAudioGroupAttachedMedia();

            for (let i = 0; i < 3; i++) {
                const idEl = document.getElementById(`ag-q${i}-id`);
                const promptEl = document.getElementById(`ag-q${i}-prompt`);
                const explEl = document.getElementById(`ag-q${i}-explanation`);
                if (idEl) idEl.value = '';
                if (promptEl) promptEl.value = '';
                if (explEl) explEl.value = '';
                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`ag-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = '';
                }
                const correct0 = document.getElementById(`ag-q${i}-correct-0`);
                if (correct0) correct0.checked = true;
            }

            if (banner) {
                banner.classList.add('hidden');
            }
        }

        updateAudioGroupAutoDifficulty();
        window._agBaselineSnapshot = getAudioGroupCurrentSnapshot();

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function openEditAudioGroupModal(agData, sectionId = '', sectionTitle = '') {
        const modal = document.getElementById('create-audio-group-modal');
        const form = document.getElementById('create-audio-group-form');
        const methodInput = document.getElementById('ag-form-method');
        if (!modal || !agData) return;

        const partNum = parseInt(agData.part_number) || 3;
        const isTalk = partNum === 4;
        const groupType = isTalk ? 'talk' : 'conversation';

        if (form) {
            form.action = `/teacher/assessments/{{ $test->id }}/audio-groups/${agData.id}`;
        }
        if (methodInput) methodInput.value = 'PUT';

        const secInput = document.getElementById('ag-section-id');
        const partInput = document.getElementById('ag-part-number');
        const typeInput = document.getElementById('ag-group-type');
        if (secInput) secInput.value = sectionId;
        if (partInput) partInput.value = partNum;
        if (typeInput) typeInput.value = groupType;

        const modalTitle = document.getElementById('ag-modal-title');
        const targetSecTitle = document.getElementById('ag-target-section-title');
        const partBadge = document.getElementById('ag-part-badge');
        const scriptLabel = document.getElementById('ag-script-label');

        if (modalTitle) modalTitle.textContent = isTalk ? 'Edit Talk Group' : 'Edit Conversation Group';
        if (targetSecTitle) targetSecTitle.textContent = `Target Section: ${sectionTitle || (isTalk ? 'Part 4: Talks' : 'Part 3: Conversations')}`;
        if (partBadge) partBadge.textContent = isTalk ? 'LISTENING • PART 4' : 'LISTENING • PART 3';
        if (scriptLabel) scriptLabel.textContent = isTalk ? 'Talk Script / Transcript (Optional)' : 'Conversation Script / Transcript (Optional)';

        // Populate audio group metadata
        const titleInput = document.getElementById('ag-title');
        const scriptInput = document.getElementById('ag-audio-script');
        if (titleInput) titleInput.value = agData.title || '';
        if (scriptInput) scriptInput.value = agData.audio_script || '';

        // Media attachment
        const mediaIdInput = document.getElementById('ag-media-asset-id');
        const audioInput = document.getElementById('ag-audio-url');
        if (mediaIdInput) mediaIdInput.value = agData.media_asset_id || '';
        if (audioInput) audioInput.value = agData.audio_url || '';

        const effectiveUrl = agData.audio_effective_url || agData.audio_url || '';
        const audioTitle = agData.audio_title || agData.title || 'Shared Audio';
        const prevCard = document.getElementById('ag-preview-audio-card');
        const emptyCard = document.getElementById('ag-empty-audio-card');
        const prevTitle = document.getElementById('ag-preview-audio-title');
        const player = document.getElementById('ag-preview-audio-player');

        if (effectiveUrl || agData.media_asset_id) {
            if (prevTitle) prevTitle.textContent = audioTitle;
            if (player) player.src = effectiveUrl;
            if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
            if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
        } else {
            removeAudioGroupAttachedMedia();
        }

        // Populate questions (up to 3 slots)
        const qList = agData.questions || [];
        for (let i = 0; i < 3; i++) {
            const q = qList[i] || null;
            const idEl = document.getElementById(`ag-q${i}-id`);
            const promptEl = document.getElementById(`ag-q${i}-prompt`);
            const explEl = document.getElementById(`ag-q${i}-explanation`);

            if (idEl) idEl.value = q ? q.id : '';
            if (promptEl) promptEl.value = q ? (q.prompt || '') : '';
            if (explEl) explEl.value = q ? (q.explanation || '') : '';

            const choices = q ? (q.choices || []) : [];
            const correctIdx = q ? (q.correct_choice ?? 0) : 0;

            for (let c = 0; c < 4; c++) {
                const choiceEl = document.getElementById(`ag-q${i}-choice-${c}`);
                if (choiceEl) choiceEl.value = choices[c] || '';
            }

            const radio = document.getElementById(`ag-q${i}-correct-${correctIdx}`) || document.getElementById(`ag-q${i}-correct-0`);
            if (radio) radio.checked = true;
        }

        const banner = document.getElementById('ag-draft-restored-banner');
        if (banner) banner.classList.add('hidden');

        updateAudioGroupAutoDifficulty();
        window._agBaselineSnapshot = getAudioGroupCurrentSnapshot();

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function closeCreateAudioGroupModal(e = null) {
        if (e && e.target !== document.getElementById('create-audio-group-modal')) {
            return;
        }
        attemptCloseAudioGroupModal();
    }

    function getPassageGroupDraftStorageKey(sectionId, groupId = null) {
        const editSuffix = groupId ? `_${groupId}` : '';
        return `iap:passage_group_draft_{{ auth()->id() }}_{{ $test->id }}_${sectionId || 'default'}${editSuffix}`;
    }

    let _pgDraftSaveTimeout = null;
    window._pgBaselineSnapshot = null;
    window._pgSubmitting = false;
    window._currentPassageGroupId = null;
    window._pgOriginalData = null;
    window._currentPassageSectionTitle = '';

    let currentPassageSetType = 'single';
    let currentPassageChildQCount = 2;
    let currentPassagePartNum = 7;
    let currentPassageActiveDoc = 0;

    function getAllowedDocumentCount() {
        if (currentPassagePartNum === 6) return 1;
        if (currentPassageSetType === 'double') return 2;
        if (currentPassageSetType === 'triple') return 3;
        return 1;
    }

    function getTargetQuestionCount() {
        if (currentPassagePartNum === 6) return 4;
        if (currentPassageSetType === 'double' || currentPassageSetType === 'triple') return 5;
        return Math.max(2, Math.min(4, currentPassageChildQCount));
    }

    function getPassageGroupCurrentSnapshot() {
        const secId = document.getElementById('pg-section-id')?.value || '';
        const partNum = document.getElementById('pg-part-number')?.value || '7';
        const passageType = document.getElementById('pg-passage-type')?.value || currentPassageSetType;
        const groupTitle = document.getElementById('pg-group-title')?.value || '';

        const passages = [];
        for (let d = 0; d < 3; d++) {
            const id = document.getElementById(`pg-doc-${d}-id`)?.value || '';
            const title = document.getElementById(`pg-doc-${d}-title`)?.value || '';
            const docType = document.getElementById(`pg-doc-${d}-type`)?.value || 'article';
            const contentEl = document.getElementById(`pg-doc-${d}-content`) || (d === 0 ? document.getElementById('pg-passage-content') : null);
            const content = contentEl?.value || '';
            const contentMode = document.getElementById(`pg-doc-${d}-content-mode`)?.value || 'text';
            const mediaAssetId = document.getElementById(`pg-doc-${d}-media-asset-id`)?.value || '';
            const imageUrl = document.getElementById(`pg-doc-${d}-image-url`)?.value || '';
            const previewTitle = document.getElementById(`pg-doc-${d}-preview-image-title`)?.textContent || '';
            const previewThumb = document.getElementById(`pg-doc-${d}-preview-image-thumb`)?.src || '';

            passages.push({
                id,
                title,
                document_type: docType,
                content,
                content_mode: contentMode,
                media_asset_id: mediaAssetId,
                image_url: imageUrl,
                preview_image_title: previewTitle,
                preview_image_thumb: previewThumb
            });
        }

        const questions = [];
        for (let i = 0; i < 5; i++) {
            const id = document.getElementById(`pg-q${i}-id`)?.value || '';
            const prompt = document.getElementById(`pg-q${i}-prompt`)?.value || '';
            const explanation = document.getElementById(`pg-q${i}-explanation`)?.value || '';
            const choices = [];
            for (let c = 0; c < 4; c++) {
                choices.push(document.getElementById(`pg-q${i}-choice-${c}`)?.value || '');
            }
            let correctChoice = null;
            for (let c = 0; c < 4; c++) {
                const radio = document.getElementById(`pg-q${i}-correct-${c}`);
                if (radio && radio.checked) {
                    correctChoice = c;
                    break;
                }
            }
            questions.push({ id, prompt, explanation, choices, correct_choice: correctChoice });
        }

        return {
            section_id: secId,
            part_number: partNum,
            passage_type: passageType,
            child_q_count: currentPassageChildQCount,
            title: groupTitle,
            passages,
            questions
        };
    }

    function isPassageGroupFormDirty() {
        const current = getPassageGroupCurrentSnapshot();
        if (!window._pgBaselineSnapshot) {
            if (current.title.trim()) return true;
            const allowedDocs = getAllowedDocumentCount();
            for (let d = 0; d < allowedDocs; d++) {
                const p = current.passages[d];
                if (p.title.trim() || p.content.trim() || p.media_asset_id || p.image_url) {
                    return true;
                }
            }
            const targetQCount = getTargetQuestionCount();
            for (let i = 0; i < targetQCount; i++) {
                const q = current.questions[i];
                if (q.prompt.trim() || q.explanation.trim() || q.choices.some(c => c.trim().length > 0)) {
                    return true;
                }
            }
            return false;
        }
        return JSON.stringify(current) !== JSON.stringify(window._pgBaselineSnapshot);
    }

    function triggerPassageGroupDraftSave() {
        clearTimeout(_pgDraftSaveTimeout);
        _pgDraftSaveTimeout = setTimeout(() => {
            const modal = document.getElementById('create-passage-group-modal');
            if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;

            const secId = document.getElementById('pg-section-id')?.value;
            if (!secId) return;

            const snapshot = getPassageGroupCurrentSnapshot();
            try {
                sessionStorage.setItem(getPassageGroupDraftStorageKey(secId, window._currentPassageGroupId), JSON.stringify(snapshot));
            } catch (e) {
                console.warn('Unable to save passage group draft to sessionStorage', e);
            }
        }, 300);
    }

    function discardPassageGroupDraft(reopenFresh = false) {
        const secId = document.getElementById('pg-section-id')?.value;
        if (secId) {
            try {
                sessionStorage.removeItem(getPassageGroupDraftStorageKey(secId, window._currentPassageGroupId));
            } catch (e) {}
        }
        const banner = document.getElementById('pg-draft-restored-banner');
        if (banner) {
            banner.classList.add('hidden');
        }
        if (reopenFresh) {
            if (window._currentPassageGroupId && window._pgOriginalData) {
                openEditPassageGroupModal(window._pgOriginalData, secId, window._currentPassageSectionTitle || '');
            } else {
                const partNum = currentPassagePartNum || 7;
                openCreatePassageGroupModal(secId, partNum, window._currentPassageSectionTitle || '');
            }
        }
    }

    function forceClosePassageGroupModal() {
        const modal = document.getElementById('create-passage-group-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        window._pgBaselineSnapshot = null;
        window._currentPassageGroupId = null;
        window._pgOriginalData = null;
    }

    function attemptClosePassageGroupModal() {
        const modal = document.getElementById('create-passage-group-modal');
        if (!modal || modal.classList.contains('hidden') || modal.style.display === 'none') return;

        if (!isPassageGroupFormDirty()) {
            forceClosePassageGroupModal();
            return;
        }

        const message = 'You have unsaved changes in this passage group. Closing now will discard changes that have not been saved.';
        if (typeof window.iapConfirm === 'function') {
            window.iapConfirm({
                title: 'Unsaved Changes',
                message: message,
                confirmText: 'Discard Changes',
                cancelText: 'Continue Editing',
                variant: 'warning',
                onConfirm: () => {
                    discardPassageGroupDraft(false);
                    forceClosePassageGroupModal();
                }
            });
        } else {
            if (confirm(message)) {
                discardPassageGroupDraft(false);
                forceClosePassageGroupModal();
            }
        }
    }

    function setPassageSetType(type) {
        currentPassageSetType = type;
        const typeInput = document.getElementById('pg-passage-type');
        if (typeInput) typeInput.value = type;

        // Button styles
        ['single', 'double', 'triple'].forEach(t => {
            const btn = document.getElementById(`pg-type-btn-${t}`);
            if (btn) {
                if (t === type) {
                    btn.className = 'flex flex-col items-start p-3 rounded-xl border-2 transition-all text-left bg-white dark:bg-slate-900 border-indigo-600 shadow-sm';
                    const iconText = btn.querySelector('div:first-child');
                    if (iconText) iconText.className = 'flex items-center gap-2 font-black text-xs text-indigo-700 dark:text-indigo-300';
                } else {
                    btn.className = 'flex flex-col items-start p-3 rounded-xl border-2 transition-all text-left bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 hover:border-indigo-400';
                    const iconText = btn.querySelector('div:first-child');
                    if (iconText) iconText.className = 'flex items-center gap-2 font-black text-xs text-slate-700 dark:text-slate-300';
                }
            }
        });

        const countBadge = document.getElementById('pg-count-badge');
        const helperText = document.getElementById('pg-helper-text');
        const tabsBar = document.getElementById('pg-doc-tabs-bar');
        const questionsHeading = document.getElementById('pg-questions-heading');
        const addQBtn = document.getElementById('pg-add-question-btn');

        if (type === 'single') {
            if (countBadge) countBadge.textContent = '2–4 Child Questions';
            if (helperText) helperText.textContent = 'Single passage followed by 2 to 4 reading comprehension questions.';
            if (tabsBar) tabsBar.classList.add('hidden');
            if (questionsHeading) questionsHeading.textContent = 'Child Questions (2 to 4 Questions Allowed)';
            if (addQBtn) addQBtn.classList.remove('hidden');

            if (currentPassageChildQCount < 2) currentPassageChildQCount = 2;
            if (currentPassageChildQCount > 4) currentPassageChildQCount = 4;
        } else if (type === 'double') {
            if (countBadge) countBadge.textContent = 'Exactly 5 Child Questions';
            if (helperText) helperText.textContent = 'Two related documents followed by exactly 5 reading comprehension questions.';
            if (tabsBar) tabsBar.classList.remove('hidden');
            if (questionsHeading) questionsHeading.textContent = 'Child Questions (Exactly 5 Questions Required)';
            if (addQBtn) addQBtn.classList.add('hidden');

            currentPassageChildQCount = 5;
        } else if (type === 'triple') {
            if (countBadge) countBadge.textContent = 'Exactly 5 Child Questions';
            if (helperText) helperText.textContent = 'Three related documents followed by exactly 5 reading comprehension questions.';
            if (tabsBar) tabsBar.classList.remove('hidden');
            if (questionsHeading) questionsHeading.textContent = 'Child Questions (Exactly 5 Questions Required)';
            if (addQBtn) addQBtn.classList.add('hidden');

            currentPassageChildQCount = 5;
        }

        if (currentPassageActiveDoc >= getAllowedDocumentCount()) {
            currentPassageActiveDoc = 0;
        }
        switchPassageDocTab(currentPassageActiveDoc);
        updateChildQuestionsVisibility();
        syncPassageFormFieldsDisabledState();
        triggerPassageGroupDraftSave();
    }

    function switchPassageDocTab(docIdx) {
        const allowedDocs = getAllowedDocumentCount();
        if (docIdx >= allowedDocs) docIdx = 0;
        currentPassageActiveDoc = docIdx;

        const tabsBar = document.getElementById('pg-doc-tabs-bar');
        if (tabsBar) {
            if (allowedDocs > 1) {
                tabsBar.classList.remove('hidden');
            } else {
                tabsBar.classList.add('hidden');
            }
        }

        for (let d = 0; d < 3; d++) {
            const panel = document.getElementById(`pg-doc-panel-${d}`);
            const tabBtn = document.getElementById(`pg-doc-tab-btn-${d}`);

            if (d < allowedDocs) {
                if (tabBtn) {
                    tabBtn.classList.remove('hidden');
                    if (d === docIdx) {
                        tabBtn.className = 'px-3 py-1.5 rounded-lg text-xs font-black border bg-indigo-600 text-white border-indigo-600 shadow-sm transition-all';
                    } else {
                        tabBtn.className = 'px-3 py-1.5 rounded-lg text-xs font-black border bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-indigo-300 transition-all';
                    }
                }
                if (panel) {
                    if (d === docIdx) {
                        panel.classList.remove('hidden');
                    } else {
                        panel.classList.add('hidden');
                    }
                }
            } else {
                if (tabBtn) {
                    tabBtn.classList.add('hidden');
                }
                if (panel) {
                    panel.classList.add('hidden');
                }
            }
        }

        syncPassageFormFieldsDisabledState();
    }

    function setPassageDocContentMode(docIdx, mode) {
        const modeInput = document.getElementById(`pg-doc-${docIdx}-content-mode`);
        if (modeInput) modeInput.value = mode;

        const textBtn = document.getElementById(`pg-doc-${docIdx}-mode-text-btn`);
        const imgBtn = document.getElementById(`pg-doc-${docIdx}-mode-image-btn`);
        const textImgBtn = document.getElementById(`pg-doc-${docIdx}-mode-text_image-btn`);

        [textBtn, imgBtn, textImgBtn].forEach(b => {
            if (b) b.className = 'px-2 py-1 rounded-lg text-[11px] font-extrabold text-slate-700 dark:text-slate-300 hover:text-indigo-600 transition-all';
        });

        if (mode === 'text' && textBtn) textBtn.className = 'px-2 py-1 rounded-lg text-[11px] font-extrabold bg-indigo-600 text-white shadow-sm transition-all';
        if (mode === 'image' && imgBtn) imgBtn.className = 'px-2 py-1 rounded-lg text-[11px] font-extrabold bg-indigo-600 text-white shadow-sm transition-all';
        if (mode === 'text_image' && textImgBtn) textImgBtn.className = 'px-2 py-1 rounded-lg text-[11px] font-extrabold bg-indigo-600 text-white shadow-sm transition-all';

        const textWrap = document.getElementById(`pg-doc-${docIdx}-text-wrap`);
        const imageWrap = document.getElementById(`pg-doc-${docIdx}-image-wrap`);

        if (mode === 'text') {
            if (textWrap) textWrap.classList.remove('hidden');
            if (imageWrap) imageWrap.classList.add('hidden');
        } else if (mode === 'image') {
            if (textWrap) textWrap.classList.add('hidden');
            if (imageWrap) imageWrap.classList.remove('hidden');
        } else if (mode === 'text_image') {
            if (textWrap) textWrap.classList.remove('hidden');
            if (imageWrap) imageWrap.classList.remove('hidden');
        }

        triggerPassageGroupDraftSave();
    }

    function removePassageDocMedia(d) {
        const mediaIdInput = document.getElementById(`pg-doc-${d}-media-asset-id`);
        const imgInput = document.getElementById(`pg-doc-${d}-image-url`);
        if (mediaIdInput) mediaIdInput.value = '';
        if (imgInput) imgInput.value = '';

        const prevCard = document.getElementById(`pg-doc-${d}-preview-image-card`);
        const emptyCard = document.getElementById(`pg-doc-${d}-empty-image-card`);
        const thumb = document.getElementById(`pg-doc-${d}-preview-image-thumb`);
        if (thumb) thumb.src = '';
        if (prevCard) { prevCard.classList.add('hidden'); prevCard.style.display = 'none'; }
        if (emptyCard) { emptyCard.classList.remove('hidden'); emptyCard.style.display = 'flex'; }

        triggerPassageGroupDraftSave();
    }

    function addPassageChildQuestion() {
        if (currentPassageSetType === 'single' && currentPassageChildQCount < 4) {
            currentPassageChildQCount++;
            updateChildQuestionsVisibility();
            syncPassageFormFieldsDisabledState();
            triggerPassageGroupDraftSave();
        }
    }

    function removePassageChildQuestion(qIdx) {
        if (currentPassageSetType === 'single' && currentPassageChildQCount > 2) {
            // Shift contents up if removing middle question
            for (let i = qIdx; i < currentPassageChildQCount - 1; i++) {
                const curPrompt = document.getElementById(`pg-q${i}-prompt`);
                const nextPrompt = document.getElementById(`pg-q${i + 1}-prompt`);
                const curExpl = document.getElementById(`pg-q${i}-explanation`);
                const nextExpl = document.getElementById(`pg-q${i + 1}-explanation`);
                const curId = document.getElementById(`pg-q${i}-id`);
                const nextId = document.getElementById(`pg-q${i + 1}-id`);

                if (curPrompt && nextPrompt) curPrompt.value = nextPrompt.value;
                if (curExpl && nextExpl) curExpl.value = nextExpl.value;
                if (curId && nextId) curId.value = nextId.value;

                for (let c = 0; c < 4; c++) {
                    const curChoice = document.getElementById(`pg-q${i}-choice-${c}`);
                    const nextChoice = document.getElementById(`pg-q${i + 1}-choice-${c}`);
                    if (curChoice && nextChoice) curChoice.value = nextChoice.value;
                }

                const nextRadioChecked = Array.from(document.querySelectorAll(`input[id^="pg-q${i + 1}-correct-"]`)).find(r => r.checked);
                if (nextRadioChecked) {
                    const curRadio = document.getElementById(`pg-q${i}-correct-${nextRadioChecked.value}`);
                    if (curRadio) curRadio.checked = true;
                }
            }

            // Clear the last slot
            const lastIdx = currentPassageChildQCount - 1;
            const lastPrompt = document.getElementById(`pg-q${lastIdx}-prompt`);
            const lastExpl = document.getElementById(`pg-q${lastIdx}-explanation`);
            const lastId = document.getElementById(`pg-q${lastIdx}-id`);
            if (lastPrompt) lastPrompt.value = '';
            if (lastExpl) lastExpl.value = '';
            if (lastId) lastId.value = '';
            for (let c = 0; c < 4; c++) {
                const choiceEl = document.getElementById(`pg-q${lastIdx}-choice-${c}`);
                if (choiceEl) choiceEl.value = '';
            }

            currentPassageChildQCount--;
            updateChildQuestionsVisibility();
            syncPassageFormFieldsDisabledState();
            triggerPassageGroupDraftSave();
        }
    }

    function syncPassageFormFieldsDisabledState() {
        const allowedDocs = getAllowedDocumentCount();
        const targetQCount = getTargetQuestionCount();

        // Disable / Enable Document inputs
        for (let d = 0; d < 3; d++) {
            const panel = document.getElementById(`pg-doc-panel-${d}`);
            const isDocActive = d < allowedDocs;
            if (panel) {
                const inputs = panel.querySelectorAll('input, textarea, select');
                inputs.forEach(el => {
                    el.disabled = !isDocActive;
                });
            }
        }

        // Disable / Enable Question inputs
        for (let i = 0; i < 5; i++) {
            const card = document.getElementById(`pg-q-card-${i}`);
            const isQActive = i < targetQCount;
            if (card) {
                const inputs = card.querySelectorAll('input, textarea, select');
                inputs.forEach(el => {
                    el.disabled = !isQActive;
                });
            }
        }
    }

    function updateChildQuestionsVisibility() {
        const isPart6 = currentPassagePartNum === 6;
        const targetCount = getTargetQuestionCount();

        for (let i = 0; i < 5; i++) {
            const card = document.getElementById(`pg-q-card-${i}`);
            const label = document.getElementById(`pg-q${i}-label`);
            const removeBtn = document.getElementById(`pg-q${i}-remove-btn`);
            const promptInput = document.getElementById(`pg-q${i}-prompt`);
            const choiceInputs = Array.from(document.querySelectorAll(`input[id^="pg-q${i}-choice-"]`));

            if (i < targetCount) {
                if (card) card.classList.remove('hidden');
                if (label) {
                    if (isPart6) {
                        label.textContent = `Question ${i + 1} of 4`;
                    } else if (currentPassageSetType === 'single') {
                        label.textContent = `Question ${i + 1} of ${targetCount}`;
                    } else {
                        label.textContent = `Question ${i + 1} of 5`;
                    }
                }
                if (removeBtn) {
                    if (!isPart6 && currentPassageSetType === 'single' && targetCount > 2 && i >= 2) {
                        removeBtn.classList.remove('hidden');
                    } else {
                        removeBtn.classList.add('hidden');
                    }
                }
                if (promptInput) {
                    if (isPart6) {
                        promptInput.removeAttribute('required');
                    } else {
                        promptInput.setAttribute('required', 'required');
                    }
                }
                if (isPart6) {
                    choiceInputs.forEach(ci => ci.removeAttribute('required'));
                } else {
                    choiceInputs.forEach(ci => ci.setAttribute('required', 'required'));
                }
            } else {
                if (card) card.classList.add('hidden');
                if (promptInput) promptInput.removeAttribute('required');
                choiceInputs.forEach(ci => ci.removeAttribute('required'));
            }
        }

        const addQBtn = document.getElementById('pg-add-question-btn');
        if (addQBtn) {
            if (!isPart6 && currentPassageSetType === 'single') {
                addQBtn.classList.remove('hidden');
                if (targetCount >= 4) {
                    addQBtn.classList.add('opacity-50', 'pointer-events-none');
                } else {
                    addQBtn.classList.remove('opacity-50', 'pointer-events-none');
                }
            } else {
                addQBtn.classList.add('hidden');
            }
        }

        syncPassageFormFieldsDisabledState();
        updatePassageGroupProgressAndBadges();
    }

    function updatePassageGroupProgressAndBadges() {
        const isPart6 = currentPassagePartNum === 6;
        const targetCount = getTargetQuestionCount();
        let completeCount = 0;

        for (let i = 0; i < targetCount; i++) {
            const choiceInputs = Array.from(document.querySelectorAll(`input[id^="pg-q${i}-choice-"]`));
            const filledChoicesCount = choiceInputs.filter(ci => ci.value && ci.value.trim().length > 0).length;
            const promptEl = document.getElementById(`pg-q${i}-prompt`);
            const promptFilled = promptEl ? promptEl.value.trim().length > 0 : false;
            const radioChecked = Array.from(document.querySelectorAll(`input[id^="pg-q${i}-correct-"]`)).some(r => r.checked);

            let isComplete = false;
            if (isPart6) {
                // Part 6 blank prompt note is optional; 4 valid choices + correct answer selected = complete question
                isComplete = (filledChoicesCount === 4) && radioChecked;
            } else {
                // Part 7 prompt stem is required + 4 choices + correct answer selected
                isComplete = promptFilled && (filledChoicesCount === 4) && radioChecked;
            }

            const badge = document.getElementById(`pg-q${i}-status-badge`);
            if (badge) {
                if (isComplete) {
                    badge.textContent = '✓ Complete';
                    badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
                } else {
                    const hasSomeInput = filledChoicesCount > 0 || promptFilled || radioChecked;
                    badge.textContent = hasSomeInput ? '🟡 In Progress' : '○ Incomplete';
                    badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700';
                }
            }

            if (isComplete) {
                completeCount++;
            }
        }

        const progressBadge = document.getElementById('pg-header-progress');
        const helperText = document.getElementById('pg-helper-text');
        const countBadge = document.getElementById('pg-count-badge');
        const submitText = document.getElementById('pg-submit-text');
        const methodInput = document.getElementById('pg-form-method');
        const isEdit = methodInput && methodInput.value === 'PUT';

        if (isPart6) {
            if (countBadge) countBadge.textContent = '4 Questions per Text';
            if (helperText) helperText.textContent = 'Each Part 6 text contains four Text Completion questions. You may save your progress before all four questions are complete.';
            if (progressBadge) {
                progressBadge.classList.remove('hidden');
                progressBadge.textContent = `Progress: ${completeCount} / 4 Complete`;
                if (completeCount === 4) {
                    progressBadge.className = 'text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
                } else {
                    progressBadge.className = 'text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800';
                }
            }
            if (submitText) {
                if (completeCount === 4) {
                    submitText.textContent = isEdit ? 'Update Passage Group' : 'Save Passage Group';
                } else {
                    submitText.textContent = isEdit ? 'Update Draft Group' : 'Save Draft Group';
                }
            }
        } else {
            if (countBadge) {
                if (currentPassageSetType === 'single') countBadge.textContent = '2 to 4 Questions';
                else countBadge.textContent = '5 Questions';
            }
            if (helperText) helperText.textContent = 'Provide reading text, attached visual document, or both.';
            if (progressBadge) progressBadge.classList.add('hidden');
            if (submitText) submitText.textContent = isEdit ? 'Update Passage Group' : 'Save Passage Group';
        }
    }

    function getIntendedPart6GroupOrdinal(sectionId) {
        if (sectionId) {
            const secCard = document.getElementById(`section-card-${sectionId}`);
            if (secCard) {
                const existingPgCards = secCard.querySelectorAll('[id^="passage-group-card-"]');
                return existingPgCards.length + 1;
            }
        }
        return 1;
    }

    function getPart6BlankPlaceholder(groupOrdinal, slotIndex) {
        const g = parseInt(groupOrdinal) || 1;
        if (g >= 1 && g <= 4) {
            const blankNum = 131 + ((g - 1) * 4) + slotIndex;
            return `Optional authoring note for blank [${blankNum}]...`;
        }
        return `Optional authoring note for Overflow Slot ${slotIndex + 1}...`;
    }

    function openCreatePassageGroupModal(sectionId, partNumber, sectionTitle = '', groupOrdinal = null) {
        const modal = document.getElementById('create-passage-group-modal');
        const form = document.getElementById('create-passage-group-form');
        const methodInput = document.getElementById('pg-form-method');
        if (!modal) return;

        window._currentPassageGroupId = null;
        window._pgOriginalData = null;
        window._currentPassageSectionTitle = sectionTitle;

        if (form) {
            form.action = `{{ route('teacher.tests.create-passage-group', $test->id) }}`;
        }
        if (methodInput) methodInput.value = 'POST';

        const partNum = parseInt(partNumber) || 7;
        currentPassagePartNum = partNum;
        const isPart6 = partNum === 6;
        const resolvedOrdinal = groupOrdinal ? parseInt(groupOrdinal) : getIntendedPart6GroupOrdinal(sectionId);
        window._currentPassageGroupOrdinal = resolvedOrdinal;

        const secInput = document.getElementById('pg-section-id');
        const partInput = document.getElementById('pg-part-number');
        const titleLabel = document.getElementById('pg-target-section-title');
        const modalTitle = document.getElementById('pg-modal-title');
        const partBadge = document.getElementById('pg-part-badge');
        const submitText = document.getElementById('pg-submit-text');
        const setTypeWrap = document.getElementById('pg-set-type-wrapper');

        if (secInput) secInput.value = sectionId;
        if (partInput) partInput.value = partNum;
        if (titleLabel) titleLabel.textContent = `Target Section: ${sectionTitle || (isPart6 ? 'Part 6: Text Completion' : 'Part 7: Reading Comprehension')}`;
        if (modalTitle) modalTitle.textContent = isPart6 ? 'Create Text Completion Group' : 'Create Reading Passage Group';
        if (partBadge) partBadge.textContent = isPart6 ? 'READING • PART 6' : 'READING • PART 7';
        if (submitText) submitText.textContent = 'Save Passage Group';

        // Check for existing sessionStorage draft
        let draftData = null;
        try {
            const rawDraft = sessionStorage.getItem(getPassageGroupDraftStorageKey(sectionId, null));
            if (rawDraft) {
                draftData = JSON.parse(rawDraft);
            }
        } catch (e) {
            draftData = null;
        }

        const banner = document.getElementById('pg-draft-restored-banner');

        if (draftData) {
            // Restore from draft
            const groupTitleInput = document.getElementById('pg-group-title');
            if (groupTitleInput) groupTitleInput.value = draftData.title || '';

            if (isPart6) {
                if (setTypeWrap) setTypeWrap.classList.add('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Text / Passage Stimulus';
                if (stimulusSub) stimulusSub.textContent = 'This text is shared by all 4 questions in this group.';
                setPassageSetType('single');
                currentPassageChildQCount = 4;
            } else {
                if (setTypeWrap) setTypeWrap.classList.remove('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Reading Passage Documents';
                if (stimulusSub) stimulusSub.textContent = 'Provide reading text, attached visual document, or both.';
                currentPassageChildQCount = draftData.child_q_count || 2;
                setPassageSetType(draftData.passage_type || 'single');
            }

            const pList = draftData.passages || [];
            for (let d = 0; d < 3; d++) {
                const p = pList[d] || {};
                const idEl = document.getElementById(`pg-doc-${d}-id`);
                const titleEl = document.getElementById(`pg-doc-${d}-title`);
                const contentEl = document.getElementById(`pg-doc-${d}-content`) || (d === 0 ? document.getElementById('pg-passage-content') : null);
                const typeEl = document.getElementById(`pg-doc-${d}-type`);
                const mediaIdEl = document.getElementById(`pg-doc-${d}-media-asset-id`);
                const imgUrlEl = document.getElementById(`pg-doc-${d}-image-url`);

                if (idEl) idEl.value = p.id || '';
                if (titleEl) titleEl.value = p.title || '';
                if (contentEl) contentEl.value = p.content || '';
                if (typeEl) typeEl.value = p.document_type || 'article';
                if (mediaIdEl) mediaIdEl.value = p.media_asset_id || '';
                if (imgUrlEl) imgUrlEl.value = p.image_url || '';

                setPassageDocContentMode(d, p.content_mode || (p.image_url ? (p.content ? 'text_image' : 'image') : 'text'));

                if (p.image_url || p.media_asset_id) {
                    const prevCard = document.getElementById(`pg-doc-${d}-preview-image-card`);
                    const emptyCard = document.getElementById(`pg-doc-${d}-empty-image-card`);
                    const thumb = document.getElementById(`pg-doc-${d}-preview-image-thumb`);
                    const titleE = document.getElementById(`pg-doc-${d}-preview-image-title`);

                    if (thumb) thumb.src = p.preview_image_thumb || p.image_url || '';
                    if (titleE) titleE.textContent = p.preview_image_title || p.title || 'Document Image';
                    if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
                    if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
                } else {
                    removePassageDocMedia(d);
                }

                const modeWrap = document.getElementById(`pg-doc-${d}-mode-selector-wrap`);
                if (modeWrap) {
                    if (isPart6) modeWrap.classList.add('hidden');
                    else modeWrap.classList.remove('hidden');
                }
            }

            const qList = draftData.questions || [];
            for (let i = 0; i < 5; i++) {
                const q = qList[i] || {};
                const idEl = document.getElementById(`pg-q${i}-id`);
                const promptEl = document.getElementById(`pg-q${i}-prompt`);
                const explEl = document.getElementById(`pg-q${i}-explanation`);
                const promptLabel = document.getElementById(`pg-q${i}-prompt-label`);
                const promptReq = document.getElementById(`pg-q${i}-prompt-req`);
                const promptNote = document.getElementById(`pg-q${i}-prompt-note`);
                const promptHelp = document.getElementById(`pg-q${i}-prompt-help`);

                if (idEl) idEl.value = q.id || '';
                if (promptEl) promptEl.value = q.prompt || '';
                if (explEl) explEl.value = q.explanation || '';

                if (isPart6) {
                    if (promptLabel) promptLabel.textContent = 'Question Note / Blank Context';
                    if (promptReq) promptReq.classList.add('hidden');
                    if (promptNote) promptNote.textContent = 'Optional note';
                    if (promptHelp) promptHelp.textContent = 'The numbered blank is normally placed directly in the passage. Use this field only when additional authoring context is needed.';
                    if (promptEl) {
                        promptEl.placeholder = getPart6BlankPlaceholder(window._currentPassageGroupOrdinal, i);
                        promptEl.removeAttribute('required');
                    }
                } else {
                    if (promptLabel) promptLabel.textContent = 'QUESTION PROMPT / STEM';
                    if (promptReq) promptReq.classList.remove('hidden');
                    if (promptNote) promptNote.textContent = 'Required';
                    if (promptHelp) promptHelp.textContent = 'Specific reading comprehension question stem for this document.';
                    if (promptEl) {
                        promptEl.placeholder = 'e.g. What is suggested about the advertisement?';
                        promptEl.setAttribute('required', 'required');
                    }
                }

                const choices = q.choices || [];
                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`pg-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = choices[c] || '';
                }
                const correctIdx = (q.correct_choice !== undefined && q.correct_choice !== null && q.correct_choice !== '') ? q.correct_choice : null;
                for (let c = 0; c < 4; c++) {
                    const radio = document.getElementById(`pg-q${i}-correct-${c}`);
                    if (radio) radio.checked = (correctIdx !== null && String(correctIdx) === String(c));
                }
            }

            if (banner) banner.classList.remove('hidden');
        } else {
            // Clear group title
            const groupTitleInput = document.getElementById('pg-group-title');
            if (groupTitleInput) groupTitleInput.value = '';

            // Configure Part 6 vs Part 7
            if (isPart6) {
                if (setTypeWrap) setTypeWrap.classList.add('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Text / Passage Stimulus';
                if (stimulusSub) stimulusSub.textContent = 'This text is shared by all 4 questions in this group.';
                setPassageSetType('single');
                currentPassageChildQCount = 4;
            } else {
                if (setTypeWrap) setTypeWrap.classList.remove('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Reading Passage Documents';
                if (stimulusSub) stimulusSub.textContent = 'Provide reading text, attached visual document, or both.';
                currentPassageChildQCount = 2;
                setPassageSetType('single');
            }

            // Clear document panels
            for (let d = 0; d < 3; d++) {
                const idEl = document.getElementById(`pg-doc-${d}-id`);
                const titleEl = document.getElementById(`pg-doc-${d}-title`);
                const contentEl = document.getElementById(`pg-doc-${d}-content`) || (d === 0 ? document.getElementById('pg-passage-content') : null);
                const typeEl = document.getElementById(`pg-doc-${d}-type`);
                if (idEl) idEl.value = '';
                if (titleEl) titleEl.value = '';
                if (contentEl) contentEl.value = '';
                if (typeEl) typeEl.value = 'article';
                removePassageDocMedia(d);
                setPassageDocContentMode(d, 'text');

                const modeWrap = document.getElementById(`pg-doc-${d}-mode-selector-wrap`);
                if (modeWrap) {
                    if (isPart6) modeWrap.classList.add('hidden');
                    else modeWrap.classList.remove('hidden');
                }
            }

            // Configure questions prompt labels & requirements
            for (let i = 0; i < 5; i++) {
                const idEl = document.getElementById(`pg-q${i}-id`);
                const promptEl = document.getElementById(`pg-q${i}-prompt`);
                const explEl = document.getElementById(`pg-q${i}-explanation`);
                const promptLabel = document.getElementById(`pg-q${i}-prompt-label`);
                const promptReq = document.getElementById(`pg-q${i}-prompt-req`);
                const promptNote = document.getElementById(`pg-q${i}-prompt-note`);
                const promptHelp = document.getElementById(`pg-q${i}-prompt-help`);

                if (idEl) idEl.value = '';
                if (promptEl) promptEl.value = '';
                if (explEl) explEl.value = '';

                if (isPart6) {
                    if (promptLabel) promptLabel.textContent = 'Question Note / Blank Context';
                    if (promptReq) promptReq.classList.add('hidden');
                    if (promptNote) promptNote.textContent = 'Optional note';
                    if (promptHelp) promptHelp.textContent = 'The numbered blank is normally placed directly in the passage. Use this field only when additional authoring context is needed.';
                    if (promptEl) {
                        promptEl.placeholder = getPart6BlankPlaceholder(window._currentPassageGroupOrdinal, i);
                        promptEl.removeAttribute('required');
                    }
                } else {
                    if (promptLabel) promptLabel.textContent = 'QUESTION PROMPT / STEM';
                    if (promptReq) promptReq.classList.remove('hidden');
                    if (promptNote) promptNote.textContent = 'Required';
                    if (promptHelp) promptHelp.textContent = 'Specific reading comprehension question stem for this document.';
                    if (promptEl) {
                        promptEl.placeholder = 'e.g. What is suggested about the advertisement?';
                        promptEl.setAttribute('required', 'required');
                    }
                }

                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`pg-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = '';
                    const radio = document.getElementById(`pg-q${i}-correct-${c}`);
                    if (radio) radio.checked = false;
                }
            }

            if (banner) banner.classList.add('hidden');
        }

        updateChildQuestionsVisibility();
        updatePassageGroupProgressAndBadges();
        window._pgBaselineSnapshot = getPassageGroupCurrentSnapshot();

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function openEditPassageGroupModal(pgData, sectionId = '', sectionTitle = '') {
        const modal = document.getElementById('create-passage-group-modal');
        const form = document.getElementById('create-passage-group-form');
        const methodInput = document.getElementById('pg-form-method');
        if (!modal || !pgData) return;

        window._currentPassageGroupId = pgData.id;
        window._pgOriginalData = pgData;
        window._currentPassageSectionTitle = sectionTitle;

        const partNum = parseInt(pgData.part_number) || 7;
        currentPassagePartNum = partNum;
        const isPart6 = partNum === 6;
        const resolvedOrdinal = pgData.group_ordinal ? parseInt(pgData.group_ordinal) : 1;
        window._currentPassageGroupOrdinal = resolvedOrdinal;

        if (form) {
            form.action = `/teacher/assessments/{{ $test->id }}/passage-groups/${pgData.id}`;
        }
        if (methodInput) methodInput.value = 'PUT';

        const secInput = document.getElementById('pg-section-id');
        const partInput = document.getElementById('pg-part-number');
        const setTypeWrap = document.getElementById('pg-set-type-wrapper');
        const modalTitle = document.getElementById('pg-modal-title');
        const targetSecTitle = document.getElementById('pg-target-section-title');
        const partBadge = document.getElementById('pg-part-badge');
        const submitText = document.getElementById('pg-submit-text');

        if (secInput) secInput.value = sectionId;
        if (partInput) partInput.value = partNum;
        if (modalTitle) modalTitle.textContent = isPart6 ? 'Edit Text Completion Group' : 'Edit Reading Passage Group';
        if (targetSecTitle) targetSecTitle.textContent = `Target Section: ${sectionTitle || (isPart6 ? 'Part 6: Text Completion' : 'Part 7: Reading Comprehension')}`;
        if (partBadge) partBadge.textContent = isPart6 ? 'READING • PART 6' : 'READING • PART 7';
        if (submitText) submitText.textContent = 'Update Passage Group';

        // Check for existing sessionStorage draft for edit mode
        let draftData = null;
        try {
            const rawDraft = sessionStorage.getItem(getPassageGroupDraftStorageKey(sectionId, pgData.id));
            if (rawDraft) {
                draftData = JSON.parse(rawDraft);
            }
        } catch (e) {
            draftData = null;
        }

        const banner = document.getElementById('pg-draft-restored-banner');

        if (draftData) {
            // Restore from transient edit draft
            const titleInput = document.getElementById('pg-group-title');
            if (titleInput) titleInput.value = draftData.title || '';

            if (isPart6) {
                if (setTypeWrap) setTypeWrap.classList.add('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Text / Passage Stimulus';
                if (stimulusSub) stimulusSub.textContent = 'This text is shared by all 4 questions in this group.';
                setPassageSetType('single');
                currentPassageChildQCount = 4;
            } else {
                if (setTypeWrap) setTypeWrap.classList.remove('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Reading Passage Documents';
                if (stimulusSub) stimulusSub.textContent = 'Provide reading text, attached visual document, or both.';
                currentPassageChildQCount = draftData.child_q_count || 2;
                setPassageSetType(draftData.passage_type || 'single');
            }

            const pList = draftData.passages || [];
            for (let d = 0; d < 3; d++) {
                const p = pList[d] || {};
                const idEl = document.getElementById(`pg-doc-${d}-id`);
                const titleEl = document.getElementById(`pg-doc-${d}-title`);
                const contentEl = document.getElementById(`pg-doc-${d}-content`) || (d === 0 ? document.getElementById('pg-passage-content') : null);
                const typeEl = document.getElementById(`pg-doc-${d}-type`);
                const mediaIdEl = document.getElementById(`pg-doc-${d}-media-asset-id`);
                const imgUrlEl = document.getElementById(`pg-doc-${d}-image-url`);

                if (idEl) idEl.value = p.id || '';
                if (titleEl) titleEl.value = p.title || '';
                if (contentEl) contentEl.value = p.content || '';
                if (typeEl) typeEl.value = p.document_type || 'article';
                if (mediaIdEl) mediaIdEl.value = p.media_asset_id || '';
                if (imgUrlEl) imgUrlEl.value = p.image_url || '';

                setPassageDocContentMode(d, p.content_mode || (p.image_url ? (p.content ? 'text_image' : 'image') : 'text'));

                if (p.image_url || p.media_asset_id) {
                    const prevCard = document.getElementById(`pg-doc-${d}-preview-image-card`);
                    const emptyCard = document.getElementById(`pg-doc-${d}-empty-image-card`);
                    const thumb = document.getElementById(`pg-doc-${d}-preview-image-thumb`);
                    const titleE = document.getElementById(`pg-doc-${d}-preview-image-title`);

                    if (thumb) thumb.src = p.preview_image_thumb || p.image_url || '';
                    if (titleE) titleE.textContent = p.preview_image_title || p.title || 'Document Image';
                    if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
                    if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
                } else {
                    removePassageDocMedia(d);
                }

                const modeWrap = document.getElementById(`pg-doc-${d}-mode-selector-wrap`);
                if (modeWrap) {
                    if (isPart6) modeWrap.classList.add('hidden');
                    else modeWrap.classList.remove('hidden');
                }
            }

            const qList = draftData.questions || [];
            for (let i = 0; i < 5; i++) {
                const q = qList[i] || {};
                const idEl = document.getElementById(`pg-q${i}-id`);
                const promptEl = document.getElementById(`pg-q${i}-prompt`);
                const explEl = document.getElementById(`pg-q${i}-explanation`);
                const promptLabel = document.getElementById(`pg-q${i}-prompt-label`);
                const promptReq = document.getElementById(`pg-q${i}-prompt-req`);
                const promptNote = document.getElementById(`pg-q${i}-prompt-note`);
                const promptHelp = document.getElementById(`pg-q${i}-prompt-help`);

                if (idEl) idEl.value = q.id || '';
                if (promptEl) promptEl.value = q.prompt || '';
                if (explEl) explEl.value = q.explanation || '';

                if (isPart6) {
                    if (promptLabel) promptLabel.textContent = 'Question Note / Blank Context';
                    if (promptReq) promptReq.classList.add('hidden');
                    if (promptNote) promptNote.textContent = 'Optional note';
                    if (promptHelp) promptHelp.textContent = 'The numbered blank is normally placed directly in the passage. Use this field only when additional authoring context is needed.';
                    if (promptEl) {
                        promptEl.placeholder = getPart6BlankPlaceholder(window._currentPassageGroupOrdinal, i);
                        promptEl.removeAttribute('required');
                    }
                } else {
                    if (promptLabel) promptLabel.textContent = 'QUESTION PROMPT / STEM';
                    if (promptReq) promptReq.classList.remove('hidden');
                    if (promptNote) promptNote.textContent = 'Required';
                    if (promptHelp) promptHelp.textContent = 'Specific reading comprehension question stem for this document.';
                    if (promptEl) {
                        promptEl.placeholder = 'e.g. What is suggested about the advertisement?';
                        promptEl.setAttribute('required', 'required');
                    }
                }

                const choices = q.choices || [];
                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`pg-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = choices[c] || '';
                }
                const correctIdx = (q.correct_choice !== undefined && q.correct_choice !== null && q.correct_choice !== '') ? q.correct_choice : null;
                for (let c = 0; c < 4; c++) {
                    const radio = document.getElementById(`pg-q${i}-correct-${c}`);
                    if (radio) radio.checked = (correctIdx !== null && String(correctIdx) === String(c));
                }
            }

            if (banner) banner.classList.remove('hidden');
        } else {
            // Populate from saved database model data
            const titleInput = document.getElementById('pg-group-title');
            if (titleInput) titleInput.value = pgData.title || '';

            // Configure Part 6 vs Part 7
            if (isPart6) {
                if (setTypeWrap) setTypeWrap.classList.add('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Text / Passage Stimulus';
                if (stimulusSub) stimulusSub.textContent = 'This text is shared by all 4 questions in this group.';
                setPassageSetType('single');
                currentPassageChildQCount = 4;
            } else {
                if (setTypeWrap) setTypeWrap.classList.remove('hidden');
                const stimulusHeading = document.getElementById('pg-stimulus-heading');
                const stimulusSub = document.getElementById('pg-stimulus-subheading');
                if (stimulusHeading) stimulusHeading.textContent = 'Reading Passage Documents';
                if (stimulusSub) stimulusSub.textContent = 'Provide reading text, attached visual document, or both.';
                setPassageSetType(pgData.passage_type || 'single');
            }

            // Clear all document slots first
            for (let d = 0; d < 3; d++) {
                const idEl = document.getElementById(`pg-doc-${d}-id`);
                const titleEl = document.getElementById(`pg-doc-${d}-title`);
                const contentEl = document.getElementById(`pg-doc-${d}-content`) || (d === 0 ? document.getElementById('pg-passage-content') : null);
                const typeEl = document.getElementById(`pg-doc-${d}-type`);
                if (idEl) idEl.value = '';
                if (titleEl) titleEl.value = '';
                if (contentEl) contentEl.value = '';
                if (typeEl) typeEl.value = 'article';
                removePassageDocMedia(d);
                setPassageDocContentMode(d, 'text');

                const modeWrap = document.getElementById(`pg-doc-${d}-mode-selector-wrap`);
                if (modeWrap) {
                    if (isPart6) modeWrap.classList.add('hidden');
                    else modeWrap.classList.remove('hidden');
                }
            }

            // Populate passages
            const passages = pgData.passages || [];
            passages.forEach((p, d) => {
                if (d >= 3) return;
                const idEl = document.getElementById(`pg-doc-${d}-id`);
                const titleEl = document.getElementById(`pg-doc-${d}-title`);
                const contentEl = document.getElementById(`pg-doc-${d}-content`) || (d === 0 ? document.getElementById('pg-passage-content') : null);
                const typeEl = document.getElementById(`pg-doc-${d}-type`);
                const mediaIdEl = document.getElementById(`pg-doc-${d}-media-asset-id`);
                const imgUrlEl = document.getElementById(`pg-doc-${d}-image-url`);

                if (idEl) idEl.value = p.id || '';
                if (titleEl) titleEl.value = p.title || '';
                if (contentEl) contentEl.value = p.content || '';
                if (typeEl) typeEl.value = p.document_type || 'article';
                if (mediaIdEl) mediaIdEl.value = p.media_asset_id || '';
                if (imgUrlEl) imgUrlEl.value = p.image_url || '';

                const hasText = !!p.content && p.content.trim().length > 0;
                const hasImg = !!p.image_url || !!p.media_asset_id;

                let mode = 'text';
                if (hasImg && hasText) mode = 'text_image';
                else if (hasImg && !hasText) mode = 'image';

                setPassageDocContentMode(d, mode);

                if (hasImg) {
                    const prevCard = document.getElementById(`pg-doc-${d}-preview-image-card`);
                    const emptyCard = document.getElementById(`pg-doc-${d}-empty-image-card`);
                    const thumb = document.getElementById(`pg-doc-${d}-preview-image-thumb`);
                    const titleE = document.getElementById(`pg-doc-${d}-preview-image-title`);

                    if (thumb) thumb.src = p.image_url;
                    if (titleE) titleE.textContent = p.title || 'Document Image';
                    if (prevCard) { prevCard.classList.remove('hidden'); prevCard.style.display = 'flex'; }
                    if (emptyCard) { emptyCard.classList.add('hidden'); emptyCard.style.display = 'none'; }
                }
            });

            // Questions
            const qList = pgData.questions || [];
            if (!isPart6 && (pgData.passage_type || 'single') === 'single') {
                currentPassageChildQCount = Math.max(2, Math.min(4, qList.length));
            }

            for (let i = 0; i < 5; i++) {
                const q = qList[i] || null;
                const idEl = document.getElementById(`pg-q${i}-id`);
                const promptEl = document.getElementById(`pg-q${i}-prompt`);
                const explEl = document.getElementById(`pg-q${i}-explanation`);
                const promptLabel = document.getElementById(`pg-q${i}-prompt-label`);
                const promptReq = document.getElementById(`pg-q${i}-prompt-req`);
                const promptNote = document.getElementById(`pg-q${i}-prompt-note`);
                const promptHelp = document.getElementById(`pg-q${i}-prompt-help`);

                if (idEl) idEl.value = q ? q.id : '';
                if (promptEl) promptEl.value = q ? (q.prompt || '') : '';
                if (explEl) explEl.value = q ? (q.explanation || '') : '';

                if (isPart6) {
                    if (promptLabel) promptLabel.textContent = 'Question Note / Blank Context';
                    if (promptReq) promptReq.classList.add('hidden');
                    if (promptNote) promptNote.textContent = 'Optional note';
                    if (promptHelp) promptHelp.textContent = 'The numbered blank is normally placed directly in the passage. Use this field only when additional authoring context is needed.';
                    if (promptEl) {
                        promptEl.placeholder = getPart6BlankPlaceholder(window._currentPassageGroupOrdinal, i);
                        promptEl.removeAttribute('required');
                    }
                } else {
                    if (promptLabel) promptLabel.textContent = 'QUESTION PROMPT / STEM';
                    if (promptReq) promptReq.classList.remove('hidden');
                    if (promptNote) promptNote.textContent = 'Required';
                    if (promptHelp) promptHelp.textContent = 'Specific reading comprehension question stem for this document.';
                    if (promptEl) {
                        promptEl.placeholder = 'e.g. What is suggested about the advertisement?';
                        promptEl.setAttribute('required', 'required');
                    }
                }

                const choices = q ? (q.choices || []) : [];
                const correctIdx = (q && q.correct_choice !== undefined && q.correct_choice !== null && q.correct_choice !== '') ? q.correct_choice : null;

                for (let c = 0; c < 4; c++) {
                    const choiceEl = document.getElementById(`pg-q${i}-choice-${c}`);
                    if (choiceEl) choiceEl.value = choices[c] || '';
                    const radio = document.getElementById(`pg-q${i}-correct-${c}`);
                    if (radio) radio.checked = (correctIdx !== null && String(correctIdx) === String(c));
                }
            }

            if (banner) banner.classList.add('hidden');
        }

        updateChildQuestionsVisibility();
        updatePassageGroupProgressAndBadges();
        window._pgBaselineSnapshot = getPassageGroupCurrentSnapshot();

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function closeCreatePassageGroupModal(e = null) {
        if (e && e.target === document.getElementById('create-passage-group-modal')) {
            // Backdrop click: do not dismiss modal to protect unsaved work
            return;
        }
        attemptClosePassageGroupModal();
    }

    function openPassageGroupPreviewModal(pgData) {
        const modal = document.getElementById('passage-group-preview-modal');
        if (!modal || !pgData) return;

        const titleEl = document.getElementById('pg-prev-modal-title');
        const typeBadge = document.getElementById('pg-prev-badge-type');
        const docsBadge = document.getElementById('pg-prev-badge-docs');
        const tabsBar = document.getElementById('pg-prev-tabs-bar');
        const container = document.getElementById('pg-prev-documents-container');

        const partNum = parseInt(pgData.part_number) || 7;
        const passType = pgData.passage_type || 'single';
        const passages = pgData.passages || [];

        if (titleEl) titleEl.textContent = pgData.title || (`Part ${partNum} ${passType.charAt(0).toUpperCase() + passType.slice(1)} Passage Group`);
        if (typeBadge) typeBadge.textContent = `${passType.charAt(0).toUpperCase() + passType.slice(1)} Passage`;
        if (docsBadge) docsBadge.textContent = `${passages.length} Document(s)`;

        if (container) container.innerHTML = '';
        if (tabsBar) {
            tabsBar.innerHTML = '';
            if (passages.length > 1) {
                tabsBar.classList.remove('hidden');
            } else {
                tabsBar.classList.add('hidden');
            }
        }

        passages.forEach((pass, pIdx) => {
            // Tab button
            if (tabsBar && passages.length > 1) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.id = `pg-preview-tab-btn-${pIdx}`;
                btn.className = `px-3 py-1.5 rounded-lg text-xs font-black border transition-all ${pIdx === 0 ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-indigo-300'}`;
                btn.textContent = pass.title || `Document ${pIdx + 1}`;
                btn.onclick = () => switchPassageGroupPreviewTab(pIdx, passages.length);
                tabsBar.appendChild(btn);
            }

            // Document card
            if (container) {
                const card = document.createElement('div');
                card.id = `pg-preview-doc-${pIdx}`;
                card.className = `space-y-3 p-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl ${pIdx > 0 && passages.length > 1 ? 'hidden' : ''}`;

                let imgHtml = '';
                if (pass.image_url) {
                    imgHtml = `
                        <div class="text-center mb-3">
                            <img src="${pass.image_url}" alt="${pass.title || 'Document Image'}" class="max-w-full rounded-xl mx-auto border border-slate-200 dark:border-slate-800 shadow-sm object-contain" style="max-height: 480px;">
                        </div>
                    `;
                }

                let textHtml = '';
                if (pass.content) {
                    textHtml = `
                        <div class="prose dark:prose-invert max-w-none text-xs sm:text-sm whitespace-pre-line leading-relaxed text-slate-800 dark:text-slate-200 select-text">
                            ${pass.content.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}
                        </div>
                    `;
                }

                card.innerHTML = `
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2 mb-2">
                        <div class="font-extrabold text-xs text-indigo-700 dark:text-indigo-400">
                            ${pass.title || ('Document ' + (pIdx + 1))}
                        </div>
                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            ${(pass.document_type || 'article').toUpperCase()}
                        </span>
                    </div>
                    ${imgHtml}
                    ${textHtml}
                `;
                container.appendChild(card);
            }
        });

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function switchPassageGroupPreviewTab(activeIdx, totalDocs) {
        for (let i = 0; i < totalDocs; i++) {
            const btn = document.getElementById(`pg-preview-tab-btn-${i}`);
            const doc = document.getElementById(`pg-preview-doc-${i}`);
            if (i === activeIdx) {
                if (btn) btn.className = 'px-3 py-1.5 rounded-lg text-xs font-black border bg-indigo-600 text-white border-indigo-600 shadow-sm transition-all';
                if (doc) doc.classList.remove('hidden');
            } else {
                if (btn) btn.className = 'px-3 py-1.5 rounded-lg text-xs font-black border bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-indigo-300 transition-all';
                if (doc) doc.classList.add('hidden');
            }
        }
    }

    function closePassageGroupPreviewModal(e = null) {
        if (!e || e.target === document.getElementById('passage-group-preview-modal')) {
            const modal = document.getElementById('passage-group-preview-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }
        }
    }

    function removeAudioGroupAttachedMedia() {
        const mediaIdInput = document.getElementById('ag-media-asset-id');
        const audioInput = document.getElementById('ag-audio-url');
        if (mediaIdInput) mediaIdInput.value = '';
        if (audioInput) audioInput.value = '';
        const prevCard = document.getElementById('ag-preview-audio-card');
        const emptyCard = document.getElementById('ag-empty-audio-card');
        const player = document.getElementById('ag-preview-audio-player');
        if (player) { player.pause(); player.src = ''; }
        if (prevCard) { prevCard.classList.add('hidden'); prevCard.style.display = 'none'; }
        if (emptyCard) { emptyCard.classList.remove('hidden'); emptyCard.style.display = 'flex'; }
        updateAudioGroupAutoDifficulty();
        triggerAudioGroupDraftSave();
    }

    function updateAudioGroupAutoDifficulty() {
        const hasAudio = !!document.getElementById('ag-media-asset-id')?.value || !!document.getElementById('ag-audio-url')?.value;
        let completeCount = 0;

        for (let i = 0; i < 3; i++) {
            const prompt = (document.getElementById(`ag-q${i}-prompt`)?.value || '').trim();
            const choices = Array.from(document.querySelectorAll(`input[name="questions[${i}][choices][]"]`))
                .map(input => input.value.trim())
                .filter(v => v.length > 0);
            const radioChecked = Array.from(document.querySelectorAll(`input[id^="ag-q${i}-correct-"]`)).some(r => r.checked);

            const isSlotComplete = prompt.length > 0 && choices.length === 4 && radioChecked;
            if (isSlotComplete) {
                completeCount++;
            }

            // Update slot status badge
            const statusBadge = document.getElementById(`ag-q${i}-status-badge`);
            if (statusBadge) {
                if (isSlotComplete) {
                    statusBadge.className = 'text-[11px] font-bold px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700';
                    statusBadge.textContent = '✓ Complete';
                } else {
                    statusBadge.className = 'text-[11px] font-bold px-2.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700';
                    statusBadge.textContent = '○ Incomplete';
                }
            }

            // Auto Difficulty badge
            const badge = document.getElementById(`ag-q${i}-diff-badge`);
            const dot = document.getElementById(`ag-q${i}-diff-dot`);
            const text = document.getElementById(`ag-q${i}-diff-text`);

            if (!badge || !text || !dot) continue;

            if (!hasAudio && choices.length === 0 && !prompt) {
                badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700';
                dot.className = 'w-1.5 h-1.5 rounded-full bg-slate-500 dark:bg-slate-400';
                text.textContent = 'Waiting for input';
            } else if (hasAudio && choices.length >= 4 && prompt.length > 5) {
                let totalWords = choices.reduce((acc, c) => acc + c.split(/\s+/).filter(Boolean).length, 0);
                let avg = choices.length > 0 ? totalWords / choices.length : 0;
                let level = (avg >= 7 || prompt.split(/\s+/).length >= 15) ? 'Hard' : ((avg <= 4 && prompt.split(/\s+/).length <= 8) ? 'Easy' : 'Medium');

                if (level === 'Easy') {
                    badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-200 dark:border-emerald-700';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-600';
                } else if (level === 'Hard') {
                    badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-300 dark:bg-rose-950/50 dark:text-rose-200 dark:border-rose-800';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-rose-600';
                } else {
                    badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-200 dark:border-amber-800';
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-amber-600';
                }
                text.textContent = `Final — ${level}`;
            } else {
                badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-200 dark:border-amber-800';
                dot.className = 'w-1.5 h-1.5 rounded-full bg-amber-600';
                text.textContent = 'Provisional — Medium';
            }
        }

        // Header progress badge
        const headerProg = document.getElementById('ag-header-progress');
        if (headerProg) {
            headerProg.textContent = `Progress: ${completeCount} / 3 Complete`;
            if (completeCount === 3) {
                headerProg.className = 'text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700';
            } else {
                headerProg.className = 'text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700';
            }
        }

        // Dynamic Submit button text
        const submitBtnText = document.getElementById('ag-submit-text');
        if (submitBtnText) {
            submitBtnText.textContent = (completeCount === 3) ? 'Save Audio Group' : 'Save Draft Group';
        }
    }

    function validateCreateAudioGroupForm(form) {
        const mediaId = form.querySelector('#ag-media-asset-id')?.value;
        const audioUrl = form.querySelector('#ag-audio-url')?.value;
        if (!mediaId && !audioUrl) {
            alert('⚠️ Please attach a shared audio file before saving this audio question group.');
            return false;
        }
        window._agSubmitting = true;
        const secId = form.querySelector('#ag-section-id')?.value;
        if (secId) {
            try {
                sessionStorage.removeItem(getAudioGroupDraftStorageKey(secId));
            } catch (e) {}
        }
        return true;
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

    let currentSectionMediaSource = 'my';
    let currentSectionMediaType = 'all';

    function setSectionMediaSource(source, e = null) {
        currentSectionMediaSource = source;
        const myBtn = document.getElementById('asm-source-my-btn');
        const instBtn = document.getElementById('asm-source-inst-btn');
        if (myBtn && instBtn) {
            if (source === 'my') {
                myBtn.className = 'px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all';
                myBtn.setAttribute('aria-selected', 'true');
                instBtn.className = 'px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all';
                instBtn.setAttribute('aria-selected', 'false');
            } else {
                instBtn.className = 'px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all';
                instBtn.setAttribute('aria-selected', 'true');
                myBtn.className = 'px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all';
                myBtn.setAttribute('aria-selected', 'false');
            }
        }
        fetchSectionMediaLibrary(currentSectionMediaType);
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
        clearSectionMediaFileSelection();
        const captionInput = document.getElementById('asm-caption');
        if (captionInput) captionInput.value = '';
        const orderInput = document.getElementById('asm-order');
        if (orderInput) orderInput.value = '';
        const titleInput = document.getElementById('asm-upload-title');
        if (titleInput) titleInput.value = '';
        hideAsmUploadFeedback();
        switchAsmMode('library');

        const modal = document.getElementById('attach-section-media-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        currentSectionMediaSource = 'my';
        const myBtn = document.getElementById('asm-source-my-btn');
        const instBtn = document.getElementById('asm-source-inst-btn');
        if (myBtn && instBtn) {
            myBtn.className = 'px-3 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-sm transition-all';
            myBtn.setAttribute('aria-selected', 'true');
            instBtn.className = 'px-3 py-1 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all';
            instBtn.setAttribute('aria-selected', 'false');
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
            clearSectionMediaFileSelection();
        }
    }

    function switchAsmMode(mode) {
        const tabLib = document.getElementById('asm-tab-library');
        const tabUpload = document.getElementById('asm-tab-upload');
        const uploadPanel = document.getElementById('asm-upload-panel');
        const libraryPanel = document.getElementById('asm-library-panel');

        if (mode === 'upload') {
            if (tabUpload) tabUpload.className = 'flex-1 py-2 px-3 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5';
            if (tabLib) tabLib.className = 'flex-1 py-2 px-3 bg-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5';
            if (uploadPanel) {
                uploadPanel.classList.remove('hidden');
                uploadPanel.style.display = 'block';
            }
            if (libraryPanel) {
                libraryPanel.classList.add('hidden');
                libraryPanel.style.display = 'none';
            }
        } else {
            if (tabLib) tabLib.className = 'flex-1 py-2 px-3 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5';
            if (tabUpload) tabUpload.className = 'flex-1 py-2 px-3 bg-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5';
            if (libraryPanel) {
                libraryPanel.classList.remove('hidden');
                libraryPanel.style.display = 'flex';
            }
            if (uploadPanel) {
                uploadPanel.classList.add('hidden');
                uploadPanel.style.display = 'none';
            }
        }
    }

    function handleSectionMediaFileSelect(input) {
        const file = input?.files?.[0];
        const emptyState = document.getElementById('asm-empty-upload-state');
        const selectedState = document.getElementById('asm-selected-upload-state');
        const filenameEl = document.getElementById('asm-selected-filename');
        const filesizeEl = document.getElementById('asm-selected-filesize');
        const typeBadgeEl = document.getElementById('asm-selected-type-badge');
        const uploadBtn = document.getElementById('asm-upload-btn');
        hideAsmUploadFeedback();

        if (!file) {
            clearSectionMediaFileSelection();
            return;
        }

        const sizeFormatted = file.size > 1048576
            ? (file.size / 1048576).toFixed(1) + ' MB'
            : (file.size / 1024).toFixed(1) + ' KB';

        let typeName = 'FILE';
        const mime = file.type || '';
        const nameLower = file.name.toLowerCase();
        if (mime.startsWith('image/') || /\.(jpg|jpeg|png|webp)$/i.test(nameLower)) {
            typeName = 'IMAGE';
        } else if (mime.startsWith('audio/') || /\.(mp3|m4a|wav)$/i.test(nameLower)) {
            typeName = 'AUDIO';
        } else if (mime === 'application/pdf' || /\.pdf$/i.test(nameLower)) {
            typeName = 'PDF';
        }

        if (filenameEl) filenameEl.textContent = file.name;
        if (filesizeEl) filesizeEl.textContent = sizeFormatted;
        if (typeBadgeEl) typeBadgeEl.textContent = typeName;

        if (emptyState) {
            emptyState.classList.add('hidden');
            emptyState.style.display = 'none';
        }
        if (selectedState) {
            selectedState.classList.remove('hidden');
            selectedState.style.display = 'block';
        }

        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.removeAttribute('aria-disabled');
            uploadBtn.className = 'px-4 py-2 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-extrabold rounded-xl shadow-md shadow-emerald-500/20 cursor-pointer transition-all inline-flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1';
        }
    }

    function clearSectionMediaFileSelection() {
        const input = document.getElementById('asm-upload-file');
        if (input) input.value = '';

        const emptyState = document.getElementById('asm-empty-upload-state');
        const selectedState = document.getElementById('asm-selected-upload-state');
        const uploadBtn = document.getElementById('asm-upload-btn');
        hideAsmUploadFeedback();

        if (emptyState) {
            emptyState.classList.remove('hidden');
            emptyState.style.display = 'flex';
        }
        if (selectedState) {
            selectedState.classList.add('hidden');
            selectedState.style.display = 'none';
        }

        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.setAttribute('aria-disabled', 'true');
            uploadBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span>Upload &amp; Select Asset</span>
            `;
            uploadBtn.className = 'px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-extrabold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all inline-flex items-center gap-1.5';
        }
    }

    function handleSectionMediaDragOver(e) {
        e.preventDefault();
        const dropzone = document.getElementById('asm-dropzone');
        if (dropzone) {
            dropzone.classList.add('border-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
        }
    }

    function handleSectionMediaDragLeave(e) {
        e.preventDefault();
        const dropzone = document.getElementById('asm-dropzone');
        if (dropzone) {
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
        }
    }

    function handleSectionMediaDrop(e) {
        e.preventDefault();
        const dropzone = document.getElementById('asm-dropzone');
        if (dropzone) {
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/40', 'dark:bg-indigo-950/20');
        }
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const input = document.getElementById('asm-upload-file');
            if (input) {
                input.files = e.dataTransfer.files;
                handleSectionMediaFileSelect(input);
            }
        }
    }

    function showAsmUploadFeedback(message, isSuccess = false) {
        const el = document.getElementById('asm-upload-feedback');
        if (el) {
            el.classList.remove('hidden');
            el.style.display = 'block';
            el.className = isSuccess
                ? 'p-2.5 rounded-xl text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
                : 'p-2.5 rounded-xl text-xs font-bold bg-rose-50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300';
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
            showAsmUploadFeedback('⚠️ Please select a file to upload first.', false);
            return;
        }

        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) {
            showAsmUploadFeedback('⚠️ File size exceeds the 10 MB limit.', false);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        if (titleInput && titleInput.value.trim()) {
            formData.append('title', titleInput.value.trim());
        }
        formData.append('_token', '{{ csrf_token() }}');

        uploadBtn.disabled = true;
        uploadBtn.setAttribute('aria-disabled', 'true');
        uploadBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Uploading...</span>
        `;
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
            const assetData = data.asset || data;
            const assetId = assetData.id || data.id;
            const item = {
                id: assetId,
                title: assetData.title || assetData.filename || assetData.original_name,
                name: assetData.filename || assetData.original_name,
                type: assetData.type,
                size: assetData.size,
                url: assetData.url || (assetId ? `/media/${assetId}/preview` : '')
            };
            sectionMediaLibrary.unshift(item);

            renderSectionMediaGrid(sectionMediaLibrary);
            const icon = item.type === 'audio' ? '🎵' : (item.type === 'image' ? '🖼️' : (item.type === 'pdf' ? '📄' : (item.type === 'passage' ? '📖' : '📎')));
            selectSectionMediaItem(item.id, item.title, item.type, icon);

            clearSectionMediaFileSelection();
            if (titleInput) titleInput.value = '';
            switchAsmMode('library');
            showAsmUploadFeedback(`✅ "${item.title}" uploaded & selected!`, true);
        })
        .catch(err => {
            showAsmUploadFeedback(`⚠️ ${err.message}`, false);
        })
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.removeAttribute('aria-disabled');
            uploadBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span>Upload &amp; Select Asset</span>
            `;
        });
    }

    function fetchSectionMediaLibrary(preferredType = 'all') {
        currentSectionMediaType = preferredType || 'all';
        const container = document.getElementById('asm-media-list-container');
        if (!container) return;
        container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">Loading media library...</div>';

        fetch(`/admin/media/list?source=${currentSectionMediaSource}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    sectionMediaLibrary = data.data;
                    filterSectionMediaModal(currentSectionMediaType);
                } else {
                    container.innerHTML = '<div class="col-span-full text-center text-rose-600 dark:text-rose-400 text-xs py-8">Failed to load media library.</div>';
                }
            })
            .catch(() => {
                container.innerHTML = '<div class="col-span-full text-center text-rose-600 dark:text-rose-400 text-xs py-8">Error communicating with media server.</div>';
            });
    }

    function filterSectionMediaModal(type, e = null) {
        currentSectionMediaType = type;
        const buttons = document.querySelectorAll('.asm-filter-btn');
        buttons.forEach(btn => {
            btn.className = 'asm-filter-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold transition-colors';
        });

        if (e && e.target && e.target.classList.contains('asm-filter-btn')) {
            e.target.className = 'asm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm';
        } else {
            buttons.forEach(btn => {
                const text = btn.textContent.toLowerCase();
                if ((type === 'all' && text.includes('all')) ||
                    (type === 'image' && text.includes('image')) ||
                    (type === 'audio' && text.includes('audio')) ||
                    (type === 'passage' && text.includes('passage')) ||
                    (type === 'pdf' && text.includes('pdf'))) {
                    btn.className = 'asm-filter-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm';
                }
            });
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
        if (currentSectionMediaType !== 'all') {
            filtered = filtered.filter(item => item.type === currentSectionMediaType);
        }
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderSectionMediaGrid(filtered);
    }

    function renderSectionMediaGrid(items) {
        const container = document.getElementById('asm-media-list-container');
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="col-span-full text-center text-slate-500 dark:text-slate-400 text-xs py-8">No media assets found in library.</div>';
            return;
        }

        container.innerHTML = '';
        items.forEach(media => {
            const card = document.createElement('div');
            card.className = 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-700 transition-all flex flex-col justify-between gap-2.5';

            let typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>';
            let iconText = '📎';
            if (media.type === 'audio') {
                typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>';
                iconText = '🎵';
            } else if (media.type === 'image') {
                typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
                iconText = '🖼️';
            } else if (media.type === 'pdf') {
                typeIconSvg = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                iconText = '📄';
            } else if (media.type === 'passage') {
                iconText = '📖';
            }

            card.innerHTML = `
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/40 inline-flex items-center gap-1">
                            ${typeIconSvg}
                            <span>${media.type}</span>
                        </span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">${media.size || ''}</span>
                    </div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white truncate" title="${media.title || media.name}">
                        ${media.title || media.name}
                    </div>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="previewAssetModal('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded-lg text-[11px] font-bold">Preview</button>
                    <button type="button" onclick="selectSectionMediaItem('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${iconText}')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[11px] font-bold shadow-sm">Select</button>
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
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.removeAttribute('aria-disabled');
            submitBtn.className = 'px-5 py-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 cursor-pointer transition-all';
        }
    }

    function clearSelectedSectionMedia() {
        document.getElementById('asm-media-asset-id').value = '';
        const prev = document.getElementById('asm-selected-preview');
        if (prev) {
            prev.classList.add('hidden');
            prev.style.display = 'none';
        }
        const submitBtn = document.getElementById('asm-submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.className = 'px-5 py-2 bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 cursor-not-allowed transition-all';
        }
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
            const globalDialog = document.getElementById('iap-global-dialog');
            if (globalDialog && globalDialog.open) {
                return;
            }
            const qMediaModal = document.getElementById('question-media-picker-modal');
            if (qMediaModal && !qMediaModal.classList.contains('hidden') && qMediaModal.style.display !== 'none') {
                closeQuestionMediaPicker();
                return;
            }
            const authoredQModal = document.getElementById('create-authored-question-modal');
            if (authoredQModal && !authoredQModal.classList.contains('hidden') && authoredQModal.style.display !== 'none') {
                attemptCloseAuthoredQuestionModal();
                return;
            }
            const audioGroupModal = document.getElementById('create-audio-group-modal');
            if (audioGroupModal && !audioGroupModal.classList.contains('hidden') && audioGroupModal.style.display !== 'none') {
                attemptCloseAudioGroupModal();
                return;
            }
            const passageGroupModal = document.getElementById('create-passage-group-modal');
            if (passageGroupModal && !passageGroupModal.classList.contains('hidden') && passageGroupModal.style.display !== 'none') {
                attemptClosePassageGroupModal();
                return;
            }
            closeResubmitModal();
            closeAttachMasterModal();
            closeAddSectionModal();
            closeEditSectionModal();
            closeAttachSectionMediaModal();
            closeAssetPreviewModal();
            closeTeacherRequestRevisionModal();
            closeQuestionMediaPicker();
            closeCreatePassageGroupModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const targetSectionId = urlParams.get('section') || @json(session('expanded_section_id') ?? request('section'));
        const focusParam = urlParams.get('focus') || @json(request('focus'));
        const hash = window.location.hash;

        let autoExpandId = targetSectionId;
        if (!autoExpandId && hash) {
            if (hash.startsWith('#section-card-')) {
                autoExpandId = hash.replace('#section-card-', '');
            } else if (hash.startsWith('#section-body-')) {
                autoExpandId = hash.replace('#section-body-', '');
            } else if (hash.startsWith('#section-')) {
                autoExpandId = hash.replace('#section-', '');
            }
        }

        let focusTargetId = focusParam;
        if (!focusTargetId && hash && !hash.startsWith('#section')) {
            focusTargetId = hash.replace('#', '');
        }

        if (autoExpandId) {
            const body = document.getElementById(`section-body-${autoExpandId}`);
            if (body) {
                toggleSectionCollapse(autoExpandId, true);
            }
        }

        if (focusTargetId) {
            const cleanId = focusTargetId.replace(/^#/, '');
            let focusEl = document.getElementById(cleanId);
            if (!focusEl && !cleanId.startsWith('question-card-') && !cleanId.startsWith('audio-group-card-')) {
                focusEl = document.getElementById(`question-card-${cleanId}`) || document.getElementById(`audio-group-card-${cleanId}`);
            }
            if (focusEl) {
                const parentSectionCard = focusEl.closest('.section-card');
                if (parentSectionCard) {
                    const secId = parentSectionCard.getAttribute('data-section-id');
                    if (secId) toggleSectionCollapse(secId, true);
                }
                const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                setTimeout(() => {
                    focusEl.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
                    focusEl.classList.add('ring-2', 'ring-indigo-500', 'transition-all');
                    setTimeout(() => {
                        focusEl.classList.remove('ring-2', 'ring-indigo-500');
                    }, 2000);
                }, 120);
                return;
            }
        }

        if (autoExpandId) {
            const card = document.getElementById(`section-card-${autoExpandId}`);
            if (card) {
                const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                setTimeout(() => {
                    card.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'nearest' });
                }, 100);
            }
        }

        // Reopen Passage Group Modal on Validation Errors and Restore Old Input
        @if(old('test_section_id') && (old('part_number') == 6 || old('part_number') == 7))
        (function() {
            const oldSecId = @json(old('test_section_id'));
            const oldPartNum = parseInt(@json(old('part_number', 7))) || 7;
            const oldSetType = @json(old('passage_type', 'single'));
            const oldTitle = @json(old('title', ''));
            const oldPassages = @json(old('passages', []));
            const oldQuestions = @json(old('questions', []));

            toggleSectionCollapse(oldSecId, true);
            openCreatePassageGroupModal(oldSecId, oldPartNum);
            setPassageSetType(oldSetType);

            const titleInput = document.getElementById('pg-group-title');
            if (titleInput && oldTitle) titleInput.value = oldTitle;

            if (Array.isArray(oldPassages)) {
                oldPassages.forEach((p, d) => {
                    if (d >= 3 || !p) return;
                    const titleEl = document.getElementById(`pg-doc-${d}-title`);
                    const contentEl = document.getElementById(`pg-doc-${d}-content`) || document.getElementById('pg-passage-content');
                    const typeEl = document.getElementById(`pg-doc-${d}-type`);
                    const mediaIdEl = document.getElementById(`pg-doc-${d}-media-asset-id`);
                    const imgUrlEl = document.getElementById(`pg-doc-${d}-image-url`);

                    if (titleEl && p.title !== undefined) titleEl.value = p.title;
                    if (contentEl && p.content !== undefined) contentEl.value = p.content;
                    if (typeEl && p.document_type !== undefined) typeEl.value = p.document_type;
                    if (mediaIdEl && p.media_asset_id !== undefined) mediaIdEl.value = p.media_asset_id;
                    if (imgUrlEl && p.image_url !== undefined) imgUrlEl.value = p.image_url;

                    if (p.content_mode) {
                        setPassageDocContentMode(d, p.content_mode);
                    } else if (p.image_url || p.media_asset_id) {
                        setPassageDocContentMode(d, p.content ? 'text_image' : 'image');
                    }
                });
            }

            if (Array.isArray(oldQuestions)) {
                if (oldPartNum === 7 && oldSetType === 'single') {
                    currentPassageChildQCount = Math.max(2, Math.min(4, oldQuestions.length));
                }
                oldQuestions.forEach((q, i) => {
                    if (i >= 5 || !q) return;
                    const promptEl = document.getElementById(`pg-q${i}-prompt`);
                    const explEl = document.getElementById(`pg-q${i}-explanation`);
                    if (promptEl && q.prompt !== undefined) promptEl.value = q.prompt;
                    if (explEl && q.explanation !== undefined) explEl.value = q.explanation;

                    if (Array.isArray(q.choices)) {
                        q.choices.forEach((choiceText, c) => {
                            const choiceEl = document.getElementById(`pg-q${i}-choice-${c}`);
                            if (choiceEl) choiceEl.value = choiceText || '';
                        });
                    }

                    if (q.correct_choice !== undefined) {
                        const radio = document.getElementById(`pg-q${i}-correct-${q.correct_choice}`);
                        if (radio) radio.checked = true;
                    }
                });
                updateChildQuestionsVisibility();
            }
        })();
        @endif

        const pgForm = document.getElementById('create-passage-group-form');
        if (pgForm) {
            pgForm.addEventListener('input', function() {
                updatePassageGroupProgressAndBadges();
                triggerPassageGroupDraftSave();
            });
            pgForm.addEventListener('change', function() {
                updatePassageGroupProgressAndBadges();
                triggerPassageGroupDraftSave();
            });
            pgForm.addEventListener('submit', function() {
                window._pgSubmitting = true;
                const secId = document.getElementById('pg-section-id')?.value;
                if (secId) {
                    try {
                        sessionStorage.removeItem(getPassageGroupDraftStorageKey(secId, window._currentPassageGroupId));
                    } catch (e) {}
                }
                syncPassageFormFieldsDisabledState();
            });
        }

        const authoredQForm = document.getElementById('create-authored-question-form');
        if (authoredQForm) {
            authoredQForm.addEventListener('input', function() {
                triggerAuthoredQuestionDraftSave();
            });
            authoredQForm.addEventListener('change', function() {
                triggerAuthoredQuestionDraftSave();
            });
            authoredQForm.addEventListener('submit', function() {
                window._authoredQSubmitting = true;
                const secId = document.getElementById('q-section-id')?.value;
                const partNum = document.getElementById('q-part-number')?.value;
                if (secId) {
                    try {
                        sessionStorage.removeItem(getAuthoredQuestionDraftStorageKey(secId, partNum));
                    } catch (e) {}
                }
            });
        }

        window.addEventListener('beforeunload', function(e) {
            if (window._agSubmitting || window._pgSubmitting || window._authoredQSubmitting) return;
            const authoredQModal = document.getElementById('create-authored-question-modal');
            if (authoredQModal && !authoredQModal.classList.contains('hidden') && authoredQModal.style.display !== 'none' && isAuthoredQuestionFormDirty()) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
            const agModal = document.getElementById('create-audio-group-modal');
            if (agModal && !agModal.classList.contains('hidden') && agModal.style.display !== 'none' && isAudioGroupFormDirty()) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
            const pgModal = document.getElementById('create-passage-group-modal');
            if (pgModal && !pgModal.classList.contains('hidden') && pgModal.style.display !== 'none' && isPassageGroupFormDirty()) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        const agForm = document.getElementById('create-audio-group-form');
        if (agForm) {
            agForm.addEventListener('input', function() {
                updateAudioGroupAutoDifficulty();
                triggerAudioGroupDraftSave();
            });
            agForm.addEventListener('change', function() {
                updateAudioGroupAutoDifficulty();
                triggerAudioGroupDraftSave();
            });
        }
    });
</script>
@endsection
