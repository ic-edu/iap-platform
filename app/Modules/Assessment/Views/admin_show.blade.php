@extends('layouts.admin')

@section('title', 'Assessment Assignment & Candidates — ' . $test->title)

@section('content')
<div class="p-6 space-y-6 max-w-7xl mx-auto">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.tests.index') }}" class="text-xs font-semibold text-indigo-400 hover:underline flex items-center gap-1">
                    &larr; Assessments Catalog
                </a>
                <span class="text-slate-600">/</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider {{ $test->isRealTest() ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                    {{ $test->isRealTest() ? '🛡️ Real Test' : '🎯 Simulator' }}
                </span>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">{{ $test->title }}</h1>
            <p class="text-sm text-slate-400">Candidate Assignment &amp; Access Governance &bull; Program: <span class="text-slate-200 font-semibold uppercase">{{ $test->test_type instanceof \BackedEnum ? $test->test_type->value : (string) ($test->test_type ?? 'GENERAL') }}</span></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tests.index') }}" class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 transition-colors">
                &larr; Back to Catalog
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

    {{-- Assessment Metadata Strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 shadow-md">
            <p class="text-xs font-semibold text-slate-400">Assessment Mode</p>
            <p class="text-base font-extrabold text-white mt-1">{{ $test->assessment_mode?->label() ?? 'Standard' }}</p>
        </div>
        <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 shadow-md">
            <p class="text-xs font-semibold text-slate-400">Duration &amp; Passing</p>
            <p class="text-base font-extrabold text-white mt-1">{{ $test->duration_minutes }} min / {{ $test->pass_score }} pts</p>
        </div>
        <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 shadow-md">
            <p class="text-xs font-semibold text-slate-400">Structure</p>
            <p class="text-base font-extrabold text-white mt-1">{{ $test->sections->count() }} Section(s) / {{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }} Qs</p>
        </div>
        <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 shadow-md">
            <p class="text-xs font-semibold text-slate-400">Status</p>
            <p class="text-base font-extrabold text-emerald-400 mt-1">{{ $test->is_published ? 'PUBLISHED LIVE' : strtoupper($test->status) }}</p>
        </div>
    </div>

    {{-- Assignment Management Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Column 1: Assign Candidate Tool --}}
        <div class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-xl p-5 h-fit">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider pb-3 border-b border-slate-800 mb-4 flex items-center gap-2">
                <span>➕</span> Assign Candidate
            </h2>

            @if($test->isRealTest())
            <div class="p-3.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs mb-4 space-y-1">
                <p class="font-bold flex items-center gap-1.5">
                    <span>🛡️</span> Real Test Payment Rule
                </p>
                <p class="text-amber-200/80">Candidates must have a confirmed <strong>PAID</strong> transaction for this Real Test before assignment can be authorized.</p>
            </div>
            @endif

            <form action="{{ route('admin.tests.assign-candidate', $test->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="candidate_id" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Select Candidate</label>
                    <select name="candidate_id" id="candidate_id" required class="w-full bg-slate-900 text-slate-200 text-xs font-semibold rounded-lg border border-slate-800 p-2.5 focus:outline-none focus:border-indigo-500">
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
        <div class="lg:col-span-2 rounded-xl bg-slate-950/80 border border-slate-800 shadow-xl overflow-hidden">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider">Active Candidate Assignments</h2>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $assignedCandidates->count() }} Candidate(s) authorized for this assessment</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300 divide-y divide-slate-800/80">
                    <thead class="bg-slate-900/60 uppercase font-bold text-[10px] tracking-wider text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Candidate</th>
                            <th class="px-4 py-3">Assigned Date</th>
                            <th class="px-4 py-3">Assigned By</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($assignedCandidates as $assignment)
                        <tr class="hover:bg-slate-900/40 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-white">{{ $assignment->user?->name ?? 'Candidate' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $assignment->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-300">
                                {{ $assignment->assigned_at ? \Carbon\Carbon::parse($assignment->assigned_at)->format('M d, Y H:i') : $assignment->created_at?->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3.5 text-slate-400">
                                {{ $assignment->assignedBy?->name ?? 'System Admin' }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ strtoupper($assignment->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <form action="{{ route('admin.tests.unassign-candidate', [$test->id, $assignment->user_id]) }}" method="POST" class="inline-flex">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 hover:text-rose-200 text-xs font-semibold border border-rose-500/30 transition-colors">
                                        ✕ Unassign
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                <span class="text-2xl block mb-2">👤</span>
                                <p class="font-bold text-xs text-slate-300">No Candidates Assigned</p>
                                <p class="text-[11px] text-slate-500 mt-1">Use the assignment tool on the left to assign candidates.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection
