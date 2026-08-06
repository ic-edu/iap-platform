@extends('layouts.admin')

@section('title', 'Focused Question Revision Editor — iC.edu Platform')

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
.fre-highlight {
    border: 2px solid #fb7185 !important;
    box-shadow: 0 0 20px rgba(251,113,133,0.3) !important;
    animation: pulseBorder 2s infinite ease-in-out;
}
@keyframes pulseBorder {
    0%, 100% { border-color: #fb7185; }
    50% { border-color: #f43f5e; }
}
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

    {{-- Navigation Breadcrumbs (PART 16) --}}
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('teacher.repository-revisions.show', $revisionRequest->id) }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Revision Task
        </a>
    </div>

    {{-- PART 2: REPOSITORY REVISION MODE BANNER --}}
    <div class="fre-banner">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <div>
                <span style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.08em;">🛠 Focused Repository Revision Mode</span>
                <h1 style="font-size:1.5rem;font-weight:900;color:#fff;margin:.25rem 0 .2rem;">
                    {{ $revisionRequest->questionBank?->title }}
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

    {{-- PART 4 & 5: FOCUSED QUESTION EDITOR FORM --}}
    <div class="fre-panel">
        <form method="POST" action="{{ route('teacher.repository-revisions.update-question', [$revisionRequest->id, $item->id]) }}">
            @csrf
            <input type="hidden" name="question_id" value="{{ $question->id ?? '' }}">

            <div style="margin-bottom:1.5rem;">
                <label style="font-size:.82rem;font-weight:800;color:#e2e8f0;display:block;margin-bottom:.4rem;">Question Prompt Text</label>
                <textarea name="prompt" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" required>{{ old('prompt', $question->prompt ?? '') }}</textarea>
            </div>

            {{-- Answer Choices Section (Highlighted if issue related to choices) --}}
            @php
                $isChoicesIssue = str_contains(strtolower($item->feedback), 'choice') || str_contains(strtolower($item->feedback), 'answer');
            @endphp
            <div id="answer-choices-section" class="p-4 rounded-xl mb-6 bg-slate-950 border border-slate-800 {{ $isChoicesIssue ? 'fre-highlight' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;">
                    <h3 style="font-size:.95rem;font-weight:800;color:#fff;margin:0;">Option Answer Choices</h3>
                    @if($isChoicesIssue)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Affected Field: Fix Answer Choices Below
                    </span>
                    @endif
                </div>

                @if($question && $question->choices->count() > 0)
                    @foreach($question->choices as $cIdx => $choice)
                    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.65rem;">
                        <input type="radio" name="correct_choice" value="{{ $choice->id }}" {{ $choice->is_correct ? 'checked' : '' }} style="accent-color:#10b981;width:1.1rem;height:1.1rem;">
                        <input type="text" name="choices[{{ $choice->id }}][content]" value="{{ old('choices.'.$choice->id.'.content', $choice->content) }}" class="flex-1 bg-slate-900 border border-slate-700 text-white rounded-lg p-2.5 text-sm" required>
                        <input type="hidden" name="choices[{{ $choice->id }}][is_correct]" value="{{ $choice->is_correct ? '1' : '0' }}">
                    </div>
                    @endforeach
                @else
                    <div style="font-size:.8rem;color:#fb7185;padding:.75rem;background:rgba(244,63,94,.1);border-radius:.5rem;">
                        ⚠️ No choices currently attached. Please save prompt and update choices in repository editor.
                    </div>
                @endif
            </div>

            {{-- Explanation Section (Highlighted if issue related to explanation) --}}
            @php
                $isExplanationIssue = str_contains(strtolower($item->feedback), 'explanation');
            @endphp
            <div id="explanation-section" class="p-4 rounded-xl mb-6 bg-slate-950 border border-slate-800 {{ $isExplanationIssue ? 'fre-highlight' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                    <label style="font-size:.82rem;font-weight:800;color:#e2e8f0;margin:0;">Pedagogical Explanation</label>
                    @if($isExplanationIssue)
                    <span style="font-size:.7rem;font-weight:800;color:#fb7185;background:rgba(244,63,94,.2);padding:.2rem .6rem;border-radius:.4rem;">
                        ⚠️ Affected Field: Provide Detailed Explanation
                    </span>
                    @endif
                </div>
                <textarea name="explanation" rows="3" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-indigo-500" placeholder="Provide detailed explanation for the correct answer...">{{ old('explanation', $question->explanation ?? '') }}</textarea>
            </div>

            <div style="display:flex;gap:1rem;margin-bottom:1.5rem;">
                <div style="flex:1;">
                    <label style="font-size:.82rem;font-weight:800;color:#e2e8f0;display:block;margin-bottom:.4rem;">Difficulty</label>
                    <select name="difficulty" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm">
                        <option value="easy" {{ ($question->difficulty ?? '') === 'easy' ? 'selected' : '' }}>Easy</option>
                        <option value="medium" {{ ($question->difficulty ?? '') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="hard" {{ ($question->difficulty ?? '') === 'hard' ? 'selected' : '' }}>Hard</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="font-size:.82rem;font-weight:800;color:#e2e8f0;display:block;margin-bottom:.4rem;">Points</label>
                    <input type="number" name="points" value="{{ old('points', $question->points ?? 1) }}" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 text-sm">
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all">
                💾 Save Question Revision & Validate Finding
            </button>
        </form>
    </div>

    {{-- PART 6 & 7: BOTTOM REVISION PROGRESS & RESUBMIT STRIP --}}
    @php
        $totalItems = $revisionRequest->items->count();
        $closedItems = $revisionRequest->items->where('status', 'CLOSED')->count();
        $percentComplete = $totalItems > 0 ? round(($closedItems / $totalItems) * 100) : 0;
        $allResolved = $closedItems >= $totalItems && $totalItems > 0;
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
                <div style="font-size:.78rem;color:#94a3b8;">All actionable items have been verified and fixed.</div>
                @else
                <div style="font-size:.9rem;font-weight:800;color:#fb7185;">
                    ⚠️ Remaining Findings: {{ $totalItems - $closedItems }} Unresolved Issue(s)
                </div>
                <div style="font-size:.78rem;color:#94a3b8;">Complete all remaining findings before resubmitting repository.</div>
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
@endsection
