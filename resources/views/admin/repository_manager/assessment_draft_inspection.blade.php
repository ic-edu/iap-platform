@extends('layouts.admin')

@section('title', 'Inspect Governed Assessment Draft — ' . $test->title)

@section('content')
<div class="space-y-6 max-w-7xl mx-auto py-2">

    {{-- Top Navigation & Back Action --}}
    <div class="flex items-center justify-between flex-wrap gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                <a href="{{ route('admin.repository-manager.assessment-requests.index') }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                    ← Back to Assessment Requests
                </a>
                <span class="text-slate-400 dark:text-slate-600">/</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    {{ $test->test_type instanceof \App\Modules\QuestionBank\Enums\TestType ? $test->test_type->label() : strtoupper($test->test_type) }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                    🛡️ Mock / Real Test
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    Draft — In Authoring
                </span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $test->title }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Repository Governance &bull; Read-Only Draft Inspection &bull; Authoring Assigned to <strong class="text-slate-700 dark:text-slate-200">{{ $test->assignedTeacher?->name ?? 'Assigned Teacher' }}</strong>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.repository-manager.assessment-requests.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-sm">
                ← Back to Assessment Requests
            </a>
        </div>
    </div>

    {{-- Governance Read-Only Callout --}}
    <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/50 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🔒</span>
            <div>
                <div class="text-xs font-bold text-indigo-900 dark:text-indigo-200">Repository Manager Read-Only Governance Surface</div>
                <div class="text-[11px] text-indigo-700 dark:text-indigo-400 mt-0.5">
                    This governed draft is currently owned and authored by <strong>{{ $test->assignedTeacher?->name ?? 'the assigned teacher' }}</strong>. Content authoring controls are strictly isolated to the Teacher authoring workspace.
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs font-bold text-indigo-800 dark:text-indigo-300">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Live Authoring in Progress
        </div>
    </div>

    {{-- 4 Primary Metric & Governance Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        {{-- Card 1: Classification --}}
        <div class="gov-card p-4 flex flex-col justify-between">
            <div>
                <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Assessment Classification</div>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Family:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $test->test_type instanceof \App\Modules\QuestionBank\Enums\TestType ? $test->test_type->label() : strtoupper($test->test_type) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Mode:</span>
                        <span class="font-bold text-purple-600 dark:text-purple-400">Mock / Real Test</span>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span class="text-slate-400">Persisted Value:</span>
                        <span class="font-mono text-slate-600 dark:text-slate-400">{{ $test->assessment_mode instanceof \App\Modules\Assessment\Enums\AssessmentMode ? $test->assessment_mode->value : ($test->assessment_mode ?? 'real_test') }}</span>
                    </div>
                </div>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between text-[11px]">
                <span class="text-slate-500 dark:text-slate-400">Publication:</span>
                <span class="font-bold text-amber-600 dark:text-amber-400">{{ $test->is_published ? 'Published' : 'Unpublished' }}</span>
            </div>
        </div>

        {{-- Card 2: Institutional Requirement Brief --}}
        <div class="gov-card p-4 flex flex-col justify-between">
            <div>
                <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Originating Brief</div>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Request:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[120px]" title="{{ $assessmentRequest->title }}">{{ $assessmentRequest->title }}</span>
                    </div>
                    @if($assessmentRequest->candidate)
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Candidate:</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $assessmentRequest->candidate->name }}</span>
                    </div>
                    @endif
                    @if($assessmentRequest->program_context)
                    <div class="flex justify-between text-[11px]">
                        <span class="text-slate-500 dark:text-slate-400">Context:</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300 truncate max-w-[120px]" title="{{ $assessmentRequest->program_context }}">{{ $assessmentRequest->program_context }}</span>
                    </div>
                    @endif
                </div>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between text-[11px]">
                <span class="text-slate-500 dark:text-slate-400">Requested By:</span>
                <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $assessmentRequest->requester?->name ?? 'Operational Admin' }}</span>
            </div>
        </div>

        {{-- Card 3: Authoring Responsibility --}}
        <div class="gov-card p-4 flex flex-col justify-between">
            <div>
                <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Authoring Responsibility</div>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Assigned Teacher:</span>
                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $test->assignedTeacher?->name ?? 'Teacher Instructor' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Created By:</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ $test->creator?->name ?? 'Repository Manager' }}</span>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span class="text-slate-500 dark:text-slate-400">Authoring Owner:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $test->assignedTeacher?->name ?? 'Teacher Instructor' }}</span>
                    </div>
                </div>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between text-[11px]">
                <span class="text-slate-500 dark:text-slate-400">Status:</span>
                <span class="font-bold text-amber-600 dark:text-amber-400">In Authoring</span>
            </div>
        </div>

        {{-- Card 4: Assessment Metrics --}}
        <div class="gov-card p-4 flex flex-col justify-between">
            <div>
                <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Assessment Metrics</div>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Duration:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $test->duration_minutes }} mins</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Pass Threshold:</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $test->pass_score }} points</span>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span class="text-slate-500 dark:text-slate-400">Scoring:</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $test->scoring_method instanceof \App\Modules\Assessment\Enums\ScoringMethod ? ucfirst($test->scoring_method->value) : ucfirst($test->scoring_method ?? 'Automatic') }}</span>
                    </div>
                </div>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between text-[11px]">
                <span class="text-slate-500 dark:text-slate-400">Structure:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $test->sections->count() }} Section(s) / {{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }} Qs</span>
            </div>
        </div>

    </div>

    {{-- Originating Request Context & Notes Detail --}}
    @if($assessmentRequest->notes || $assessmentRequest->required_sections || $assessmentRequest->requested_deadline)
    <div class="gov-card p-5">
        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <span>📋</span> Originating Institutional Brief Specifications
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            @if($assessmentRequest->requested_deadline)
            <div>
                <span class="text-slate-400 font-semibold block mb-0.5">Requested Delivery Deadline:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $assessmentRequest->requested_deadline->format('M d, Y') }}</span>
            </div>
            @endif
            @if($assessmentRequest->required_sections)
            <div>
                <span class="text-slate-400 font-semibold block mb-0.5">Required Section Structure:</span>
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $assessmentRequest->required_sections }}</span>
            </div>
            @endif
            @if($assessmentRequest->notes)
            <div class="md:col-span-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span class="text-slate-400 font-semibold block mb-0.5">Operational Brief Notes:</span>
                <p class="text-slate-700 dark:text-slate-300 font-medium leading-relaxed bg-slate-50 dark:bg-slate-950/60 p-3 rounded-lg border border-slate-200 dark:border-slate-800">
                    {{ $assessmentRequest->notes }}
                </p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Assessment Structure & Question Progress (Read-Only) --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑</span> Assessment Sections &amp; Authoring Progress
            </h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">
                {{ $test->sections->count() }} Section(s) Configured &bull; {{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }} Total Questions
            </span>
        </div>

        @if($test->sections->isEmpty())
        <div class="gov-card p-8 text-center text-slate-400">
            <div class="text-3xl mb-2">📝</div>
            <div class="text-sm font-bold text-slate-700 dark:text-slate-300">No Sections Configured</div>
            <p class="text-xs text-slate-500 mt-1">The assigned teacher has not created any sections for this draft assessment yet.</p>
        </div>
        @else
        <div class="space-y-4">
            @foreach($test->sections as $section)
            <div class="gov-card p-5 border-l-4 border-l-indigo-500">
                <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                Section {{ $section->order }}
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $section->title }}</h3>
                        </div>
                        @if($section->section_type)
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Type: <strong class="text-slate-700 dark:text-slate-300">{{ $section->section_type?->label() ?? ($section->section_type->value ?? 'General') }}</strong>
                            @if($section->duration_minutes)
                            &bull; Duration: {{ $section->duration_minutes }} mins
                            @endif
                        </div>
                        @endif
                    </div>
                    <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-3 py-1 rounded-lg border border-indigo-200 dark:border-indigo-800/40">
                        {{ $section->testQuestions->count() }} Question(s)
                    </div>
                </div>

                @if($section->testQuestions->isEmpty())
                <div class="py-6 text-center text-slate-400">
                    <p class="text-xs font-semibold">No questions added to this section yet.</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Assigned teacher ({{ $test->assignedTeacher?->name ?? 'Teacher' }}) is authoring questions for this section.</p>
                </div>
                @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-3 py-2 w-12">#</th>
                                <th class="px-3 py-2">Question Prompt</th>
                                <th class="px-3 py-2 w-32">Type</th>
                                <th class="px-3 py-2 w-28">Source Bank</th>
                                <th class="px-3 py-2 w-20 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            @foreach($section->testQuestions as $tq)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20">
                                <td class="px-3 py-2.5 font-bold text-slate-700 dark:text-slate-300">
                                    {{ $tq->order }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ \Illuminate\Support\Str::limit($tq->question?->prompt ?? 'Question prompt', 120) }}
                                    </div>
                                    @if($tq->question && $tq->question->choices->isNotEmpty())
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 flex gap-3 flex-wrap">
                                        @foreach($tq->question->choices as $choice)
                                        <span class="{{ $choice->is_correct ? 'font-bold text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">
                                            {{ $choice->label }}: {{ \Illuminate\Support\Str::limit($choice->content, 30) }} {{ $choice->is_correct ? '✓' : '' }}
                                        </span>
                                        @endforeach
                                    </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium">
                                    {{ $tq->question?->question_type?->label() ?? ($tq->question?->question_type ?? 'Multiple Choice') }}
                                </td>
                                <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400 text-[11px]">
                                    {{ $tq->question?->questionBank?->title ?? 'Direct / Custom' }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold text-slate-800 dark:text-slate-200">
                                    {{ $tq->points ?? ($tq->question?->points ?? 1) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
