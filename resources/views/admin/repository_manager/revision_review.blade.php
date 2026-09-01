@extends('layouts.admin')

@section('title', 'Repository Revision Review — Governance Center')

@section('content')
<div class="space-y-6">

    {{-- Back & Header Navigation --}}
    <div>
        <a href="{{ route('admin.repository-manager.revisions.index') }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back to Revision Queue
        </a>

        <div class="flex items-center justify-between gap-4 mt-2 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">
                        🛡 Repository Revision Review
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                        Baseline: {{ strtoupper($questionBank->status ?? 'Published') }}
                    </span>
                    @if($revisionRequest->status === 'OPEN')
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                            New Revision
                        </span>
                    @elseif($revisionRequest->status === 'RESUBMITTED')
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">
                            Resubmitted
                        </span>
                    @elseif($revisionRequest->status === 'IN_PROGRESS' || $revisionRequest->status === 'NEEDS_REVISION')
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                            Needs Changes
                        </span>
                    @elseif($revisionRequest->status === 'COMPLETED' || $revisionRequest->status === 'APPROVED')
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                            Approved & Applied
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">
                            {{ $revisionRequest->status }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Repository: <strong class="text-slate-800 dark:text-slate-200">{{ $questionBank->title }}</strong> • Revision Request ID: <code class="text-indigo-500 font-mono text-[11px]">{{ $revisionRequest->id }}</code>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg text-xs font-semibold">
                    🔒 Published baseline protected
                </span>
            </div>
        </div>
    </div>

    {{-- Top Context Card --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[10px] block">Requesting Teacher</span>
                <div class="font-bold text-slate-900 dark:text-white mt-0.5 text-sm">{{ $revisionRequest->teacher?->name ?? 'Teacher' }}</div>
                <div class="text-slate-500 dark:text-slate-400 text-[11px]">{{ $revisionRequest->teacher?->email }}</div>
            </div>
            <div>
                <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[10px] block">Teacher Revision Requirement</span>
                <div class="text-amber-600 dark:text-amber-400 font-semibold mt-0.5 text-sm">
                    "{{ $revisionRequest->notes }}"
                </div>
            </div>
            <div>
                <span class="text-slate-400 dark:text-slate-500 font-bold uppercase text-[10px] block">Timeline</span>
                <div class="text-slate-800 dark:text-slate-200 font-medium mt-0.5">Submitted {{ $revisionRequest->created_at?->diffForHumans() }}</div>
                <div class="text-slate-400 text-[10px]">{{ $revisionRequest->created_at?->format('d M Y, H:i T') }}</div>
            </div>
        </div>
    </div>

    {{-- Main Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left 2 Cols: Staged Items & Before/After Comparison --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="flex items-center justify-between">
                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Proposed Question Changes ({{ $revisionRequest->items->count() }})</span>
                </h2>
                <span class="text-xs text-slate-500">Compare live baseline against staged teacher edits</span>
            </div>

            @forelse($revisionRequest->items as $idx => $item)
            @php
                $q = $item->question;
                $proposed = $item->proposed_data ?? [];
                $hasStagedEdits = !empty($proposed);
            @endphp
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
                
                {{-- Item Header --}}
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/80 rounded-md text-indigo-700 dark:text-indigo-300 font-bold text-xs">
                            Item #{{ $idx + 1 }} @if($q) • Question ID: {{ substr($q->id, 0, 8) }}... @endif
                        </span>
                        @if($hasStagedEdits)
                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 rounded text-[10px] font-extrabold uppercase">
                                ✓ Staged Edits Present
                            </span>
                        @else
                            <span class="px-2 py-0.5 bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 rounded text-[10px] font-extrabold uppercase">
                                Awaiting Teacher Staging
                            </span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500">Status: <strong class="uppercase font-bold text-slate-700 dark:text-slate-300">{{ $item->status }}</strong></span>
                </div>

                {{-- Teacher Note for this item --}}
                <div class="p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-lg text-xs">
                    <span class="font-bold text-amber-800 dark:text-amber-300 block mb-0.5">Teacher Feedback Note:</span>
                    <span class="text-amber-900 dark:text-amber-200">{{ $item->feedback }}</span>
                </div>

                @if($q)
                {{-- Before / After Side-by-Side Comparison --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs pt-2">
                    
                    {{-- BEFORE (Live Master Baseline) --}}
                    <div class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                            <span class="font-extrabold text-slate-600 dark:text-slate-400 uppercase text-[10px] tracking-wider">
                                ◀ BEFORE (Published Baseline)
                            </span>
                            <span class="px-2 py-0.5 bg-slate-200 dark:bg-slate-800 rounded text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                Live Master
                            </span>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Question Prompt</span>
                            <p class="font-medium text-slate-800 dark:text-slate-200 text-xs leading-relaxed">
                                {{ $q->prompt ?? 'No prompt text' }}
                            </p>
                        </div>

                        {{-- Choices --}}
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Answer Choices</span>
                            <div class="space-y-1">
                                @forelse($q->choices as $c)
                                <div class="px-2.5 py-1.5 rounded border text-[11px] flex items-center justify-between {{ $c->is_correct ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 font-bold' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300' }}">
                                    <span><strong>{{ $c->label }}.</strong> {{ $c->content }}</span>
                                    @if($c->is_correct) <span class="text-[10px] font-extrabold">✓ CORRECT</span> @endif
                                </div>
                                @empty
                                <span class="text-slate-400 italic text-[11px]">No answer choices recorded.</span>
                                @endforelse
                            </div>
                        </div>

                        @if($q->explanation)
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Academic Explanation</span>
                            <p class="text-slate-600 dark:text-slate-400 text-[11px] leading-relaxed">
                                {{ $q->explanation }}
                            </p>
                        </div>
                        @endif

                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex gap-2 text-[10px] text-slate-500">
                            <span>Diff: <strong>{{ $q->difficulty ?? 'Medium' }}</strong></span>
                            <span>•</span>
                            <span>Points: <strong>{{ $q->points ?? 1 }}</strong></span>
                        </div>
                    </div>

                    {{-- AFTER (Teacher Proposed Staged Revision) --}}
                    <div class="p-4 bg-indigo-50/50 dark:bg-indigo-950/20 border-2 {{ $hasStagedEdits ? 'border-indigo-300 dark:border-indigo-700' : 'border-dashed border-slate-300 dark:border-slate-700' }} rounded-lg space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-indigo-200 dark:border-indigo-900">
                            <span class="font-extrabold text-indigo-700 dark:text-indigo-300 uppercase text-[10px] tracking-wider">
                                ▶ AFTER (Teacher Proposed Staging)
                            </span>
                            @if($hasStagedEdits)
                            <span class="px-2 py-0.5 bg-indigo-600 text-white rounded text-[10px] font-bold">
                                Staged Revision
                            </span>
                            @else
                            <span class="px-2 py-0.5 bg-slate-200 dark:bg-slate-800 rounded text-[10px] font-bold text-slate-500">
                                Unmodified
                            </span>
                            @endif
                        </div>

                        <div>
                            <span class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase block mb-1">Proposed Prompt</span>
                            <p class="font-medium text-slate-900 dark:text-white text-xs leading-relaxed">
                                {{ $proposed['prompt'] ?? $q->prompt ?? 'No prompt text' }}
                            </p>
                        </div>

                        {{-- Proposed Choices --}}
                        <div>
                            <span class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase block mb-1">Proposed Answer Choices</span>
                            <div class="space-y-1">
                                @if(!empty($proposed['choices']))
                                    @foreach($proposed['choices'] as $pc)
                                    <div class="px-2.5 py-1.5 rounded border text-[11px] flex items-center justify-between {{ !empty($pc['is_correct']) ? 'bg-emerald-100 dark:bg-emerald-950/60 border-emerald-400 dark:border-emerald-600 text-emerald-800 dark:text-emerald-200 font-bold' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200' }}">
                                        <span><strong>{{ $pc['label'] ?? 'A' }}.</strong> {{ $pc['content'] }}</span>
                                        @if(!empty($pc['is_correct'])) <span class="text-[10px] font-extrabold text-emerald-600 dark:text-emerald-400">✓ CORRECT</span> @endif
                                    </div>
                                    @endforeach
                                @else
                                    @forelse($q->choices as $c)
                                    <div class="px-2.5 py-1.5 rounded border text-[11px] flex items-center justify-between {{ $c->is_correct ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 font-bold' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300' }}">
                                        <span><strong>{{ $c->label }}.</strong> {{ $c->content }}</span>
                                        @if($c->is_correct) <span class="text-[10px] font-extrabold">✓ CORRECT</span> @endif
                                    </div>
                                    @empty
                                    <span class="text-slate-400 italic text-[11px]">No answer choices.</span>
                                    @endforelse
                                @endif
                            </div>
                        </div>

                        @if(!empty($proposed['explanation']) || $q->explanation)
                        <div>
                            <span class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase block mb-1">Proposed Explanation</span>
                            <p class="text-slate-800 dark:text-slate-200 text-[11px] leading-relaxed">
                                {{ $proposed['explanation'] ?? $q->explanation }}
                            </p>
                        </div>
                        @endif

                        <div class="pt-2 border-t border-indigo-200 dark:border-indigo-900 flex gap-2 text-[10px] text-indigo-700 dark:text-indigo-300 font-semibold">
                            <span>Diff: {{ $proposed['difficulty'] ?? $q->difficulty ?? 'Medium' }}</span>
                            <span>•</span>
                            <span>Points: {{ $proposed['points'] ?? $q->points ?? 1 }}</span>
                        </div>
                    </div>

                </div>
                @endif

            </div>
            @empty
            <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
                <p class="text-xs text-slate-500">No individual question items found in this revision request.</p>
            </div>
            @endforelse

        </div>

        {{-- Right Col: RM Governance Decision Center & Logs --}}
        <div class="space-y-6">

            {{-- Governance Decision Center --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                    <span>🛡 Governance Decision Center</span>
                </h3>

                @if(in_array($revisionRequest->status, ['OPEN', 'RESUBMITTED'], true))
                    
                    {{-- 1. Approve & Apply Form --}}
                    <form action="{{ route('admin.repository-manager.revisions.approve', $revisionRequest->id) }}" method="POST" class="space-y-2" onsubmit="event.preventDefault(); iapConfirm({ title: 'Approve & Apply Revision?', message: 'Are you sure you want to approve and atomically apply these changes to the live published repository?', confirmText: 'Approve & Apply', variant: 'success', form: this });">
                        @csrf
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">Approval Notes (Optional):</label>
                        <textarea name="notes" rows="2" placeholder="Institutional approval remarks..." class="w-full text-xs p-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"></textarea>
                        <button type="submit" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition shadow-sm flex items-center justify-center gap-1">
                            ✓ Approve & Apply Revision to Master
                        </button>
                    </form>

                    <div class="relative flex py-1 items-center">
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
                        <span class="flex-shrink mx-2 text-[10px] uppercase font-bold text-slate-400">or</span>
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
                    </div>

                    {{-- 2. Request Changes Form --}}
                    <form action="{{ route('admin.repository-manager.revisions.request-changes', $revisionRequest->id) }}" method="POST" class="space-y-2">
                        @csrf
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">Request Further Changes from Teacher:</label>
                        <textarea name="notes" rows="2" required placeholder="Specify what the teacher needs to adjust..." class="w-full text-xs p-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"></textarea>
                        <button type="submit" class="w-full py-2 px-4 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg transition shadow-sm flex items-center justify-center gap-1">
                            ⚠️ Request Changes from Teacher
                        </button>
                    </form>

                    <div class="relative flex py-1 items-center">
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
                        <span class="flex-shrink mx-2 text-[10px] uppercase font-bold text-slate-400">or</span>
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
                    </div>

                    {{-- 3. Reject Form --}}
                    <form action="{{ route('admin.repository-manager.revisions.reject', $revisionRequest->id) }}" method="POST" class="space-y-2" onsubmit="event.preventDefault(); iapConfirm({ title: 'Reject Revision Request?', message: 'Are you sure you want to reject and close this revision request?', confirmText: 'Reject Revision', variant: 'danger', form: this });">
                        @csrf
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">Rejection Reason:</label>
                        <textarea name="notes" rows="2" required placeholder="Reason for rejecting revision..." class="w-full text-xs p-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500"></textarea>
                        <button type="submit" class="w-full py-2 px-4 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg transition shadow-sm flex items-center justify-center gap-1">
                            🚫 Reject Revision Request
                        </button>
                    </form>

                @else
                    <div class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-center text-xs text-slate-500">
                        This revision request is currently in <strong>{{ $revisionRequest->status }}</strong> state. Governance decision actions are locked.
                    </div>
                @endif

            </div>

            {{-- Audit Activity Log --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    📜 Governance Audit Trail
                </h3>

                <div class="space-y-2 max-h-80 overflow-y-auto">
                    @forelse($logs as $log)
                    <div class="p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-[11px]">
                        <div class="flex items-center justify-between text-slate-500 text-[10px]">
                            <span class="font-bold text-slate-700 dark:text-slate-300">{{ $log->actor?->name ?? 'System' }}</span>
                            <span>{{ $log->created_at?->diffForHumans() }}</span>
                        </div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ str_replace('_', ' ', strtoupper($log->action)) }}
                        </div>
                        @if($log->approval_note)
                        <div class="text-slate-600 dark:text-slate-400 mt-1 italic">
                            "{{ $log->approval_note }}"
                        </div>
                        @endif
                    </div>
                    @empty
                    <div class="text-slate-400 italic text-xs">No activity logs recorded.</div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
