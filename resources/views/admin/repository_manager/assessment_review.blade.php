@extends('layouts.admin')

@section('title', 'Review Assessment — Repository Smart Review Engine')

@push('styles')
<style>
@media (max-width: 1024px) {
    .sticky-governance-sidebar {
        position: static !important;
    }
}
#btn-back-to-top {
    transition: opacity .3s ease, transform .2s ease;
}
#btn-back-to-top:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px -5px rgba(99,102,241,.5) !important;
}
</style>
@endpush

@section('content')
<div class="py-6 space-y-6">
    {{-- Header --}}
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">⚡ Repository Smart Review Engine</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Review by Exception: All questions are implicitly <strong class="text-slate-700 dark:text-slate-200">Default OK</strong>. Interact only with questions requiring attention.</p>
        </div>
        <div>
            <a href="{{ route('admin.repository-manager.assessment-approval') }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-sm">
                ← Back
            </a>
        </div>
    </div>

    {{-- Floating Back to Top Button (TASK 1, 2, 3, 4) --}}
    <button id="btn-back-to-top" type="button" title="Back to Top" onclick="window.scrollTo({top:0, behavior:'smooth'})" class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-white dark:bg-slate-900 border border-indigo-500 dark:border-indigo-400 text-slate-700 dark:text-white text-lg font-black flex items-center justify-center cursor-pointer shadow-lg shadow-indigo-500/20 opacity-0 pointer-events-none transition-all">
        ↑
    </button>

    {{-- Toast Notification Container --}}
    <div id="smart-review-toast" class="fixed bottom-20 right-6 z-50 bg-emerald-600 text-white px-5 py-3 rounded-xl text-xs font-extrabold shadow-xl shadow-emerald-950/40 hidden items-center gap-2 transition-opacity duration-300">
        ✨ <span id="toast-message">Review saved.</span>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 text-emerald-700 dark:text-emerald-400 p-4 rounded-xl text-xs sm:text-sm font-bold">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if(session('warning'))
    <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 text-amber-700 dark:text-amber-400 p-4 rounded-xl text-xs sm:text-sm font-bold">
        ⚠️ {{ session('warning') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/40 text-rose-700 dark:text-rose-400 p-4 rounded-xl text-xs sm:text-sm font-bold">
        🚫 {{ session('error') }}
    </div>
    @endif

    {{-- Review by Exception Summary Banner --}}
    @if(isset($reviewProgress))
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <div class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-1">
                    ⚡ Review by Exception Mode
                </div>
                <div class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white">
                    Total Assessment Questions: {{ $reviewProgress['total'] }}
                </div>
            </div>
            <div class="flex gap-2.5 text-xs flex-wrap">
                <div class="bg-emerald-50 dark:bg-slate-800/80 px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-800/40 text-emerald-700 dark:text-emerald-400 font-bold inline-flex items-center gap-1.5">
                    🟢 Default OK: <span id="summary-default-ok-count">{{ $reviewProgress['default_ok'] }}</span>
                </div>
                <div class="bg-amber-50 dark:bg-slate-800/80 px-3 py-1.5 rounded-lg border border-amber-200 dark:border-amber-800/40 text-amber-700 dark:text-amber-400 font-bold inline-flex items-center gap-1.5">
                    🟡 Flagged Revisions: <span id="summary-flagged-count">{{ $reviewProgress['flagged'] }}</span>
                </div>
                @if(($reviewProgress['critical'] ?? 0) > 0)
                <div class="bg-rose-50 dark:bg-slate-800/80 px-3 py-1.5 rounded-lg border border-rose-200 dark:border-rose-800/40 text-rose-700 dark:text-rose-400 font-bold inline-flex items-center gap-1.5">
                    🔴 Critical Blockers: <span id="summary-critical-count">{{ $reviewProgress['critical'] }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if(isset($reviewProgress) && $reviewProgress['total'] === 0)
    <div class="bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/40 text-rose-700 dark:text-rose-400 p-4 rounded-xl text-xs sm:text-sm font-bold">
        ⚠️ Incomplete Assessment: Contains 0 questions. This assessment cannot be approved and must be returned/rejected for authoring.
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Left: Assessment Details & Question Cards with Smart Review Panel --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between items-start flex-wrap gap-3 mb-4">
                    <div>
                        <span class="text-[11px] font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider bg-indigo-50 dark:bg-slate-800 px-2.5 py-1 rounded-md border border-indigo-200 dark:border-slate-700 inline-block">
                            {{ is_object($test->test_type) ? $test->test_type->value : $test->test_type }}
                        </span>
                        <h2 class="text-xl font-black text-slate-900 dark:text-white mt-2 mb-1">{{ $test->title }}</h2>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Author: <strong class="text-slate-700 dark:text-slate-300">{{ $test->creator?->name ?? 'System' }}</strong> ({{ $test->creator?->email }})</div>
                    </div>
                    <div>
                        @if(in_array($test->status, ['pending', 'pending_approval']))
                            <span class="px-3 py-1 rounded-lg text-xs font-extrabold uppercase bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                ⏳ Pending Review
                            </span>
                        @elseif($test->status === 'approved')
                            <span class="px-3 py-1 rounded-lg text-xs font-extrabold uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                ✓ Approved &amp; Active
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-lg text-xs font-extrabold uppercase bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                                ⚠️ Needs Revision
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 bg-slate-50 dark:bg-slate-800/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/60 mt-4">
                    <div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">Duration</div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-white mt-0.5">⏱ {{ $test->duration_minutes }} Mins</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">Pass Score</div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-white mt-0.5">🎯 {{ $test->pass_score }} pts</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">Sections</div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-white mt-0.5">📚 {{ $test->sections->count() }} Sections</div>
                    </div>
                </div>
            </div>

            {{-- Sections Breakdown & Question Cards --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white mb-4">Question Inspection &amp; Annotation Workspace</h3>

                @if($test->sections->isEmpty())
                    <div class="text-slate-400 dark:text-slate-500 text-xs text-center py-8">No test sections created yet.</div>
                @else
                    @php $qIdxGlobal = 1; @endphp
                    @foreach($test->sections as $sec)
                    <div class="bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 rounded-xl p-4 sm:p-5 mb-5">
                        <div class="flex justify-between items-center mb-3">
                            <div class="font-extrabold text-slate-900 dark:text-white text-sm sm:text-base">Section {{ $sec->order }}: {{ $sec->title ?? 'Section' }}</div>
                            <span class="text-xs text-indigo-700 dark:text-indigo-300 font-bold bg-indigo-50 dark:bg-indigo-500/20 border border-indigo-200 dark:border-indigo-500/30 px-2.5 py-1 rounded-md">
                                {{ $sec->testQuestions->count() }} Questions
                            </span>
                        </div>

                        @if($sec->testQuestions->isNotEmpty())
                            <div class="flex flex-col gap-4 mt-3">
                                @foreach($sec->testQuestions as $idx => $tq)
                                    @php
                                        $q = $tq->question;
                                        $qRev = $questionReviews[$q?->id] ?? null;
                                        $isFlagged = $qRev && in_array($qRev->status, ['needs_revision', 'critical_issue']);
                                        $qStatus = $isFlagged ? $qRev->status : 'default_ok';

                                        $cardBorderClass = $qStatus === 'critical_issue'
                                            ? 'border-rose-400 dark:border-rose-500/60 ring-1 ring-rose-400/40'
                                            : ($qStatus === 'needs_revision'
                                                ? 'border-amber-400 dark:border-amber-500/60 ring-1 ring-amber-400/40'
                                                : 'border-slate-200 dark:border-slate-700');
                                    @endphp
                                    @if($q)
                                    <div id="question-card-{{ $q->id }}" class="question-card bg-white dark:bg-slate-900 rounded-xl p-4 border {{ $cardBorderClass }} transition-colors shadow-sm" data-question-id="{{ $q->id }}">
                                        <div class="flex justify-between items-start gap-3 mb-2">
                                            <div>
                                                <div class="flex items-center flex-wrap gap-2 mb-1.5">
                                                    <span class="text-xs font-extrabold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-0.5 rounded-md">
                                                        Q#{{ $qIdxGlobal }}
                                                    </span>
                                                    <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md uppercase">{{ $q->question_type }}</span>
                                                    @php $diffVal = is_object($q->difficulty) ? $q->difficulty->value : $q->difficulty; @endphp
                                                    <span class="text-[11px] font-bold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/40 px-2 py-0.5 rounded-md uppercase">{{ $diffVal ?? 'easy' }}</span>

                                                    {{-- Status Badge (BUSINESS RULE 4: 🟢 Default OK, 🟡 Needs Revision, 🔴 Critical) --}}
                                                    @php
                                                        $badgeClass = $qStatus === 'critical_issue'
                                                            ? 'text-xs font-bold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/40 px-2.5 py-0.5 rounded-md'
                                                            : ($qStatus === 'needs_revision'
                                                                ? 'text-xs font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 px-2.5 py-0.5 rounded-md'
                                                                : 'text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 px-2.5 py-0.5 rounded-md');
                                                    @endphp
                                                    <span id="badge-status-{{ $q->id }}" class="{{ $badgeClass }}">
                                                        @if($qStatus === 'critical_issue')
                                                            🔴 Critical Issue ({{ ucfirst($qRev->field ?? 'General') }})
                                                        @elseif($qStatus === 'needs_revision')
                                                            🟡 Needs Revision ({{ ucfirst($qRev->field ?? 'General') }})
                                                        @else
                                                            🟢 Default OK
                                                        @endif
                                                    </span>
                                                </div>

                                                <div class="font-bold text-slate-900 dark:text-white text-sm leading-relaxed">
                                                    {{ $q->prompt ?? '(Empty Prompt Stem)' }}
                                                </div>
                                            </div>

                                            <div>
                                                <button type="button" id="btn-toggle-flag-{{ $q->id }}" onclick="toggleInlineFlag('{{ $q->id }}')"
                                                    class="{{ $isFlagged ? 'px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-sm' : 'px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1.5' }}">
                                                    {{ $isFlagged ? '✖ Flagged (Click to Edit)' : '⚠️ Flag Revision' }}
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Choices Inspection --}}
                                        @if($q->choices && $q->choices->isNotEmpty())
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                                                @foreach($q->choices as $cIdx => $choice)
                                                    <div class="text-xs p-2.5 rounded-lg border transition-colors {{ $choice->is_correct ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 font-bold' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium' }}">
                                                        {{ chr(65 + $cIdx) }}. {{ $choice->content ?? $choice->choice_text }} {{ $choice->is_correct ? '✓ (Correct)' : '' }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Rationale --}}
                                        @if($q->explanation)
                                            <div class="text-xs text-slate-600 dark:text-slate-400 mt-2.5 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-lg border-l-4 border-indigo-500 dark:border-indigo-400">
                                                <strong class="text-slate-700 dark:text-slate-300">Rationale:</strong> {{ $q->explanation }}
                                            </div>
                                        @endif

                                        {{-- Displayed Feedback Comment if Flagged --}}
                                        <div id="feedback-display-{{ $q->id }}" class="text-xs mt-2.5 p-2.5 rounded-lg border {{ $isFlagged && $qRev?->comment ? 'block' : 'hidden' }} {{ $qStatus === 'critical_issue' ? 'bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800/40' : 'bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800/40' }}">
                                            💬 <strong class="text-slate-800 dark:text-slate-200">Reviewer Feedback (<span id="feedback-field-{{ $q->id }}">{{ ucfirst($qRev?->field ?? 'general') }}</span>):</strong> "<span id="feedback-text-{{ $q->id }}">{{ $qRev?->comment }}</span>"
                                        </div>

                                        {{-- Inline Expandable Annotation Panel (BUSINESS RULES 2 & 3 & UX NO RELOAD) --}}
                                        <div id="inline-rev-panel-{{ $q->id }}" class="mt-3 p-4 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl {{ $isFlagged ? '' : 'hidden' }}">
                                            <div class="text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3 flex justify-between items-center">
                                                <span>📋 Question Revision Annotation</span>
                                                <button type="button" onclick="clearQuestionFlag('{{ $q->id }}')" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-bold cursor-pointer bg-transparent border-0">
                                                    ✓ Clear Flag &amp; Mark OK
                                                </button>
                                            </div>

                                            <form id="form-annotation-{{ $q->id }}" onsubmit="submitQuestionAnnotation(event, '{{ $q->id }}')" class="flex flex-col gap-3">
                                                @csrf
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Field *</label>
                                                        <select name="field" required class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                                            <option value="stem" {{ ($qRev?->field === 'stem') ? 'selected' : '' }}>Stem / Prompt Text</option>
                                                            <option value="choices" {{ ($qRev?->field === 'choices') ? 'selected' : '' }}>Choices / Options</option>
                                                            <option value="correct_answer" {{ ($qRev?->field === 'correct_answer') ? 'selected' : '' }}>Correct Answer Selection</option>
                                                            <option value="explanation" {{ ($qRev?->field === 'explanation') ? 'selected' : '' }}>Explanation / Rationale</option>
                                                            <option value="media" {{ ($qRev?->field === 'media') ? 'selected' : '' }}>Media Attachment</option>
                                                            <option value="general" {{ ($qRev?->field === 'general') ? 'selected' : '' }}>General Question Quality</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Severity</label>
                                                        <select name="severity" class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                                            <option value="warning" {{ ($qRev?->severity === 'warning') ? 'selected' : '' }}>Warning (Requires Fix)</option>
                                                            <option value="critical" {{ ($qRev?->severity === 'critical' || $qStatus === 'critical_issue') ? 'selected' : '' }}>Critical Blocker</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Reviewer Annotation Comment *</label>
                                                    <textarea name="comment" rows="2" required placeholder="Describe the specific correction required for the author..." class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">{{ $qRev?->comment }}</textarea>
                                                </div>

                                                <div class="flex justify-end gap-2">
                                                    <button type="button" onclick="document.getElementById('inline-rev-panel-{{ $q->id }}').classList.add('hidden');" class="px-3.5 py-1.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-bold transition-colors cursor-pointer">Close</button>
                                                    <button type="submit" class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-extrabold transition-colors cursor-pointer inline-flex items-center gap-1.5 shadow-sm">
                                                        💾 Save Question Review
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @php $qIdxGlobal++; @endphp
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Right: Question Navigator, Governance Decision Form & Audit Log (Sticky Sidebar TASK 5) --}}
        <div class="sticky-governance-sidebar flex flex-col gap-6" style="position:sticky;top:1.5rem;">

            {{-- BUSINESS RULE 4: Question Navigator Sidebar Widget --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
                <div class="text-sm font-extrabold text-slate-900 dark:text-white mb-3 flex items-center justify-between">
                    <span>🧭 Question Navigator</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">Jump to question</span>
                </div>

                <div class="flex flex-wrap gap-1.5 max-h-44 overflow-y-auto pr-1">
                    @php $navQIdx = 1; @endphp
                    @foreach($test->sections as $sec)
                        @foreach($sec->testQuestions as $tq)
                            @if($tq->question)
                            @php
                                $qNavRev = $questionReviews[$tq->question->id] ?? null;
                                $qNavStatus = ($qNavRev && in_array($qNavRev->status, ['needs_revision', 'critical_issue'])) ? $qNavRev->status : 'default_ok';
                                $badgeSymbol = $qNavStatus === 'critical_issue' ? '🔴' : ($qNavStatus === 'needs_revision' ? '🟡' : '🟢');
                                $pillClass = $qNavStatus === 'critical_issue'
                                    ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 hover:bg-rose-100 dark:hover:bg-rose-900/50'
                                    : ($qNavStatus === 'needs_revision'
                                        ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50 hover:bg-amber-100 dark:hover:bg-amber-900/50'
                                        : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/50');
                            @endphp
                            <a id="nav-pill-{{ $tq->question->id }}" href="#question-card-{{ $tq->question->id }}" onclick="document.getElementById('question-card-{{ $tq->question->id }}').scrollIntoView({behavior:'smooth'});return false;" class="px-2.5 py-1 rounded-md text-xs font-bold inline-flex items-center gap-1 transition-colors {{ $pillClass }}">
                                <span id="nav-pill-symbol-{{ $tq->question->id }}">{{ $badgeSymbol }}</span> Q{{ $navQIdx }}
                            </a>
                            @php $navQIdx++; @endphp
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white mb-4">Governance Decision</h3>

                @if($test->status === 'approved' && !$test->is_published)
                    {{-- APPROVED: Ready for Publication --}}
                    <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-xl p-5 mb-4 text-center">
                        <div class="text-emerald-700 dark:text-emerald-400 font-extrabold text-base mb-1">✓ Governance Approved</div>
                        <div class="text-slate-500 dark:text-slate-400 text-xs mb-4">Ready for Publication (Not Live)</div>
                        <form action="{{ route('admin.publications.assessments.publish', $test->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    onclick="event.preventDefault(); iapConfirm({ title: 'Publish Assessment Live?', message: 'This will publish the Assessment live for candidate delivery and candidate assignments.', confirmText: 'Publish Assessment', variant: 'success', form: this.form });"
                                    class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
                                🚀 Publish Assessment
                            </button>
                        </form>
                    </div>
                @elseif($test->status === 'published' && $test->is_published)
                    {{-- PUBLISHED: Live --}}
                    <div class="bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800/40 rounded-xl p-5 mb-4 text-center">
                        <div class="text-indigo-700 dark:text-indigo-400 font-extrabold text-base mb-1">● Published / Live</div>
                        <div class="text-slate-500 dark:text-slate-400 text-xs mb-4">Currently live for candidate delivery &amp; assignments</div>
                        <form action="{{ route('admin.publications.assessments.unpublish', $test->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    onclick="event.preventDefault(); iapConfirm({ title: 'Unpublish Assessment?', message: 'This Assessment will no longer be available for new candidate access or new assignments. Its governance approval will remain valid.', confirmText: 'Unpublish Assessment', variant: 'warning', form: this.form });"
                                    class="w-full py-3 bg-amber-600 hover:bg-amber-700 text-white font-extrabold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-2 shadow-md shadow-amber-600/20 transition-all">
                                ⏸️ Unpublish Assessment
                            </button>
                        </form>
                    </div>
                @elseif(in_array($test->status, ['pending_approval', 'needs_revision']))
                    {{-- BUSINESS RULE 5: Approve Form with Review by Exception Guard --}}
                    <form action="{{ route('admin.repository-manager.assessment-approve', $test->id) }}" method="POST" class="mb-4">
                        @csrf
                        <div class="mb-3">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Approval Notes (Optional)</label>
                            <input type="text" name="notes" placeholder="e.g. Assessment meets institutional quality standards." class="w-full bg-slate-50 dark:bg-slate-800/60 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 rounded-lg p-2.5 text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        </div>

                        @php
                            $approveAllowed = (isset($reviewProgress) && $reviewProgress['is_allowed']);
                        @endphp
                        <button id="btn-approve-assessment"
                                type="submit"
                                onclick="event.preventDefault(); iapConfirm({ title: 'Approve Assessment?', message: 'Are you sure you want to approve this assessment? Once approved, the assessment is ready for publication.', confirmText: 'Approve Assessment', variant: 'success', form: this.form });"
                                {{ $approveAllowed ? '' : 'disabled' }}
                                class="{{ $approveAllowed ? 'w-full p-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20 transition-all' : 'w-full p-3 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-extrabold border border-slate-200 dark:border-slate-700 rounded-xl text-xs cursor-not-allowed flex items-center justify-center gap-2 transition-all' }}">
                            ✓ Approve Assessment
                        </button>
                        <div id="approve-guard-hint" class="text-[11px] text-amber-600 dark:text-amber-400 text-center mt-1.5 font-semibold {{ $approveAllowed ? 'hidden' : '' }}">
                            All flagged questions must be resolved before approval.
                        </div>
                    </form>

                    <hr class="border-t border-slate-200 dark:border-slate-800 my-4">

                    {{-- BUSINESS RULE 6: Return Assessment Revision Form with Flagged Badge --}}
                    <form action="{{ route('admin.repository-manager.assessment-revision', $test->id) }}" method="POST" class="mb-4">
                        @csrf
                        <div class="mb-3">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Overall Revision Summary</label>
                            <textarea name="notes" rows="3" placeholder="Provide overall review notes for teacher..." class="w-full bg-slate-50 dark:bg-slate-800/60 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 rounded-lg p-2.5 text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"></textarea>
                        </div>

                        @php
                            $revAllowed = (isset($reviewProgress) && $reviewProgress['is_revision_allowed']);
                            $flaggedCount = $reviewProgress['flagged'] ?? 0;
                        @endphp
                        <button id="btn-request-revision"
                                type="submit"
                                onclick="event.preventDefault(); iapConfirm({ title: 'Request Assessment Revision?', message: 'This will return the Assessment to the Teacher for revision. The Teacher will need to address the review findings before resubmitting.', confirmText: 'Request Revision', variant: 'warning', form: this.form });"
                                {{ $revAllowed ? '' : 'disabled' }}
                                class="{{ $revAllowed ? 'w-full p-3 bg-amber-600 hover:bg-amber-700 text-white font-extrabold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-2 shadow-md shadow-amber-600/20 transition-all' : 'w-full p-3 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-extrabold border border-slate-200 dark:border-slate-700 rounded-xl text-xs cursor-not-allowed flex items-center justify-center gap-2 transition-all' }}">
                            ⚠️ Request Assessment Revision
                        </button>
                        <div id="revision-badge-container" class="mt-1.5 text-center">
                            <span id="revision-badge-text" class="text-[11px] font-bold block {{ $revAllowed ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500' }}">
                                {{ (isset($reviewProgress) && $flaggedCount > 0) ? $flaggedCount . ' Questions Flagged' : '0 Questions Flagged — No revisions needed' }}
                            </span>
                        </div>
                    </form>

                    <hr class="border-t border-slate-200 dark:border-slate-800 my-4">

                    {{-- Send to Archived Decision (Governance Rejection & Historical Archival) --}}
                    <form action="{{ route('admin.repository-manager.assessment-archive', $test->id) }}" method="POST" id="form-send-to-archived">
                        @csrf
                        <div class="mb-3">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-1">Archive / Rejection Reason</label>
                            <input type="text" name="notes" placeholder="Reason for archiving this submission..." class="w-full bg-slate-50 dark:bg-slate-800/60 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 rounded-lg p-2.5 text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        </div>
                        <button type="submit"
                                onclick="event.preventDefault(); iapConfirm({ title: 'Send Assessment to Archived?', message: 'This will end the current assessment submission workflow and preserve the assessment as an archived historical record. It will no longer remain in the active governance workflow.', confirmText: 'Send to Archived', variant: 'warning', form: this.form });"
                                class="w-full p-2.5 bg-slate-600 hover:bg-slate-700 text-white font-bold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-1.5 transition-colors shadow-sm">
                            📦 Send to Archived
                        </button>
                    </form>
                @else
                    {{-- OTHER STATES (e.g. Needs Revision, Draft, Archived) --}}
                    <div class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 text-center text-xs text-slate-600 dark:text-slate-300 font-medium">
                        Status: <strong class="text-slate-900 dark:text-white capitalize">{{ str_replace('_', ' ', $test->status) }}</strong>
                    </div>
                @endif
            </div>

            {{-- Audit Logs Widget --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <h4 class="text-sm font-extrabold text-slate-900 dark:text-white mb-3">📜 Activity Audit History</h4>
                @if($logs->isEmpty())
                    <div class="text-xs text-slate-500 dark:text-slate-400 text-center py-2">No prior activity logged.</div>
                @else
                    @foreach($logs as $l)
                    <div class="border-b border-slate-100 dark:border-slate-800/80 py-2.5 last:border-b-0 text-xs">
                        <div class="font-bold text-slate-800 dark:text-slate-200">{{ ucfirst($l->action) }}</div>
                        <div class="text-slate-600 dark:text-slate-400 text-[11px] mt-0.5">{{ $l->approval_note }}</div>
                        <div class="text-slate-400 dark:text-slate-500 text-[10px] mt-1">By {{ $l->reviewer?->name ?? $l->actor?->name ?? 'System' }} • {{ $l->created_at?->diffForHumans() }}</div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

{{-- UX & ASYNCHRONOUS UPDATE SCRIPT (NO FULL PAGE RELOAD) --}}
<script>
function showToast(message) {
    const toast = document.getElementById('smart-review-toast');
    const msgEl = document.getElementById('toast-message');
    if (!toast || !msgEl) return;
    msgEl.textContent = message;
    toast.classList.remove('hidden');
    toast.classList.add('flex');
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.classList.remove('flex');
            toast.classList.add('hidden');
        }, 300);
    }, 2500);
}

function toggleInlineFlag(questionId) {
    const panel = document.getElementById('inline-rev-panel-' + questionId);
    if (!panel) return;
    panel.classList.toggle('hidden');
}

async function submitQuestionAnnotation(e, questionId) {
    e.preventDefault();
    const form = document.getElementById('form-annotation-' + questionId);
    if (!form) return;

    const formData = new FormData(form);
    const url = "{{ route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => 'Q_ID']) }}".replace('Q_ID', questionId);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            updateQuestionUI(questionId, data);
            showToast("Review saved.");
        }
    } catch(err) {
        form.submit(); // fallback
    }
}

async function clearQuestionFlag(questionId) {
    const url = "{{ route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => 'Q_ID']) }}".replace('Q_ID', questionId);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            }
        });

        const data = await response.json();
        if (data.success) {
            updateQuestionUI(questionId, data);
            showToast("Flag cleared — Question marked OK.");
        }
    } catch(err) {
        window.location.reload(); // fallback
    }
}

function updateQuestionUI(questionId, data) {
    const isCritical = (data.status === 'critical_issue' || data.severity === 'critical');
    const isFlagged = (data.status !== 'default_ok');

    // 1. Update Card Border
    const card = document.getElementById('question-card-' + questionId);
    if (card) {
        card.classList.remove('border-rose-400', 'dark:border-rose-500/60', 'ring-1', 'ring-rose-400/40', 'border-amber-400', 'dark:border-amber-500/60', 'ring-amber-400/40', 'border-slate-200', 'dark:border-slate-700');
        if (isCritical) {
            card.classList.add('border-rose-400', 'dark:border-rose-500/60', 'ring-1', 'ring-rose-400/40');
        } else if (isFlagged) {
            card.classList.add('border-amber-400', 'dark:border-amber-500/60', 'ring-1', 'ring-amber-400/40');
        } else {
            card.classList.add('border-slate-200', 'dark:border-slate-700');
        }
        card.style.borderColor = '';
    }

    // 2. Update Badge Status
    const badge = document.getElementById('badge-status-' + questionId);
    if (badge) {
        badge.style.color = '';
        badge.style.background = '';
        badge.style.border = '';
        if (isCritical) {
            badge.className = 'text-xs font-bold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/40 px-2.5 py-0.5 rounded-md';
            badge.textContent = '🔴 Critical Issue (' + capitalize(data.field || 'general') + ')';
        } else if (isFlagged) {
            badge.className = 'text-xs font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 px-2.5 py-0.5 rounded-md';
            badge.textContent = '🟡 Needs Revision (' + capitalize(data.field || 'general') + ')';
        } else {
            badge.className = 'text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 px-2.5 py-0.5 rounded-md';
            badge.textContent = '🟢 Default OK';
        }
    }

    // 3. Update Toggle Button
    const btnToggle = document.getElementById('btn-toggle-flag-' + questionId);
    if (btnToggle) {
        btnToggle.style.background = '';
        btnToggle.style.borderColor = '';
        if (isFlagged) {
            btnToggle.className = 'px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-sm';
            btnToggle.textContent = '✖ Flagged (Click to Edit)';
        } else {
            btnToggle.className = 'px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1.5';
            btnToggle.textContent = '⚠️ Flag Revision';
        }
    }

    // 4. Update Display Feedback Note
    const feedbackBox = document.getElementById('feedback-display-' + questionId);
    const feedbackField = document.getElementById('feedback-field-' + questionId);
    const feedbackText = document.getElementById('feedback-text-' + questionId);
    if (feedbackBox && isFlagged) {
        feedbackBox.classList.remove('hidden');
        feedbackBox.className = 'text-xs mt-2.5 p-2.5 rounded-lg border ' + (isCritical ? 'bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800/40' : 'bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800/40');
        if (feedbackField) feedbackField.textContent = capitalize(data.field || 'general');
        if (feedbackText) feedbackText.textContent = data.comment || '';
    } else if (feedbackBox) {
        feedbackBox.classList.add('hidden');
    }

    // 5. Update Navigator Pill
    const navPill = document.getElementById('nav-pill-' + questionId);
    const navSymbol = document.getElementById('nav-pill-symbol-' + questionId);
    if (navPill && navSymbol) {
        navPill.style.background = '';
        navPill.style.border = '';
        navPill.style.color = '';
        if (isCritical) {
            navSymbol.textContent = '🔴';
            navPill.className = 'px-2.5 py-1 rounded-md text-xs font-bold inline-flex items-center gap-1 transition-colors bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 hover:bg-rose-100 dark:hover:bg-rose-900/50';
        } else if (isFlagged) {
            navSymbol.textContent = '🟡';
            navPill.className = 'px-2.5 py-1 rounded-md text-xs font-bold inline-flex items-center gap-1 transition-colors bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50 hover:bg-amber-100 dark:hover:bg-amber-900/50';
        } else {
            navSymbol.textContent = '🟢';
            navPill.className = 'px-2.5 py-1 rounded-md text-xs font-bold inline-flex items-center gap-1 transition-colors bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/50';
        }
    }

    // 6. Update Summary Banner Counters
    const defOkCountEl = document.getElementById('summary-default-ok-count');
    const flaggedCountEl = document.getElementById('summary-flagged-count');
    if (defOkCountEl) defOkCountEl.textContent = data.default_ok_count;
    if (flaggedCountEl) flaggedCountEl.textContent = data.flagged_count;

    // 7. Update Approve & Revision Buttons State
    const btnApprove = document.getElementById('btn-approve-assessment');
    const approveHint = document.getElementById('approve-guard-hint');
    if (btnApprove) {
        btnApprove.disabled = !data.is_allowed;
        btnApprove.style.background = '';
        btnApprove.style.borderColor = '';
        btnApprove.style.color = '';
        btnApprove.className = data.is_allowed
            ? 'w-full p-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20 transition-all'
            : 'w-full p-3 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-extrabold border border-slate-200 dark:border-slate-700 rounded-xl text-xs cursor-not-allowed flex items-center justify-center gap-2 transition-all';
    }
    if (approveHint) {
        if (data.is_allowed) {
            approveHint.classList.add('hidden');
        } else {
            approveHint.classList.remove('hidden');
        }
    }

    const btnRevision = document.getElementById('btn-request-revision');
    const revisionBadgeText = document.getElementById('revision-badge-text');
    if (btnRevision) {
        btnRevision.disabled = !data.is_revision_allowed;
        btnRevision.style.background = '';
        btnRevision.style.borderColor = '';
        btnRevision.style.color = '';
        btnRevision.className = data.is_revision_allowed
            ? 'w-full p-3 bg-amber-600 hover:bg-amber-700 text-white font-extrabold rounded-xl text-xs cursor-pointer flex items-center justify-center gap-2 shadow-md shadow-amber-600/20 transition-all'
            : 'w-full p-3 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-extrabold border border-slate-200 dark:border-slate-700 rounded-xl text-xs cursor-not-allowed flex items-center justify-center gap-2 transition-all';
    }
    if (revisionBadgeText) {
        revisionBadgeText.className = data.is_revision_allowed
            ? 'text-[11px] font-bold block text-amber-600 dark:text-amber-400'
            : 'text-[11px] font-bold block text-slate-400 dark:text-slate-500';
        revisionBadgeText.textContent = data.is_revision_allowed ? (data.flagged_count + ' Questions Flagged') : '0 Questions Flagged — No revisions needed';
    }
}

function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

// Back to Top button scroll visibility listener (TASK 1 & 3)
window.addEventListener('scroll', function() {
    const btn = document.getElementById('btn-back-to-top');
    if (!btn) return;
    if (window.scrollY > 500) {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    } else {
        btn.style.opacity = '0';
        btn.style.pointerEvents = 'none';
    }
});

// BFCACHE pageshow reload guard for mutable governance state
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
@endsection
