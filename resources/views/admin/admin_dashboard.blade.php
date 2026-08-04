@extends('layouts.admin')

@section('content')

    <!-- Operational KPI Metrics Cards (SA-006 Synchronization) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Active Users</span>
            <span class="text-3xl font-black text-white mt-1 block">{{ $totalUsers }}</span>
            <span class="text-xs text-slate-500 mt-1 block">{{ $teachersCount }} Teachers | {{ $studentsCount }} Candidates</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Published Question Banks</span>
            <span class="text-3xl font-black text-indigo-400 mt-1 block">{{ $publishedQuestionBanksCount }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Live item pools</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Published Assessments</span>
            <span class="text-3xl font-black text-emerald-400 mt-1 block">{{ $publishedTestsCount }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Live candidate exams</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Pending Approvals</span>
            <span class="text-3xl font-black text-amber-400 mt-1 block">{{ $pendingApprovalsCount }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Approval engine queue</span>
        </div>
    </div>

    <!-- Quick Operations Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Recent Registered Platform Users</h3>
            <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-400 font-semibold hover:underline">View All Users →</a>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">User</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Assigned Role</th>
                    <th class="p-4 text-right">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
                @foreach ($recentUsers as $user)
                    <tr>
                        <td class="p-4 font-semibold text-white font-sans text-xs">{{ $user->name }}</td>
                        <td class="p-4 text-slate-400">{{ $user->email }}</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 font-bold rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase">
                                {{ $user->roles->first()?->name ?? 'User' }}
                            </span>
                        </td>
                        <td class="p-4 text-right text-slate-400 font-sans text-xs">{{ $user->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
