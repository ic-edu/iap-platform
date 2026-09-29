@extends('layouts.admin')

@section('title', 'Question Generator — Teacher Workspace')

@section('content')
<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                <a href="{{ route('teacher.dashboard') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Teacher Workspace</a>
                <span>/</span>
                <span class="text-indigo-600 dark:text-indigo-400 font-semibold">Question Generator</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Question Generator</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">AI-assisted item generation powered by calibrated academic blueprints.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.question-banks.index') }}" class="px-3.5 py-2 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <span>All Question Banks</span>
            </a>
        </div>
    </div>

    {{-- Info Banner --}}
    <div class="p-4 bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900/50 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-700/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-indigo-950 dark:text-indigo-200">Active Vertical Slice: TOEIC Reading Part 5</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">PRODUCTION READY</span>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                    Select a target editable Question Bank below to launch the dedicated generation workspace, configure blueprint parameters, and materialize questions as drafts.
                </p>
            </div>
        </div>
        <div class="text-xs text-slate-600 dark:text-slate-400 flex items-center gap-2 self-start md:self-auto bg-white/80 dark:bg-slate-900/80 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-slate-800 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Governance: Drafts remain subject to approval</span>
        </div>
    </div>

    {{-- Question Banks Selection Grid --}}
    @if($questionBanks->isEmpty())
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-200 mb-1">No Editable Question Banks Found</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-6">
                You do not have any editable Question Banks (Draft, Needs Revision, or Rejected) to generate questions into. Create a new bank to start generating.
            </p>
            <a href="{{ route('admin.question-banks.index') }}" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Create New Question Bank</span>
            </a>
        </div>
    @else
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Available Target Question Banks ({{ $questionBanks->count() }})</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">Only showing editable repositories owned by you</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($questionBanks as $bank)
                    @php
                        $status = strtolower($bank->status ?? 'draft');
                        $statusBadge = match($status) {
                            'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                            'needs_revision', 'rejected' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                            default => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                        };
                        $statusLabel = match($status) {
                            'draft' => 'Draft',
                            'needs_revision' => 'Needs Revision',
                            'rejected' => 'Rejected',
                            default => ucfirst($status),
                        };
                    @endphp
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-500/40 rounded-2xl p-5 flex flex-col justify-between transition-all duration-200 shadow-sm hover:shadow-md group">
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-2.5">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded border uppercase {{ $statusBadge }}">
                                    {{ $statusLabel }}
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 uppercase font-semibold">
                                    {{ $bank->test_type?->value ?? $bank->test_type }}
                                </span>
                            </div>

                            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-white transition-colors line-clamp-1 mb-1">
                                {{ $bank->title }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mb-4">
                                {{ $bank->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-3">
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $bank->questions_count }}</span> questions
                                <span class="text-slate-300 dark:text-slate-600 mx-1">•</span>
                                <span>{{ $bank->updated_at?->diffForHumans() ?? 'Recently' }}</span>
                            </div>

                            <a href="{{ route('admin.question-banks.generation.index', $bank->id) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition-all inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>Open Generator</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
