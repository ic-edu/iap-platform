@extends('layouts.admin')

@section('title', 'Repository Manager Command Center — Mission Control Governance')

@section('content')
<div class="space-y-6">

    {{-- Executive Mission Control Hero (PART 1) --}}
    <div class="gov-hero">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="bg-indigo-600/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider border border-indigo-500/30">
                    Enterprise Governance
                </span>
                <span class="text-indigo-600 dark:text-indigo-400 text-xs font-bold">Mission Control Workspace</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Repository Manager Command Center</h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1 max-w-2xl">
                Actionable decision engine for quality assurance, assessment approval, passage verification, and duplicate prevention.
            </p>
        </div>

        {{-- PART 1: MISSION CONTROL PRIMARY HERO ACTION --}}
        <div class="flex gap-2 flex-wrap items-center">
            <a href="{{ route('admin.academic-library.explorer', ['from' => 'dashboard']) }}" class="gov-btn-secondary text-xs font-bold px-4 py-2 rounded-lg inline-flex items-center gap-1.5 shadow-sm">
                🔍 IRQA Explorer
            </a>
        </div>
    </div>

    {{-- PRIORITY 1: ACTIONABLE MISSION CONTROL KPI CARDS (PART 2 & PART 5) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
        
        {{-- Card 1: Question Bank Governance --}}
        <a href="{{ route('admin.repository-manager.questions-approval') }}" class="gov-card p-4 flex flex-col justify-between hover:border-indigo-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Question Bank Governance</span>
                <span class="text-lg">📚</span>
            </div>
            <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 my-1">{{ $pendingQuestionsCount }}</div>
            <div class="text-[10px] text-slate-400 font-bold">Governance Queue →</div>
        </a>

        {{-- Card 2: Assessment Approval --}}
        <a href="{{ route('admin.repository-manager.assessment-approval') }}" class="gov-card p-4 flex flex-col justify-between hover:border-sky-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-sky-600 dark:group-hover:text-sky-400">Assessment Approval</span>
                <span class="text-lg">📋</span>
            </div>
            <div class="text-2xl font-black text-sky-600 dark:text-sky-400 my-1">{{ $pendingAssessmentsCount }}</div>
            <div class="text-[10px] text-slate-400 font-bold">Assessment Queue →</div>
        </a>

        {{-- Card 3: Pending Media Reviews --}}
        <a href="{{ route('admin.repository-manager.media-approval') }}" class="gov-card p-4 flex flex-col justify-between hover:border-purple-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-purple-600 dark:group-hover:text-purple-400">Pending Media</span>
                <span class="text-lg">🖼</span>
            </div>
            <div class="text-2xl font-black text-purple-600 dark:text-purple-400 my-1">{{ $pendingMediaCount }}</div>
            <div class="text-[10px] text-slate-400 font-bold">Media Queue →</div>
        </a>

        {{-- Card 4: Duplicate Center --}}
        <a href="{{ route('admin.repository-manager.duplicates') }}" class="gov-card p-4 flex flex-col justify-between hover:border-rose-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-rose-600 dark:group-hover:text-rose-400">Duplicate Center</span>
                <span class="text-lg">🔍</span>
            </div>
            <div class="text-2xl font-black text-rose-600 dark:text-rose-400 my-1">{{ $duplicatesCount }}</div>
            <div class="text-[10px] text-slate-400 font-bold">Duplicate Center →</div>
        </a>

        {{-- Card 5: Repository Explorer --}}
        <a href="{{ route('admin.academic-library.explorer', ['from' => 'dashboard']) }}" class="gov-card p-4 flex flex-col justify-between hover:border-sky-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-sky-600 dark:group-hover:text-sky-400">Repository Explorer</span>
                <span class="text-lg">🏛</span>
            </div>
            <div class="text-2xl font-black text-sky-600 dark:text-sky-400 my-1">{{ $totalRepositoriesCount }}</div>
            <div class="text-[10px] text-slate-400 font-bold">Total Repositories →</div>
        </a>

        {{-- Card 6: Metadata Compliance --}}
        <a href="{{ route('admin.academic-library.quality', ['from' => 'dashboard']) }}" class="gov-card p-4 flex flex-col justify-between hover:border-indigo-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Metadata Compliance</span>
                <span class="text-lg">✅</span>
            </div>
            <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 my-1">{{ $metadataCompleteness }}%</div>
            <div class="text-[10px] text-slate-400 font-bold">Metadata Validator →</div>
        </a>

        {{-- Card 7: Health Score --}}
        <a href="{{ route('admin.academic-library.analytics') }}" class="gov-card p-4 flex flex-col justify-between hover:border-emerald-500 transition-all no-underline group" style="min-height: 120px;">
            <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider group-hover:text-emerald-600 dark:group-hover:text-emerald-400">Health Score</span>
                <span class="text-lg">💚</span>
            </div>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 my-1">{{ $repositoryHealthScore }}<span class="text-xs font-normal text-slate-400">/100</span></div>
            <div class="text-[10px] text-slate-400 font-bold">Full Analytics →</div>
        </a>

    </div>

    {{-- PRIORITY 1: URGENT ALERTS & TEACHER REVISIONS (PART 4 & PART 9) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Panel 1: Urgent Academic Alerts --}}
        <div class="gov-card space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">🚨 Urgent Academic Alerts</h3>
                <a href="{{ route('admin.academic-library.quality') }}" style="font-size:.78rem;color:#f87171;font-weight:700;text-decoration:none;">Alert Center →</a>
            </div>

            @php
                $activeAlerts = array_filter($urgentAlerts, fn($alert) => $alert['count'] > 0);
            @endphp

            @if(count($activeAlerts) > 0)
                <div class="space-y-3">
                    @foreach($activeAlerts as $alert)
                    <a href="{{ $alert['link'] }}" class="flex justify-between items-center p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 hover:bg-rose-100/50 transition-colors no-underline">
                        <div>
                            <div class="text-xs font-bold">{{ $alert['title'] }}</div>
                            <div class="text-[10px] text-rose-500/80 mt-0.5">Immediate reviewer action required</div>
                        </div>
                        <span class="text-xs font-black bg-rose-600 text-white px-2.5 py-1 rounded-full shadow-sm">
                            {{ $alert['count'] }}
                        </span>
                    </a>
                    @endforeach
                </div>
            @else
                <div class="text-center p-6 text-emerald-600 dark:text-emerald-400 text-xs bg-emerald-50/50 dark:bg-emerald-950/20 rounded-xl border border-emerald-200 dark:border-emerald-900/40 font-bold">
                    ✓ No urgent academic alerts.
                </div>
            @endif
        </div>

        {{-- Panel 2: Teacher Revision Queue --}}
        <div class="gov-card space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">📥 Teacher Revision Queue</h3>
                <a href="{{ route('admin.repository-manager.questions-approval') }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">View All Queue →</a>
            </div>

            @if($teacherSubmissionsQueue->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-3 py-2.5">Teacher</th>
                            <th class="px-3 py-2.5">Question Bank</th>
                            <th class="px-3 py-2.5">Submitted</th>
                            <th class="px-3 py-2.5">Status</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($teacherSubmissionsQueue as $item)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-3 py-3">
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $item->teacher?->name ?? 'Teacher' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $item->teacher?->email }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 text-xs">{{ $item->questionBank?->title ?? 'Question Bank' }}</div>
                                @if(!empty($item->notes))
                                <div class="text-[10px] text-slate-500 max-w-[180px] truncate" title="{{ $item->notes }}">
                                    "{{ $item->notes }}"
                                </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-[10px] text-slate-400">
                                {{ ($item->updated_at ?? $item->created_at)?->diffForHumans() }}
                            </td>
                            <td class="px-3 py-3">
                                @if($item->status === 'RESUBMITTED')
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    Resubmitted
                                </span>
                                @else
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    New Revision
                                </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('admin.repository-manager.question-bank-validate', $item->question_bank_id) }}" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[11px] font-bold shadow-sm transition-colors inline-block">
                                    Review Workspace →
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="text-center p-6 text-slate-400 text-xs bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-200 dark:border-slate-800">
                    ✓ No pending teacher revisions.
                </div>
            @endif
        </div>

    </div>

    {{-- PRIORITY 2: RECENTLY UPDATED REPOSITORIES & AUDIT LOG (PART 3 & PART 9) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Panel 3: Open Repository Governance Queue --}}
        <div class="gov-card space-y-4">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">⚡ Open Repository Governance Queue</h3>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">(Recently Updated Repositories)</span>
                </div>
                <a href="{{ route('admin.repository-manager.questions-approval') }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">Open Queue →</a>
            </div>

            @if(isset($openApprovalTasks) && $openApprovalTasks->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-3 py-2.5">Repository Title</th>
                            <th class="px-3 py-2.5">Teacher Author</th>
                            <th class="px-3 py-2.5">Submitted</th>
                            <th class="px-3 py-2.5">Status</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($openApprovalTasks as $task)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-3 py-3">
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $task->questionBank?->title ?? 'Repository Asset' }}</div>
                                <div class="text-[10px] text-slate-400">Task ID: {{ substr($task->id, 0, 13) }}</div>
                            </td>
                            <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-300">
                                {{ $task->teacher?->name ?? 'Teacher' }}
                            </td>
                            <td class="px-3 py-3 text-[10px] text-slate-400">
                                {{ $task->submitted_at ? \Carbon\Carbon::parse($task->submitted_at)->diffForHumans() : $task->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-3 py-3">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    {{ $task->status }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('admin.repository-manager.question-bank-validate', $task->question_bank_id) }}" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[11px] font-bold shadow-sm transition-colors inline-block">
                                    Validation Workspace →
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @elseif($recentlyUpdatedRepositories->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-3 py-2.5">Question Bank Repository</th>
                            <th class="px-3 py-2.5">Exam Type</th>
                            <th class="px-3 py-2.5">Status</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($recentlyUpdatedRepositories as $qb)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-3 py-3">
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $qb->title }}</div>
                                <div class="text-[10px] text-slate-400">ID: {{ $qb->id }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    {{ is_object($qb->test_type) ? $qb->test_type->value : $qb->test_type }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                    {{ $qb->status ?? 'Active' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('admin.repository-manager.question-bank-validate', $qb->id) }}" class="px-2.5 py-1 gov-btn-secondary text-[11px] font-bold rounded shadow-sm inline-block">
                                    Validation Workspace →
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="text-center p-6 text-slate-400 text-xs bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-200 dark:border-slate-800">
                    ✨ Governance queue is empty. No pending approval tasks.
                </div>
            @endif
        </div>

        {{-- Panel 4: Repository Activity Audit Log --}}
        <div class="gov-card space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">📜 Repository Activity Audit Log</h3>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">Real-time Audit Trail</span>
            </div>

            @if($recentActivityLogs->count() > 0)
                <div class="space-y-3">
                    @foreach($recentActivityLogs as $log)
                    <div class="flex gap-3 items-start p-3 bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800/80 rounded-xl border-l-4 border-l-indigo-600">
                        <div class="text-base">📝</div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-slate-900 dark:text-slate-100">
                                {{ $log->actor?->name ?? 'User' }} <span class="font-normal text-slate-500 dark:text-slate-400">executed action</span> <code class="text-indigo-600 dark:text-indigo-400 font-mono text-[11px]">{{ $log->action }}</code>
                            </div>
                            @if($log->approval_note)
                            <div class="text-xs text-slate-600 dark:text-slate-300 mt-1 italic">
                                "{{ $log->approval_note }}"
                            </div>
                            @endif
                            <div class="text-[10px] text-slate-400 mt-1">
                                {{ $log->created_at?->format('d M Y, H:i:s') }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center p-6 text-slate-400 text-xs bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-200 dark:border-slate-800">
                    No activity audit logs recorded yet.
                </div>
            @endif
        </div>

    </div>

    {{-- PRIORITY 3: COMPACT INSTITUTIONAL ANALYTICS CTA BANNER (PART 2 & PART 9) --}}
    <div class="gov-hero flex justify-between items-center flex-wrap gap-4 p-6">
        <div>
            <div class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Priority 3 • Institutional Quality Engine</div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white mt-0.5">Macro IRQA Analytics &amp; Teacher Submission Performance</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Deep-dive into category coverage, difficulty balance distribution, and author performance analytics on the dedicated analytics page.</p>
        </div>
        <a href="{{ route('admin.academic-library.analytics') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow-md transition-colors inline-block">
            Open Full Analytics →
        </a>
    </div>

</div>
@endsection

