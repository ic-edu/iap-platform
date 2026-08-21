@extends('layouts.admin')

@section('title', 'Assessment Assignment & Operations — iC.edu Platform')

@section('content')
<div class="p-6 space-y-6 max-w-7xl mx-auto">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    ASSESSMENT OPERATIONS
                </span>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">Assessment Assignment &amp; Operations</h1>
            <p class="text-sm text-slate-400">Institutional Assessment Catalog, Candidate Assignment Management &amp; Eligibility Verification</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 transition-colors">
                &larr; Operational Dashboard
            </a>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-colors shadow-lg shadow-indigo-600/20">
                👥 Candidate Directory
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

    {{-- Filter & Search Bar --}}
    <form method="GET" action="{{ route('admin.tests.index') }}" class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3 flex-1 min-w-[280px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Assessment Title..." class="w-full bg-slate-900 text-slate-200 text-sm rounded-lg border border-slate-800 px-3.5 py-2 focus:outline-none focus:border-indigo-500 transition-colors" />
        </div>
        <div class="flex items-center gap-3">
            <select name="type" onchange="this.form.submit()" class="bg-slate-900 text-slate-200 text-xs font-semibold rounded-lg border border-slate-800 px-3 py-2 focus:outline-none focus:border-indigo-500">
                <option value="">All Programs</option>
                <option value="toeic" {{ request('type') === 'toeic' ? 'selected' : '' }}>TOEIC</option>
                <option value="toefl" {{ request('type') === 'toefl' ? 'selected' : '' }}>TOEFL</option>
                <option value="ielts" {{ request('type') === 'ielts' ? 'selected' : '' }}>IELTS</option>
                <option value="general" {{ request('type') === 'general' ? 'selected' : '' }}>General English</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="bg-slate-900 text-slate-200 text-xs font-semibold rounded-lg border border-slate-800 px-3 py-2 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published Live</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 transition-colors">
                Filter
            </button>
            @if(request()->hasAny(['search', 'type', 'status', 'filter']))
            <a href="{{ route('admin.tests.index') }}" class="px-3 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 text-xs font-bold border border-rose-500/20 transition-colors">
                Reset
            </a>
            @endif
        </div>
    </form>

    @if(request('filter') === 'active-assignments' || request('filter') === 'active')
    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs flex items-center justify-between shadow-md">
        <span class="flex items-center gap-2">
            <span>🎯</span>
            <span>Filtered: <strong>Assessments with Active Candidate Assignments</strong></span>
        </span>
        <a href="{{ route('admin.tests.index') }}" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold border border-slate-700 transition-colors">
            Clear Filter ✕
        </a>
    </div>
    @endif

    {{-- Assessments Table --}}
    <div class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Assessment Catalog ({{ $tests->total() }})</h2>
            <span class="text-xs text-slate-400">Institutional Approved &amp; Live Assessments</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300 divide-y divide-slate-800/80">
                <thead class="bg-slate-900/60 uppercase font-bold text-[10px] tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Assessment Title</th>
                        <th class="px-5 py-3.5">Assessment Mode</th>
                        <th class="px-5 py-3.5">Program</th>
                        <th class="px-5 py-3.5">Duration &amp; Passing</th>
                        <th class="px-5 py-3.5">Questions</th>
                        <th class="px-5 py-3.5">Active Assignments</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($tests as $test)
                    @php
                        $isReal = $test->isRealTest();
                        $qCount = $test->sections->sum(fn($s) => $s->testQuestions->count());
                        $activeAssignments = $test->assignments()->where('status', 'active')->count();
                    @endphp
                    <tr class="hover:bg-slate-900/40 transition-colors">
                        <td class="px-5 py-4">
                            <div class="font-bold text-white text-sm">{{ $test->title }}</div>
                            <div class="text-[11px] text-slate-500 font-mono mt-0.5">ID: {{ $test->id }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider {{ $isReal ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                                {{ $isReal ? '🛡️ Real Test' : '🎯 Simulator' }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                                {{ strtoupper($test->test_type instanceof \BackedEnum ? $test->test_type->value : (string) ($test->test_type ?? 'GENERAL')) }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-200 font-semibold">{{ $test->duration_minutes }} mins</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">Pass: {{ $test->pass_score }} pts</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-slate-200 font-semibold">{{ $qCount }} questions</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $test->sections->count() }} section(s)</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full {{ $activeAssignments > 0 ? 'bg-emerald-400' : 'bg-slate-600' }}"></span>
                                <span class="font-bold text-white">{{ $activeAssignments }}</span>
                                <span class="text-[11px] text-slate-400">candidate(s)</span>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $test->is_published ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700' }}">
                                {{ $test->is_published ? 'PUBLISHED' : strtoupper($test->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.tests.show', $test->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20">
                                <span>Manage Assignments &rarr;</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                            <span class="text-3xl block mb-2">📭</span>
                            <p class="font-bold text-sm text-slate-300">No Assessments Found</p>
                            <p class="text-xs text-slate-500 mt-1">There are no assessments matching the selected filters.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tests->hasPages())
        <div class="p-4 border-t border-slate-800 bg-slate-900/40">
            {{ $tests->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
