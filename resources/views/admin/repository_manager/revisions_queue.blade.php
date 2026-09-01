@extends('layouts.admin')

@section('title', 'Teacher Repository Revision Queue — Repository Governance')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <a href="{{ route('admin.repository-manager.dashboard') }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back to Command Center
        </a>
        <div class="flex items-center justify-between gap-4 mt-1 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    Teacher Repository Revision Queue
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Review and govern teacher-submitted revisions and proposed updates to institutional question banks.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.repository-manager.questions-approval') }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    Question Banks Approval Queue →
                </a>
            </div>
        </div>
    </div>

    {{-- Metrics Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'open']) }}" class="p-4 rounded-xl border {{ $statusFilter === 'open' ? 'border-amber-500 bg-amber-500/5' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900' }} transition">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">New Revisions</div>
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['open_count'] }}</div>
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'resubmitted']) }}" class="p-4 rounded-xl border {{ $statusFilter === 'resubmitted' ? 'border-indigo-500 bg-indigo-500/5' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900' }} transition">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Resubmitted</div>
            <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ $metrics['resubmitted_count'] }}</div>
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'needs_revision']) }}" class="p-4 rounded-xl border {{ $statusFilter === 'needs_revision' ? 'border-rose-500 bg-rose-500/5' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900' }} transition">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Needs Changes</div>
            <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{{ $metrics['needs_rev_count'] }}</div>
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'approved']) }}" class="p-4 rounded-xl border {{ $statusFilter === 'approved' ? 'border-emerald-500 bg-emerald-500/5' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900' }} transition">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Approved</div>
            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['approved_count'] }}</div>
        </a>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'actionable']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $statusFilter === 'actionable' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            Active Awaiting Review
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'open']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $statusFilter === 'open' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            New Revisions
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'resubmitted']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $statusFilter === 'resubmitted' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            Resubmitted
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'needs_revision']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $statusFilter === 'needs_revision' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            Needs Changes
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'approved']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $statusFilter === 'approved' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            Approved
        </a>
        <a href="{{ route('admin.repository-manager.revisions.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $statusFilter === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            All Revisions
        </a>
    </div>

    {{-- Revisions Table --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        @if($revisions->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Repository Bank</th>
                        <th class="px-4 py-3">Requesting Teacher</th>
                        <th class="px-4 py-3">Teacher Note</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3 text-right">Governance Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach($revisions as $rev)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $rev->questionBank?->title ?? 'Repository #' . $rev->question_bank_id }}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                Bank Status: <span class="font-semibold text-emerald-600 dark:text-emerald-400 uppercase">{{ $rev->questionBank?->status ?? 'Published' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $rev->teacher?->name ?? 'Teacher' }}</div>
                            <div class="text-[10px] text-slate-400">{{ $rev->teacher?->email }}</div>
                        </td>
                        <td class="px-4 py-3.5 max-w-xs">
                            <span class="text-slate-600 dark:text-slate-300 font-medium line-clamp-2">"{{ $rev->notes }}"</span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-slate-700 dark:text-slate-300 text-[11px] font-bold">
                                {{ $rev->items->count() }} item(s)
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            @if($rev->status === 'OPEN')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                    New Revision
                                </span>
                            @elseif($rev->status === 'RESUBMITTED')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">
                                    Resubmitted
                                </span>
                            @elseif($rev->status === 'IN_PROGRESS' || $rev->status === 'NEEDS_REVISION')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                    Needs Changes
                                </span>
                            @elseif($rev->status === 'COMPLETED' || $rev->status === 'APPROVED')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                    Approved
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">
                                    {{ $rev->status }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ $rev->created_at?->diffForHumans() }}
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <a href="{{ route('admin.repository-manager.revisions.review', $rev->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition shadow-sm">
                                Review Revision →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800">
            {{ $revisions->links() }}
        </div>
        @else
        <div class="p-12 text-center">
            <div class="w-12 h-12 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-3 text-xl">
                ✓
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No Revisions Found</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                There are currently no teacher repository revision requests matching the selected filter.
            </p>
        </div>
        @endif
    </div>

</div>
@endsection
