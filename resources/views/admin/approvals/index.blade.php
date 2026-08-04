@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>🛡️</span> Super Admin Content Approval Center
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review, approve, and authorize assessment tests, question banks, staff user creations, and enterprise deletion requests.</p>
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
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 mb-6">
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] text-slate-400 font-medium uppercase block">Pending Assessments</span>
                <span class="text-2xl font-extrabold text-amber-400 mt-0.5 block">{{ $pendingCount }}</span>
            </div>
            <span class="text-2xl">⏳</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] text-slate-400 font-medium uppercase block">Pending Question Banks</span>
                <span class="text-2xl font-extrabold text-indigo-400 mt-0.5 block">{{ $pendingQuestionBankCount ?? 0 }}</span>
            </div>
            <span class="text-2xl">📂</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] text-slate-400 font-medium uppercase block">Pending Bank Archives</span>
                <span class="text-2xl font-extrabold text-amber-500 mt-0.5 block">{{ $pendingQuestionBankArchiveCount ?? 0 }}</span>
            </div>
            <span class="text-2xl">📦</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] text-slate-400 font-medium uppercase block">Pending Staff Creations</span>
                <span class="text-2xl font-extrabold text-purple-400 mt-0.5 block">{{ $pendingUserCreationCount ?? 0 }}</span>
            </div>
            <span class="text-2xl">👤</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] text-slate-400 font-medium uppercase block">Pending Deletions</span>
                <span class="text-2xl font-extrabold text-rose-400 mt-0.5 block">{{ $pendingUserDeletionCount ?? 0 }}</span>
            </div>
            <span class="text-2xl">📩</span>
        </div>
    </div>

    <!-- Pending Question Banks Table (QB-001) -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>📂</span> Question Bank Approval Queue
                </h2>
                <p class="text-xs text-slate-400">Review pending question bank submissions submitted by Teachers for Super Admin authorization.</p>
            </div>
            <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                {{ $pendingQuestionBankCount ?? 0 }} Pending Banks
            </span>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Title</th>
                    <th class="p-4">Author</th>
                    <th class="p-4">Test Type</th>
                    <th class="p-4">Submitted Date</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($pendingQuestionBanks ?? [] as $bank)
                    <tr>
                        <td class="p-4 font-semibold text-white">
                            {{ $bank->title }}
                            <div class="text-xs text-slate-400 font-normal">Category: {{ $bank->category?->name ?? 'General' }} | Items: {{ $bank->questions->count() }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-300">
                            {{ $bank->creator?->name ?? 'Teacher Author' }}
                            <div class="text-slate-500 font-mono">{{ $bank->creator?->email }}</div>
                        </td>
                        <td class="p-4 text-xs font-bold text-indigo-400 uppercase">
                            {{ $bank->test_type?->value ?? 'General' }}
                        </td>
                        <td class="p-4 text-xs text-slate-400">{{ $bank->updated_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.approvals.question-banks.approve', $bank->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Approve Question Bank {{ $bank->title }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.question-banks.reject', $bank->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Reject Question Bank {{ $bank->title }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">No pending Question Banks in approval queue. All submitted question banks have been reviewed.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pending Question Bank Archive Requests Table (QB-002) -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>📦</span> Question Bank Archive Approval Queue
                </h2>
                <p class="text-xs text-slate-400">Review pending question bank archive requests submitted by Operational Administrators.</p>
            </div>
            <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                {{ $pendingQuestionBankArchiveCount ?? 0 }} Pending Archive Requests
            </span>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Question Bank</th>
                    <th class="p-4">Requested By (Admin)</th>
                    <th class="p-4">Reason / Justification</th>
                    <th class="p-4">Request Date</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($pendingArchiveRequests ?? [] as $archReq)
                    @php $bank = $archReq->questionBank; @endphp
                    <tr>
                        <td class="p-4">
                            <div class="font-semibold text-white">{{ $bank?->title ?? 'Question Bank' }}</div>
                            <div class="text-xs text-slate-400">Category: {{ $bank?->category?->name ?? 'General' }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-300">
                            {{ $archReq->requester?->name ?? 'Admin' }}
                            <div class="text-slate-500 font-mono">{{ $archReq->requester?->email }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-300 max-w-xs">
                            <span class="italic bg-slate-950 p-2 rounded border border-slate-800 block">{{ $archReq->reason }}</span>
                        </td>
                        <td class="p-4 text-xs text-slate-400">{{ $archReq->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.approvals.question-banks.archives.approve', $archReq->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Approve archive of Question Bank {{ $bank?->title }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve Archive
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.question-banks.archives.reject', $archReq->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Reject archive request for Question Bank {{ $bank?->title }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">No pending Question Bank archive requests in approval queue. All archive requests have been processed.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pending Staff Creation Requests Table (UAC-003) -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>👤</span> Staff User Creation Approval Queue
                </h2>
                <p class="text-xs text-slate-400">Review pending internal staff user creations (Teachers, Finance, Admins) submitted by Administrators.</p>
            </div>
            <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                {{ $pendingUserCreationCount ?? 0 }} Pending Requests
            </span>
        </div>
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
                @forelse ($userCreationRequests ?? [] as $createReq)
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
                                  onsubmit="return confirm('Approve and activate staff user account {{ $target?->email }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve &amp; Activate
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.users.creation.reject', $createReq->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Reject staff user creation request for {{ $target?->email }}?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 font-semibold text-xs rounded-lg border border-rose-500/30 transition-colors">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">No pending staff creation requests in approval queue. All staff user creation workflows are up to date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
@endsection
