<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>🛡️</span> Super Admin Content Approval Center
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review, approve, and publish assessment tests submitted by teachers and authors.</p>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-medium uppercase">Pending Review Submissions</span>
                <span class="text-3xl font-extrabold text-amber-400 mt-1 block">{{ $pendingCount }}</span>
            </div>
            <span class="text-3xl">⏳</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-medium uppercase">Approved &amp; Live Assessments</span>
                <span class="text-3xl font-extrabold text-emerald-400 mt-1 block">{{ $publishedCount }}</span>
            </div>
            <span class="text-3xl">🚀</span>
        </div>
    </div>

    <!-- Pending Tests Table -->
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
                                    ✓ Approve &amp; Publish
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
