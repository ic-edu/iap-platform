@extends('layouts.admin')

@section('title', 'Full Question Revision Editor — iC.edu Platform')

@push('styles')
<style>
.fre-container { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; }
.fre-banner {
    background: linear-gradient(135deg, #ffffff 0%, #f5f7ff 50%, #eef2ff 100%);
    border: 2px solid #818cf8;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 12px 30px -8px rgba(99,102,241,0.12);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .fre-banner, html[data-theme="dark"] .fre-banner {
    background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
    border: 2px solid #6366f1;
    box-shadow: 0 16px 36px -10px rgba(99,102,241,0.3);
}
.fre-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 2px 6px rgba(15,23,42,0.03);
    transition: background 0.2s ease, border-color 0.2s ease;
}
html.dark .fre-panel, html[data-theme="dark"] .fre-panel {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: none;
}
.fre-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
    transition: all .2s ease;
}
html.dark .fre-section, html[data-theme="dark"] .fre-section {
    background: #080f1d;
    border-color: #1e293b;
}
.fre-highlight {
    border: 2px solid #e11d48 !important;
    box-shadow: 0 0 20px rgba(225,29,72,0.2) !important;
    animation: pulseHighlight 2s infinite ease-in-out;
}
html.dark .fre-highlight, html[data-theme="dark"] .fre-highlight {
    border-color: #fb7185 !important;
    box-shadow: 0 0 20px rgba(251,113,133,0.35) !important;
}
@keyframes pulseHighlight {
    0%, 100% { border-color: #e11d48; }
    50% { border-color: #f43f5e; }
}
.fre-val-badge {
    padding: .3rem .65rem;
    border-radius: .4rem;
    font-size: .72rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
.fre-val-badge--pass { background: rgba(5,150,105,.12); color: #059669; border: 1px solid rgba(5,150,105,.25); }
html.dark .fre-val-badge--pass, html[data-theme="dark"] .fre-val-badge--pass { background: rgba(52,211,153,.15); color: #34d399; border-color: rgba(52,211,153,.3); }
.fre-val-badge--fail { background: rgba(225,29,72,.12); color: #e11d48; border: 1px solid rgba(225,29,72,.25); }
html.dark .fre-val-badge--fail, html[data-theme="dark"] .fre-val-badge--fail { background: rgba(244,63,94,.15); color: #fb7185; border-color: rgba(244,63,94,.3); }

.fre-progress-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 99px;
    overflow: hidden;
    margin-top: .5rem;
}
html.dark .fre-progress-bar, html[data-theme="dark"] .fre-progress-bar {
    background: #1e293b;
}
.fre-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #059669);
    border-radius: 99px;
    transition: width .3s ease;
}
html.dark .fre-progress-fill, html[data-theme="dark"] .fre-progress-fill {
    background: linear-gradient(90deg, #6366f1, #34d399);
}
</style>
@endpush

@section('content')
<div class="fre-container">

    {{-- Navigation Breadcrumbs --}}
    <div class="flex items-center gap-4 mb-2">
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-bold transition-colors">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-bold transition-colors">
            ← Back
        </a>
        @else
        <a href="{{ route('teacher.repository-revisions.show', $revisionRequest->id) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-bold transition-colors">
            ← Back to Revision Task
        </a>
        @endif
        <a href="{{ route('teacher.dashboard') }}" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-semibold transition-colors">
            Dashboard
        </a>
    </div>

    {{-- REPOSITORY REVISION OVERLAY GOVERNANCE BANNER --}}
    <div class="fre-banner">
        <div class="flex justify-between items-start gap-4 flex-wrap mb-4">
            <div>
                <span class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">🛠 Focused Repository Revision Mode</span>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white mt-1 mb-1">
                    {{ $bank?->title ?? 'Repository Asset' }}
                </h1>
                <div class="text-xs text-slate-600 dark:text-slate-400">
                    Requested By: <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $revisionRequest->requestedBy?->name ?? 'Repository Manager' }}</strong> • Status: <strong class="text-amber-700 dark:text-amber-400 uppercase font-bold">Needs Revision</strong>
                </div>
            </div>

            <span class="px-3 py-1.5 bg-rose-50 dark:bg-rose-500/15 border border-rose-300 dark:border-rose-500/40 text-rose-700 dark:text-rose-400 rounded-lg text-xs font-extrabold">
                Finding Status: {{ $item->status }}
            </span>
        </div>

        <div class="bg-slate-50 dark:bg-slate-950/80 border-l-4 border-rose-500 p-4 rounded-r-xl border border-slate-200 dark:border-slate-800">
            <div class="text-xs font-extrabold text-rose-700 dark:text-rose-400 uppercase mb-1">Actionable Finding Feedback:</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white mb-1.5">
                ⚠️ {{ $item->feedback }}
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300">
                <strong>Reviewer Notes:</strong> "{{ $revisionRequest->notes }}"
            </div>
        </div>
    </div>

    {{-- LIVE VALIDATION CHECKLIST STRIP --}}
    @php
        $vChecks = $validationData['checks'] ?? [];
        $vPassed = $validationData['passed_count'] ?? 0;
        $vTotal  = $validationData['total_count'] ?? 8;
    @endphp
    <div class="fre-panel" style="padding:1.25rem;">
        <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
            <div class="text-sm font-black text-slate-900 dark:text-white">
                📊 Live Validation Checklist ({{ $vPassed }} / {{ $vTotal }} Passed)
            </div>
            <span class="text-xs font-extrabold {{ $vPassed === $vTotal ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                {{ $vPassed === $vTotal ? '✔ 100% Quality Standards Satisfied' : '⚠️ Outstanding Quality Requirements' }}
            </span>
        </div>

        <div class="flex gap-2 flex-wrap">
            @foreach($vChecks as $key => $check)
            <span id="val-badge-{{ $key }}" class="fre-val-badge {{ $check['passed'] ? 'fre-val-badge--pass' : 'fre-val-badge--fail' }}">
                {{ $check['passed'] ? '✓' : '✖' }} {{ $check['label'] }}
            </span>
            @endforeach
        </div>
    </div>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 p-4 rounded-xl text-xs font-extrabold">
        {{ session('success') }}
    </div>
    @endif

    @if(session('info'))
    <div class="bg-sky-500/10 border border-sky-500/20 text-sky-600 dark:text-sky-400 p-4 rounded-xl text-xs font-extrabold">
        ℹ️ {{ session('info') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 p-4 rounded-xl text-xs font-extrabold">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    {{-- FULL QUESTION EDITOR FORM --}}
    @php
        $vChecks = $validationData['checks'] ?? [];
        $vPassed = $validationData['passed_count'] ?? 0;
        $vTotal  = $validationData['total_count'] ?? count($vChecks);

        $explanationPassed = $vChecks['explanation']['passed'] ?? true;
        $promptPassed      = $vChecks['prompt']['passed'] ?? true;
        $categoryPassed    = $vChecks['category']['passed'] ?? true;
        $difficultyPassed  = $vChecks['difficulty']['passed'] ?? true;
        $choicesPassed     = $vChecks['choices']['passed'] ?? true;
        $correctPassed     = $vChecks['correct_answer']['passed'] ?? true;
        $mediaPassed       = $vChecks['media']['passed'] ?? true;

        $fbLower = strtolower($item->feedback ?? '');

        // Canonical Validation Sync: Field-level highlighting strictly obeys the live validation result
        $highlightExplanation = !$explanationPassed && (str_contains($fbLower, 'explanation') || empty(trim($question->explanation ?? '')));
        $highlightPrompt      = !$promptPassed && (str_contains($fbLower, 'prompt') || empty(trim($question->prompt ?? '')));
        $highlightCategory    = !$categoryPassed && (str_contains($fbLower, 'category') || empty($bank->acl_category_id));
        $highlightDifficulty  = !$difficultyPassed && (str_contains($fbLower, 'difficulty') || empty($question->difficulty));
        $highlightChoices     = (!$choicesPassed || !$correctPassed) && (str_contains($fbLower, 'choice') || str_contains($fbLower, 'answer'));
        $highlightMedia       = !$mediaPassed && (str_contains($fbLower, 'media') || str_contains($fbLower, 'attachment'));

        $qTypeVal             = $question->question_type->value ?? $question->question_type ?? 'multiple_choice';
        $qDiffVal             = is_object($question->difficulty ?? null) ? $question->difficulty->value : ($question->difficulty ?? 'medium');
    @endphp

    <div class="fre-panel">
        <form method="POST" action="{{ route('teacher.repository-revisions.update-question', [$revisionRequest->id, $item->id]) }}">
            @csrf
            <input type="hidden" name="question_id" value="{{ $question->id ?? '' }}">

            {{-- 1. Category Assignment Section --}}
            <div id="category-section" class="fre-section {{ $highlightCategory ? 'fre-highlight' : '' }}">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-extrabold text-slate-900 dark:text-white m-0">🏷 Academic Subject Category</label>
                    @if($highlightCategory)
                    <span class="text-[11px] font-extrabold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/20 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-500/30">
                        ⚠️ Action Required: Assign Category Below
                    </span>
                    @endif
                </div>
                <select name="category_id" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500">
                    <option value="">-- Select Category --</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ ($bank->acl_category_id ?? '') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- 2. Question Prompt Section --}}
            <div id="prompt-section" class="fre-section {{ $highlightPrompt ? 'fre-highlight' : '' }}">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-extrabold text-slate-900 dark:text-white m-0">📝 Question Prompt Text</label>
                    @if($highlightPrompt)
                    <span class="text-[11px] font-extrabold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/20 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-500/30">
                        ⚠️ Action Required: Update Question Prompt
                    </span>
                    @endif
                </div>
                <textarea name="prompt" rows="3" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" required>{{ old('prompt', $question->prompt ?? '') }}</textarea>
            </div>

            {{-- 3. Question Type & Difficulty & Points Section --}}
            <div id="difficulty-section" class="fre-section {{ $highlightDifficulty ? 'fre-highlight' : '' }}">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-xs font-extrabold text-slate-900 dark:text-white m-0">⚙️ Question Type, Difficulty &amp; Points</label>
                    @if($highlightDifficulty)
                    <span class="text-[11px] font-extrabold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/20 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-500/30">
                        ⚠️ Action Required: Adjust Difficulty / Parameters
                    </span>
                    @endif
                </div>
                @php
                    $isToeic = ($bank && ((is_object($bank->test_type) ? $bank->test_type->value : (string)$bank->test_type) === 'toeic')) || !empty($question->part_number);
                    $curPart = $question->part_number ?? 1;
                @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;">
                    @if($isToeic)
                    <div style="grid-column: 1 / -1;" class="bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-500/40 p-3.5 rounded-xl">
                        <label class="text-xs font-extrabold text-indigo-800 dark:text-indigo-300 block mb-1.5">🎯 TOEIC Part Selection *</label>
                        <select name="part_number" id="fre_part_number" class="w-full bg-white dark:bg-slate-950 border border-indigo-300 dark:border-indigo-500/50 text-slate-900 dark:text-white rounded-xl p-2.5 text-sm font-bold">
                            <option value="1" {{ $curPart == 1 ? 'selected' : '' }}>Part 1: Photographs (Listening — Image &amp; Audio Required, 4 Choices)</option>
                            <option value="2" {{ $curPart == 2 ? 'selected' : '' }}>Part 2: Question-Response (Listening — Audio Required, Exactly 3 Choices)</option>
                            <option value="3" {{ $curPart == 3 ? 'selected' : '' }}>Part 3: Conversations (Listening — Audio Required, 4 Choices)</option>
                            <option value="4" {{ $curPart == 4 ? 'selected' : '' }}>Part 4: Talks (Listening — Audio Required, 4 Choices)</option>
                            <option value="5" {{ $curPart == 5 ? 'selected' : '' }}>Part 5: Incomplete Sentences (Reading — Audio Forbidden, 4 Choices)</option>
                            <option value="6" {{ $curPart == 6 ? 'selected' : '' }}>Part 6: Text Completion (Reading — Passage Required, 4 Choices)</option>
                            <option value="7" {{ $curPart == 7 ? 'selected' : '' }}>Part 7: Reading Comprehension (Reading — Passage Required, 4 Choices)</option>
                        </select>
                        <input type="hidden" name="section" value="{{ in_array($curPart, [1,2,3,4]) ? 'listening' : 'reading' }}">
                    </div>
                    @endif
                    <div>
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Question Type</label>
                        <select id="fre_question_type" name="question_type" onchange="updateRevisionAnswerOptionsUI()" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-2.5 text-sm focus:outline-none focus:border-indigo-500">
                            <option value="multiple_choice" {{ $qTypeVal === 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                            <option value="single_choice" {{ $qTypeVal === 'single_choice' ? 'selected' : '' }}>Single Choice</option>
                            @if(!$isToeic)
                            <option value="listening" {{ $qTypeVal === 'listening' ? 'selected' : '' }}>Listening</option>
                            <option value="reading" {{ $qTypeVal === 'reading' ? 'selected' : '' }}>Reading</option>
                            <option value="true_false" {{ $qTypeVal === 'true_false' ? 'selected' : '' }}>True / False</option>
                            <option value="short_answer" {{ $qTypeVal === 'short_answer' ? 'selected' : '' }}>Short Answer</option>
                            <option value="essay" {{ $qTypeVal === 'essay' ? 'selected' : '' }}>Essay</option>
                            <option value="speaking" {{ $qTypeVal === 'speaking' ? 'selected' : '' }}>Speaking</option>
                            <option value="matching" {{ $qTypeVal === 'matching' ? 'selected' : '' }}>Matching Pairs</option>
                            <option value="ordering" {{ $qTypeVal === 'ordering' ? 'selected' : '' }}>Ordering Sequence</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Difficulty *</label>
                        <select name="difficulty" required class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-2.5 text-sm">
                            <option value="easy" {{ $qDiffVal === 'easy' ? 'selected' : '' }}>Easy</option>
                            <option value="medium" {{ $qDiffVal === 'medium' || empty($qDiffVal) ? 'selected' : '' }}>Medium</option>
                            <option value="hard" {{ $qDiffVal === 'hard' ? 'selected' : '' }}>Hard</option>
                        </select>
                    </div>
                    <input type="hidden" name="points" value="{{ old('points', $question->points ?? 1) }}">
                </div>
            </div>

            {{-- 4. Media Asset Manager Section --}}
            <div id="media-section" class="fre-section {{ $highlightMedia ? 'fre-highlight' : '' }}">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-xs font-extrabold text-slate-900 dark:text-white m-0">📎 Media Asset Manager</label>
                    @if($highlightMedia)
                    <span class="text-[11px] font-extrabold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/20 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-500/30">
                        ⚠️ Action Required: Attach / Replace Media Asset
                    </span>
                    @endif
                </div>

                @if($question->mediaAsset ?? $question->media)
                @php $m = $question->mediaAsset ?? $question->media; @endphp
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 rounded-xl mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🎧</span>
                        <div>
                            <div class="text-xs font-extrabold text-slate-900 dark:text-white">{{ $m->title ?? $m->original_name }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Type: {{ strtoupper($m->type ?? 'FILE') }} • ID: {{ substr($m->id, 0, 8) }}</div>
                        </div>
                    </div>
                    <label class="text-xs text-rose-600 dark:text-rose-400 font-bold flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="remove_media" value="1" style="accent-color:#f43f5e;">
                        Remove Media
                    </label>
                </div>
                @endif

                <div>
                    <label class="text-xs text-slate-700 dark:text-slate-300 font-bold block mb-1">Select / Replace Attached Media:</label>
                    <select name="media_asset_id" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-2.5 text-sm">
                        <option value="">-- No Media Asset Attached --</option>
                        @foreach($mediaAssets as $ma)
                        <option value="{{ $ma->id }}" {{ ($question->media_asset_id ?? '') == $ma->id ? 'selected' : '' }}>
                            {{ $ma->title ?? $ma->original_name }} ({{ strtoupper($ma->type ?? 'FILE') }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- 5. DYNAMIC QUESTION TYPE CONTROL CONTAINER --}}
            @php
                $isChoiceBased = in_array($qTypeVal, ['multiple_choice', 'single_choice', 'listening', 'reading']);
            @endphp
            <div id="answer-choices-section" class="fre-section {{ ($highlightChoices && $isChoiceBased) ? 'fre-highlight' : '' }}">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-xs font-extrabold text-slate-900 dark:text-white m-0">🎯 Dynamic Answer Controls</label>
                    @if($highlightChoices && $isChoiceBased)
                    <span class="text-[11px] font-extrabold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/20 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-500/30">
                        ⚠️ Action Required: Fix Option Choices / Answer Fields Below
                    </span>
                    @endif
                </div>

                {{-- Dynamic Target Container --}}
                <div id="fre_dynamic_answer_container">
                    @if($qTypeVal === 'essay')
                        <div id="essay-notice-box" class="p-4 bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-500/30 rounded-xl">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="text-lg">📝</span>
                                <strong class="text-indigo-800 dark:text-indigo-300 text-xs font-bold">Essay / Open-Ended Question</strong>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mb-3 leading-relaxed">
                                Essay questions do not require answer choices or a correct answer.
                            </p>
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Sample Model Answer &amp; Scoring Guidelines (Optional)</label>
                            <textarea name="reference_answer_text" rows="3" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter optional model reference answer or grading rubric guidelines...">{{ old('reference_answer_text', $question->reference_answer ?? '') }}</textarea>
                        </div>
                    @elseif($isChoiceBased)
                        @if($question->choices->count() > 0)
                            @foreach($question->choices as $cIdx => $choice)
                            <div class="flex items-center gap-3 mb-2.5">
                                <input type="radio" name="correct_choice_id" value="{{ $choice->id }}" {{ $choice->is_correct ? 'checked' : '' }} style="accent-color:#10b981;width:1.2rem;height:1.2rem;" title="Mark as Correct Choice">
                                <span class="font-extrabold text-indigo-600 dark:text-indigo-400 w-6">{{ $choice->label ?? chr(65 + $cIdx) }}.</span>
                                <input type="text" name="choices[{{ $choice->id }}][content]" value="{{ old('choices.'.$choice->id.'.content', $choice->content) }}" class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-sm" required>
                                <input type="hidden" name="choices[{{ $choice->id }}][label]" value="{{ $choice->label ?? chr(65 + $cIdx) }}">
                            </div>
                            @endforeach
                        @else
                            <div class="text-xs text-rose-700 dark:text-rose-400 p-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-lg mb-3 font-semibold">
                                ⚠️ No answer choices currently attached to this question. Add choices below.
                            </div>
                        @endif

                        {{-- Add New Choice Input --}}
                        <div class="mt-4 pt-4 border-t border-dashed border-slate-200 dark:border-slate-800">
                            <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mb-1.5">+ Add Additional Choice:</div>
                            <div class="flex gap-3 items-center">
                                <input type="text" name="new_choice_label" placeholder="Label (e.g. C)" style="width:90px;" class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                                <input type="text" name="new_choice_content" placeholder="Choice Content text..." class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                                <label class="text-xs text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" name="new_choice_is_correct" value="1" style="accent-color:#10b981;">
                                    Correct
                                </label>
                            </div>
                        </div>
                    @elseif($qTypeVal === 'true_false')
                        <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-2">True / False Correct Answer Designation</label>
                        <div class="flex gap-6 items-center">
                            <label class="text-xs font-bold text-slate-800 dark:text-white flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="tf_correct_choice" value="true" checked style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> True
                            </label>
                            <label class="text-xs font-bold text-slate-800 dark:text-white flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="tf_correct_choice" value="false" style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> False
                            </label>
                        </div>
                    @elseif($qTypeVal === 'short_answer')
                        <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-1">Accepted Exact Correct Answer String *</label>
                        <input type="text" name="short_answer_text" value="{{ old('short_answer_text') }}" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter expected exact string answer...">
                    @elseif($qTypeVal === 'speaking')
                        <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-1">Speaking Audio Prompt &amp; Evaluation Rubric</label>
                        <textarea name="speaking_rubric" rows="3" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter speaking response instructions, target vocabulary, and scoring rubric...">{{ old('speaking_rubric') }}</textarea>
                    @endif
                </div>
            </div>

            {{-- 6. Pedagogical Explanation Section --}}
            <div id="explanation-section" class="fre-section {{ $highlightExplanation ? 'fre-highlight' : '' }}">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-extrabold text-slate-900 dark:text-white m-0">💡 Pedagogical Explanation &amp; Rationale</label>
                    <span id="explanation-action-required" class="text-[11px] font-extrabold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/20 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-500/30" style="{{ $highlightExplanation ? '' : 'display:none;' }}">
                        ⚠️ Action Required: Provide Explanation
                    </span>
                </div>
                <textarea name="explanation" rows="3" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Provide detailed academic rationale for the correct choice...">{{ old('explanation', $question->explanation ?? '') }}</textarea>
            </div>

            {{-- 7. Tags & Metadata Section --}}
            <div id="metadata-section" class="fre-section">
                <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-2">🏷 Tags &amp; Metadata</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;">
                    <div>
                        <label class="text-xs text-slate-600 dark:text-slate-300 font-medium block mb-1">Tags (comma separated)</label>
                        <input type="text" name="tags" value="{{ old('tags', 'toeic, grammar, reading') }}" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-2.5 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 dark:text-slate-300 font-medium block mb-1">Repository Version</label>
                        <input type="text" value="v{{ $bank->current_version ?? '1.0' }}" readonly class="w-full bg-slate-100 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 rounded-xl p-2.5 text-sm font-mono">
                    </div>
                </div>
            </div>

            {{-- SAVE QUESTION SUBMIT BUTTON --}}
            <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all cursor-pointer">
                💾 Save Question Revision &amp; Validate Quality Standards
            </button>
        </form>
    </div>

    {{-- BOTTOM REVISION PROGRESS & RESUBMIT STRIP --}}
    @php
        $totalItems = $revisionRequest->items->count();
        $closedItems = $revisionRequest->items->where('status', 'CLOSED')->count();
        $percentComplete = $totalItems > 0 ? round(($closedItems / $totalItems) * 100) : 0;
        $allResolved = $closedItems >= $totalItems && $totalItems > 0 && $vPassed === $vTotal;
    @endphp

    <div class="fre-panel border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/50 to-slate-50 dark:from-slate-900 dark:to-indigo-950/40">
        <div class="mb-4">
            <div class="flex justify-between items-center">
                <span class="text-xs font-bold text-slate-900 dark:text-white">Revision Completion Progress</span>
                <span class="text-xs font-extrabold {{ $allResolved ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                    ✔ {{ $closedItems }} / {{ $totalItems }} Findings Fixed ({{ $percentComplete }}%)
                </span>
            </div>
            <div class="fre-progress-bar">
                <div class="fre-progress-fill" style="width: {{ $percentComplete }}%;"></div>
            </div>
        </div>

        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                @if($allResolved)
                <div class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400 mb-0.5">
                    ✔ All Findings Resolved — Ready For Resubmission
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">All actionable items and live validation checklist items pass 100%.</div>
                @else
                <div class="text-sm font-extrabold text-rose-600 dark:text-rose-400 mb-0.5">
                    ⚠️ Remaining Findings: Unresolved Issue(s) Exist
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Complete all remaining findings and live validation checklist before resubmitting repository.</div>
                @endif
            </div>

            <form method="POST" action="{{ route('teacher.repository-revisions.resubmit', $revisionRequest->id) }}">
                @csrf
                <button type="submit" {{ !$allResolved ? 'disabled' : '' }} class="px-6 py-3.5 {{ $allResolved ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg cursor-pointer' : 'bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed border border-slate-300 dark:border-slate-700' }} font-extrabold text-xs rounded-xl transition-all inline-flex items-center gap-2">
                    🚀 Resubmit Repository &amp; Trigger IRQA Re-Scan
                </button>
            </form>
        </div>
    </div>

</div>

{{-- DYNAMIC QUESTION TYPE RENDERING & SMART AUTO-SCROLL SCRIPT --}}
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Smart auto scroll to highlighted finding field
    const highlighted = document.querySelector('.fre-highlight');
    if (highlighted) {
        highlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Live sync for explanation textarea
    const explanationTextarea = document.querySelector('textarea[name="explanation"]');
    const explanationSection = document.getElementById('explanation-section');
    const explanationBadge = document.getElementById('val-badge-explanation');
    const explanationActionReq = document.getElementById('explanation-action-required');

    if (explanationTextarea) {
        explanationTextarea.addEventListener('input', function() {
            const hasVal = this.value.trim().length > 0;
            if (hasVal) {
                if (explanationSection) explanationSection.classList.remove('fre-highlight');
                if (explanationActionReq) explanationActionReq.style.display = 'none';
                if (explanationBadge) {
                    explanationBadge.className = 'fre-val-badge fre-val-badge--pass';
                    explanationBadge.innerHTML = '✓ Explanation';
                }
            } else {
                if (explanationSection) explanationSection.classList.add('fre-highlight');
                if (explanationActionReq) explanationActionReq.style.display = 'inline';
                if (explanationBadge) {
                    explanationBadge.className = 'fre-val-badge fre-val-badge--fail';
                    explanationBadge.innerHTML = '✖ Explanation';
                }
            }
        });
    }
});

function updateRevisionAnswerOptionsUI() {
    const typeSelect = document.getElementById('fre_question_type');
    const container = document.getElementById('fre_dynamic_answer_container');
    if (!typeSelect || !container) return;

    const type = typeSelect.value;

    if (['multiple_choice', 'single_choice', 'listening', 'reading'].includes(type)) {
        // Render Multiple Choice / Single Choice Options
        container.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:.65rem;margin-bottom:.85rem;">
                <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-1">Option Answer Choices (Select 1 Correct Answer)</label>
                ${['A', 'B', 'C', 'D'].map((lbl, i) => `
                    <div class="flex items-center gap-3">
                        <input type="radio" name="correct_choice_id" value="${i}" ${i === 0 ? 'checked' : ''} style="accent-color:#10b981;width:1.2rem;height:1.2rem;">
                        <span class="font-extrabold text-indigo-600 dark:text-indigo-400 w-6">${lbl}.</span>
                        <input type="hidden" name="choices[${i}][label]" value="${lbl}">
                        <input type="text" name="choices[${i}][content]" class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2.5 text-sm" placeholder="Option ${lbl} content..." required>
                    </div>
                `).join('')}
            </div>
            <div class="mt-4 pt-4 border-t border-dashed border-slate-200 dark:border-slate-800">
                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mb-1.5">+ Add Additional Choice:</div>
                <div class="flex gap-3 items-center">
                    <input type="text" name="new_choice_label" placeholder="Label (e.g. E)" style="width:90px;" class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                    <input type="text" name="new_choice_content" placeholder="Choice Content text..." class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                    <label class="text-xs text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="new_choice_is_correct" value="1" style="accent-color:#10b981;">
                        Correct
                    </label>
                </div>
            </div>
        `;
    } else if (type === 'true_false') {
        // Render True / False Radio Selector
        container.innerHTML = `
            <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-2">True / False Correct Answer Designation</label>
            <div class="flex gap-6 items-center">
                <label class="text-xs font-bold text-slate-800 dark:text-white flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="tf_correct_choice" value="true" checked style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> True
                </label>
                <label class="text-xs font-bold text-slate-800 dark:text-white flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="tf_correct_choice" value="false" style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> False
                </label>
            </div>
        `;
    } else if (type === 'short_answer') {
        // Render Short Answer Accepted Strings
        container.innerHTML = `
            <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-1">Accepted Exact Correct Answer String *</label>
            <input type="text" name="short_answer_text" required class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter expected exact string answer...">
        `;
    } else if (type === 'essay') {
        // Render Essay Reference Answer & Rubric
        container.innerHTML = `
            <div id="essay-notice-box" class="p-4 bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-500/30 rounded-xl">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-lg">📝</span>
                    <strong class="text-indigo-800 dark:text-indigo-300 text-xs font-bold">Essay / Open-Ended Question</strong>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-300 mb-3 leading-relaxed">
                    Essay questions do not require answer choices or a correct answer.
                </p>
                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Sample Model Answer &amp; Scoring Guidelines (Optional)</label>
                <textarea name="reference_answer_text" rows="3" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter optional model reference answer or grading rubric guidelines..."></textarea>
            </div>
        `;
    } else if (type === 'speaking') {
        // Render Speaking Prompt & Rubric
        container.innerHTML = `
            <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-1">Speaking Audio Prompt &amp; Evaluation Rubric</label>
            <textarea name="speaking_rubric" rows="3" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter speaking response instructions, target vocabulary, and scoring rubric..."></textarea>
        `;
    } else if (type === 'matching') {
        // Render Matching Pair Editor
        container.innerHTML = `
            <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-2">Matching Pair Elements</label>
            <div class="flex flex-col gap-2">
                <div class="flex gap-2">
                    <input type="text" placeholder="Premise (Left Column)" class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                    <span class="text-indigo-600 dark:text-indigo-400 font-extrabold self-center">➔</span>
                    <input type="text" placeholder="Target Match (Right Column)" class="flex-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                </div>
            </div>
        `;
    } else if (type === 'ordering') {
        // Render Ordering Sequence Editor
        container.innerHTML = `
            <label class="text-xs font-extrabold text-slate-900 dark:text-white block mb-2">Correct Ordering Sequence Items (In Proper Order)</label>
            <div class="flex flex-col gap-2">
                <input type="text" placeholder="Step 1 Item..." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
                <input type="text" placeholder="Step 2 Item..." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 text-sm">
            </div>
        `;
    }
}
</script>
@endsection

