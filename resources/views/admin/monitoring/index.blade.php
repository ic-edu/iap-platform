<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ __('Platform Monitoring Subsystem') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ __('System Observability & Monitoring Dashboard') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold rounded-lg flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> {{ strtoupper($health['status'] ?? 'healthy') }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Key Observability KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-5 bg-slate-950 border border-slate-800 rounded-xl shadow-sm border-l-4 border-l-emerald-500">
                <div class="text-xs font-semibold uppercase text-slate-400">System Status</div>
                <div class="text-2xl font-extrabold text-white mt-1 capitalize">{{ $health['status'] ?? 'Healthy' }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Core services active</div>
            </div>
            <div class="p-5 bg-slate-950 border border-slate-800 rounded-xl shadow-sm border-l-4 border-l-indigo-500">
                <div class="text-xs font-semibold uppercase text-slate-400">Memory Usage</div>
                <div class="text-2xl font-extrabold text-indigo-400 mt-1">{{ $metrics['memory_usage_mb'] ?? '0' }} MB</div>
                <div class="text-[11px] text-slate-500 mt-1">Allocated runtime RAM</div>
            </div>
            <div class="p-5 bg-slate-950 border border-slate-800 rounded-xl shadow-sm border-l-4 border-l-purple-500">
                <div class="text-xs font-semibold uppercase text-slate-400">DB Connection</div>
                <div class="text-2xl font-extrabold text-purple-400 mt-1 capitalize">{{ $metrics['db_connection'] ?? 'connected' }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Primary database node</div>
            </div>
            <div class="p-5 bg-slate-950 border border-slate-800 rounded-xl shadow-sm border-l-4 border-l-amber-500">
                <div class="text-xs font-semibold uppercase text-slate-400">Failed Queue Jobs</div>
                <div class="text-2xl font-extrabold text-amber-400 mt-1">{{ $metrics['failed_jobs_count'] ?? 0 }}</div>
                <div class="text-[11px] text-slate-500 mt-1">Requires admin review</div>
            </div>
        </div>

        <!-- Detailed Observability Metrics Grid -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-sm">
            <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <span>⚡</span> Engine &amp; Runtime Metrics
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-slate-950 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-xs text-white">PHP Engine Version</span>
                        <p class="text-[11px] text-slate-400">Server Execution Engine</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-mono font-bold rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        v{{ $health['php_version'] ?? PHP_VERSION }}
                    </span>
                </div>

                <div class="p-4 bg-slate-950 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-xs text-white">Laravel Core Framework</span>
                        <p class="text-[11px] text-slate-400">Application Architecture</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-mono font-bold rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        v{{ $health['laravel_version'] ?? app()->version() }}
                    </span>
                </div>

                <div class="p-4 bg-slate-950 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-xs text-white">Queue Worker Health</span>
                        <p class="text-[11px] text-slate-400">Async Task Processors</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        {{ strtoupper($health['queue_health'] ?? 'ACTIVE') }}
                    </span>
                </div>

                <div class="p-4 bg-slate-950 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-xs text-white">Memory Cache Engine</span>
                        <p class="text-[11px] text-slate-400">High-Speed Data Store</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        {{ strtoupper($health['cache_status'] ?? 'ACTIVE') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
