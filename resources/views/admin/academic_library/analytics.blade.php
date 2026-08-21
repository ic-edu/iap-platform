@extends('layouts.admin')

@section('title', 'IRQA Quality Analytics — iC.edu Platform')

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb Navigation --}}
    @php
        $anBackUrl = match(request('from')) {
            'explorer' => route('admin.academic-library.explorer'),
            default    => route('admin.academic-library.quality'),
        };
    @endphp
    <div class="flex justify-between items-center">
        <a href="{{ $anBackUrl }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back
        </a>
        <span class="px-3 py-1 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full text-[10px] font-bold tracking-wider">INSTITUTIONAL QUALITY ANALYTICS</span>
    </div>

    {{-- Hero Header --}}
    <div class="gov-hero">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">📊 IRQA Quality Analytics</h1>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Comprehensive health distribution, completion percentages, coverage ratios, and quality trends.</p>
        </div>
        <div>
            <a href="{{ route('admin.academic-library.explorer') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow transition-colors inline-flex items-center gap-1.5">
                🔍 Open Explorer
            </a>
        </div>
    </div>

    {{-- KPI Gauges Grid (TASK 1.5) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="gov-card p-4 flex flex-col gap-1">
            <div class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 leading-none">{{ $analyticsData['avg_health_score'] }}%</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Average Health Score</div>
        </div>
        <div class="gov-card p-4 flex flex-col gap-1">
            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 leading-none">{{ $analyticsData['metadata_completion'] }}%</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Metadata Completion</div>
        </div>
        <div class="gov-card p-4 flex flex-col gap-1">
            <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 leading-none">{{ $analyticsData['question_completeness'] }}%</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Question Completeness</div>
        </div>
        <div class="gov-card p-4 flex flex-col gap-1">
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 leading-none">{{ $analyticsData['explanation_coverage'] }}%</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Explanation Coverage</div>
        </div>
        <div class="gov-card p-4 flex flex-col gap-1">
            <div class="text-2xl font-extrabold text-purple-600 dark:text-purple-400 leading-none">{{ $analyticsData['difficulty_balance'] }}%</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Difficulty Balance</div>
        </div>
        <div class="gov-card p-4 flex flex-col gap-1">
            <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 leading-none">{{ $analyticsData['overall_coverage'] }}%</div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Global Category Coverage</div>
        </div>
    </div>

    {{-- Health Distribution Breakdown (TASK 1.5) --}}
    <div class="gov-card space-y-4">
        <div class="text-sm font-bold text-slate-900 dark:text-white">📈 Repository Health Distribution</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 rounded-xl p-5 text-center">
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $analyticsData['distribution']['excellent'] }}</div>
                <div class="text-[11px] text-emerald-700 dark:text-emerald-300 mt-1 font-bold">EXCELLENT (90-100)</div>
            </div>
            <div class="bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-900/40 rounded-xl p-5 text-center">
                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ $analyticsData['distribution']['good'] }}</div>
                <div class="text-[11px] text-indigo-700 dark:text-indigo-300 mt-1 font-bold">GOOD (75-89)</div>
            </div>
            <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 rounded-xl p-5 text-center">
                <div class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ $analyticsData['distribution']['needs_improvement'] }}</div>
                <div class="text-[11px] text-rose-700 dark:text-rose-300 mt-1 font-bold">NEEDS IMPROVEMENT (&lt;75)</div>
            </div>
        </div>
    </div>

    {{-- Top & Lowest Repositories Spotlight (TASK 1.5) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Top Healthy Repo --}}
        @if($analyticsData['top_healthy_repo'])
        <div class="gov-card border-emerald-300 dark:border-emerald-800/60 p-5 space-y-2">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">🏆 Top Healthy Repository</div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $analyticsData['top_healthy_repo']['title'] }}</h3>
            <div class="text-xs text-slate-600 dark:text-slate-400">
                Health Score: <strong class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $analyticsData['top_healthy_repo']['health_score'] }} / 100</strong> • Questions: {{ $analyticsData['top_healthy_repo']['total_questions'] }}
            </div>
        </div>
        @endif

        {{-- Lowest Repo --}}
        @if($analyticsData['lowest_repo'])
        <div class="gov-card border-rose-300 dark:border-rose-800/60 p-5 space-y-3">
            <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">⚠️ Highest Priority for Improvement</div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $analyticsData['lowest_repo']['title'] }}</h3>
            <div class="text-xs text-slate-600 dark:text-slate-400">
                Health Score: <strong class="text-rose-600 dark:text-rose-400 font-bold">{{ $analyticsData['lowest_repo']['health_score'] }} / 100</strong> • Questions: {{ $analyticsData['lowest_repo']['total_questions'] }}
            </div>
            <div class="pt-1">
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'sort' => 'health_asc']) }}" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-bold shadow-sm transition-colors inline-block">
                    Fix Lowest Repositories →
                </a>
            </div>
        </div>
        @endif
    </div>

</div>
@endsection

