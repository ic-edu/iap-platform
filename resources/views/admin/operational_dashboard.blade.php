@extends('layouts.admin')

@section('title', 'Operational Dashboard — Candidate & Assessment Operations')

@section('content')
<div class="p-6 space-y-6 max-w-7xl mx-auto">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    OPERATIONAL WORKSPACE
                </span>
                @if($unreadNotificationsCount > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                    🔔 {{ $unreadNotificationsCount }} New Alerts
                </span>
                @endif
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">Operational Dashboard</h1>
            <p class="text-sm text-slate-400">Candidate Operations, Payment Eligibility &amp; Test Assignments — {{ now()->format('l, d F Y') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-bold border border-slate-700 transition-colors shadow-sm">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Manage Candidates
            </a>
        </div>
    </div>

    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-300 text-sm flex items-center justify-between shadow-lg">
        <span class="flex items-center gap-2">✅ {{ session('status') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-500/30 text-rose-300 text-sm flex items-center justify-between shadow-lg">
        <span class="flex items-center gap-2">⚠️ {{ session('error') }}</span>
    </div>
    @endif

    {{-- Operational KPI Grid --}}
    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
            <span>Live Operational Metrics</span>
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- 1. Total Candidates --}}
            <a href="{{ route('admin.users.index') }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-indigo-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400">Total Registered Candidates</p>
                        <p class="text-3xl font-black text-white mt-1 group-hover:text-indigo-300 transition-colors">{{ number_format($totalCandidates) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xl">
                        👥
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-400">
                    <span class="text-indigo-400 group-hover:translate-x-0.5 transition-transform">View Candidates &rarr;</span>
                </div>
            </a>

            {{-- 2. Paid / Eligible Candidates --}}
            <a href="{{ route('admin.users.index', ['filter' => 'paid-eligible']) }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-emerald-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400">Paid &amp; Eligible Candidates</p>
                        <p class="text-3xl font-black text-white mt-1 group-hover:text-emerald-300 transition-colors">{{ number_format($paidEligibleCandidatesCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xl">
                        💳
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-400">
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">View Eligible Candidates &rarr;</span>
                </div>
            </a>

            {{-- 3. Active Test Assignments --}}
            <a href="{{ route('admin.tests.index', ['filter' => 'active-assignments']) }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400">Active Test Assignments</p>
                        <p class="text-3xl font-black text-white mt-1 group-hover:text-amber-300 transition-colors">{{ number_format($activeAssignmentsCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 text-xl">
                        🎯
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-400">
                    <span class="text-amber-400 group-hover:translate-x-0.5 transition-transform">View Active Assignments &rarr;</span>
                </div>
            </a>

            {{-- 4. Completed Tests --}}
            <a href="{{ route('admin.reporting.index') }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-sky-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400">Completed Assessments</p>
                        <p class="text-3xl font-black text-white mt-1 group-hover:text-sky-300 transition-colors">{{ number_format($completedAttemptsCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-sky-500/10 text-sky-400 border border-sky-500/20 text-xl">
                        ✅
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-400">
                    <span class="text-sky-400 group-hover:translate-x-0.5 transition-transform">View Completed Results &rarr;</span>
                </div>
            </a>

        </div>
    </section>

    {{-- Action Panel: Candidates Requiring Action --}}
    <section id="action-queue" class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Candidates Requiring Action</h2>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-slate-800 text-slate-300 border border-slate-700">
                    {{ count($actionRequiredCandidates) }} Awaiting Assignment
                </span>
            </div>
            <span class="text-xs text-slate-400 font-medium">Real Test Payment Verification</span>
        </div>

        @if(count($actionRequiredCandidates) === 0)
        <div class="py-8 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40">
            <span class="text-3xl block mb-2">🎉</span>
            <p class="text-sm font-bold text-slate-300">All Clear</p>
            <p class="text-xs text-slate-400 mt-1">There are no paid candidates currently waiting for Real Test assignment.</p>
        </div>
        @else
        <div class="divide-y divide-slate-800/80">
            @foreach($actionRequiredCandidates as $item)
            <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-900/40 px-3 rounded-lg transition-colors">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center font-bold text-xs flex-shrink-0">
                        {{ strtoupper(substr($item['user']->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold text-white truncate">{{ $item['user']->name }}</p>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                PAID
                            </span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                REAL TEST
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Target: <span class="text-slate-200 font-semibold">{{ $item['test']->title }}</span> &bull; Paid: {{ \Carbon\Carbon::parse($item['paid_at'])->diffForHumans() }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <form action="{{ route('admin.tests.assign-candidate', $item['test']->id) }}" method="POST" class="inline-flex">
                        @csrf
                        <input type="hidden" name="candidate_id" value="{{ $item['user']->id }}">
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/20 flex items-center gap-1.5">
                            <span>✓ Assign Real Test</span>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Two Column Layout: Recent Active Assignments & Assessment Inventory --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Column 1: Recent Active Candidate Assignments --}}
        <section class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>🎯</span> Recent Active Assignments
                </h2>
                <a href="{{ route('admin.tests.index', ['filter' => 'active-assignments']) }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">View Active Assignments &rarr;</a>
            </div>

            @if($recentAssignments->isEmpty())
            <div class="py-8 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40">
                <p class="text-xs text-slate-400">No active candidate assignments on record.</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($recentAssignments as $assignment)
                <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800/80 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-white truncate">{{ $assignment->user?->name ?? 'Candidate' }}</p>
                        <p class="text-[11px] text-slate-400 truncate">{{ $assignment->test?->title ?? 'Test' }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $assignment->test?->isRealTest() ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                            {{ $assignment->test?->assessment_mode?->label() ?? 'Assessment' }}
                        </span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            ACTIVE
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </section>

        {{-- Column 2: Assessment Inventory --}}
        <section class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>📋</span> Assessment Inventory
                </h2>
            </div>

            @if($availableTests->isEmpty())
            <div class="py-8 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40">
                <p class="text-xs text-slate-400">No published assessments available.</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($availableTests->take(6) as $test)
                <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800/80 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-bold text-white truncate">{{ $test->title }}</p>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $test->isRealTest() ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                                {{ $test->assessment_mode?->label() ?? 'Assessment' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            {{ $test->active_assignments_count }} Active Candidate(s) Assigned
                        </p>
                    </div>
                    <a href="{{ route('admin.tests.show', $test->id) }}" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition-colors flex-shrink-0">
                        Details &rarr;
                    </a>
                </div>
                @endforeach
            </div>
            @endif
        </section>

    </div>

</div>
@endsection
