@extends('layouts.admin')

@section('title', 'Lazy Question Editor — Question #' . $question->id)

@section('content')
<div style="padding: 1.5rem 0; max-width: 900px; margin: 0 auto;">
    {{-- Header & Navigation --}}
    <div class="gov-hero-indigo rounded-2xl p-6 sm:p-7 flex justify-between items-center flex-wrap gap-5 mb-6">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-1">
                ⚡ Focused Question Authoring Workspace
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mb-1">
                Edit Question (ID: {{ $question->id }})
            </h1>
        </div>
        <div class="flex gap-3 items-center">
            <a href="{{ route('teacher.tests.show', $test->id) }}" class="gov-btn-secondary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                ← Back to Assessment
            </a>
            <a href="{{ route('teacher.dashboard') }}" class="gov-btn-primary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                Dashboard
            </a>
        </div>
    </div>

    @if(session('status'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ✅ {{ session('status') }}
    </div>
    @endif

    {{-- Question Editor Card --}}
    <div class="gov-card p-6 sm:p-7">
        <form id="edit-question-form" method="POST" action="{{ route('teacher.tests.update-question', ['test' => $test->id, 'question' => $question->id]) }}" onsubmit="return validateEditQuestionForm(this)" style="display:flex;flex-direction:column;gap:1.25rem;">
            @csrf
            @method('PUT')

            <div id="edit-q-validation-error" style="display:none;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#f87171;padding:.75rem 1rem;border-radius:.6rem;font-size:.85rem;font-weight:700;">
                ⚠️ Please select the correct answer.
            </div>

            <div>
                <label style="display:block;font-size:.85rem;font-weight:700;color:#334155;margin-bottom:.4rem;">Prompt Stem Text</label>
                <textarea name="prompt" rows="4" required oninput="updateEditorAutoDifficulty()" style="width:100%;padding:.75rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.6rem;color:#0f172a;font-size:.9rem;line-height:1.5;">{{ old('prompt', $question->prompt) }}</textarea>
            </div>

            @php
                $isToeic = ($test && ((is_object($test->test_type) ? $test->test_type->value : (string)$test->test_type) === 'toeic')) || !empty($question->part_number);
                $curPart = $question->part_number ?? 1;
            @endphp

            @if($isToeic)
            <div style="background:#eef2ff;border:1px solid #c7d2fe;padding:1rem;border-radius:.75rem;">
                <label style="display:block;font-size:.85rem;font-weight:800;color:#4338ca;margin-bottom:.4rem;">🎯 TOEIC Part Selection *</label>
                <select name="part_number" id="eq-part-number" onchange="onToeicPartChange(this.value)" style="width:100%;padding:.7rem;background:#ffffff;border:1px solid #818cf8;border-radius:.6rem;color:#0f172a;font-size:.88rem;font-weight:700;">
                    <option value="1" {{ $curPart == 1 ? 'selected' : '' }}>Part 1: Photographs (Listening — Image &amp; Audio Required, 4 Choices)</option>
                    <option value="2" {{ $curPart == 2 ? 'selected' : '' }}>Part 2: Question-Response (Listening — Audio Required, Exactly 3 Choices)</option>
                    <option value="3" {{ $curPart == 3 ? 'selected' : '' }}>Part 3: Conversations (Listening — Audio Required, 4 Choices)</option>
                    <option value="4" {{ $curPart == 4 ? 'selected' : '' }}>Part 4: Talks (Listening — Audio Required, 4 Choices)</option>
                    <option value="5" {{ $curPart == 5 ? 'selected' : '' }}>Part 5: Incomplete Sentences (Reading — Audio Forbidden, 4 Choices)</option>
                    <option value="6" {{ $curPart == 6 ? 'selected' : '' }}>Part 6: Text Completion (Reading — Passage Required, 4 Choices)</option>
                    <option value="7" {{ $curPart == 7 ? 'selected' : '' }}>Part 7: Reading Comprehension (Reading — Passage Required, 4 Choices)</option>
                </select>
                <input type="hidden" name="section" id="eq-section" value="{{ in_array($curPart, [1,2,3,4]) ? 'listening' : 'reading' }}">
            </div>

            <div id="eq-passage-container" style="display:{{ in_array($curPart, [6,7]) ? 'block' : 'none' }};background:#f8fafc;padding:1rem;border-radius:.75rem;border:1px solid #e2e8f0;">
                <label style="display:block;font-size:.85rem;font-weight:800;color:#334155;margin-bottom:.4rem;">📖 Reading Passage Text <span style="color:#f43f5e;">*</span></label>
                <textarea name="passage_text" id="eq-passage-text" rows="4" oninput="updateEditorAutoDifficulty()" placeholder="Enter passage text for Part 6 / 7..." style="width:100%;padding:.75rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.6rem;color:#0f172a;font-size:.88rem;">{{ old('passage_text', $question->passage_text ?? ($question->passage?->content ?? '')) }}</textarea>
            </div>
            @endif

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div>
                    <label style="display:block;font-size:.85rem;font-weight:700;color:#334155;margin-bottom:.4rem;">Question Type</label>
                    <select name="question_type" style="width:100%;padding:.7rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.6rem;color:#0f172a;font-size:.88rem;">
                        <option value="multiple_choice" {{ $question->question_type === 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                        <option value="single_choice" {{ $question->question_type === 'single_choice' ? 'selected' : '' }}>Single Choice</option>
                        @if(!$isToeic)
                        <option value="true_false" {{ $question->question_type === 'true_false' ? 'selected' : '' }}>True / False</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.85rem;font-weight:700;color:#334155;margin-bottom:.4rem;">⚡ Auto-Detected Difficulty</label>
                    @php
                        $diffVal = is_object($question->difficulty) ? $question->difficulty->value : ($question->difficulty ?? 'medium');
                        $diffScore = $question->difficulty_score;
                        $diffStatus = $question->difficulty_status ?? 'final';
                    @endphp
                    <div id="eq-auto-diff-card" style="padding:.65rem .85rem;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:.6rem;display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span id="eq-auto-diff-dot" style="width:8px;height:8px;border-radius:50%;background:{{ $diffVal === 'easy' ? '#10b981' : ($diffVal === 'hard' ? '#f43f5e' : '#f59e0b') }};display:inline-block;"></span>
                            <span id="eq-auto-diff-text" style="font-size:.85rem;font-weight:800;color:#0f172a;">
                                {{ ucfirst($diffStatus) }}: {{ ucfirst($diffVal) }}@if(!empty($diffScore)) (Score: {{ $diffScore }})@endif
                            </span>
                        </div>
                        <span id="eq-auto-diff-badge-source" style="font-size:.7rem;font-weight:700;text-transform:uppercase;padding:.2rem .45rem;border-radius:.35rem;background:#e2e8f0;color:#475569;">
                            System Auto
                        </span>
                    </div>
                </div>
            </div>

            {{-- Question Media Section --}}
            <div style="background:#f8fafc;padding:1.1rem;border-radius:.75rem;border:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;">
                    <div>
                        <span style="font-size:.85rem;font-weight:800;color:#0f172a;display:block;">🖼️ / 🎧 Question Media Attachments</span>
                        <span style="font-size:.75rem;color:#64748b;">Attach Question-level Photo (Image) and/or Audio Prompt (e.g. TOEIC Part 1 Photographs).</span>
                    </div>
                    <button type="button" onclick="openQuestionMediaPicker()" style="padding:.4rem .85rem;background:#4f46e5;color:#fff;border:none;border-radius:.45rem;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                        📎 + Attach Media
                    </button>
                </div>

                <input type="hidden" id="eq-media-asset-id" name="media_asset_id" value="{{ old('media_asset_id', $question->media_asset_id) }}">
                <input type="hidden" id="eq-image-url" name="image_url" value="{{ old('image_url', $question->image_url) }}">
                <input type="hidden" id="eq-audio-url" name="audio_url" value="{{ old('audio_url', $question->audio_url) }}">

                @php
                    $hasImg = !empty($question->image_url);
                    $hasAudio = !empty($question->audio_url);
                @endphp

                <div id="eq-attached-media-container" style="display:flex;flex-direction:column;gap:.6rem;margin-top:.6rem;">
                    {{-- Image preview card --}}
                    <div id="eq-preview-image-card" style="display:{{ $hasImg ? 'flex' : 'none' }};background:#ffffff;border:1px solid #e2e8f0;border-radius:.5rem;padding:.6rem;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <img id="eq-preview-image-thumb" src="{{ $question->image_url ?? '' }}" alt="Thumbnail" style="width:52px;height:52px;object-fit:cover;border-radius:.35rem;border:1px solid #cbd5e1;">
                            <div>
                                <span style="font-size:.78rem;font-weight:700;color:#0369a1;display:block;">🖼️ Attached Image (Photograph)</span>
                                <span id="eq-preview-image-title" style="font-size:.72rem;color:#475569;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">{{ $question->image_url }}</span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <button type="button" onclick="previewQuestionModalMedia('image')" style="background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                👁️ Preview
                            </button>
                            <button type="button" onclick="openQuestionMediaPicker('image')" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                Change
                            </button>
                            <button type="button" onclick="removeQuestionAttachedMedia('image')" style="background:#fff1f2;color:#be123c;border:1px solid #fecdd3;border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                ✕ Remove
                            </button>
                        </div>
                    </div>

                    {{-- Image empty placeholder --}}
                    <div id="eq-empty-image-card" style="display:{{ $hasImg ? 'none' : 'flex' }};align-items:center;justify-content:space-between;background:#ffffff;border:1px dashed #cbd5e1;border-radius:.5rem;padding:.55rem .75rem;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span style="font-size:1.1rem;">🖼️</span>
                            <div>
                                <span style="font-size:.75rem;font-weight:700;color:#334155;display:block;">Question Photograph / Image</span>
                                <span style="font-size:.68rem;color:#64748b;">No image attached</span>
                            </div>
                        </div>
                        <button type="button" onclick="openQuestionMediaPicker('image')" style="padding:.3rem .65rem;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.25rem;">
                            + Attach Image
                        </button>
                    </div>

                    {{-- Audio preview card --}}
                    <div id="eq-preview-audio-card" style="display:{{ $hasAudio ? 'flex' : 'none' }};background:#ffffff;border:1px solid #e2e8f0;border-radius:.5rem;padding:.6rem;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:.75rem;flex:1;">
                            <span style="font-size:1.5rem;">🎧</span>
                            <div style="flex:1;">
                                <span style="font-size:.78rem;font-weight:700;color:#4338ca;display:block;">🎵 Attached Audio Prompt</span>
                                <span id="eq-preview-audio-title" style="font-size:.72rem;color:#475569;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;margin-bottom:.3rem;">{{ $question->audio_url }}</span>
                                <audio id="eq-preview-audio-player" controls style="height:28px;width:100%;max-width:300px;" src="{{ $question->audio_url ?? '' }}"></audio>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <button type="button" onclick="previewQuestionModalMedia('audio')" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                👁️ Preview
                            </button>
                            <button type="button" onclick="openQuestionMediaPicker('audio')" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                Change
                            </button>
                            <button type="button" onclick="removeQuestionAttachedMedia('audio')" style="background:#fff1f2;color:#be123c;border:1px solid #fecdd3;border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                ✕ Remove
                            </button>
                        </div>
                    </div>

                    {{-- Audio empty placeholder --}}
                    <div id="eq-empty-audio-card" style="display:{{ $hasAudio ? 'none' : 'flex' }};align-items:center;justify-content:space-between;background:#ffffff;border:1px dashed #cbd5e1;border-radius:.5rem;padding:.55rem .75rem;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span style="font-size:1.1rem;">🎧</span>
                            <div>
                                <span style="font-size:.75rem;font-weight:700;color:#334155;display:block;">Question Audio Prompt</span>
                                <span style="font-size:.68rem;color:#64748b;">No audio attached</span>
                            </div>
                        </div>
                        <button type="button" onclick="openQuestionMediaPicker('audio')" style="padding:.3rem .65rem;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.25rem;">
                            + Attach Audio
                        </button>
                    </div>
                </div>
            </div>

            {{-- Choices Section --}}
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;">
                    <div>
                        <label id="eq-choices-title" style="font-size:.85rem;font-weight:700;color:#334155;margin:0;display:block;">{{ ($curPart == 2) ? 'Responses & Correct Answer Selection' : 'Choices & Correct Answer Selection' }}</label>
                        <span id="eq-choices-helper" style="font-size:.72rem;color:#64748b;">{{ ($curPart == 2) ? 'Candidates hear the three responses in the audio and select A, B, or C. Response transcripts are optional authoring metadata and are not shown during the test.' : 'Select exactly one radio button on the left to set the correct answer.' }}</span>
                    </div>
                    <button type="button" onclick="addChoiceRow()" id="eq-add-choice-btn" style="font-size:.75rem;font-weight:700;color:#4338ca;background:#eef2ff;border:1px solid #c7d2fe;padding:.25rem .65rem;border-radius:.4rem;cursor:pointer;display:{{ ($curPart == 2) ? 'none' : 'inline-block' }};">
                        + Add Choice
                    </button>
                </div>
                <div id="choices-container" style="display:flex;flex-direction:column;gap:.6rem;">
                    @php
                        if ($curPart == 2) {
                            $choicesList = $question->choices && $question->choices->isNotEmpty()
                                ? $question->choices->take(3)
                                : collect([
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => true],
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                ]);
                        } else {
                            $choicesList = $question->choices && $question->choices->isNotEmpty()
                                ? $question->choices
                                : collect([
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                    (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                ]);
                        }
                    @endphp
                    @foreach($choicesList as $cIdx => $cObj)
                    @php
                        $isCorrectChoice = is_object($cObj) && (bool) $cObj->is_correct;
                        if (old('correct_choice') !== null) {
                            $isCorrectChoice = (string) old('correct_choice') === (string) $cIdx;
                        }
                    @endphp
                    <div class="choice-row" id="edit-choice-row-{{ $cIdx }}" style="display:flex;align-items:center;gap:.75rem;background:{{ $isCorrectChoice ? '#ecfdf5' : '#f8fafc' }};padding:.65rem .85rem;border-radius:.6rem;border:1px solid {{ $isCorrectChoice ? '#10b981' : '#e2e8f0' }};transition:all .15s ease;">
                        <input type="radio" name="correct_choice" value="{{ $cIdx }}" id="edit-correct-{{ $cIdx }}" onchange="updateEditorCorrectChoice()" {{ $isCorrectChoice ? 'checked' : '' }} style="accent-color:#10b981;width:1.1rem;height:1.1rem;cursor:pointer;">
                        <label for="edit-correct-{{ $cIdx }}" class="choice-label" style="font-weight:800;color:#4f46e5;font-size:.85rem;width:1.5rem;cursor:pointer;margin:0;">{{ chr(65 + $cIdx) }}.</label>
                        <input type="text" name="choices[{{ $cIdx }}]" oninput="updateEditorAutoDifficulty()" value="{{ is_object($cObj) ? ($cObj->content ?? $cObj->choice_text) : '' }}" placeholder="{{ ($curPart == 2) ? 'Optional response transcript...' : 'Option ' . chr(65 + $cIdx) . ' text' }}" style="flex:1;padding:.5rem .75rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.5rem;color:#0f172a;font-size:.88rem;">
                        <span class="correct-indicator" id="edit-correct-badge-{{ $cIdx }}" style="display:{{ $isCorrectChoice ? 'inline-flex' : 'none' }};align-items:center;gap:.3rem;padding:.3rem .65rem;border-radius:.4rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;font-size:.72rem;font-weight:800;letter-spacing:.03em;white-space:nowrap;">
                            ✓ CORRECT ANSWER
                        </span>
                        <button type="button" onclick="removeChoiceRow(this)" class="btn-remove-choice" style="color:#e11d48;background:none;border:none;cursor:pointer;font-size:1.1rem;padding:0 .25rem;line-height:1;display:{{ ($curPart == 2) ? 'none' : 'inline-block' }};" title="Remove choice">✕</button>
                    </div>
                    @endforeach
                </div>
            </div>

            <script>
            function updateEditorAutoDifficulty() {
                const form = document.getElementById('edit-question-form');
                if (!form) return;

                const partVal = parseInt(form.querySelector('#eq-part-number')?.value || '1');
                const prompt = (form.querySelector('textarea[name="prompt"]')?.value || '').trim();
                const choices = Array.from(form.querySelectorAll('#choices-container input[type="text"]'))
                    .map(input => input.value.trim())
                    .filter(v => v.length > 0);
                const hasImg = document.getElementById('eq-preview-image-card')?.style.display !== 'none' && !!document.getElementById('eq-image-url')?.value;
                const hasAudio = document.getElementById('eq-preview-audio-card')?.style.display !== 'none' && !!document.getElementById('eq-audio-url')?.value;
                const passageText = (form.querySelector('#eq-passage-text')?.value || '').trim();

                const dot = document.getElementById('eq-auto-diff-dot');
                const text = document.getElementById('eq-auto-diff-text');
                if (!text || !dot) return;

                let status = 'pending';
                let level = 'Medium';
                let dotColor = '#f59e0b';

                if (partVal === 1) {
                    if (!hasImg && !hasAudio && choices.length === 0 && !prompt) {
                        status = 'Pending';
                        text.textContent = 'Pending: Waiting for required inputs';
                        dotColor = '#94a3b8';
                    } else if (hasImg && hasAudio && choices.length >= 4) {
                        status = 'Final';
                        let totalWords = choices.reduce((acc, c) => acc + c.split(/\s+/).filter(Boolean).length, 0);
                        let avg = choices.length > 0 ? totalWords / choices.length : 0;
                        level = avg >= 8 ? 'Hard' : (avg <= 5.5 ? 'Easy' : 'Medium');
                        dotColor = level === 'Easy' ? '#10b981' : (level === 'Hard' ? '#f43f5e' : '#f59e0b');
                        text.textContent = `Final: ${level}`;
                    } else {
                        status = 'Provisional';
                        text.textContent = 'Provisional: Medium';
                        dotColor = '#0ea5e9';
                    }
                } else if (partVal === 2) {
                    if (!hasAudio && choices.length === 0 && !prompt) {
                        status = 'Pending';
                        text.textContent = 'Pending: Waiting for inputs';
                        dotColor = '#94a3b8';
                    } else if (choices.length >= 3 && (prompt || hasAudio)) {
                        status = 'Final';
                        level = prompt.toLowerCase().match(/^(when|where|who|what time)\b/) ? 'Easy' : (prompt.toLowerCase().match(/^(why don\'t|could you|would you)\b/) ? 'Medium' : 'Hard');
                        dotColor = level === 'Easy' ? '#10b981' : (level === 'Hard' ? '#f43f5e' : '#f59e0b');
                        text.textContent = `Final: ${level}`;
                    } else {
                        status = 'Provisional';
                        text.textContent = 'Provisional: Medium';
                        dotColor = '#0ea5e9';
                    }
                } else if (partVal >= 3 && partVal <= 4) {
                    if (!hasAudio && choices.length === 0 && !prompt) {
                        status = 'Pending';
                        text.textContent = 'Pending: Waiting for inputs';
                        dotColor = '#94a3b8';
                    } else if (prompt && choices.length >= 4) {
                        status = 'Final';
                        level = prompt.toLowerCase().match(/(imply|suggest|probably do next|look at)/) ? 'Hard' : (prompt.toLowerCase().match(/(problem|where|what time)/) ? 'Easy' : 'Medium');
                        dotColor = level === 'Easy' ? '#10b981' : (level === 'Hard' ? '#f43f5e' : '#f59e0b');
                        text.textContent = `Final: ${level}`;
                    } else {
                        status = 'Provisional';
                        text.textContent = 'Provisional: Medium';
                        dotColor = '#0ea5e9';
                    }
                } else if (partVal === 5) {
                    if (!prompt && choices.length === 0) {
                        status = 'Pending';
                        text.textContent = 'Pending: Waiting for inputs';
                        dotColor = '#94a3b8';
                    } else if (prompt && choices.length >= 4) {
                        status = 'Final';
                        let words = prompt.split(/\s+/).filter(Boolean).length;
                        level = words >= 20 ? 'Hard' : (words <= 12 ? 'Easy' : 'Medium');
                        dotColor = level === 'Easy' ? '#10b981' : (level === 'Hard' ? '#f43f5e' : '#f59e0b');
                        text.textContent = `Final: ${level}`;
                    } else {
                        status = 'Provisional';
                        text.textContent = 'Provisional: Medium';
                        dotColor = '#0ea5e9';
                    }
                } else if (partVal === 6 || partVal === 7) {
                    if (!passageText && !prompt && choices.length === 0) {
                        status = 'Pending';
                        text.textContent = 'Pending: Waiting for inputs';
                        dotColor = '#94a3b8';
                    } else if (passageText && prompt && choices.length >= 4) {
                        status = 'Final';
                        level = prompt.toLowerCase().match(/(suggest|infer|not mentioned|except)/) ? 'Hard' : 'Medium';
                        dotColor = level === 'Easy' ? '#10b981' : (level === 'Hard' ? '#f43f5e' : '#f59e0b');
                        text.textContent = `Final: ${level}`;
                    } else {
                        status = 'Provisional';
                        text.textContent = 'Provisional: Medium';
                        dotColor = '#0ea5e9';
                    }
                } else {
                    if (!prompt && choices.length === 0) {
                        status = 'Pending';
                        text.textContent = 'Pending: Waiting for inputs';
                        dotColor = '#94a3b8';
                    } else if (prompt && choices.length >= 2) {
                        status = 'Final';
                        text.textContent = 'Final: Medium';
                        dotColor = '#f59e0b';
                    } else {
                        status = 'Provisional';
                        text.textContent = 'Provisional: Medium';
                        dotColor = '#0ea5e9';
                    }
                }

                dot.style.background = dotColor;
            }

            function onToeicPartChange(part) {
                part = parseInt(part);
                const secInput = document.getElementById('eq-section');
                if (secInput) {
                    secInput.value = (part >= 1 && part <= 4) ? 'listening' : 'reading';
                }

                const passageBox = document.getElementById('eq-passage-container');
                if (passageBox) {
                    passageBox.style.display = (part === 6 || part === 7) ? 'block' : 'none';
                }

                const addChoiceBtn = document.getElementById('eq-add-choice-btn') || document.querySelector('button[onclick="addChoiceRow()"]');
                const choiceRows = document.querySelectorAll('#choices-container .choice-row');
                const titleLabel = document.getElementById('eq-choices-title');
                const helperSpan = document.getElementById('eq-choices-helper');

                if (part === 2) {
                    if (titleLabel) titleLabel.textContent = 'Responses & Correct Answer Selection';
                    if (helperSpan) helperSpan.textContent = 'Candidates hear the three responses in the audio and select A, B, or C. Response transcripts are optional authoring metadata and are not shown during the test.';
                    if (addChoiceBtn) addChoiceBtn.style.display = 'none';
                    if (choiceRows.length > 3) {
                        for (let i = 3; i < choiceRows.length; i++) {
                            choiceRows[i].remove();
                        }
                    }
                    document.querySelectorAll('.btn-remove-choice').forEach(btn => btn.style.display = 'none');
                    document.querySelectorAll('#choices-container input[type="text"]').forEach(input => {
                        input.placeholder = 'Optional response transcript...';
                    });
                } else {
                    if (titleLabel) titleLabel.textContent = 'Choices & Correct Answer Selection';
                    if (helperSpan) helperSpan.textContent = 'Select exactly one radio button on the left to set the correct answer.';
                    if (addChoiceBtn) addChoiceBtn.style.display = 'inline-block';
                    document.querySelectorAll('.btn-remove-choice').forEach(btn => btn.style.display = 'inline-block');
                    const currentCount = document.querySelectorAll('#choices-container .choice-row').length;
                    if (currentCount < 4) {
                        for (let i = currentCount; i < 4; i++) {
                            addChoiceRow();
                        }
                    }
                    reindexChoices();
                }

                updateEditorAutoDifficulty();
            }

            document.addEventListener('DOMContentLoaded', function() {
                const partSelect = document.getElementById('eq-part-number');
                if (partSelect) {
                    onToeicPartChange(partSelect.value);
                }
            });

            function updateEditorCorrectChoice() {
                const selected = document.querySelector('#edit-question-form input[name="correct_choice"]:checked');
                const errBox = document.getElementById('edit-q-validation-error');
                if (errBox && selected) errBox.style.display = 'none';

                const rows = document.querySelectorAll('#choices-container .choice-row');
                rows.forEach((row) => {
                    const radio = row.querySelector('input[name="correct_choice"]');
                    const badge = row.querySelector('.correct-indicator');
                    const isSelected = selected && radio && selected.value === radio.value;

                    if (badge) {
                        badge.style.display = isSelected ? 'inline-flex' : 'none';
                    }
                    row.style.borderColor = isSelected ? '#10b981' : '#e2e8f0';
                    row.style.background = isSelected ? '#ecfdf5' : '#f8fafc';
                });
            }

            function validateEditQuestionForm(form) {
                const qType = form.querySelector('select[name="question_type"]')?.value || 'multiple_choice';
                if (['multiple_choice', 'single_choice'].includes(qType) || form.querySelectorAll('.choice-row').length > 0) {
                    const selected = form.querySelector('input[name="correct_choice"]:checked');
                    if (!selected) {
                        const errBox = document.getElementById('edit-q-validation-error');
                        if (errBox) {
                            errBox.textContent = 'Please select the correct answer.';
                            errBox.style.display = 'block';
                            errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                        return false;
                    }
                }
                return true;
            }

            function reindexChoices() {
                const rows = document.querySelectorAll('#choices-container .choice-row');
                const partSelect = document.getElementById('eq-part-number');
                const isPart2 = partSelect && parseInt(partSelect.value) === 2;

                rows.forEach((row, idx) => {
                    const label = String.fromCharCode(65 + idx);
                    const radio = row.querySelector('input[type="radio"]');
                    const labelSpan = row.querySelector('.choice-label');
                    const textInput = row.querySelector('input[type="text"]');
                    const badge = row.querySelector('.correct-indicator');
                    const removeBtn = row.querySelector('.btn-remove-choice');

                    row.id = `edit-choice-row-${idx}`;
                    if (radio) {
                        radio.value = idx;
                        radio.id = `edit-correct-${idx}`;
                        radio.onchange = updateEditorCorrectChoice;
                    }
                    if (labelSpan) {
                        labelSpan.textContent = label + '.';
                        labelSpan.htmlFor = `edit-correct-${idx}`;
                    }
                    if (textInput) {
                        textInput.name = `choices[${idx}]`;
                        textInput.placeholder = isPart2 ? 'Optional response transcript...' : `Option ${label} text`;
                    }
                    if (badge) {
                        badge.id = `edit-correct-badge-${idx}`;
                    }
                    if (removeBtn) {
                        removeBtn.style.display = (isPart2 || rows.length <= 2) ? 'none' : 'inline-block';
                    }
                });
                updateEditorCorrectChoice();
            }

            function addChoiceRow() {
                const container = document.getElementById('choices-container');
                const rows = container.querySelectorAll('.choice-row');
                const idx = rows.length;
                const label = String.fromCharCode(65 + idx);
                const partSelect = document.getElementById('eq-part-number');
                const isPart2 = partSelect && parseInt(partSelect.value) === 2;

                const div = document.createElement('div');
                div.className = 'choice-row';
                div.id = `edit-choice-row-${idx}`;
                div.style.cssText = 'display:flex;align-items:center;gap:.75rem;background:#f8fafc;padding:.65rem .85rem;border-radius:.6rem;border:1px solid #e2e8f0;transition:all .15s ease;';
                div.innerHTML = `
                    <input type="radio" name="correct_choice" value="${idx}" id="edit-correct-${idx}" onchange="updateEditorCorrectChoice()" style="accent-color:#10b981;width:1.1rem;height:1.1rem;cursor:pointer;">
                    <label for="edit-correct-${idx}" class="choice-label" style="font-weight:800;color:#4f46e5;font-size:.85rem;width:1.5rem;cursor:pointer;margin:0;">${label}.</label>
                    <input type="text" name="choices[${idx}]" oninput="updateEditorAutoDifficulty()" placeholder="${isPart2 ? 'Optional response transcript...' : 'Option ' + label + ' text'}" style="flex:1;padding:.5rem .75rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.5rem;color:#0f172a;font-size:.88rem;">
                    <span class="correct-indicator" id="edit-correct-badge-${idx}" style="display:none;align-items:center;gap:.3rem;padding:.3rem .65rem;border-radius:.4rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;font-size:.72rem;font-weight:800;letter-spacing:.03em;white-space:nowrap;">
                        ✓ CORRECT ANSWER
                    </span>
                    <button type="button" onclick="removeChoiceRow(this)" class="btn-remove-choice" style="color:#e11d48;background:none;border:none;cursor:pointer;font-size:1.1rem;padding:0 .25rem;line-height:1;display:${isPart2 ? 'none' : 'inline-block'};" title="Remove choice">✕</button>
                `;
                container.appendChild(div);
                reindexChoices();
            }

            function removeChoiceRow(btn) {
                const rows = document.querySelectorAll('#choices-container .choice-row');
                if (rows.length <= 2) return;
                btn.closest('.choice-row').remove();
                reindexChoices();
            }

            document.addEventListener('DOMContentLoaded', function() {
                reindexChoices();
                updateEditorCorrectChoice();
            });
            </script>

            <div>
                <label style="display:block;font-size:.85rem;font-weight:700;color:#334155;margin-bottom:.4rem;">Explanation / Rationale</label>
                <textarea name="explanation" rows="2" style="width:100%;padding:.75rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.6rem;color:#0f172a;font-size:.88rem;">{{ old('explanation', $question->explanation) }}</textarea>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;border-top:1px solid #e2e8f0;padding-top:1.25rem;">
                <a href="{{ route('teacher.tests.show', $test->id) }}" style="padding:.75rem 1.25rem;background:#f8fafc;color:#334155;border:1px solid #cbd5e1;border-radius:.65rem;font-size:.85rem;font-weight:700;text-decoration:none;">
                    Cancel
                </a>
                <button type="submit" style="padding:.75rem 1.75rem;background:#4f46e5;color:#fff;border:none;border-radius:.65rem;font-size:.88rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(79,70,229,.25);">
                    💾 Save Question Edits & Return to Summary
                </button>
            </div>
        </form>
    </div>
</div>

@push('modals')
{{-- General Asset Preview Modal (Root Portal Layer) --}}
<div id="asset-preview-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:10002;align-items:center;justify-content:center;padding:1.5rem;" onclick="closeAssetPreviewModal(event)">
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:1rem;max-width:680px;width:100%;max-height:85vh;overflow-y:auto;padding:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.15);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:.6rem;">
            <div id="apm-title" style="font-size:1rem;font-weight:800;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Preview Asset</div>
            <button type="button" onclick="closeAssetPreviewModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;">×</button>
        </div>
        <div id="apm-content" style="display:flex;justify-content:center;align-items:center;min-height:180px;">
        </div>
    </div>
</div>

{{-- Question Media Picker Modal (Root Portal Layer) --}}
<div id="question-media-picker-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:10001;align-items:center;justify-content:center;padding:1rem;" onclick="closeQuestionMediaPicker(event)">
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:1.25rem;max-width:720px;width:100%;max-height:90vh;display:flex;flex-direction:column;padding:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.18);gap:1rem;" onclick="event.stopPropagation()">

        {{-- Header --}}
        <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #e2e8f0;padding-bottom:.75rem;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:.5rem;">
                    <svg style="width:1.25rem;height:1.25rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                    <span>Attach Question Media</span>
                </div>
                <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;font-weight:500;">
                    Upload a new working file or choose from existing media library.
                </div>
            </div>
            <button type="button" onclick="closeQuestionMediaPicker()" aria-label="Close media picker" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1;padding:.25rem;border-radius:.35rem;">✕</button>
        </div>

        {{-- SECTION A: UPLOAD NEW FILE --}}
        <div style="display:flex;flex-direction:column;gap:.5rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#1e293b;">
                        Upload New File
                    </div>
                    <div style="font-size:.7rem;color:#64748b;margin-top:.15rem;">
                        New uploads are saved to My Media and can be attached immediately.
                    </div>
                </div>
                <div id="eqm-accepted-formats-label" style="font-size:.7rem;color:#64748b;font-weight:700;">
                    Max 10 MB
                </div>
            </div>

            {{-- Dropzone Panel --}}
            <div id="eqm-dropzone" ondragover="handleEditorMediaDragOver(event)" ondragleave="handleEditorMediaDragLeave(event)" ondrop="handleEditorMediaDrop(event)" style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:.85rem;padding:1.1rem;text-align:center;transition:all .2s ease;">

                {{-- Accessible Native Input --}}
                <input type="file" id="eqm-direct-file-input" accept="image/jpeg,image/png,image/webp,audio/mpeg,audio/mp3,audio/wav,audio/x-m4a,audio/m4a,application/pdf" onchange="handleEditorMediaFileSelect(this)" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" aria-label="Select media file to upload">

                {{-- State 1: Empty / No file selected --}}
                <div id="eqm-empty-upload-state" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;">
                    <div style="width:2.5rem;height:2.5rem;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;">
                        <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size:.8rem;font-weight:700;color:#1e293b;">
                            Select a file to upload or drag and drop here
                        </div>
                        <div id="eqm-no-file-text" style="font-size:.72rem;color:#64748b;margin-top:.15rem;">
                            No file selected
                        </div>
                        <div id="eqm-accepted-formats-text" style="font-size:.7rem;color:#94a3b8;font-family:monospace;margin-top:.25rem;">
                            JPG, JPEG, PNG, WebP, MP3, M4A, WAV, PDF
                        </div>
                    </div>
                    <div style="margin-top:.25rem;">
                        <button type="button" id="eqm-choose-file-btn" onclick="document.getElementById('eqm-direct-file-input').click()" style="padding:.45rem 1.1rem;background:#4f46e5;color:#fff;border:none;border-radius:.6rem;font-size:.75rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 1px 2px rgba(0,0,0,.05);">
                            <svg style="width:.9rem;height:.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>Choose File</span>
                        </button>
                    </div>
                </div>

                {{-- State 2: Selected File State --}}
                <div id="eqm-selected-upload-state" style="display:none;background:#ffffff;border:1px solid #e2e8f0;border-radius:.65rem;padding:.75rem;box-shadow:0 1px 3px rgba(0,0,0,.05);">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;">
                        <div style="display:flex;align-items:center;gap:.75rem;min-width:0;text-align:left;">
                            <div id="eqm-selected-icon-container" style="width:2.25rem;height:2.25rem;border-radius:.45rem;background:#eef2ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;flex-shrink:0;">
                                <svg style="width:1.15rem;height:1.15rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div style="min-width:0;">
                                <div style="display:flex;align-items:center;gap:.4rem;">
                                    <span id="eqm-selected-type-badge" style="font-size:.65rem;font-weight:900;text-transform:uppercase;padding:.1rem .35rem;border-radius:.25rem;background:#e0e7ff;color:#3730a3;">FILE</span>
                                    <span id="eqm-selected-filesize" style="font-size:.7rem;color:#64748b;font-family:monospace;"></span>
                                </div>
                                <div id="eqm-selected-filename" style="font-size:.8rem;font-weight:800;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:.15rem;max-width:340px;"></div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.4rem;flex-shrink:0;">
                            <button type="button" onclick="document.getElementById('eqm-direct-file-input').click()" style="padding:.35rem .65rem;background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">
                                Change File
                            </button>
                            <button type="button" onclick="clearEditorMediaFileSelection()" style="padding:.35rem .65rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;" title="Remove selected file">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Action Row --}}
                <div style="margin-top:.75rem;display:flex;justify-content:flex-end;align-items:center;gap:.5rem;">
                    <button type="button" id="eqm-upload-btn" onclick="uploadQuestionMediaFile()" disabled aria-disabled="true" style="padding:.45rem 1.1rem;background:#e2e8f0;color:#94a3b8;border:1px solid #cbd5e1;border-radius:.6rem;font-size:.75rem;font-weight:800;cursor:not-allowed;display:inline-flex;align-items:center;gap:.4rem;transition:all .2s ease;">
                        <svg style="width:.9rem;height:.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span>Upload &amp; Attach</span>
                    </button>
                </div>

                {{-- Inline Error Box --}}
                <div id="eqm-upload-error" style="display:none;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;padding:.5rem .75rem;border-radius:.55rem;font-size:.72rem;font-weight:700;text-align:left;margin-top:.6rem;"></div>
            </div>
        </div>

        {{-- VISUAL SEPARATOR --}}
        <div style="position:relative;margin:.25rem 0;text-align:center;">
            <div style="position:absolute;top:50%;left:0;right:0;border-top:1px solid #e2e8f0;z-index:1;"></div>
            <span style="position:relative;z-index:2;background:#ffffff;padding:0 .75rem;font-size:.68rem;font-weight:900;letter-spacing:.05em;color:#94a3b8;text-transform:uppercase;">
                OR CHOOSE EXISTING MEDIA
            </span>
        </div>

        {{-- SECTION B: CHOOSE EXISTING MEDIA (SOURCE TABS + FILTERS) --}}
        <div style="display:flex;flex-direction:column;gap:.6rem;flex:1;min-height:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                <div>
                    <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#1e293b;">
                        Choose Existing Media
                    </div>
                    <div style="font-size:.7rem;color:#64748b;">
                        Browse your working drafts or approved institutional media.
                    </div>
                </div>

                {{-- Source Selector Tabs: [ My Media ] [ Institutional Library ] --}}
                <div style="display:flex;align-items:center;gap:.25rem;padding:.2rem;background:#f1f5f9;border-radius:.5rem;border:1px solid #cbd5e1;" role="tablist">
                    <button type="button" id="eqm-source-my-btn" onclick="setEditorMediaSource('my', event)" role="tab" aria-selected="true" style="padding:.25rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:800;background:#4f46e5;color:#fff;border:none;cursor:pointer;">
                        My Media
                    </button>
                    <button type="button" id="eqm-source-inst-btn" onclick="setEditorMediaSource('institutional', event)" role="tab" aria-selected="false" style="padding:.25rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:700;background:transparent;color:#475569;border:none;cursor:pointer;">
                        Institutional Library
                    </button>
                </div>
            </div>

            {{-- Library Filters & Search --}}
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between;">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                    <button type="button" onclick="filterQuestionMediaModal('all', event)" class="eqm-filter-btn active" style="padding:.35rem .7rem;background:#4f46e5;color:#fff;border:none;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">All Media</button>
                    <button type="button" onclick="filterQuestionMediaModal('image', event)" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">Images</button>
                    <button type="button" onclick="filterQuestionMediaModal('audio', event)" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">Audio Tracks</button>
                    <button type="button" onclick="filterQuestionMediaModal('passage', event)" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">Passages</button>
                    <button type="button" onclick="filterQuestionMediaModal('pdf', event)" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">PDFs</button>
                </div>
                <div style="position:relative;min-width:180px;">
                    <input type="text" id="eqm-search-input" onkeyup="searchQuestionMediaModal(this.value)" placeholder="Search media..." style="padding:.35rem .7rem .35rem 1.8rem;background:#ffffff;border:1px solid #cbd5e1;border-radius:.45rem;color:#0f172a;font-size:.75rem;width:100%;box-sizing:border-box;">
                    <svg style="position:absolute;left:.55rem;top:.55rem;width:.75rem;height:.75rem;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            {{-- Media Grid Container --}}
            <div id="eqm-media-list-container" style="flex:1;min-height:180px;max-height:260px;overflow-y:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.65rem;padding:.75rem;display:grid;grid-template-columns:repeat(auto-fill, minmax(210px, 1fr));gap:.6rem;">
                <div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">Loading media library...</div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="display:flex;justify-content:flex-end;gap:.5rem;border-top:1px solid #e2e8f0;padding-top:.75rem;">
            <button type="button" onclick="closeQuestionMediaPicker()" style="padding:.45rem 1.1rem;background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;border-radius:.6rem;font-size:.75rem;font-weight:700;cursor:pointer;">Close</button>
        </div>
    </div>
</div>
@endpush

<script>
let questionMediaLibrary = [];
let currentEditorMediaSource = 'my';
let currentEditorMediaType = 'all';

function setEditorMediaSource(source, e = null) {
    currentEditorMediaSource = source;
    const myBtn = document.getElementById('eqm-source-my-btn');
    const instBtn = document.getElementById('eqm-source-inst-btn');
    if (myBtn && instBtn) {
        if (source === 'my') {
            myBtn.style.background = '#4f46e5';
            myBtn.style.color = '#ffffff';
            myBtn.style.fontWeight = '800';
            myBtn.setAttribute('aria-selected', 'true');
            instBtn.style.background = 'transparent';
            instBtn.style.color = '#475569';
            instBtn.style.fontWeight = '700';
            instBtn.setAttribute('aria-selected', 'false');
        } else {
            instBtn.style.background = '#4f46e5';
            instBtn.style.color = '#ffffff';
            instBtn.style.fontWeight = '800';
            instBtn.setAttribute('aria-selected', 'true');
            myBtn.style.background = 'transparent';
            myBtn.style.color = '#475569';
            myBtn.style.fontWeight = '700';
            myBtn.setAttribute('aria-selected', 'false');
        }
    }
    fetchQuestionMediaLibrary(() => {
        filterQuestionMediaModal(currentEditorMediaType);
    });
}

function openQuestionMediaPicker(defaultType = 'all') {
    currentEditorMediaType = defaultType;
    const modal = document.getElementById('question-media-picker-modal');
    if (modal) modal.style.display = 'flex';

    const formatsText = document.getElementById('eqm-accepted-formats-text');
    const formatsLabel = document.getElementById('eqm-accepted-formats-label');
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

    clearEditorMediaFileSelection();
    fetchQuestionMediaLibrary(() => {
        if (defaultType && defaultType !== 'all') {
            filterQuestionMediaModal(defaultType);
        }
    });
}

function closeQuestionMediaPicker(e) {
    if (!e || e.target === document.getElementById('question-media-picker-modal')) {
        const modal = document.getElementById('question-media-picker-modal');
        if (modal) modal.style.display = 'none';
        clearEditorMediaFileSelection();
    }
}

function handleEditorMediaFileSelect(input) {
    const file = input?.files?.[0];
    const emptyState = document.getElementById('eqm-empty-upload-state');
    const selectedState = document.getElementById('eqm-selected-upload-state');
    const filenameEl = document.getElementById('eqm-selected-filename');
    const filesizeEl = document.getElementById('eqm-selected-filesize');
    const typeBadgeEl = document.getElementById('eqm-selected-type-badge');
    const uploadBtn = document.getElementById('eqm-upload-btn');
    const errBox = document.getElementById('eqm-upload-error');

    if (errBox) {
        errBox.style.display = 'none';
        errBox.textContent = '';
    }

    if (!file) {
        clearEditorMediaFileSelection();
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

    if (emptyState) emptyState.style.display = 'none';
    if (selectedState) selectedState.style.display = 'block';

    if (uploadBtn) {
        uploadBtn.disabled = false;
        uploadBtn.removeAttribute('aria-disabled');
        uploadBtn.style.background = '#059669';
        uploadBtn.style.color = '#ffffff';
        uploadBtn.style.border = 'none';
        uploadBtn.style.cursor = 'pointer';
        uploadBtn.style.boxShadow = '0 2px 4px rgba(5,150,105,.25)';
    }
}

function clearEditorMediaFileSelection() {
    const input = document.getElementById('eqm-direct-file-input');
    if (input) input.value = '';

    const emptyState = document.getElementById('eqm-empty-upload-state');
    const selectedState = document.getElementById('eqm-selected-upload-state');
    const uploadBtn = document.getElementById('eqm-upload-btn');
    const errBox = document.getElementById('eqm-upload-error');

    if (emptyState) emptyState.style.display = 'flex';
    if (selectedState) selectedState.style.display = 'none';
    if (errBox) {
        errBox.style.display = 'none';
        errBox.textContent = '';
    }

    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.setAttribute('aria-disabled', 'true');
        uploadBtn.style.background = '#e2e8f0';
        uploadBtn.style.color = '#94a3b8';
        uploadBtn.style.border = '1px solid #cbd5e1';
        uploadBtn.style.cursor = 'not-allowed';
        uploadBtn.style.boxShadow = 'none';
        uploadBtn.innerHTML = `
            <svg style="width:.9rem;height:.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            <span>Upload &amp; Attach</span>
        `;
    }
}

function handleEditorMediaDragOver(e) {
    e.preventDefault();
    const dropzone = document.getElementById('eqm-dropzone');
    if (dropzone) {
        dropzone.style.borderColor = '#6366f1';
        dropzone.style.background = '#eef2ff';
    }
}

function handleEditorMediaDragLeave(e) {
    e.preventDefault();
    const dropzone = document.getElementById('eqm-dropzone');
    if (dropzone) {
        dropzone.style.borderColor = '#cbd5e1';
        dropzone.style.background = '#f8fafc';
    }
}

function handleEditorMediaDrop(e) {
    e.preventDefault();
    const dropzone = document.getElementById('eqm-dropzone');
    if (dropzone) {
        dropzone.style.borderColor = '#cbd5e1';
        dropzone.style.background = '#f8fafc';
    }
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        const input = document.getElementById('eqm-direct-file-input');
        if (input) {
            input.files = e.dataTransfer.files;
            handleEditorMediaFileSelect(input);
        }
    }
}

function fetchQuestionMediaLibrary(onLoaded) {
    const container = document.getElementById('eqm-media-list-container');
    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">Loading media library...</div>';
    fetch(`/admin/media/list?source=${currentEditorMediaSource}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.data)) {
                questionMediaLibrary = data.data;
                renderQuestionMediaGrid(questionMediaLibrary);
                if (typeof onLoaded === 'function') onLoaded();
            } else {
                container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#f43f5e;font-size:.75rem;padding:2rem;">Failed to load media library.</div>';
            }
        })
        .catch(() => {
            container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#f43f5e;font-size:.75rem;padding:2rem;">Error communicating with media server.</div>';
        });
}

function filterQuestionMediaModal(type, e = null) {
    currentEditorMediaType = type;
    const buttons = document.querySelectorAll('.eqm-filter-btn');
    buttons.forEach(btn => {
        btn.style.background = '#f1f5f9';
        btn.style.color = '#475569';
        btn.style.border = '1px solid #cbd5e1';
    });

    // Highlight matching button
    if (e && e.target && e.target.classList.contains('eqm-filter-btn')) {
        e.target.style.background = '#4f46e5';
        e.target.style.color = '#ffffff';
        e.target.style.border = 'none';
    } else {
        buttons.forEach(btn => {
            const text = btn.textContent.toLowerCase();
            if ((type === 'all' && text.includes('all')) ||
                (type === 'image' && text.includes('images')) ||
                (type === 'audio' && text.includes('audio')) ||
                (type === 'passage' && text.includes('passages')) ||
                (type === 'pdf' && text.includes('pdfs'))) {
                btn.style.background = '#4f46e5';
                btn.style.color = '#ffffff';
                btn.style.border = 'none';
            }
        });
    }

    const query = (document.getElementById('eqm-search-input')?.value || '').toLowerCase();
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
    const container = document.getElementById('eqm-media-list-container');
    if (!items || items.length === 0) {
        container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">No media assets found in library.</div>';
        return;
    }

    container.innerHTML = '';
    items.forEach(item => {
        const card = document.createElement('div');
        card.style.cssText = 'background:#ffffff;border:1px solid #e2e8f0;border-radius:.65rem;padding:.75rem;display:flex;flex-direction:column;justify-content:space-between;gap:.5rem;box-shadow:0 1px 2px rgba(0,0,0,.03);';

        let icon = '<svg style="width:.85rem;height:.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>';
        if (item.type === 'audio') {
            icon = '<svg style="width:.85rem;height:.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>';
        } else if (item.type === 'image') {
            icon = '<svg style="width:.85rem;height:.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        } else if (item.type === 'pdf') {
            icon = '<svg style="width:.85rem;height:.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
        }

        card.innerHTML = `
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
                    <span style="font-size:.65rem;font-weight:800;color:#4338ca;background:#eef2ff;border:1px solid #c7d2fe;padding:.1rem .35rem;border-radius:.25rem;text-transform:uppercase;display:inline-flex;align-items:center;gap:.25rem;">
                        ${icon} ${item.type}
                    </span>
                    <span style="font-size:.68rem;color:#64748b;font-weight:500;">${item.size || ''}</span>
                </div>
                <div style="font-size:.78rem;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${item.title || item.name}">
                    ${item.title || item.name}
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.4rem;padding-top:.4rem;border-top:1px solid #f1f5f9;">
                <button type="button" onclick="previewAssetModal('${item.id}', '${(item.title || item.name || '').replace(/'/g, "\\'")}', '${item.type}', '${item.url}')" style="padding:.25rem .55rem;background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;border-radius:.35rem;font-size:.7rem;font-weight:700;cursor:pointer;">
                    Preview
                </button>
                <button type="button" onclick='applySelectedQuestionMedia(${JSON.stringify(item)})' style="padding:.25rem .75rem;background:#4f46e5;color:#fff;border:none;border-radius:.35rem;font-size:.7rem;font-weight:700;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.05);">
                    Attach
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

function uploadQuestionMediaFile() {
    const fileInput = document.getElementById('eqm-direct-file-input');
    const file = fileInput?.files?.[0];
    const btn = document.getElementById('eqm-upload-btn');
    const errBox = document.getElementById('eqm-upload-error');

    if (errBox) {
        errBox.style.display = 'none';
        errBox.textContent = '';
    }

    if (!file) {
        if (errBox) {
            errBox.textContent = '⚠️ Please select a file to upload first.';
            errBox.style.display = 'block';
        }
        return;
    }

    btn.disabled = true;
    btn.setAttribute('aria-disabled', 'true');
    btn.innerHTML = `
        <svg style="width:.9rem;height:.9rem;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
            <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Uploading...</span>
    `;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

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
        if (data.success && assetId) {
            const item = {
                id: assetId,
                title: assetData.title || assetData.filename || assetData.original_name,
                name: assetData.filename || assetData.original_name,
                url: assetData.url || (assetId ? `/media/${assetId}/preview` : ''),
                type: assetData.type,
                size: assetData.size
            };
            questionMediaLibrary.unshift(item);
            applySelectedQuestionMedia(item);
            clearEditorMediaFileSelection();
        } else {
            if (errBox) {
                errBox.textContent = '⚠️ ' + (data.message || 'Error uploading media asset.');
                errBox.style.display = 'block';
            }
        }
    })
    .catch((err) => {
        if (errBox) {
            errBox.textContent = '⚠️ ' + (err.message || 'Communication error while uploading media asset.');
            errBox.style.display = 'block';
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.removeAttribute('aria-disabled');
        btn.innerHTML = `
            <svg style="width:.9rem;height:.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            <span>Upload &amp; Attach</span>
        `;
    });
}

function applySelectedQuestionMedia(item) {
    const mediaIdInput = document.getElementById('eq-media-asset-id');
    const imgInput = document.getElementById('eq-image-url');
    const audioInput = document.getElementById('eq-audio-url');
    const noMediaMsg = document.getElementById('eq-no-media-msg');
    const emptyImg = document.getElementById('eq-empty-image-card');
    const emptyAudio = document.getElementById('eq-empty-audio-card');

    if (!mediaIdInput.value) {
        mediaIdInput.value = item.id;
    }

    if (item.type === 'image') {
        imgInput.value = item.url;
        const card = document.getElementById('eq-preview-image-card');
        const thumb = document.getElementById('eq-preview-image-thumb');
        const title = document.getElementById('eq-preview-image-title');
        if (card) card.style.display = 'flex';
        if (emptyImg) emptyImg.style.display = 'none';
        if (thumb) thumb.src = item.url;
        if (title) title.innerText = item.title || item.name || 'Photograph';
    } else if (item.type === 'audio') {
        audioInput.value = item.url;
        const card = document.getElementById('eq-preview-audio-card');
        const player = document.getElementById('eq-preview-audio-player');
        const title = document.getElementById('eq-preview-audio-title');
        if (card) card.style.display = 'flex';
        if (emptyAudio) emptyAudio.style.display = 'none';
        if (player) player.src = item.url;
        if (title) title.innerText = item.title || item.name || 'Audio Statement';
    } else {
        if (item.url) imgInput.value = item.url;
        mediaIdInput.value = item.id;
        const card = document.getElementById('eq-preview-image-card');
        const title = document.getElementById('eq-preview-image-title');
        if (card) card.style.display = 'flex';
        if (emptyImg) emptyImg.style.display = 'none';
        if (title) title.innerText = `[${item.type.toUpperCase()}] ` + (item.title || item.name);
    }

    if (noMediaMsg) noMediaMsg.style.display = 'none';
    closeQuestionMediaPicker();
    if (typeof updateEditorAutoDifficulty === 'function') {
        updateEditorAutoDifficulty();
    }
}

function removeQuestionAttachedMedia(type) {
    const mediaIdInput = document.getElementById('eq-media-asset-id');
    const imgInput = document.getElementById('eq-image-url');
    const audioInput = document.getElementById('eq-audio-url');
    const noMediaMsg = document.getElementById('eq-no-media-msg');
    const emptyImg = document.getElementById('eq-empty-image-card');
    const emptyAudio = document.getElementById('eq-empty-audio-card');

    if (type === 'image') {
        imgInput.value = '';
        const card = document.getElementById('eq-preview-image-card');
        if (card) card.style.display = 'none';
        if (emptyImg) emptyImg.style.display = 'flex';
    } else if (type === 'audio') {
        audioInput.value = '';
        const card = document.getElementById('eq-preview-audio-card');
        const player = document.getElementById('eq-preview-audio-player');
        if (card) card.style.display = 'none';
        if (player) player.src = '';
        if (emptyAudio) emptyAudio.style.display = 'flex';
    }

    if (!imgInput.value && !audioInput.value) {
        mediaIdInput.value = '';
        if (noMediaMsg) noMediaMsg.style.display = 'block';
    }

    if (typeof updateEditorAutoDifficulty === 'function') {
        updateEditorAutoDifficulty();
    }
}

function previewAssetModal(id, title, type, url) {
    document.getElementById('apm-title').textContent = `${type.toUpperCase()}: ${title}`;
    const contentEl = document.getElementById('apm-content');

    if (type === 'image') {
        contentEl.innerHTML = `<img src="${url}" alt="${title}" style="max-width:100%;max-height:450px;border-radius:.6rem;object-fit:contain;">`;
    } else if (type === 'audio') {
        contentEl.innerHTML = `
            <div style="width:100%;text-align:center;padding:1.5rem;background:#090d16;border-radius:.75rem;">
                <div style="font-size:3rem;margin-bottom:.5rem;">🎵</div>
                <audio controls controlsList="nodownload noplaybackrate" src="${url}" preload="metadata" style="width:100%;max-width:480px;accent-color:#6366f1;"></audio>
            </div>`;
    } else if (type === 'pdf') {
        contentEl.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%;height:450px;border:none;border-radius:.6rem;background:#fff;"></iframe>`;
    } else {
        contentEl.innerHTML = `<div style="padding:1.5rem;color:#cbd5e1;font-size:.85rem;line-height:1.6;white-space:pre-wrap;background:#090d16;border-radius:.6rem;width:100%;">Reading / text passage preview...</div>`;
    }

    const modal = document.getElementById('asset-preview-modal');
    if (modal) modal.style.display = 'flex';
}

function closeAssetPreviewModal(e) {
    if (!e || e.target === document.getElementById('asset-preview-modal')) {
        const modal = document.getElementById('asset-preview-modal');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('apm-content').innerHTML = '';
        }
    }
}

function previewQuestionModalMedia(type) {
    const url = document.getElementById('eq-' + type + '-url')?.value;
    const title = document.getElementById('eq-preview-' + type + '-title')?.innerText || (type === 'image' ? 'Photograph' : 'Audio Prompt');
    if (url) {
        previewAssetModal('', title, type, url);
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeQuestionMediaPicker();
        closeAssetPreviewModal();
    }
});
</script>
@endsection
