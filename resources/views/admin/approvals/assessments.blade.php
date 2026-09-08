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
                <span>⏳</span> Pending Assessment Approval Queue
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Review, authorize, and approve assessment test submissions for institutional publication.</p>
        </div>
        <div>
            <span class="px-3 py-1.5 text-xs font-bold rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                {{ $pendingTests->total() }} Pending Assessments
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
                    <th class="p-4">Test Title</th>
                    <th class="p-4">Test Type</th>
                    <th class="p-4">Author / Teacher</th>
                    <th class="p-4">Duration &amp; Pass Threshold</th>
                    <th class="p-4 text-right">Approval Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($pendingTests as $test)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-950/40 transition-colors">
                        <td class="p-4 font-semibold text-slate-900 dark:text-white">
                            {{ $test->title }}
                            <div class="text-xs font-normal text-slate-400 dark:text-slate-500 mt-0.5">Slug: {{ $test->slug }}</div>
                        </td>
                        <td class="p-4 text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase">{{ $test->test_type?->value ?? 'General' }}</td>
                        <td class="p-4 text-xs text-slate-700 dark:text-slate-300">
                            {{ $test->creator?->name ?? 'Teacher' }}
                            <div class="text-slate-400 dark:text-slate-500 font-mono">{{ $test->creator?->email }}</div>
                        </td>
                        <td class="p-4 text-xs text-slate-700 dark:text-slate-300">
                            {{ $test->duration_minutes }} mins | Pass: <span class="font-bold text-slate-900 dark:text-white">{{ $test->pass_score }} pts</span>
                        </td>
                        <td class="p-4 text-right space-x-2">
                            <form action="{{ route('admin.approvals.approve', $test->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition-colors cursor-pointer">
                                    ✓ Approve &amp; Authorize
                                </button>
                            </form>
                            <form action="{{ route('admin.approvals.reject', $test->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-600/20 dark:hover:bg-rose-600/30 text-rose-700 dark:text-rose-400 font-semibold text-xs rounded-lg border border-rose-200 dark:border-rose-500/30 transition-colors cursor-pointer">
                                    ✗ Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-12 text-center">
                            <div class="text-3xl mb-2">✨</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white mb-1">No Pending Assessment Submissions</div>
                            <div class="text-xs text-slate-400 dark:text-slate-500">All submitted assessment tests have been reviewed and authorized.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($pendingTests->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $pendingTests->links() }}
            </div>
        @endif
    </div>
@endsection
