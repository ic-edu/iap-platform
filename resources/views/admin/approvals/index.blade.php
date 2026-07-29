<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>🛡️</span> Super Admin Content Approval Center
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review, approve, and authorize assessment tests and enterprise user deletion requests.</p>
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

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-medium uppercase">Pending Assessment Submissions</span>
                <span class="text-3xl font-extrabold text-amber-400 mt-1 block">{{ $pendingCount }}</span>
            </div>
            <span class="text-3xl">⏳</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-medium uppercase">Pending User Deletion Requests</span>
                <span class="text-3xl font-extrabold text-rose-400 mt-1 block">{{ $pendingUserDeletionCount ?? 0 }}</span>
            </div>
            <span class="text-3xl">📩</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-medium uppercase">Approved &amp; Live Assessments</span>
                <span class="text-3xl font-extrabold text-emerald-400 mt-1 block">{{ $publishedCount }}</span>
            </div>
            <span class="text-3xl">🚀</span>
        </div>
    </div>

    <!-- Pending User Deletion Requests Table (UAC-002) -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>📩</span> Enterprise User Deletion Approval Queue
                </h2>
                <p class="text-xs text-slate-400">Review pending user account deletion requests submitted by Administrators.</p>
            </div>
            <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">
                {{ $pendingUserDeletionCount ?? 0 }} Pending Requests
            </span>
        </div>
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
                @forelse ($userDeletionRequests ?? [] as $delReq)
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
                                  onsubmit="return confirm('Confirm soft deletion of user {{ $target?->email }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve &amp; Soft Delete
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.users.reject', $delReq->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Reject deletion request for {{ $target?->email }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500">No pending user deletion requests in approval queue. All user deletion workflows are up to date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pending Assessment Tests Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="p-4 border-b border-slate-800">
            <h2 class="text-sm font-bold text-white">Submitted Assessment Tests Queue</h2>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Test Title</th>
                    <th class="p-4">Test Type</th>
                    <th class="p-4">Author / Teacher</th>
                    <th class="p-4">Duration &amp; Pass Threshold</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($pendingTests as $test)
                    <tr>
                        <td class="p-4 font-semibold text-white">
                            {{ $test->title }}
                            <div class="text-xs font-normal text-slate-400 mt-0.5">Slug: {{ $test->slug }}</div>
                        </td>
                        <td class="p-4 text-xs font-bold text-indigo-400 uppercase">{{ $test->test_type?->value ?? 'General' }}</td>
                        <td class="p-4 text-xs text-slate-300">
                            {{ $test->creator?->name ?? 'Teacher' }}
                            <div class="text-slate-500 font-mono">{{ $test->creator?->email }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-300">
                            {{ $test->duration_minutes }} mins | Pass: <span class="font-bold text-white">{{ $test->pass_score }} pts</span>
                        </td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.approvals.approve', $test->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve &amp; Authorize
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.reject', $test->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">No pending test submissions in approval queue. All submitted assessments have been processed.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $pendingTests->links() }}</div>
</x-admin-layout>
