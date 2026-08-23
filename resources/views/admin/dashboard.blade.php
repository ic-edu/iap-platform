@extends('layouts.admin')

@section('content')

    <!-- Platform User Breakdown KPIs (Strictly Non-Overlapping Baseline v1.1 Counts) -->
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 mb-6">
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase text-slate-400">Total Users</span>
            <span class="text-2xl font-extrabold text-white block mt-1">{{ $totalUsers }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase text-purple-600 dark:text-purple-400">Super Admin</span>
            <span class="text-2xl font-extrabold text-purple-600 dark:text-purple-400 block mt-1">{{ $superAdminsCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase text-rose-600 dark:text-rose-400">Admin</span>
            <span class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 block mt-1">{{ $adminsCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase text-amber-600 dark:text-amber-400">Teachers</span>
            <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 block mt-1">{{ $teachersCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase text-emerald-600 dark:text-emerald-400">Finance</span>
            <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 block mt-1">{{ $financeCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase text-indigo-600 dark:text-indigo-400">Students</span>
            <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 block mt-1">{{ $studentsCount }}</span>
        </div>
    </div>

    <!-- Assessment Platform Activity KPIs (SA-006 Executive Dashboard Synchronization) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Published Question Banks</div>
            <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $publishedQuestionBanksCount }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Live item pools</div>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-400 uppercase">Published Tests</div>
            <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $publishedTestsCount }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Live CBT packages</div>
        </div>

        <!-- Clickable Pending Approvals Card (Navigates to Approval Center) -->
        <a href="{{ route('admin.approvals.index') }}" class="p-5 bg-slate-900 border border-amber-500/30 hover:border-amber-500/70 bg-gradient-to-br from-amber-500/5 to-transparent rounded-xl transition-all block group shadow-sm">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase">Pending Approvals</div>
                <span class="text-xs text-amber-600 dark:text-amber-400 group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $pendingApprovalsCount }}</div>
            <div class="text-[11px] text-amber-600/70 dark:text-amber-300/70 mt-1 group-hover:underline">Approval Center queue &rarr;</div>
        </a>

        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-400 uppercase">Certificates Issued</div>
            <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ $certificatesCount }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Authentic credentials</div>
        </div>
    </div>

    <!-- Recent Platform Activity Feeds -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Latest Registered Users -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>👥</span> Latest Registered Users
                </h3>
                <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">Manage Users &rarr;</a>
            </div>
            <div class="space-y-3">
                @foreach($recentUsers as $u)
                    <div class="p-3 bg-slate-900 border border-slate-800/80 rounded-lg flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white block">{{ $u->name }}</span>
                            <span class="text-slate-400 font-mono text-[11px]">{{ $u->email }}</span>
                        </div>
                        <x-role-badge :role="$u->roles->pluck('name')->first() ?? 'student'" />
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Latest Issued Certificates -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>🏆</span> Recent Issued Certificates
                </h3>
                <a href="{{ route('admin.certificates.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">View Registry &rarr;</a>
            </div>
            <div class="space-y-3">
                @foreach($recentCertificates as $cert)
                    <div class="p-3 bg-slate-900 border border-slate-800/80 rounded-lg flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white block">{{ $cert->user?->name ?? 'Candidate' }}</span>
                            <span class="text-slate-400 font-mono text-[11px]">{{ $cert->certificate_number }}</span>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                            VALID
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
