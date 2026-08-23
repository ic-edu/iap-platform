@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-mono font-bold text-indigo-400">{{ $contentResetRequest->request_number }}</span>
                @if($contentResetRequest->isHardReset())
                    <span class="px-2.5 py-0.5 rounded bg-rose-500/15 text-rose-400 font-bold text-xs border border-rose-500/30 uppercase">
                        Hard Reset Mode
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded bg-blue-500/15 text-blue-400 font-bold text-xs border border-blue-500/30 uppercase">
                        Content Refresh Mode
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-400 mt-1">Requested by <strong>{{ $contentResetRequest->requester?->name }}</strong> on {{ $contentResetRequest->created_at->format('d M Y, H:i') }}</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.content-reset.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl border border-slate-700 transition-colors">
                &larr; Back to Registry
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold">
            {{ session('error') }}
        </div>
    @endif

    @php
        $audit = $contentResetRequest->audit_report ?? [];
        $isBlocked = $audit['is_blocked'] ?? false;
        $isFullyApproved = $contentResetRequest->isFullyApproved();
    @endphp

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Forensic Audit & Plan -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Blockers Alert -->
            @if($isBlocked)
                <div class="p-5 rounded-2xl bg-rose-950/40 border-2 border-rose-500/50 shadow-lg">
                    <h3 class="text-sm font-bold text-rose-300 flex items-center gap-2 mb-2">
                        <span>🛑</span> Forensic Blockers Detected (Execution Prohibited)
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-xs text-rose-200">
                        @foreach($audit['blockers'] ?? [] as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Audit Metrics Summary Card -->
            <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm">
                <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <span>🔬</span> Forensic Audit Dependency Summary
                </h2>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-xxs uppercase text-slate-500 font-bold block">Assessments Target</span>
                        <span class="text-xl font-black text-white mt-0.5 block">{{ $audit['target_assessments_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-xxs uppercase text-slate-500 font-bold block">Test Sections</span>
                        <span class="text-xl font-black text-white mt-0.5 block">{{ $audit['target_sections_count'] ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-xxs uppercase text-slate-500 font-bold block">Bound Questions</span>
                        <span class="text-xl font-black text-white mt-0.5 block">{{ $audit['bound_questions_total'] ?? 0 }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-emerald-500/30 bg-emerald-500/5">
                        <span class="text-xxs uppercase text-emerald-400 font-bold block">Shared (Preserved)</span>
                        <span class="text-xl font-black text-emerald-400 mt-0.5 block">{{ $audit['shared_questions_preserved'] ?? 0 }}</span>
                    </div>
                </div>

                <!-- Shared Questions Detail Table -->
                @if(!empty($audit['shared_questions_detail']))
                    <div class="mb-4">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Shared Dependency Protection Map</h4>
                        <div class="bg-slate-950 border border-slate-800 rounded-xl overflow-hidden text-xs">
                            <table class="w-full text-left">
                                <thead class="bg-slate-900/80 text-slate-400 text-xxs uppercase border-b border-slate-800">
                                    <tr>
                                        <th class="p-2.5">Question ID</th>
                                        <th class="p-2.5">Prompt</th>
                                        <th class="p-2.5">Decision</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    @foreach($audit['shared_questions_detail'] as $shared)
                                        <tr>
                                            <td class="p-2.5 font-mono text-slate-400">{{ substr($shared['question_id'], 0, 12) }}...</td>
                                            <td class="p-2.5 text-slate-300 max-w-xs truncate">{{ $shared['prompt'] }}</td>
                                            <td class="p-2.5">
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold text-xxs border border-emerald-500/40">
                                                    {{ $shared['status'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- Operational Reason -->
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                    <span class="text-slate-500 text-xxs uppercase font-bold block mb-1">Business Reason</span>
                    <p class="text-slate-300 leading-relaxed">{{ $contentResetRequest->reason }}</p>
                </div>
            </div>

            <!-- Execution Result / Log (If Executed) -->
            @if(!empty($contentResetRequest->execution_log))
                <div class="p-6 bg-slate-900 border border-emerald-500/40 rounded-2xl shadow-sm">
                    <h3 class="text-sm font-bold text-emerald-400 flex items-center gap-2 mb-3">
                        <span>✅</span> Execution &amp; Integrity Verification Log
                    </h3>
                    <div class="grid sm:grid-cols-3 gap-3 text-xs">
                        <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                            <span class="text-xxs text-slate-500 uppercase block">Completed At</span>
                            <span class="font-bold text-white">{{ $contentResetRequest->completed_at?->format('d M Y, H:i:s') }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                            <span class="text-xxs text-slate-500 uppercase block">Executed By</span>
                            <span class="font-bold text-white">{{ $contentResetRequest->execution_log['executor_name'] ?? 'System' }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                            <span class="text-xxs text-slate-500 uppercase block">Post-Reset Integrity</span>
                            <span class="font-bold text-emerald-400">PASSED &amp; VERIFIED</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Governance Approval & Execution Controls -->
        <div class="space-y-6">
            <!-- Dual Approval Card (For Hard Reset) -->
            @if($contentResetRequest->isHardReset())
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm space-y-4">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>⚖️</span> Dual Governance Approval Gate
                    </h2>
                    <p class="text-xxs text-slate-400 leading-relaxed">
                        Content Hard Reset strictly requires independent authorizations from both the CEO and Super Admin.
                    </p>

                    <!-- CEO Gate -->
                    <div class="p-4 rounded-xl border {{ $contentResetRequest->isCeoApproved() ? 'bg-emerald-500/5 border-emerald-500/30' : 'bg-slate-950 border-slate-800' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-xs text-white">1. CEO Authorization</span>
                            <span class="px-2 py-0.5 rounded text-xxs font-bold {{ $contentResetRequest->isCeoApproved() ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                                {{ $contentResetRequest->isCeoApproved() ? 'APPROVED' : 'AWAITING' }}
                            </span>
                        </div>
                        @if($contentResetRequest->isCeoApproved())
                            <p class="text-xxs text-slate-400">Approved by <strong>{{ $contentResetRequest->ceoApprover?->name }}</strong> at {{ $contentResetRequest->ceo_approved_at?->format('d M Y, H:i') }}</p>
                        @elseif(auth()->user()->hasRole('ceo') || auth()->user()->hasRole('super-admin'))
                            @if(auth()->id() !== $contentResetRequest->requested_by)
                                <form action="{{ route('admin.content-reset.approve-ceo', $contentResetRequest) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg transition-colors cursor-pointer">
                                        ✓ Approve as CEO
                                    </button>
                                </form>
                            @else
                                <span class="text-xxs text-amber-400 block mt-2">Self-approval prohibited (You are the requester).</span>
                            @endif
                        @endif
                    </div>

                    <!-- Super Admin Gate -->
                    <div class="p-4 rounded-xl border {{ $contentResetRequest->isSaApproved() ? 'bg-emerald-500/5 border-emerald-500/30' : 'bg-slate-950 border-slate-800' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-xs text-white">2. Super Admin Authorization</span>
                            <span class="px-2 py-0.5 rounded text-xxs font-bold {{ $contentResetRequest->isSaApproved() ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                                {{ $contentResetRequest->isSaApproved() ? 'APPROVED' : 'AWAITING' }}
                            </span>
                        </div>
                        @if($contentResetRequest->isSaApproved())
                            <p class="text-xxs text-slate-400">Approved by <strong>{{ $contentResetRequest->saApprover?->name }}</strong> at {{ $contentResetRequest->sa_approved_at?->format('d M Y, H:i') }}</p>
                        @elseif(auth()->user()->hasRole('super-admin'))
                            @if(auth()->id() !== $contentResetRequest->requested_by && auth()->id() !== $contentResetRequest->ceo_approver_id)
                                <form action="{{ route('admin.content-reset.approve-sa', $contentResetRequest) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg transition-colors cursor-pointer">
                                        ✓ Approve as Super Admin
                                    </button>
                                </form>
                            @else
                                <span class="text-xxs text-amber-400 block mt-2">Dual approval requires a distinct administrator.</span>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            <!-- Execution Actions -->
            <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm space-y-3">
                <h2 class="text-sm font-bold text-white flex items-center gap-2 mb-2">
                    <span>⚡</span> Execution Controls
                </h2>

                <!-- Dry-Run Button -->
                <form action="{{ route('admin.content-reset.execute', $contentResetRequest) }}" method="POST">
                    @csrf
                    <input type="hidden" name="dry_run" value="1">
                    <button type="submit" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl border border-slate-700 transition-colors cursor-pointer">
                        🔍 Execute Forensic Dry-Run
                    </button>
                </form>

                <!-- Live Execution Button -->
                @if(!$isBlocked && ($contentResetRequest->isRefresh() || $isFullyApproved) && $contentResetRequest->status !== 'completed')
                    <form action="{{ route('admin.content-reset.execute', $contentResetRequest) }}" method="POST">
                        @csrf
                        <input type="hidden" name="dry_run" value="0">
                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-rose-600/30 transition-colors cursor-pointer">
                            🚀 Execute Controlled Reset
                        </button>
                    </form>
                @elseif($contentResetRequest->status === 'completed')
                    <div class="p-3 text-center rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold">
                        Reset Operation Completed
                    </div>
                @else
                    <div class="p-3 text-center rounded-xl bg-slate-950 border border-slate-800 text-slate-500 text-xxs">
                        Live execution disabled until all approvals are satisfied and blockers resolved.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
