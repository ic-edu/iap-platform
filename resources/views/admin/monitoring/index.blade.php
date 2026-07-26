<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('System Observability & Monitoring Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-emerald-500">
                <div class="text-sm font-medium text-gray-500">System Status</div>
                <div class="text-2xl font-bold text-gray-900 capitalize">{{ $health['status'] }}</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                <div class="text-sm font-medium text-gray-500">Memory Usage</div>
                <div class="text-2xl font-bold text-gray-900">{{ $metrics['memory_usage_mb'] }} MB</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                <div class="text-sm font-medium text-gray-500">DB Connection</div>
                <div class="text-2xl font-bold text-gray-900 capitalize">{{ $metrics['db_connection'] }}</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-amber-500">
                <div class="text-sm font-medium text-gray-500">Failed Queue Jobs</div>
                <div class="text-2xl font-bold text-gray-900">{{ $metrics['failed_jobs_count'] }}</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Detailed System Metrics</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 border rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold capitalize text-gray-800">PHP Version</span>
                        <p class="text-xs text-gray-500">Engine Version</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                        {{ $health['php_version'] }}
                    </span>
                </div>
                <div class="p-4 border rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold capitalize text-gray-800">Laravel Version</span>
                        <p class="text-xs text-gray-500">Framework Core</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                        {{ $health['laravel_version'] }}
                    </span>
                </div>
                <div class="p-4 border rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold capitalize text-gray-800">Queue Health</span>
                        <p class="text-xs text-gray-500">Worker Status</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                        {{ $health['queue_health'] }}
                    </span>
                </div>
                <div class="p-4 border rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-semibold capitalize text-gray-800">Cache Status</span>
                        <p class="text-xs text-gray-500">Memory Cache</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                        {{ $health['cache_status'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
