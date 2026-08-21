@extends('layouts.admin')

@section('title', 'IRQA Quality Dashboard — iC.edu Platform')

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb & Navigation --}}
    @php
        $qBackUrl = match(request('from')) {
            'explorer'         => route('admin.academic-library.explorer', ['filter' => request('filter', 'all')]),
            'academic_library' => route('admin.academic-library.index'),
            'admin_dashboard'  => route('admin.dashboard'),
            'dashboard'        => route('admin.repository-manager.dashboard'),
            default            => route('admin.repository-manager.dashboard'),
        };
    @endphp
    <div class="flex justify-between items-center">
        <a href="{{ $qBackUrl }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="gov-hero">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">🛡 Institutional Repository Quality Assurance (IRQA)</h1>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Comprehensive metadata completeness, difficulty balance, explanation coverage, and governance audit dashboard.</p>
        </div>
    </div>

    {{-- Global Quality Metrics Grid (PART 11 & TASK 1) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {{-- Card 1: Total Repositories --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'all', 'from' => 'quality']) }}" class="gov-card p-4 flex flex-col gap-1 hover:border-indigo-500 transition-all cursor-pointer no-underline group" title="View all institutional repositories">
            <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 leading-none">{{ $summary['total_repositories'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Total Repositories →</div>
            <div class="text-[10px] text-slate-400">All institutional banks</div>
        </a>

        {{-- Card 2: Healthy Repositories --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'healthy', 'from' => 'quality']) }}" class="gov-card p-4 flex flex-col gap-1 hover:border-emerald-500 transition-all cursor-pointer no-underline group" title="View healthy repositories with zero issues">
            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 leading-none">{{ $summary['healthy_count'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">Healthy Repositories →</div>
            <div class="text-[10px] text-slate-400">Zero active issues</div>
        </a>

        {{-- Card 3: Unreviewed Needs Improvement --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'sort' => 'health_asc', 'from' => 'quality']) }}" class="gov-card p-4 flex flex-col gap-1 hover:border-rose-500 transition-all cursor-pointer no-underline group" title="Active IRQA issues awaiting governance review">
            <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 leading-none">{{ $summary['needs_improvement_count'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 group-hover:text-rose-600 dark:group-hover:text-rose-400 transition-colors">Needing Improvement →</div>
            <div class="text-[10px] text-slate-400">Unreviewed active issues</div>
        </a>

        {{-- Card 4: Reviewed Issues --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'reviewed_issues', 'from' => 'quality']) }}" class="gov-card p-4 flex flex-col gap-1 hover:border-purple-500 transition-all cursor-pointer no-underline group" title="Repositories reviewed by Repository Manager with tracked quality issues">
            <div class="text-2xl font-extrabold text-purple-600 dark:text-purple-400 leading-none">{{ $summary['reviewed_issues_count'] ?? 0 }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">Reviewed Issues →</div>
            <div class="text-[10px] text-slate-400">Reviewed with findings</div>
        </a>

        {{-- Card 5: Awaiting Approval --}}
        @if($summary['pending_approval_count'] > 0)
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'awaiting_approval', 'from' => 'quality']) }}" class="gov-card p-4 flex flex-col gap-1 hover:border-amber-500 transition-all cursor-pointer no-underline group" title="Repositories submitted and pending governance approval">
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 leading-none">{{ $summary['pending_approval_count'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">Awaiting Approval →</div>
            <div class="text-[10px] text-slate-400">Pending RM decision</div>
        </a>
        @else
        <button type="button" onclick="openNoApprovalModal()" class="gov-card p-4 flex flex-col gap-1 text-left cursor-pointer group" title="No pending approvals">
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 leading-none">{{ $summary['pending_approval_count'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Awaiting Approval ⓘ</div>
            <div class="text-[10px] text-slate-400">No pending submissions</div>
        </button>
        @endif

        {{-- Card 6: Average Health Score --}}
        <a href="{{ route('admin.academic-library.analytics') }}" class="gov-card p-4 flex flex-col gap-1 hover:border-sky-500 transition-all cursor-pointer no-underline group" title="Open IRQA Quality Analytics">
            <div class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 leading-none">{{ $summary['avg_health_score'] }} <span class="text-xs text-slate-400 font-medium">/ 100</span></div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">Average Health Score 📊</div>
            <div class="text-[10px] text-slate-400">Overall quality metric</div>
        </a>
    </div>

    {{-- Duplicate Detection Alerts Panel (PART 6) --}}
    @if(count($summary['duplicates']['duplicate_titles']) > 0 || count($summary['duplicates']['duplicate_prompts']) > 0)
    <div class="p-4 rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20 space-y-2">
        <div class="text-xs font-bold text-rose-700 dark:text-rose-400">⚠️ Duplicate Detection Warnings (PART 6)</div>
        <div class="text-xs text-slate-700 dark:text-slate-300 space-y-1">
            @foreach($summary['duplicates']['duplicate_titles'] as $dupTitle)
            <div>• {{ $dupTitle }}</div>
            @endforeach
            @foreach($summary['duplicates']['duplicate_prompts'] as $dupPrompt)
            <div>• {{ $dupPrompt }}</div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Repository Quality Audit Table (PART 1 to 10) --}}
    <div class="gov-card p-0 overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-900 dark:text-white">📋 Repository Health &amp; Governance Audit</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Repository Title</th>
                        <th class="px-4 py-3">Health Score</th>
                        <th class="px-4 py-3">Questions</th>
                        <th class="px-4 py-3">Explanation %</th>
                        <th class="px-4 py-3">Difficulty Distribution</th>
                        <th class="px-4 py-3">Governance Owner</th>
                        <th class="px-4 py-3">Quality Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach($summary['audits'] as $audit)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <a href="{{ route('admin.repository-manager.question-bank-validate', $audit['bank_id']) }}" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline block text-xs">
                                {{ $audit['title'] }}
                            </a>
                            <div class="text-[10px] text-slate-400 mt-0.5">{{ $audit['governance']['current_version'] }} • {{ is_object($audit['test_type']) ? $audit['test_type']->label() : strtoupper((string) ($audit['test_type_label'] ?? $audit['test_type'] ?? 'GENERAL')) }}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-sm font-extrabold text-slate-900 dark:text-white">
                                {{ $audit['health_score'] }} <span class="text-[10px] text-slate-400 font-normal">/ 100</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 font-semibold text-slate-700 dark:text-slate-300">
                            {{ $audit['total_questions'] }} items
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="font-bold {{ $audit['explanation_pct'] >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $audit['explanation_pct'] }}%
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex gap-1.5 text-[11px]">
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">Easy: {{ $audit['difficulty_counts']['easy'] }}</span> • 
                                <span class="text-amber-600 dark:text-amber-400 font-bold">Med: {{ $audit['difficulty_counts']['medium'] }}</span> • 
                                <span class="text-rose-600 dark:text-rose-400 font-bold">Hard: {{ $audit['difficulty_counts']['hard'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $audit['governance']['institution_owner'] }}</div>
                            <div class="text-[10px] text-slate-400">Author: {{ $audit['governance']['contributor'] }}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            @if($audit['needs_improvement'])
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 inline-block">
                                Needs Improvement
                            </span>
                            <div class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5 max-w-[200px] leading-tight">
                                {{ $audit['warnings'][0] ?? 'Low health rating' }}
                            </div>
                            @else
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 inline-block">
                                Healthy ({{ $audit['health_grade'] }})
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Floating Dialog Modal for 0 Awaiting Approval (TASK 1.4) --}}
    <div id="noApprovalModal" style="display:none;" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="gov-card max-w-sm w-full p-6 text-center shadow-2xl space-y-3">
            <div class="text-4xl">🎉</div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">No Repository Awaiting Approval</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Everything has already been reviewed.</p>
            <div class="pt-2">
                <button type="button" onclick="closeNoApprovalModal()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow transition-colors cursor-pointer">
                    OK, Understood
                </button>
            </div>
        </div>
    </div>

    <script>
    function openNoApprovalModal() {
        document.getElementById('noApprovalModal').style.display = 'flex';
    }
    function closeNoApprovalModal() {
        document.getElementById('noApprovalModal').style.display = 'none';
    }
    </script>

</div>
@endsection

