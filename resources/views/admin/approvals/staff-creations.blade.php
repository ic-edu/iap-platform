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
                <span>👤</span> Staff User Creation Approval Queue
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review pending internal staff user creations (Teachers, Finance, Admins) submitted by Administrators.</p>
        </div>
        <div>
            <span class="px-3 py-1.5 text-xs font-bold rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                {{ $userCreationRequests->total() }} Pending Requests
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
                    <th class="p-4">Staff User Details</th>
                    <th class="p-4">Requested Role</th>
                    <th class="p-4">Created By (Admin)</th>
                    <th class="p-4">Request Date</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($userCreationRequests as $createReq)
                    @php
                        $target = $createReq->targetUser;
                        $roleName = $createReq->requested_role;
                        $roleBadge = match($roleName) {
                            'admin' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                            'teacher' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'finance' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            default => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                        };
                    @endphp
                    <tr>
                        <td class="p-4">
                            <div class="font-semibold text-white">{{ $target?->name ?? 'Staff Account' }}</div>
                            <div class="text-xs font-mono text-indigo-400">{{ $target?->email ?? 'N/A' }}</div>
                        </td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-[10px] font-bold rounded border uppercase {{ $roleBadge }}">
                                {{ $roleName }}
                            </span>
                        </td>
                        <td class="p-4 text-xs text-slate-300">
                            {{ $createReq->requester?->name ?? 'Admin' }}
                            <div class="text-slate-500 font-mono">{{ $createReq->requester?->email }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-400">{{ $createReq->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.approvals.users.creation.approve', $createReq->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Approve Staff User Creation?', message: 'Approve and activate staff user account {{ addslashes($target?->email) }}?', confirmText: 'Approve & Activate', variant: 'success', form: this });">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve &amp; Activate
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.users.creation.reject', $createReq->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Reject Staff User Request?', message: 'Reject staff user creation request for {{ addslashes($target?->email) }}?', confirmText: 'Reject Request', variant: 'danger', form: this });">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-12 text-center">
                            <div class="text-3xl mb-2">✨</div>
                            <div class="text-sm font-bold text-white mb-1">No Pending Staff Creation Requests</div>
                            <div class="text-xs text-slate-500">All staff user creation workflows are up to date.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($userCreationRequests->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $userCreationRequests->links() }}
            </div>
        @endif
    </div>
@endsection
