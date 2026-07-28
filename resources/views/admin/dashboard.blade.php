<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ __('Super Admin Management Hub') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ __('Platform Overview Dashboard') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold rounded-lg flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> SYSTEM HEALTHY
                </span>
            </div>
        </div>
    </x-slot>

    <!-- Platform User Breakdown KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Total Users</span>
            <span class="text-2xl font-extrabold text-white block mt-1">{{ $totalUsers }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Students</span>
            <span class="text-2xl font-extrabold text-indigo-400 block mt-1">{{ $studentsCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Teachers</span>
            <span class="text-2xl font-extrabold text-amber-400 block mt-1">{{ $teachersCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Admins</span>
            <span class="text-2xl font-extrabold text-rose-400 block mt-1">{{ $adminsCount }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl col-span-2 sm:col-span-1">
            <span class="text-[10px] font-bold uppercase text-slate-400">Finance Users</span>
            <span class="text-2xl font-extrabold text-emerald-400 block mt-1">{{ $financeCount }}</span>
        </div>
    </div>

    <!-- Assessment Platform Activity KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-400 uppercase">Question Banks</div>
            <div class="text-3xl font-extrabold text-white mt-1">{{ $questionBanksCount }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Item pools active</div>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-400 uppercase">Published Tests</div>
            <div class="text-3xl font-extrabold text-emerald-400 mt-1">{{ $publishedTestsCount }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Live CBT packages</div>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-400 uppercase">Pending Approvals</div>
            <div class="text-3xl font-extrabold text-amber-400 mt-1">{{ $pendingApprovalsCount }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Awaiting review</div>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <div class="text-xs font-semibold text-slate-400 uppercase">Certificates Issued</div>
            <div class="text-3xl font-extrabold text-indigo-400 mt-1">{{ $certificatesCount }}</div>
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
                <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-400 hover:underline font-semibold">Manage Users &rarr;</a>
            </div>
            <div class="space-y-3">
                @foreach($recentUsers as $u)
                    <div class="p-3 bg-slate-900 border border-slate-800/80 rounded-lg flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white block">{{ $u->name }}</span>
                            <span class="text-slate-400 font-mono text-[11px]">{{ $u->email }}</span>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            {{ $u->roles->pluck('name')->first() ?? 'student' }}
                        </span>
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
                <a href="{{ route('admin.certificates.index') }}" class="text-xs text-indigo-400 hover:underline font-semibold">View Registry &rarr;</a>
            </div>
            <div class="space-y-3">
                @foreach($recentCertificates as $cert)
                    <div class="p-3 bg-slate-900 border border-slate-800/80 rounded-lg flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-white block">{{ $cert->user?->name ?? 'Candidate' }}</span>
                            <span class="text-slate-400 font-mono text-[11px]">{{ $cert->certificate_number }}</span>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            VALID
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-admin-layout>
