<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>📋</span> System Audit &amp; Activity Logs
            </h1>
            <p class="text-xs text-slate-400 mt-1">Real-time audit trail recording system events, security policy updates, and user activities.</p>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Log Ref</th>
                    <th class="p-4">User</th>
                    <th class="p-4">Action Event</th>
                    <th class="p-4">Description</th>
                    <th class="p-4">IP Address</th>
                    <th class="p-4 text-right">Timestamp</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
                @foreach ($logs as $log)
                    <tr>
                        <td class="p-4 font-bold text-slate-500">{{ $log['id'] }}</td>
                        <td class="p-4 font-semibold text-white">{{ $log['user'] }}</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 font-bold rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                {{ $log['action'] }}
                            </span>
                        </td>
                        <td class="p-4 text-slate-300 font-sans text-xs">{{ $log['description'] }}</td>
                        <td class="p-4 text-slate-400">{{ $log['ip'] }}</td>
                        <td class="p-4 text-right text-slate-400">{{ $log['time'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin-layout>
