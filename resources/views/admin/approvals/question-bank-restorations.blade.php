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
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 transition-colors shadow-sm">
                    {{ $backLabel }}
                </a>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>♻️</span> Question Bank Restoration Approval Queue
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Review and authorize archived Question Bank restoration requests submitted for Super Admin approval.</p>
        </div>
        <div>
            <span class="px-3 py-1.5 text-xs font-bold rounded-full bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/20">
                {{ $pendingRestorationRequests->total() }} Pending Requests
            </span>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="p-4">Repository Title</th>
                    <th class="p-4">Teacher / Author</th>
                    <th class="p-4">Restoration Reason</th>
                    <th class="p-4">Requested Date</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Restoration Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($pendingRestorationRequests as $bank)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-950/40 transition-colors">
                        <td class="p-4 font-semibold text-slate-900 dark:text-white">
                            <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $bank->title }}
                            </a>
                            <div class="text-xs text-slate-400 dark:text-slate-500 font-normal">Category: {{ $bank->aclCategory?->name ?? 'General' }} | Items: {{ $bank->questions->count() }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-700 dark:text-slate-300">
                            {{ $bank->creator?->name ?? 'Teacher Author' }}
                            <div class="text-slate-400 dark:text-slate-500 font-mono">{{ $bank->creator?->email }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-700 dark:text-slate-300 max-w-xs">
                            <span class="italic bg-slate-50 dark:bg-slate-950 p-2 rounded border border-slate-200 dark:border-slate-800 block text-indigo-700 dark:text-indigo-200">
                                {{ $bank->activityLogs()->where('action', 'restore_requested')->latest()->first()?->approval_note ?? 'Requested restoration from archive' }}
                            </span>
                        </td>
                        <td class="p-4 text-xs text-slate-500 dark:text-slate-400">{{ $bank->updated_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-4 text-xs font-bold text-amber-600 dark:text-amber-400 uppercase">
                            <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/20">Pending Restore</span>
                        </td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.question-banks.approve-restore', $bank->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Approve Restoration Request?', message: 'Approve restoration of Question Bank {{ addslashes($bank->title) }} to active Approved status?', confirmText: 'Approve Restoration', variant: 'success', form: this });">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors cursor-pointer">
                                    ✓ Approve Restore
                                </button>
                            </form>
                            <form action="{{ route('admin.question-banks.reject-restore', $bank->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="button" id="reject-restore-trigger-btn-app-{{ $bank->id }}" onclick="document.getElementById('inline-reject-restore-panel-app-{{ $bank->id }}').classList.remove('hidden'); this.classList.add('hidden');" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-600/20 dark:hover:bg-rose-600/30 text-rose-700 dark:text-rose-400 font-semibold text-xs rounded-lg border border-rose-200 dark:border-rose-500/30 transition-colors cursor-pointer">
                                    ✗ Reject Restore
                                </button>
                                <div id="inline-reject-restore-panel-app-{{ $bank->id }}" class="hidden inline-flex items-center gap-2 p-1.5 bg-white dark:bg-slate-900 border border-rose-400 dark:border-rose-500/50 rounded-xl shadow-lg">
                                    <input type="text" name="reason" placeholder="Rejection reason..." class="px-2 py-0.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white rounded-lg focus:outline-none focus:border-rose-500" style="width:160px;" required>
                                    <button type="button" onclick="document.getElementById('inline-reject-restore-panel-app-{{ $bank->id }}').classList.add('hidden'); document.getElementById('reject-restore-trigger-btn-app-{{ $bank->id }}').classList.remove('hidden');" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer">Cancel</button>
                                    <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs rounded-lg shadow transition-colors cursor-pointer">Reject</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-12 text-center">
                            <div class="text-3xl mb-2">✨</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white mb-1">No Pending Restoration Requests</div>
                            <div class="text-xs text-slate-400 dark:text-slate-500">All restoration requests have been processed.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($pendingRestorationRequests->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $pendingRestorationRequests->links() }}
            </div>
        @endif
    </div>
@endsection
