<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">Teacher Authoring Workspace</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Teacher Workspace Dashboard</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.question-banks.index') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                    📂 Author Question Banks
                </a>
                <a href="{{ route('admin.tests.index') }}" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                    📋 Create Test Draft
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Teacher Question Bank KPI Cards (QB-001 Issue 2) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Question Banks</span>
            <span class="text-3xl font-black text-indigo-400 mt-1 block">{{ $totalQuestionBanks }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Total authoring pools</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Draft</span>
            <span class="text-3xl font-black text-slate-300 mt-1 block">{{ $draftQuestionBanks }}</span>
            <span class="text-xs text-slate-500 mt-1 block">In authoring draft</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Pending Approval</span>
            <span class="text-3xl font-black text-amber-400 mt-1 block">{{ $pendingApprovalQuestionBanks }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Awaiting Super Admin</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Published</span>
            <span class="text-3xl font-black text-emerald-400 mt-1 block">{{ $publishedQuestionBanks }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Published live by Admin</span>
        </div>
    </div>

    <!-- Question Banks Authoring Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Recent Authoring Question Banks</h3>
            <a href="{{ route('admin.question-banks.index') }}" class="text-xs text-indigo-400 font-semibold hover:underline">Manage All Banks →</a>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Bank Title</th>
                    <th class="p-4">Category / Type</th>
                    <th class="p-4">Questions</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
                @foreach ($recentQuestionBanks as $bank)
                    @php
                        $statusBadge = match($bank->status) {
                            'published' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'approved' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                            'pending_approval' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                            default => 'bg-slate-800 text-slate-400 border-slate-700',
                        };
                    @endphp
                    <tr>
                        <td class="p-4 font-semibold text-white font-sans text-xs">{{ $bank->title }}</td>
                        <td class="p-4 text-slate-400 font-sans text-xs">{{ $bank->category?->name ?? strtoupper($bank->test_type?->value ?? 'General') }}</td>
                        <td class="p-4 font-bold text-indigo-400">{{ $bank->questions->count() }} items</td>
                        <td class="p-4 font-sans text-xs">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded border uppercase {{ $statusBadge }}">
                                {{ $bank->status ?? 'draft' }}
                            </span>
                        </td>
                        <td class="p-4 text-right font-sans text-xs">
                            <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="text-indigo-400 font-bold hover:underline">Author Items →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin-layout>
