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
                <span>📂</span> Question Bank Approval Queue
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review pending question bank submissions submitted by Teachers for Super Admin authorization.</p>
        </div>
        <div>
            <span class="px-3 py-1.5 text-xs font-bold rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                {{ $pendingQuestionBanks->total() }} Pending Question Banks
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
                    <th class="p-4">Title</th>
                    <th class="p-4">Author</th>
                    <th class="p-4">Test Type</th>
                    <th class="p-4">Submitted Date</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($pendingQuestionBanks as $bank)
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
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Approve Question Bank?', message: 'Approve Question Bank {{ addslashes($bank->title) }}?', confirmText: 'Approve & Publish', variant: 'success', form: this });">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                    ✓ Approve
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.question-banks.reject', $bank->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Reject Question Bank?', message: 'Reject Question Bank {{ addslashes($bank->title) }}?', confirmText: 'Reject Repository', variant: 'danger', form: this });">
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
                            <div class="text-sm font-bold text-white mb-1">No Pending Question Banks</div>
                            <div class="text-xs text-slate-500">All submitted question banks have been reviewed and authorized.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($pendingQuestionBanks->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $pendingQuestionBanks->links() }}
            </div>
        @endif
    </div>
@endsection
