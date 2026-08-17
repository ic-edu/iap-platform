@extends('layouts.admin')

@section('content')
    @php
        $from = request('from');
        $backUrl = match($from) {
            'notifications' => route('notifications.index'),
            'dashboard' => route('admin.dashboard'),
            default => route('admin.approvals.index'),
        };
        $backLabel = match($from) {
            'notifications' => '← Back to Notification Center',
            'dashboard' => '← Back to Dashboard',
            default => '← Back to Approval Command Center',
        };
    @endphp

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="mb-2">
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-lg text-xs font-medium text-slate-300 transition-colors">
                    {{ $backLabel }}
                </a>
            </div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>📩</span> Enterprise User Deletion Approval Queue
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review pending user account deletion requests submitted by Administrators.</p>
        </div>
        <div>
            <span class="px-3 py-1.5 text-xs font-bold rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">
                {{ $userDeletionRequests->total() }} Pending Requests
            </span>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Target User</th>
                    <th class="p-4">Current Role</th>
                    <th class="p-4">Submitted By (Admin)</th>
                    <th class="p-4">Justification / Reason</th>
                    <th class="p-4">Request Date</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($userDeletionRequests as $delReq)
                    @php
                        $target = $delReq->targetUser;
                        $roleName = $target?->roles->first()?->name ?? 'User';
                    @endphp
                    <tr>
                        <td class="p-4">
                            <div class="font-semibold text-white">{{ $target?->name ?? 'User Account' }}</div>
                            <div class="text-xs font-mono text-indigo-400">{{ $target?->email ?? 'Deleted User' }}</div>
                        </td>
                        <td class="p-4 text-xs font-bold text-amber-400 uppercase">{{ $roleName }}</td>
                        <td class="p-4 text-xs text-slate-300">
                            {{ $delReq->requester?->name ?? 'Admin' }}
                            <div class="text-slate-500 font-mono">{{ $delReq->requester?->email }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-300 max-w-xs">
                            <span class="italic bg-slate-950 p-2 rounded border border-slate-800 block">{{ $delReq->reason }}</span>
                        </td>
                        <td class="p-4 text-xs text-slate-400">{{ $delReq->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.approvals.users.approve', $delReq->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Approve User Deletion?', message: 'Confirm soft deletion of user {{ addslashes($target?->email) }}?', confirmText: 'Soft Delete User', variant: 'danger', form: this });">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve &amp; Soft Delete
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.users.reject', $delReq->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Reject Deletion Request?', message: 'Reject deletion request for {{ addslashes($target?->email) }}?', confirmText: 'Reject Request', variant: 'warning', form: this });">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-12 text-center">
                            <div class="text-3xl mb-2">✨</div>
                            <div class="text-sm font-bold text-white mb-1">No Pending User Deletion Requests</div>
                            <div class="text-xs text-slate-500">All enterprise user deletion workflows are up to date.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($userDeletionRequests->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $userDeletionRequests->links() }}
            </div>
        @endif
    </div>
@endsection
