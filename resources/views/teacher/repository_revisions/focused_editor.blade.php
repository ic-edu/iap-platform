@extends('layouts.admin')

@section('title', 'Full Question Revision Editor — iC.edu Platform')

@push('styles')
<style>
.fre-container { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; }
.fre-banner {
    background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
    border: 2px solid #6366f1;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 16px 36px -10px rgba(99,102,241,0.3);
}
.fre-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem;
}
.fre-section {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
    transition: all .2s ease;
}
.fre-highlight {
    border: 2px solid #fb7185 !important;
    box-shadow: 0 0 20px rgba(251,113,133,0.35) !important;
    animation: pulseHighlight 2s infinite ease-in-out;
}
@keyframes pulseHighlight {
    0%, 100% { border-color: #fb7185; }
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
.fre-val-badge--pass { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.fre-val-badge--fail { background: rgba(244,63,94,.15); color: #fb7185; border: 1px solid rgba(244,63,94,.3); }

.fre-progress-bar {
    height: 8px;
    background: #1e293b;
    border-radius: 99px;
    overflow: hidden;
    margin-top: .5rem;
}
.fre-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #34d399);
    transition: width .3s ease;
}
</style>
@endpush

@section('content')
<div class="fre-container">

    {{-- Navigation Breadcrumbs --}}
    <div style="display:flex;gap:1.25rem;align-items:center;margin-bottom:1rem;">
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        @else
        <a href="{{ route('teacher.repository-revisions.show', $revisionRequest->id) }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Revision Task
        </a>
        @endif
        <a href="{{ route('teacher.dashboard') }}" style="color:#cbd5e1;font-size:.82rem;font-weight:700;text-decoration:none;">
            Dashboard
        </a>
    </div>

    {{-- REPOSITORY REVISION OVERLAY GOVERNANCE BANNER --}}
    <div class="fre-banner">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <div>
                <span style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.08em;">🛠 Focused Repository Revision Mode</span>
                <h1 style="font-size:1.5rem;font-weight:900;color:#fff;margin:.25rem 0 .2rem;">
                    {{ $bank?->title ?? 'Repository Asset' }}
                </h1>
                <div style="font-size:.82rem;color:#94a3b8;">
                    Requested By: <strong style="color:#e2e8f0;">{{ $revisionRequest->requestedBy?->name ?? 'Repository Manager' }}</strong> • Status: <strong style="color:#fbbf24;text-transform:uppercase;">Needs Revision</strong>
                </div>
            </div>

            <span style="padding:.35rem .8rem;background:rgba(244,63,94,.15);border:1px solid rgba(244,63,94,.4);color:#fb7185;border-radius:.6rem;font-size:.75rem;font-weight:800;">
                Finding Status: {{ $item->status }}
            </span>
        </div>

        <div style="background:#080f1d;border-left:4px solid #fb7185;padding:1rem 1.25rem;border-radius:0 .75rem .75rem 0;">
            <div style="font-size:.7rem;font-weight:800;color:#fb7185;text-transform:uppercase;margin-bottom:.25rem;">Actionable Finding Feedback:</div>
            <div style="font-size:.9rem;font-weight:700;color:#fff;margin-bottom:.4rem;">
                ⚠️ {{ $item->feedback }}
            </div>
            <div style="font-size:.8rem;color:#cbd5e1;">
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
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;">
            <div style="font-size:.88rem;font-weight:900;color:#fff;">
                📊 Live Validation Checklist ({{ $vPassed }} / {{ $vTotal }} Passed)
            </div>
            <span style="font-size:.75rem;font-weight:800;color:{{ $vPassed === $vTotal ? '#34d399' : '#fbbf24' }};">
                {{ $vPassed === $vTotal ? '✔ 100% Quality Standards Satisfied' : '⚠️ Outstanding Quality Requirements' }}
            </span>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            @foreach($vChecks as $key => $check)
            <span id="val-badge-{{ $key }}" class="fre-val-badge {{ $check['passed'] ? 'fre-val-badge--pass' : 'fre-val-badge--fail' }}">
                {{ $check['passed'] ? '✓' : '✖' }} {{ $check['label'] }}
            </span>
            @endforeach
        </div>
    </div>

    @if(session('success'))
    <div style="background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.4);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:800;">
        {{ session('success') }}
    </div>
    @endif

    @if(session('info'))
    <div style="background:rgba(56,189,248,.15);border:1px solid rgba(56,189,248,.4);color:#38bdf8;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:800;">
        ℹ️ {{ session('info') }}
    </div>
    @endif

    @if(session('error'))
    <div style="background:rgba(244,63,94,.15);border:1px solid rgba(244,63,94,.4);color:#fb7185;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:800;">
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
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                    <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;margin:0;">🏷 Academic Subject Category</label>
                    @if($highlightCategory)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Action Required: Assign Category Below
                    </span>
                    @endif
                </div>
                <select name="category_id" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500">
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
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                    <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;margin:0;">📝 Question Prompt Text</label>
                    @if($highlightPrompt)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Action Required: Update Question Prompt
                    </span>
                    @endif
                </div>
                <textarea name="prompt" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" required>{{ old('prompt', $question->prompt ?? '') }}</textarea>
            </div>

            {{-- 3. Question Type & Difficulty & Points Section --}}
            <div id="difficulty-section" class="fre-section {{ $highlightDifficulty ? 'fre-highlight' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                    <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;margin:0;">⚙️ Question Type, Difficulty & Points</label>
                    @if($highlightDifficulty)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Action Required: Adjust Difficulty / Parameters
                    </span>
                    @endif
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;">
                    <div>
                        <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Question Type</label>
                        <select id="fre_question_type" name="question_type" onchange="updateRevisionAnswerOptionsUI()" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm focus:outline-none focus:border-indigo-500">
                            <option value="multiple_choice" {{ $qTypeVal === 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                            <option value="single_choice" {{ $qTypeVal === 'single_choice' ? 'selected' : '' }}>Single Choice</option>
                            <option value="listening" {{ $qTypeVal === 'listening' ? 'selected' : '' }}>Listening</option>
                            <option value="reading" {{ $qTypeVal === 'reading' ? 'selected' : '' }}>Reading</option>
                            <option value="true_false" {{ $qTypeVal === 'true_false' ? 'selected' : '' }}>True / False</option>
                            <option value="short_answer" {{ $qTypeVal === 'short_answer' ? 'selected' : '' }}>Short Answer</option>
                            <option value="essay" {{ $qTypeVal === 'essay' ? 'selected' : '' }}>Essay</option>
                            <option value="speaking" {{ $qTypeVal === 'speaking' ? 'selected' : '' }}>Speaking</option>
                            <option value="matching" {{ $qTypeVal === 'matching' ? 'selected' : '' }}>Matching Pairs</option>
                            <option value="ordering" {{ $qTypeVal === 'ordering' ? 'selected' : '' }}>Ordering Sequence</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Difficulty</label>
                        <select name="difficulty" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm">
                            <option value="easy" {{ $qDiffVal === 'easy' ? 'selected' : '' }}>Easy</option>
                            <option value="medium" {{ $qDiffVal === 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="hard" {{ $qDiffVal === 'hard' ? 'selected' : '' }}>Hard</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.3rem;">Points</label>
                        <input type="number" name="points" value="{{ old('points', $question->points ?? 1) }}" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm">
                    </div>
                </div>
            </div>

            {{-- 4. Media Asset Manager Section --}}
            <div id="media-section" class="fre-section {{ $highlightMedia ? 'fre-highlight' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                    <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;margin:0;">📎 Media Asset Manager</label>
                    @if($highlightMedia)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Action Required: Attach / Replace Media Asset
                    </span>
                    @endif
                </div>

                @if($question->mediaAsset ?? $question->media)
                @php $m = $question->mediaAsset ?? $question->media; @endphp
                <div style="background:#0f172a;border:1px solid #334155;padding:.85rem 1rem;border-radius:.75rem;margin-bottom:.85rem;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <span style="font-size:1.5rem;">🎧</span>
                        <div>
                            <div style="font-size:.82rem;font-weight:800;color:#fff;">{{ $m->title ?? $m->original_name }}</div>
                            <div style="font-size:.72rem;color:#64748b;">Type: {{ strtoupper($m->type ?? 'FILE') }} • ID: {{ substr($m->id, 0, 8) }}</div>
                        </div>
                    </div>
                    <label style="font-size:.75rem;color:#fb7185;font-weight:700;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                        <input type="checkbox" name="remove_media" value="1" style="accent-color:#f43f5e;">
                        Remove Media
                    </label>
                </div>
                @endif

                <div>
                    <label style="font-size:.75rem;color:#cbd5e1;font-weight:700;display:block;margin-bottom:.3rem;">Select / Replace Attached Media:</label>
                    <select name="media_asset_id" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm">
                        <option value="">-- No Media Asset Attached --</option>
                        @foreach($mediaAssets as $ma)
                        <option value="{{ $ma->id }}" {{ ($question->media_asset_id ?? '') == $ma->id ? 'selected' : '' }}>
                            {{ $ma->title ?? $ma->original_name }} ({{ strtoupper($ma->type ?? 'FILE') }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- 5. DYNAMIC QUESTION TYPE CONTROL CONTAINER (REUSED SHARED CONTROL LOGIC) --}}
            @php
                $isChoiceBased = in_array($qTypeVal, ['multiple_choice', 'single_choice', 'listening', 'reading']);
            @endphp
            <div id="answer-choices-section" class="fre-section {{ ($highlightChoices && $isChoiceBased) ? 'fre-highlight' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;">
                    <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;margin:0;">🎯 Dynamic Answer Controls</label>
                    @if($highlightChoices && $isChoiceBased)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Action Required: Fix Option Choices / Answer Fields Below
                    </span>
                    @endif
                </div>

                {{-- Dynamic Target Container --}}
                <div id="fre_dynamic_answer_container">
                    @if($qTypeVal === 'essay')
                        <div id="essay-notice-box" style="padding:1rem 1.25rem;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);border-radius:.75rem;">
                            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem;">
                                <span style="font-size:1.1rem;">📝</span>
                                <strong style="color:#818cf8;font-size:.88rem;">Essay / Open-Ended Question</strong>
                            </div>
                            <p style="font-size:.82rem;color:#cbd5e1;margin:0 0 .75rem;line-height:1.5;">
                                Essay questions do not require answer choices or a correct answer.
                            </p>
                            <label style="font-size:.78rem;font-weight:800;color:#cbd5e1;display:block;margin-bottom:.3rem;">Sample Model Answer &amp; Scoring Guidelines (Optional)</label>
                            <textarea name="reference_answer_text" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter optional model reference answer or grading rubric guidelines...">{{ old('reference_answer_text', $question->reference_answer ?? '') }}</textarea>
                        </div>
                    @elseif($isChoiceBased)
                        @if($question->choices->count() > 0)
                            @foreach($question->choices as $cIdx => $choice)
                            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.65rem;">
                                <input type="radio" name="correct_choice_id" value="{{ $choice->id }}" {{ $choice->is_correct ? 'checked' : '' }} style="accent-color:#10b981;width:1.2rem;height:1.2rem;" title="Mark as Correct Choice">
                                <span style="font-weight:800;color:#818cf8;width:1.5rem;">{{ $choice->label ?? chr(65 + $cIdx) }}.</span>
                                <input type="text" name="choices[{{ $choice->id }}][content]" value="{{ old('choices.'.$choice->id.'.content', $choice->content) }}" class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2.5 text-sm" required>
                                <input type="hidden" name="choices[{{ $choice->id }}][label]" value="{{ $choice->label ?? chr(65 + $cIdx) }}">
                            </div>
                            @endforeach
                        @else
                            <div style="font-size:.8rem;color:#fb7185;padding:.75rem;background:rgba(244,63,94,.1);border-radius:.5rem;margin-bottom:.75rem;">
                                ⚠️ No answer choices currently attached to this question. Add choices below.
                            </div>
                        @endif

                        {{-- Add New Choice Input --}}
                        <div style="margin-top:1rem;padding-top:1rem;border-top:1px dashed #334155;">
                            <div style="font-size:.78rem;font-weight:700;color:#818cf8;margin-bottom:.4rem;">+ Add Additional Choice:</div>
                            <div style="display:flex;gap:.75rem;align-items:center;">
                                <input type="text" name="new_choice_label" placeholder="Choice Label (e.g. C)" style="width:70px;" class="bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                                <input type="text" name="new_choice_content" placeholder="Choice Content text..." class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                                <label style="font-size:.75rem;color:#34d399;font-weight:700;display:flex;align-items:center;gap:.3rem;">
                                    <input type="checkbox" name="new_choice_is_correct" value="1" style="accent-color:#10b981;">
                                    Correct
                                </label>
                            </div>
                        </div>
                    @elseif($qTypeVal === 'true_false')
                        <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.5rem;">True / False Correct Answer Designation</label>
                        <div style="display:flex;gap:1.5rem;align-items:center;">
                            <label style="font-size:.85rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                                <input type="radio" name="tf_correct_choice" value="true" checked style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> True
                            </label>
                            <label style="font-size:.85rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                                <input type="radio" name="tf_correct_choice" value="false" style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> False
                            </label>
                        </div>
                    @elseif($qTypeVal === 'short_answer')
                        <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.4rem;">Accepted Exact Correct Answer String *</label>
                        <input type="text" name="short_answer_text" value="{{ old('short_answer_text') }}" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter expected exact string answer...">
                    @elseif($qTypeVal === 'speaking')
                        <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.4rem;">Speaking Audio Prompt &amp; Evaluation Rubric</label>
                        <textarea name="speaking_rubric" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter speaking response instructions, target vocabulary, and scoring rubric...">{{ old('speaking_rubric') }}</textarea>
                    @endif
                </div>
            </div>

            {{-- 6. Pedagogical Explanation Section --}}
            <div id="explanation-section" class="fre-section {{ $highlightExplanation ? 'fre-highlight' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                    <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;margin:0;">💡 Pedagogical Explanation &amp; Rationale</label>
                    <span id="explanation-action-required" style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;{{ $highlightExplanation ? '' : 'display:none;' }}">
                        ⚠️ Action Required: Provide Explanation
                    </span>
                </div>
                <textarea name="explanation" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Provide detailed academic rationale for the correct choice...">{{ old('explanation', $question->explanation ?? '') }}</textarea>
            </div>

            {{-- 7. Tags & Metadata Section --}}
            <div id="metadata-section" class="fre-section">
                <label style="font-size:.85rem;font-weight:800;color:#f1f5f9;display:block;margin-bottom:.5rem;">🏷 Tags &amp; Metadata</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;">
                    <div>
                        <label style="font-size:.75rem;color:#cbd5e1;display:block;margin-bottom:.2rem;">Tags (comma separated)</label>
                        <input type="text" name="tags" value="{{ old('tags', 'toeic, grammar, reading') }}" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm">
                    </div>
                    <div>
                        <label style="font-size:.75rem;color:#cbd5e1;display:block;margin-bottom:.2rem;">Repository Version</label>
                        <input type="text" value="v{{ $bank->current_version ?? '1.0' }}" readonly class="w-full bg-slate-950 border border-slate-800 text-slate-400 rounded-xl p-2.5 text-sm">
                    </div>
                </div>
            </div>

            {{-- SAVE QUESTION SUBMIT BUTTON --}}
            <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all">
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

    <div class="fre-panel" style="border-color:#3730a3;background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);">
        <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:.82rem;font-weight:800;color:#fff;">Revision Completion Progress</span>
                <span style="font-size:.82rem;font-weight:900;color:{{ $allResolved ? '#34d399' : '#fbbf24' }};">
                    ✔ {{ $closedItems }} / {{ $totalItems }} Findings Fixed ({{ $percentComplete }}%)
                </span>
            </div>
            <div class="fre-progress-bar">
                <div class="fre-progress-fill" style="width: {{ $percentComplete }}%;"></div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
                @if($allResolved)
                <div style="font-size:.9rem;font-weight:800;color:#34d399;">
                    ✔ All Findings Resolved — Ready For Resubmission
                </div>
                <div style="font-size:.78rem;color:#94a3b8;">All actionable items and live validation checklist items pass 100%.</div>
                @else
                <div style="font-size:.9rem;font-weight:800;color:#fb7185;">
                    ⚠️ Remaining Findings: Unresolved Issue(s) Exist
                </div>
                <div style="font-size:.78rem;color:#94a3b8;">Complete all remaining findings and live validation checklist before resubmitting repository.</div>
                @endif
            </div>

            <form method="POST" action="{{ route('teacher.repository-revisions.resubmit', $revisionRequest->id) }}">
                @csrf
                <button type="submit" {{ !$allResolved ? 'disabled' : '' }} class="px-6 py-3.5 {{ $allResolved ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg' : 'bg-slate-800 text-slate-500 cursor-not-allowed' }} font-extrabold text-xs rounded-xl transition-all inline-flex items-center gap-2">
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
                <label style="font-size:.78rem;font-weight:800;color:#fff;display:block;margin-bottom:.3rem;">Option Answer Choices (Select 1 Correct Answer)</label>
                ${['A', 'B', 'C', 'D'].map((lbl, i) => `
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <input type="radio" name="correct_choice_id" value="${i}" ${i === 0 ? 'checked' : ''} style="accent-color:#10b981;width:1.2rem;height:1.2rem;">
                        <span style="font-weight:800;color:#818cf8;width:1.5rem;">${lbl}.</span>
                        <input type="hidden" name="choices[${i}][label]" value="${lbl}">
                        <input type="text" name="choices[${i}][content]" class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2.5 text-sm" placeholder="Option ${lbl} content..." required>
                    </div>
                `).join('')}
            </div>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px dashed #334155;">
                <div style="font-size:.78rem;font-weight:700;color:#818cf8;margin-bottom:.4rem;">+ Add Additional Choice:</div>
                <div style="display:flex;gap:.75rem;align-items:center;">
                    <input type="text" name="new_choice_label" placeholder="Choice Label (e.g. E)" style="width:70px;" class="bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                    <input type="text" name="new_choice_content" placeholder="Choice Content text..." class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                    <label style="font-size:.75rem;color:#34d399;font-weight:700;display:flex;align-items:center;gap:.3rem;">
                        <input type="checkbox" name="new_choice_is_correct" value="1" style="accent-color:#10b981;">
                        Correct
                    </label>
                </div>
            </div>
        `;
    } else if (type === 'true_false') {
        // Render True / False Radio Selector
        container.innerHTML = `
            <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.5rem;">True / False Correct Answer Designation</label>
            <div style="display:flex;gap:1.5rem;align-items:center;">
                <label style="font-size:.85rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                    <input type="radio" name="tf_correct_choice" value="true" checked style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> True
                </label>
                <label style="font-size:.85rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                    <input type="radio" name="tf_correct_choice" value="false" style="accent-color:#10b981;width:1.2rem;height:1.2rem;"> False
                </label>
            </div>
        `;
    } else if (type === 'short_answer') {
        // Render Short Answer Accepted Strings
        container.innerHTML = `
            <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.4rem;">Accepted Exact Correct Answer String *</label>
            <input type="text" name="short_answer_text" required class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter expected exact string answer...">
        `;
    } else if (type === 'essay') {
        // Render Essay Reference Answer & Rubric
        container.innerHTML = `
            <div id="essay-notice-box" style="padding:1rem 1.25rem;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);border-radius:.75rem;">
                <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem;">
                    <span style="font-size:1.1rem;">📝</span>
                    <strong style="color:#818cf8;font-size:.88rem;">Essay / Open-Ended Question</strong>
                </div>
                <p style="font-size:.82rem;color:#cbd5e1;margin:0 0 .75rem;line-height:1.5;">
                    Essay questions do not require answer choices or a correct answer.
                </p>
                <label style="font-size:.78rem;font-weight:800;color:#cbd5e1;display:block;margin-bottom:.3rem;">Sample Model Answer &amp; Scoring Guidelines (Optional)</label>
                <textarea name="reference_answer_text" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter optional model reference answer or grading rubric guidelines..."></textarea>
            </div>
        `;
    } else if (type === 'speaking') {
        // Render Speaking Prompt & Rubric
        container.innerHTML = `
            <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.4rem;">Speaking Audio Prompt &amp; Evaluation Rubric</label>
            <textarea name="speaking_rubric" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Enter speaking response instructions, target vocabulary, and scoring rubric..."></textarea>
        `;
    } else if (type === 'matching') {
        // Render Matching Pair Editor
        container.innerHTML = `
            <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.5rem;">Matching Pair Elements</label>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                <div style="display:flex;gap:.5rem;">
                    <input type="text" placeholder="Premise (Left Column)" class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                    <span style="color:#818cf8;font-weight:800;align-self:center;">➔</span>
                    <input type="text" placeholder="Target Match (Right Column)" class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                </div>
            </div>
        `;
    } else if (type === 'ordering') {
        // Render Ordering Sequence Editor
        container.innerHTML = `
            <label style="font-size:.82rem;font-weight:800;color:#fff;display:block;margin-bottom:.5rem;">Correct Ordering Sequence Items (In Proper Order)</label>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                <input type="text" placeholder="Step 1 Item..." class="w-full bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
                <input type="text" placeholder="Step 2 Item..." class="w-full bg-slate-900 border border-slate-700 text-white rounded-lg p-2 text-sm">
            </div>
        `;
    }
}
</script>
@endsection
