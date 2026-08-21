@extends('layouts.admin')

@section('title', 'Lazy Question Editor — Question #' . $question->id)

@section('content')
<div style="padding: 1.5rem 0; max-width: 900px; margin: 0 auto;">
    {{-- Header & Navigation --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:.78rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem;">
                ⚡ Focused Question Authoring Workspace
            </div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0;">
                Edit Question (ID: {{ $question->id }})
            </h1>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;">
            <a href="{{ route('teacher.tests.show', $test->id) }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ← Back to Assessment
            </a>
            <a href="{{ route('teacher.dashboard') }}" style="padding:.6rem 1.1rem;background:#6366f1;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                🏠 Dashboard
            </a>
        </div>
    </div>

    @if(session('status'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ✅ {{ session('status') }}
    </div>
    @endif

    {{-- Question Editor Card --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;box-shadow:0 20px 40px -15px rgba(0,0,0,.5);">
        <form id="edit-question-form" method="POST" action="{{ route('teacher.tests.update-question', ['test' => $test->id, 'question' => $question->id]) }}" onsubmit="return validateEditQuestionForm(this)" style="display:flex;flex-direction:column;gap:1.25rem;">
            @csrf
            @method('PUT')

            <div id="edit-q-validation-error" style="display:none;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#f87171;padding:.75rem 1rem;border-radius:.6rem;font-size:.85rem;font-weight:700;">
                ⚠️ Please select the correct answer.
            </div>

            <div>
                <label style="display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Prompt Stem Text</label>
                <textarea name="prompt" rows="4" required style="width:100%;padding:.75rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;line-height:1.5;">{{ old('prompt', $question->prompt) }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div>
                    <label style="display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Question Type</label>
                    <select name="question_type" style="width:100%;padding:.7rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.88rem;">
                        <option value="multiple_choice" {{ $question->question_type === 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                        <option value="single_choice" {{ $question->question_type === 'single_choice' ? 'selected' : '' }}>Single Choice</option>
                        <option value="true_false" {{ $question->question_type === 'true_false' ? 'selected' : '' }}>True / False</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Difficulty</label>
                    @php $diffVal = is_object($question->difficulty) ? $question->difficulty->value : $question->difficulty; @endphp
                    <select name="difficulty" style="width:100%;padding:.7rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.88rem;">
                        <option value="easy" {{ $diffVal === 'easy' ? 'selected' : '' }}>Easy</option>
                        <option value="medium" {{ $diffVal === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="hard" {{ $diffVal === 'hard' ? 'selected' : '' }}>Hard</option>
                    </select>
                </div>
            </div>

            {{-- Question Media Section --}}
            <div style="background:#1e293b;padding:1.1rem;border-radius:.75rem;border:1px solid #334155;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;">
                    <div>
                        <span style="font-size:.85rem;font-weight:800;color:#e2e8f0;display:block;">🖼️ / 🎧 Question Media Attachments</span>
                        <span style="font-size:.75rem;color:#94a3b8;">Attach Question-level Photo (Image) and/or Audio Prompt (e.g. TOEIC Part 1 Photographs).</span>
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
                    <div id="eq-preview-image-card" style="display:{{ $hasImg ? 'flex' : 'none' }};background:#0f172a;border:1px solid #334155;border-radius:.5rem;padding:.6rem;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <img id="eq-preview-image-thumb" src="{{ $question->image_url ?? '' }}" alt="Thumbnail" style="width:52px;height:52px;object-fit:cover;border-radius:.35rem;border:1px solid #475569;">
                            <div>
                                <span style="font-size:.78rem;font-weight:700;color:#38bdf8;display:block;">🖼️ Attached Image (Photograph)</span>
                                <span id="eq-preview-image-title" style="font-size:.72rem;color:#cbd5e1;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">{{ $question->image_url }}</span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <button type="button" onclick="openQuestionMediaPicker('image')" style="background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3);border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                Change
                            </button>
                            <button type="button" onclick="removeQuestionAttachedMedia('image')" style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                ✕ Remove
                            </button>
                        </div>
                    </div>

                    {{-- Image empty placeholder --}}
                    <div id="eq-empty-image-card" style="display:{{ $hasImg ? 'none' : 'flex' }};align-items:center;justify-content:space-between;background:#0f172a;border:1px dashed #334155;border-radius:.5rem;padding:.55rem .75rem;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span style="font-size:1.1rem;">🖼️</span>
                            <div>
                                <span style="font-size:.75rem;font-weight:700;color:#cbd5e1;display:block;">Question Photograph / Image</span>
                                <span style="font-size:.68rem;color:#64748b;">No image attached</span>
                            </div>
                        </div>
                        <button type="button" onclick="openQuestionMediaPicker('image')" style="padding:.3rem .65rem;background:rgba(56,189,248,.12);color:#38bdf8;border:1px solid rgba(56,189,248,.3);border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.25rem;">
                            + Attach Image
                        </button>
                    </div>

                    {{-- Audio preview card --}}
                    <div id="eq-preview-audio-card" style="display:{{ $hasAudio ? 'flex' : 'none' }};background:#0f172a;border:1px solid #334155;border-radius:.5rem;padding:.6rem;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:.75rem;flex:1;">
                            <span style="font-size:1.5rem;">🎧</span>
                            <div style="flex:1;">
                                <span style="font-size:.78rem;font-weight:700;color:#818cf8;display:block;">🎵 Attached Audio Prompt</span>
                                <span id="eq-preview-audio-title" style="font-size:.72rem;color:#cbd5e1;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;margin-bottom:.3rem;">{{ $question->audio_url }}</span>
                                <audio id="eq-preview-audio-player" controls style="height:28px;width:100%;max-width:300px;" src="{{ $question->audio_url ?? '' }}"></audio>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <button type="button" onclick="openQuestionMediaPicker('audio')" style="background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3);border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                Change
                            </button>
                            <button type="button" onclick="removeQuestionAttachedMedia('audio')" style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);border-radius:.4rem;padding:.35rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                ✕ Remove
                            </button>
                        </div>
                    </div>

                    {{-- Audio empty placeholder --}}
                    <div id="eq-empty-audio-card" style="display:{{ $hasAudio ? 'none' : 'flex' }};align-items:center;justify-content:space-between;background:#0f172a;border:1px dashed #334155;border-radius:.5rem;padding:.55rem .75rem;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span style="font-size:1.1rem;">🎧</span>
                            <div>
                                <span style="font-size:.75rem;font-weight:700;color:#cbd5e1;display:block;">Question Audio Prompt</span>
                                <span style="font-size:.68rem;color:#64748b;">No audio attached</span>
                            </div>
                        </div>
                        <button type="button" onclick="openQuestionMediaPicker('audio')" style="padding:.3rem .65rem;background:rgba(129,140,248,.12);color:#818cf8;border:1px solid rgba(129,140,248,.3);border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.25rem;">
                            + Attach Audio
                        </button>
                    </div>
                </div>
            </div>

            {{-- Choices Section --}}
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;">
                    <div>
                        <label style="font-size:.85rem;font-weight:700;color:#cbd5e1;margin:0;display:block;">Choices &amp; Correct Answer Selection</label>
                        <span style="font-size:.72rem;color:#94a3b8;">Select exactly one radio button on the left to set the correct answer.</span>
                    </div>
                    <button type="button" onclick="addChoiceRow()" style="font-size:.75rem;font-weight:700;color:#818cf8;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);padding:.25rem .65rem;border-radius:.4rem;cursor:pointer;">
                        + Add Choice
                    </button>
                </div>
                <div id="choices-container" style="display:flex;flex-direction:column;gap:.6rem;">
                    @php 
                        $choicesList = $question->choices && $question->choices->isNotEmpty() 
                            ? $question->choices 
                            : collect([
                                (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                                (object)['content' => '', 'choice_text' => '', 'is_correct' => false],
                            ]); 
                    @endphp
                    @foreach($choicesList as $cIdx => $cObj)
                    @php
                        $isCorrectChoice = is_object($cObj) && (bool) $cObj->is_correct;
                        if (old('correct_choice') !== null) {
                            $isCorrectChoice = (string) old('correct_choice') === (string) $cIdx;
                        }
                    @endphp
                    <div class="choice-row" id="edit-choice-row-{{ $cIdx }}" style="display:flex;align-items:center;gap:.75rem;background:{{ $isCorrectChoice ? 'rgba(16,185,129,0.06)' : '#1e293b' }};padding:.65rem .85rem;border-radius:.6rem;border:1px solid {{ $isCorrectChoice ? 'rgba(16,185,129,0.6)' : '#334155' }};transition:all .15s ease;">
                        <input type="radio" name="correct_choice" value="{{ $cIdx }}" id="edit-correct-{{ $cIdx }}" onchange="updateEditorCorrectChoice()" {{ $isCorrectChoice ? 'checked' : '' }} style="accent-color:#10b981;width:1.1rem;height:1.1rem;cursor:pointer;">
                        <label for="edit-correct-{{ $cIdx }}" class="choice-label" style="font-weight:800;color:#818cf8;font-size:.85rem;width:1.5rem;cursor:pointer;margin:0;">{{ chr(65 + $cIdx) }}.</label>
                        <input type="text" name="choices[{{ $cIdx }}]" value="{{ is_object($cObj) ? ($cObj->content ?? $cObj->choice_text) : '' }}" placeholder="Option {{ chr(65 + $cIdx) }} text" style="flex:1;padding:.5rem .75rem;background:#0f172a;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.88rem;">
                        <span class="correct-indicator" id="edit-correct-badge-{{ $cIdx }}" style="display:{{ $isCorrectChoice ? 'inline-flex' : 'none' }};align-items:center;gap:.3rem;padding:.3rem .65rem;border-radius:.4rem;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.4);color:#34d399;font-size:.72rem;font-weight:800;letter-spacing:.03em;white-space:nowrap;">
                            ✓ CORRECT ANSWER
                        </span>
                        <button type="button" onclick="removeChoiceRow(this)" class="btn-remove-choice" style="color:#f87171;background:none;border:none;cursor:pointer;font-size:1.1rem;padding:0 .25rem;line-height:1;" title="Remove choice">✕</button>
                    </div>
                    @endforeach
                </div>
            </div>

            <script>
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
                    row.style.borderColor = isSelected ? 'rgba(16,185,129,0.6)' : '#334155';
                    row.style.background = isSelected ? 'rgba(16,185,129,0.06)' : '#1e293b';
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
                        textInput.placeholder = `Option ${label} text`;
                    }
                    if (badge) {
                        badge.id = `edit-correct-badge-${idx}`;
                    }
                    if (removeBtn) {
                        removeBtn.style.display = rows.length > 2 ? 'inline-block' : 'none';
                    }
                });
                updateEditorCorrectChoice();
            }

            function addChoiceRow() {
                const container = document.getElementById('choices-container');
                const rows = container.querySelectorAll('.choice-row');
                const idx = rows.length;
                const label = String.fromCharCode(65 + idx);

                const div = document.createElement('div');
                div.className = 'choice-row';
                div.id = `edit-choice-row-${idx}`;
                div.style.cssText = 'display:flex;align-items:center;gap:.75rem;background:#1e293b;padding:.65rem .85rem;border-radius:.6rem;border:1px solid #334155;transition:all .15s ease;';
                div.innerHTML = `
                    <input type="radio" name="correct_choice" value="${idx}" id="edit-correct-${idx}" onchange="updateEditorCorrectChoice()" style="accent-color:#10b981;width:1.1rem;height:1.1rem;cursor:pointer;">
                    <label for="edit-correct-${idx}" class="choice-label" style="font-weight:800;color:#818cf8;font-size:.85rem;width:1.5rem;cursor:pointer;margin:0;">${label}.</label>
                    <input type="text" name="choices[${idx}]" placeholder="Option ${label} text" style="flex:1;padding:.5rem .75rem;background:#0f172a;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.88rem;">
                    <span class="correct-indicator" id="edit-correct-badge-${idx}" style="display:none;align-items:center;gap:.3rem;padding:.3rem .65rem;border-radius:.4rem;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.4);color:#34d399;font-size:.72rem;font-weight:800;letter-spacing:.03em;white-space:nowrap;">
                        ✓ CORRECT ANSWER
                    </span>
                    <button type="button" onclick="removeChoiceRow(this)" class="btn-remove-choice" style="color:#f87171;background:none;border:none;cursor:pointer;font-size:1.1rem;padding:0 .25rem;line-height:1;" title="Remove choice">✕</button>
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
                <label style="display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Explanation / Rationale</label>
                <textarea name="explanation" rows="2" style="width:100%;padding:.75rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.88rem;">{{ old('explanation', $question->explanation) }}</textarea>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;border-top:1px solid #1e293b;padding-top:1.25rem;">
                <a href="{{ route('teacher.tests.show', $test->id) }}" style="padding:.75rem 1.25rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.65rem;font-size:.85rem;font-weight:700;text-decoration:none;">
                    Cancel
                </a>
                <button type="submit" style="padding:.75rem 1.75rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.88rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,.35);">
                    💾 Save Question Edits & Return to Summary
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Question Media Picker Modal --}}
<div id="question-media-picker-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:10001;align-items:center;justify-content:center;padding:1rem;" onclick="closeQuestionMediaPicker(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1.25rem;max-width:760px;width:100%;max-height:90vh;display:flex;flex-direction:column;padding:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.7);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #1e293b;padding-bottom:.75rem;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                    <span>📎</span> Attach Question Media
                </div>
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.2rem;">
                    Select an Image (photograph) or Audio prompt from the Institutional Media Library, or upload directly.
                </div>
            </div>
            <button type="button" onclick="closeQuestionMediaPicker()" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;">×</button>
        </div>

        {{-- Direct Upload Toggle Bar --}}
        <div style="background:#1e293b;border:1px solid #334155;border-radius:.75rem;padding:.75rem 1rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="font-size:1.1rem;">⬆️</span>
                <input type="file" id="eqm-direct-file-input" accept="image/*,audio/*,application/pdf" style="font-size:.75rem;color:#cbd5e1;">
            </div>
            <button type="button" id="eqm-upload-btn" onclick="uploadQuestionMediaFile()" style="padding:.4rem 1rem;background:#10b981;color:#fff;border:none;border-radius:.45rem;font-size:.75rem;font-weight:800;cursor:pointer;">
                Upload &amp; Attach
            </button>
        </div>

        {{-- Library Filters & Search --}}
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                <button type="button" onclick="filterQuestionMediaModal('all')" class="eqm-filter-btn active" style="padding:.35rem .7rem;background:#6366f1;color:#fff;border:none;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">All Media</button>
                <button type="button" onclick="filterQuestionMediaModal('image')" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">🖼️ Images</button>
                <button type="button" onclick="filterQuestionMediaModal('audio')" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">🎵 Audio Tracks</button>
                <button type="button" onclick="filterQuestionMediaModal('passage')" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">📖 Passages</button>
                <button type="button" onclick="filterQuestionMediaModal('pdf')" class="eqm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">📄 PDFs</button>
            </div>
            <input type="text" id="eqm-search-input" onkeyup="searchQuestionMediaModal(this.value)" placeholder="Search media library..." style="padding:.35rem .7rem;background:#1e293b;border:1px solid #334155;border-radius:.45rem;color:#fff;font-size:.75rem;min-width:180px;">
        </div>

        {{-- Media Grid Container --}}
        <div id="eqm-media-list-container" style="flex:1;min-height:220px;max-height:300px;overflow-y:auto;background:#090d16;border:1px solid #1e293b;border-radius:.65rem;padding:.75rem;display:grid;grid-template-columns:repeat(auto-fill, minmax(210px, 1fr));gap:.6rem;">
            <div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">Loading media library...</div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.75rem;border-top:1px solid #1e293b;padding-top:.75rem;margin-top:.75rem;">
            <button type="button" onclick="closeQuestionMediaPicker()" style="padding:.5rem 1rem;background:#334155;color:#fff;border:none;border-radius:.45rem;font-size:.8rem;font-weight:700;cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
let questionMediaLibrary = [];

function openQuestionMediaPicker(defaultType = 'all') {
    const modal = document.getElementById('question-media-picker-modal');
    if (modal) modal.style.display = 'flex';
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
    }
}

function fetchQuestionMediaLibrary(onLoaded) {
    const container = document.getElementById('eqm-media-list-container');
    if (questionMediaLibrary.length > 0) {
        renderQuestionMediaGrid(questionMediaLibrary);
        if (typeof onLoaded === 'function') onLoaded();
        return;
    }

    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">Loading media library...</div>';
    fetch('/admin/media/list')
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

function filterQuestionMediaModal(type) {
    const buttons = document.querySelectorAll('.eqm-filter-btn');
    buttons.forEach(btn => {
        btn.style.background = '#1e293b';
        btn.style.color = '#cbd5e1';
        btn.style.border = '1px solid #334155';
    });
    
    // Highlight matching button
    buttons.forEach(btn => {
        if ((type === 'all' && btn.textContent.includes('All Media')) ||
            (type === 'image' && btn.textContent.includes('Images')) ||
            (type === 'audio' && btn.textContent.includes('Audio')) ||
            (type === 'passage' && btn.textContent.includes('Passages')) ||
            (type === 'pdf' && btn.textContent.includes('PDFs'))) {
            btn.style.background = '#6366f1';
            btn.style.color = '#fff';
            btn.style.border = 'none';
        }
    });

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
        container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">No media assets found.</div>';
        return;
    }

    container.innerHTML = '';
    items.forEach(item => {
        const card = document.createElement('div');
        card.style.cssText = 'background:#131d31;border:1px solid #334155;border-radius:.55rem;padding:.65rem;display:flex;flex-direction:column;justify-content:space-between;gap:.4rem;';

        let icon = '📎';
        if (item.type === 'audio') icon = '🎵';
        else if (item.type === 'image') icon = '🖼️';
        else if (item.type === 'passage') icon = '📖';
        else if (item.type === 'pdf') icon = '📄';

        let previewHtml = '';
        if (item.type === 'image') {
            previewHtml = `<img src="${item.url}" style="width:100%;height:60px;object-fit:cover;border-radius:.35rem;border:1px solid #334155;margin-bottom:.3rem;">`;
        }

        card.innerHTML = `
            <div>
                ${previewHtml}
                <div style="display:flex;align-items:center;gap:.35rem;margin-bottom:.25rem;">
                    <span style="font-size:.65rem;font-weight:800;color:#818cf8;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);padding:.1rem .35rem;border-radius:.25rem;text-transform:uppercase;">
                        ${icon} ${item.type}
                    </span>
                </div>
                <div style="font-size:.75rem;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${item.title || item.name}">
                    ${item.title || item.name}
                </div>
            </div>
            <button type="button" onclick='applySelectedQuestionMedia(${JSON.stringify(item)})' style="margin-top:.4rem;padding:.35rem .6rem;background:#4f46e5;color:#fff;border:none;border-radius:.35rem;font-size:.72rem;font-weight:700;cursor:pointer;width:100%;text-align:center;">
                Use This Media
            </button>
        `;
        container.appendChild(card);
    });
}

function uploadQuestionMediaFile() {
    const fileInput = document.getElementById('eqm-direct-file-input');
    const file = fileInput?.files?.[0];
    if (!file) {
        alert('Please select a file to upload first.');
        return;
    }

    const btn = document.getElementById('eqm-upload-btn');
    btn.disabled = true;
    btn.innerHTML = 'Uploading...';

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('/admin/media', {
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
        btn.disabled = false;
        btn.innerHTML = 'Upload &amp; Attach';
        const assetData = data.asset || data;
        const assetId = assetData.id || data.id;
        if (data.success && assetId) {
            const item = {
                id: assetId,
                title: assetData.title || assetData.filename || assetData.original_name,
                name: assetData.filename || assetData.original_name,
                url: assetData.url || (assetId ? `/media/${assetId}/preview` : ''),
                type: assetData.type
            };
            questionMediaLibrary.unshift(item);
            applySelectedQuestionMedia(item);
            fileInput.value = '';
        } else {
            alert(data.message || 'Error uploading media asset.');
        }
    })
    .catch((err) => {
        btn.disabled = false;
        btn.innerHTML = 'Upload &amp; Attach';
        alert(err.message || 'Communication error while uploading media asset.');
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
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeQuestionMediaPicker();
    }
});
</script>
@endsection
