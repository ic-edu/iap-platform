@extends('layouts.admin')

@section('title', 'Teacher Revision Center — Returned Items')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="gov-hero-indigo rounded-2xl p-6 sm:p-7 flex justify-between items-center flex-wrap gap-5">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-amber-500 mb-1">Author Workspace • Quality Review</div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mb-1">⚠️ Teacher Revision Center</h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-2xl">Review Repository Manager feedback notes and revise returned Question Banks and Assessments.</p>
        </div>
        <div class="flex gap-3 items-center">
            <a href="{{ route('teacher.dashboard') }}" class="gov-btn-secondary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                ← Back
            </a>
            <a href="{{ route('teacher.dashboard') }}" class="gov-btn-primary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                Dashboard
            </a>
        </div>
    </div>

    {{-- Question Banks Needing Revision Section --}}
    <div class="gov-card overflow-hidden !p-0">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                📂 Question Banks Needing Revision ({{ $revisionQuestionBanks->count() }})
            </h3>
        </div>

        @if($revisionQuestionBanks->isEmpty())
            <div class="p-10 text-center text-slate-500 dark:text-slate-400">
                <div class="text-3xl mb-2">🎉</div>
                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">No Question Banks Awaiting Revision</div>
                <div class="text-xs mt-1 text-slate-500 dark:text-slate-400">All your question banks are either in draft, pending approval, or approved.</div>
            </div>
        @else
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach($revisionQuestionBanks as $bank)
                <div class="p-5">
                    <div class="flex justify-between items-start flex-wrap gap-4 mb-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    ⚠️ Needs Revision
                                </span>
                                <span class="text-xs text-indigo-600 dark:text-indigo-400 font-bold uppercase">
                                    {{ is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type }}
                                </span>
                            </div>
                            <h4 class="text-base font-bold text-slate-900 dark:text-white mb-0.5">{{ $bank->title }}</h4>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                📝 {{ $bank->questions->count() }} Questions • Returned by <strong class="text-slate-700 dark:text-slate-300">{{ $bank->reviewer_name }}</strong> {{ $bank->returned_at?->diffForHumans() }}
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('teacher.question-banks.show', $bank->id) }}" class="gov-btn-primary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5 shadow">
                                ✏️ Continue Revision
                            </a>
                        </div>
                    </div>

                    {{-- Prominent Repository Manager Feedback Box (TASK 4) --}}
                    <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 mt-3">
                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">
                            💬 Repository Manager Feedback
                        </div>
                        <div class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed">
                            "{{ $bank->latest_feedback }}"
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Assessments Needing Revision Section --}}
    @if($revisionAssessments->isNotEmpty())
    <div class="gov-card overflow-hidden !p-0">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                📋 Assessment Tests Needing Revision ({{ $revisionAssessments->count() }})
            </h3>
        </div>
        <div class="divide-y divide-slate-200 dark:divide-slate-800">
            @foreach($revisionAssessments as $test)
            <div class="p-5">
                <div class="flex justify-between items-start flex-wrap gap-4 mb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2.5 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                ⚠️ Needs Revision
                            </span>
                            <span class="text-xs text-indigo-600 dark:text-indigo-400 font-bold uppercase">
                                {{ is_object($test->test_type) ? $test->test_type->value : $test->test_type }}
                            </span>
                        </div>
                        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-0.5">{{ $test->title }}</h4>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            ⏱ {{ $test->duration_minutes }} Mins • Returned by <strong class="text-slate-700 dark:text-slate-300">{{ $test->reviewer_name }}</strong> {{ $test->returned_at?->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('teacher.tests.show', $test->id) }}" class="gov-btn-primary px-4 py-2 text-xs font-bold inline-flex items-center gap-1.5 shadow">
                            ✏️ Revise Assessment
                        </a>
                    </div>
                </div>

                {{-- Feedback Box --}}
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 mt-3">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">
                        💬 Repository Manager Feedback
                    </div>
                    <div class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed">
                        "{{ $test->latest_feedback }}"
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
