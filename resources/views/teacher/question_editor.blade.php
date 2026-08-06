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
        <div>
            <a href="{{ route('teacher.tests.show', $test->id) }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ← Back
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
        <form method="POST" action="{{ route('teacher.tests.update-question', ['test' => $test->id, 'question' => $question->id]) }}" style="display:flex;flex-direction:column;gap:1.25rem;">
            @csrf
            @method('PUT')

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

            {{-- Choices Section --}}
            <div>
                <label style="display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:.6rem;">Choices & Correct Answer Selection</label>
                <div style="display:flex;flex-direction:column;gap:.6rem;">
                    @php $choicesList = $question->choices && $question->choices->isNotEmpty() ? $question->choices : collect([1,2,3,4]); @endphp
                    @foreach($choicesList as $cIdx => $cObj)
                    <div style="display:flex;align-items:center;gap:.75rem;background:#1e293b;padding:.65rem .85rem;border-radius:.6rem;border:1px solid #334155;">
                        <input type="radio" name="correct_choice" value="{{ $cIdx }}" {{ is_object($cObj) && $cObj->is_correct ? 'checked' : '' }} style="accent-color:#34d399;width:1.1rem;height:1.1rem;">
                        <span style="font-weight:800;color:#818cf8;font-size:.85rem;width:1.5rem;">{{ chr(65 + $cIdx) }}.</span>
                        <input type="text" name="choices[{{ $cIdx }}]" value="{{ is_object($cObj) ? ($cObj->content ?? $cObj->choice_text) : '' }}" placeholder="Option {{ chr(65 + $cIdx) }} text" style="flex:1;padding:.5rem .75rem;background:#0f172a;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.88rem;">
                    </div>
                    @endforeach
                </div>
            </div>

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
@endsection
