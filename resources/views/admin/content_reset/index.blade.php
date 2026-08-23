@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>🛡️</span> Content Refresh &amp; Controlled Hard Reset
            </h1>
            <p class="text-xs text-slate-400 mt-1">Forensically gated content maintenance, draft cleanup, and authorized dual-approval reset operations.</p>
        </div>
        <div>
            <a href="{{ route('admin.content-reset.create') }}" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-md transition-colors inline-flex items-center gap-2">
                <span>➕</span> New Reset Request
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

    <!-- Requests Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white">Reset &amp; Refresh Request Registry</h2>
            <span class="text-xs text-slate-500">{{ $requests->total() }} total requests</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/60 text-slate-400 uppercase text-xxs tracking-wider border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Request #</th>
                        <th class="py-3 px-4">Mode</th>
                        <th class="py-3 px-4">Requester</th>
                        <th class="py-3 px-4">Scope</th>
                        <th class="py-3 px-4">Risk</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Approvals</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($requests as $req)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-3.5 px-4 font-mono font-bold text-indigo-400">
                                {{ $req->request_number }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($req->isHardReset())
                                    <span class="px-2 py-0.5 rounded bg-rose-500/15 text-rose-400 font-bold text-xxs border border-rose-500/30 uppercase">
                                        Hard Reset
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-blue-500/15 text-blue-400 font-bold text-xxs border border-blue-500/30 uppercase">
                                        Refresh
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-white font-medium">{{ $req->requester?->name ?? 'System' }}</span>
                            </td>
                            <td class="py-3.5 px-4 max-w-xs truncate text-slate-400">
                                {{ implode(', ', $req->scope ?? []) }}
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $riskClass = match($req->risk_classification) {
                                        'critical' => 'text-rose-400 bg-rose-500/10 border-rose-500/30',
                                        'high'     => 'text-amber-400 bg-amber-500/10 border-amber-500/30',
                                        'medium'   => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/30',
                                        default    => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded text-xxs font-bold border uppercase {{ $riskClass }}">
                                    {{ $req->risk_classification }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-xxs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                    {{ str_replace('_', ' ', $req->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($req->isHardReset())
                                    <div class="flex items-center gap-1.5 text-xxs">
                                        <span title="CEO Approval" class="px-1.5 py-0.5 rounded {{ $req->isCeoApproved() ? 'bg-emerald-500/20 text-emerald-300 font-bold' : 'bg-slate-800 text-slate-500' }}">
                                            CEO: {{ $req->isCeoApproved() ? '✓' : '✗' }}
                                        </span>
                                        <span title="Super Admin Approval" class="px-1.5 py-0.5 rounded {{ $req->isSaApproved() ? 'bg-emerald-500/20 text-emerald-300 font-bold' : 'bg-slate-800 text-slate-500' }}">
                                            SA: {{ $req->isSaApproved() ? '✓' : '✗' }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xxs text-slate-500">Single Gate</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="{{ route('admin.content-reset.show', $req) }}" class="text-indigo-400 hover:text-indigo-300 font-semibold">
                                    Inspect &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-500">
                                No content reset or refresh requests on record.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
@endsection
