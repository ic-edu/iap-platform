@extends('layouts.admin')

@section('title', 'Assessment Assignment & Candidates — ' . $test->title)

@section('content')
<div class="p-6 space-y-6 max-w-7xl mx-auto">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.tests.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                    &larr; Assessments Catalog
                </a>
                <span class="text-slate-400 dark:text-slate-600">/</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider {{ $test->isRealTest() ? 'bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-500/30' }}">
                    {{ $test->isRealTest() ? '🛡️ Mock Test' : '🎯 Test Simulator' }}
                </span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $test->title }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Candidate Assignment &amp; Access Governance &bull; Program: <span class="text-slate-800 dark:text-slate-200 font-semibold uppercase">{{ $test->test_type instanceof \BackedEnum ? $test->test_type->value : (string) ($test->test_type ?? 'GENERAL') }}</span></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tests.index') }}" class="px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 transition-colors">
                &larr; Back to Catalog
            </a>
        </div>
    </div>

    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-300 text-sm flex items-center justify-between shadow-sm">
        <span class="flex items-center gap-2">✅ {{ session('status') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-sm flex items-center justify-between shadow-sm">
        <span class="flex items-center gap-2">⚠️ {{ session('error') }}</span>
    </div>
    @endif

    {{-- Assessment Metadata Strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Assessment Mode</p>
            <p class="text-base font-extrabold text-slate-900 dark:text-white mt-1">{{ $test->assessment_mode?->label() ?? 'Standard' }}</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Duration &amp; Passing</p>
            <p class="text-base font-extrabold text-slate-900 dark:text-white mt-1">{{ $test->duration_minutes }} min / {{ $test->getPassingThresholdDisplay() }}</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Structure</p>
            <p class="text-base font-extrabold text-slate-900 dark:text-white mt-1">{{ $test->sections->count() }} Section(s) / {{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }} Qs</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Status</p>
            <p class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $test->is_published ? 'PUBLISHED LIVE' : strtoupper($test->status) }}</p>
        </div>
    </div>

    @if(!$test->isPublished())
    {{-- Unpublished Assessment Inspection Workspace for RA --}}
    <div class="space-y-6">
        {{-- Lifecycle Status Banner --}}
        @php
            $rawStatus = strtolower(trim((string) $test->status));
            $bannerConfig = match($rawStatus) {
                'approved' => [
                    'icon'  => '📦',
                    'title' => 'Approved — Awaiting Live Publication',
                    'desc'  => 'This assessment has passed Repository Review and received RM approval. It is currently awaiting live publication. Candidate assignment will be automatically unlocked once published.',
                    'style' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300',
                    'badge' => 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                ],
                'pending', 'pending_approval', 'pending_review', 'submitted' => [
                    'icon'  => '🔍',
                    'title' => 'Submitted — Awaiting Repository Manager Review',
                    'desc'  => 'The assigned teacher has submitted this assessment. It is currently under governance review by the Repository Manager.',
                    'style' => 'bg-indigo-500/10 border-indigo-500/30 text-indigo-700 dark:text-indigo-300',
                    'badge' => 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border-indigo-500/30',
                ],
                'needs_revision', 'revision_requested', 'rejected' => [
                    'icon'  => '🔄',
                    'title' => 'Revision in Progress',
                    'desc'  => 'The Repository Manager requested editorial or structural revisions on this assessment. Revisions are currently being completed.',
                    'style' => 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300',
                    'badge' => 'bg-rose-500/20 text-rose-600 dark:text-rose-400 border-rose-500/30',
                ],
                'archived' => [
                    'icon'  => '🗄️',
                    'title' => 'Archived Assessment Record',
                    'desc'  => 'This assessment has been retired to the repository archive and is not active for candidate testing or live assignment.',
                    'style' => 'bg-slate-500/10 border-slate-500/30 text-slate-700 dark:text-slate-300',
                    'badge' => 'bg-slate-500/20 text-slate-600 dark:text-slate-400 border-slate-500/30',
                ],
                default => [
                    'icon'  => '✍️',
                    'title' => 'In Authoring',
                    'desc'  => 'This assessment draft is currently being authored by the assigned teacher.',
                    'style' => 'bg-sky-500/10 border-sky-500/30 text-sky-700 dark:text-sky-300',
                    'badge' => 'bg-sky-500/20 text-sky-600 dark:text-sky-400 border-sky-500/30',
                ],
            };
        @endphp

        <div class="p-5 rounded-2xl border {{ $bannerConfig['style'] }} shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="text-2xl flex-shrink-0">{{ $bannerConfig['icon'] }}</div>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <h3 class="text-sm font-black">{{ $bannerConfig['title'] }}</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $bannerConfig['badge'] }}">
                            {{ strtoupper($test->status) }}
                        </span>
                    </div>
                    <p class="text-xs opacity-90 leading-relaxed max-w-3xl">{{ $bannerConfig['desc'] }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <span class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200/60 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                    🔒 Unpublished
                </span>
            </div>
        </div>

        {{-- Inspection Grid: Assessment Details & Section Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Column 1: Governance & Assignment Lock Notice --}}
            <div class="rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm p-5 space-y-4 h-fit">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
                    <span>🔒</span> Candidate Assignment
                </h2>

                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-center space-y-2">
                    <div class="text-2xl">⏳</div>
                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Assignment Controls Locked</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        Candidate assignment is restricted to published assessments. Once the Repository Manager approves and publishes this assessment live, authorized assignment tools will become active.
                    </p>
                </div>

                @if($test->assignedTeacher)
                <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-xs space-y-1">
                    <span class="text-slate-500 dark:text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Assigned Teacher</span>
                    <p class="font-bold text-slate-800 dark:text-slate-200">{{ $test->assignedTeacher->name }}</p>
                    <p class="text-slate-500 dark:text-slate-400 text-[11px]">{{ $test->assignedTeacher->email }}</p>
                </div>
                @endif
            </div>

            {{-- Column 2 & 3: Section Breakdown & Structure --}}
            <div class="lg:col-span-2 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                        <span>📑</span> Assessment Sections &amp; Items
                    </h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $test->sections->count() }} Section(s)</span>
                </div>

                @if($test->sections->isEmpty())
                <div class="p-8 text-center text-slate-400">
                    <p class="text-xs">No sections configured in this draft yet.</p>
                </div>
                @else
                <div class="space-y-3">
                    @foreach($test->sections as $section)
                    <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 block mb-0.5">Section {{ $section->order }}</span>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $section->title }}</p>
                            @if($section->instructions)
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ \Illuminate\Support\Str::limit($section->instructions, 100) }}</p>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="px-2.5 py-1 rounded text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ $section->testQuestions->count() }} Questions
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @elseif($test->isSimulator())
    {{-- Simulator / Practice Informational Overview --}}
    <div class="rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
        <div class="flex items-start gap-4 pb-5 border-b border-slate-200 dark:border-slate-800">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 dark:bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-2xl flex-shrink-0">
                🎯
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Practice Test Simulator</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    This assessment is configured as an open-access practice simulator for candidate self-paced preparation.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Access &amp; Availability Governance</h3>
                    <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Candidate Availability:</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">Automatic / Open Candidate Access</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Payment Requirement:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">None (Free Practice)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">RA Assignment Required:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Not Required</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Certificate Issuance:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Ineligible (Simulator Policy)</span>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs">
                    <p class="font-semibold flex items-center gap-1.5 mb-1">
                        <span>ℹ️</span> Candidate Access Semantics
                    </p>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                        Published simulators appear automatically under Candidate <strong>Available Tests</strong>. Candidates can begin practice sessions independently without manual Registration Admin candidate authorization.
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Scoring &amp; Performance Policy</h3>
                    <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Scoring Engine:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Accuracy-based (Practice Mode)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Pass Threshold:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">75% Accuracy</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Total Questions:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }} Questions</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Sections:</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $test->sections->count() }} Section(s)</span>
                        </div>
                    </div>
                </div>

                @php
                    $attemptCount = \App\Modules\Assessment\Models\Attempt::where('test_id', $test->id)->count();
                    $completedCount = \App\Modules\Assessment\Models\Attempt::where('test_id', $test->id)->where('status', \App\Modules\Assessment\Enums\AttemptStatus::Submitted->value)->count();
                @endphp
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                    <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Practice Activity Metrics</h4>
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="p-2.5 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <span class="text-xs text-slate-500 dark:text-slate-400 block">Total Attempts</span>
                            <span class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5 block">{{ $attemptCount }}</span>
                        </div>
                        <div class="p-2.5 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <span class="text-xs text-slate-500 dark:text-slate-400 block">Completed</span>
                            <span class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5 block">{{ $completedCount }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    {{-- Mock / Real Governed Test Assignment Management Section (Preserved) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Column 1: Assign Candidate Tool --}}
        <div class="rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm p-5 h-fit">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-200 dark:border-slate-800 mb-4 flex items-center gap-2">
                <span>➕</span> Assign Candidate
            </h2>

            <div class="p-3.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-xs mb-4 space-y-1">
                <p class="font-bold flex items-center gap-1.5">
                    <span>🛡️</span> Mock Test Payment Rule
                </p>
                <p class="text-slate-600 dark:text-amber-200/80">Candidates must have a confirmed <strong>PAID</strong> transaction for this Mock Test before assignment can be authorized.</p>
            </div>

            <form action="{{ route('admin.tests.assign-candidate', $test->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="candidate_id" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Select Candidate</label>
                    <select name="candidate_id" id="candidate_id" required class="w-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-200 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-800 p-2.5 focus:outline-none focus:border-indigo-500">
                        <option value="">-- Choose Candidate --</option>
                        @foreach($availableStudents as $student)
                        <option value="{{ $student->id }}">
                            {{ $student->name }} ({{ $student->email }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 flex items-center justify-center gap-2">
                    <span>✓ Authorize &amp; Assign Candidate</span>
                </button>
            </form>
        </div>

        {{-- Column 2 & 3: Active Assignments List --}}
        <div class="lg:col-span-2 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Active Candidate Assignments</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $assignedCandidates->count() }} Candidate(s) authorized for this assessment</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800/80">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 uppercase font-bold text-[10px] tracking-wider text-slate-600 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Candidate</th>
                            <th class="px-4 py-3">Assigned Date</th>
                            <th class="px-4 py-3">Assigned By</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50">
                        @forelse($assignedCandidates as $assignment)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $assignment->user?->name ?? 'Candidate' }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $assignment->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                {{ $assignment->assigned_at ? \Carbon\Carbon::parse($assignment->assigned_at)->format('M d, Y H:i') : $assignment->created_at?->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400">
                                {{ $assignment->assignedBy?->name ?? 'System Admin' }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    {{ strtoupper($assignment->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <form action="{{ route('admin.tests.unassign-candidate', [$test->id, $assignment->user_id]) }}" method="POST" class="inline-flex">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-300 hover:text-rose-500 text-xs font-semibold border border-rose-500/30 transition-colors">
                                        ✕ Unassign
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                <span class="text-2xl block mb-2">👤</span>
                                <p class="font-bold text-xs text-slate-700 dark:text-slate-300">No Candidates Assigned</p>
                                <p class="text-[11px] text-slate-500 mt-1">Use the assignment tool on the left to assign candidates.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    @endif

</div>
@endsection
